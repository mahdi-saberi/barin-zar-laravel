<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminStoreTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function signIn(): void
    {
        $user = User::factory()->create();
        config(['barin.admin_email' => $user->email]);
        $this->actingAs($user);
    }

    public function test_permission_and_admin_sections(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

        $this->signIn();
        $this->get('/admin')->assertOk()->assertSee('محصولات منتشرشده');
        foreach (['product', 'page', 'campaign', 'seo', 'settings', 'studio', 'finance'] as $tab) {
            $this->get('/admin?tab='.$tab)->assertOk();
        }
        foreach (['product', 'page', 'campaign', 'seo'] as $tab) {
            $this->get('/admin?tab='.$tab.'&create=1')->assertOk();
        }
        $this->get('/admin?tab=page&edit=home')->assertOk()->assertSee('چیدمان صفحه اصلی');
    }

    public function test_admin_product_table_paginates_and_filters_legacy_stock(): void
    {
        $this->signIn();
        $response = $this->get('/admin?tab=product')->assertOk()->assertSee('admin-product-table');
        $items = $response->viewData('items');
        $this->assertCount(20, $items);
        $this->assertSame(100, $items->total());
        $this->assertStringContainsString('tab=product', $items->nextPageUrl());

        $leaf = Entry::products()->where('slug', 'leaf')->firstOrFail();
        $leaf->update(['data' => ['stock' => '0'] + $leaf->data]);
        $search = $this->get('/admin?tab=product&category=necklace&stock=out&q=leaf')->assertOk();
        $this->assertSame(['leaf'], $search->viewData('items')->pluck('slug')->all());

        $this->from('/admin?tab=product&page=2')->patch('/admin/products/leaf/visibility', ['published' => '0'])
            ->assertRedirect('/admin?tab=product&page=2');
        $this->get('/admin?tab=product&visibility=hidden')->assertOk()->assertSee('ویرایش');
        $this->get('/product/leaf')->assertNotFound();
        $this->patch('/admin/products/leaf/visibility', ['published' => '1'])->assertRedirect();
        $this->get('/product/leaf')->assertOk();
    }

    public function test_creating_a_product_cannot_overwrite_an_existing_slug_and_edits_preserve_concurrent_sales(): void
    {
        $this->signIn();
        $data = ['slug' => 'leaf', 'name' => 'نام تازه', 'category' => 'necklace', 'milligrams' => '4000',
            'labor' => '1000', 'cost' => '0', 'profit_bps' => '0', 'discount_bps' => '0', 'tax_bps' => '0',
            'stock' => '3', 'images' => "/assets/p1.webp\n\n/assets/m1.webp", 'image_alts' => "نمای محصول\n\nروی مدل",
            'published' => '1'];
        $url = '/admin?tab=product&edit=leaf';
        $this->from('/admin?tab=product&create=1')->post('/admin/save/product', $data)
            ->assertSessionHasErrors('slug');
        $this->assertNotSame($data['name'], Entry::getData('product', 'leaf')['name']);

        $created = ['slug' => 'new-piece'] + $data;
        $this->post('/admin/save/product', $created)->assertRedirect('/admin?tab=product&edit=new-piece');
        $this->assertSame(['/assets/p1.webp', '/assets/m1.webp'], Entry::getData('product', 'new-piece')['images']);
        $this->assertSame(['نمای محصول', 'روی مدل'], Entry::getData('product', 'new-piece')['image_alts']);

        $entry = Entry::products()->where('slug', 'leaf')->firstOrFail();
        $entry->update(['data' => ['stock' => 2] + $entry->data]);
        $edit = $data + ['edit' => 'leaf', 'original_stock' => '3'];
        $this->from($url)->post('/admin/save/product', ['stock' => '4'] + $edit)->assertSessionHasErrors('stock');
        $this->post('/admin/save/product', $edit)->assertRedirect($url);
        $this->assertSame(2, $entry->fresh()->data['stock']);
        $this->assertSame($data['name'], $entry->fresh()->data['name']);
    }

    public function test_product_form_creates_unique_identifiers_and_preserves_them_when_editing(): void
    {
        $this->signIn();
        $this->get('/admin?tab=product&create=1')->assertOk()
            ->assertDontSee('name="slug"', false)->assertDontSee('name="images"', false)
            ->assertSee('data-media-field', false)->assertSee('quill-2.0.3/quill.js', false);

        $data = ['name' => 'انگشتر طلای جدید', 'category' => 'ring', 'milligrams' => 3000,
            'stock' => 1, 'labor' => 0, 'cost' => 0, 'profit_bps' => 0, 'discount_bps' => 0, 'tax_bps' => 0,
            'media' => json_encode([['path' => '/assets/ring.webp', 'alt' => 'نمای روبه‌رو']])];
        $slugs = [];
        for ($i = 0; $i < 2; $i++) {
            $response = $this->post('/admin/save/product', $data)->assertRedirectContains('tab=product&edit=');
            parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
            $slugs[] = $query['edit'];
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+-[0-9a-hjkmnp-tv-z]{26}$/', $query['edit']);
        }

        $this->assertNotSame(...$slugs);
        $edit = ['edit' => $slugs[0], 'original_stock' => 1, 'name' => 'نام تازه محصول'];
        $this->post('/admin/save/product', $edit + $data)->assertRedirect('/admin?tab=product&edit='.$slugs[0]);
        $this->assertSame($edit['name'], Entry::getData('product', $slugs[0])['name']);
        $this->assertSame($data['name'], Entry::getData('product', $slugs[1])['name']);
        $this->get('/product/'.$slugs[0])->assertOk();
    }

    public function test_video_upload_and_mixed_gallery_keep_a_photo_for_the_storefront_cover(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/admin/media')->assertForbidden();
        $this->signIn();
        Storage::fake('public');
        $text = UploadedFile::fake()->createWithContent('text.mp4', 'plain text');
        $this->postJson('/admin/upload', [
            'kind' => 'video', 'file' => new UploadedFile($text->getPathname(), 'text.mp4', 'video/mp4', null, true),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $clip = UploadedFile::fake()->create('ring-detail.mp4', 1, 'video/mp4');
        $video = $this->postJson('/admin/upload', ['kind' => 'video', 'file' => $clip])->assertOk()->json('path');
        Storage::disk('public')->assertExists(Str::after($video, '/storage/'));
        $this->getJson('/admin/media?type=video&q=ring')->assertExactJson([
            'items' => [['path' => $video, 'type' => 'video', 'label' => 'ring-detail']], 'next' => null,
        ]);
        $this->getJson('/admin/media?type=image&q=ring')->assertJsonMissing(['path' => $video]);

        $gallery = [
            ['path' => $video, 'alt' => ''],
            ['path' => '/assets/ring-detail.webp', 'alt' => 'نمای نزدیک'],
            ['path' => '/assets/ring.webp', 'alt' => 'نمای اصلی'],
        ];
        $data = ['slug' => 'mixed-gallery', 'name' => 'گالری مختلط', 'category' => 'ring', 'milligrams' => 3500,
            'stock' => 1, 'labor' => 0, 'cost' => 0, 'profit_bps' => 0, 'discount_bps' => 0, 'tax_bps' => 0,
            'media' => json_encode($gallery), 'description_html' => '1',
            'description' => '<p class="ql-align-center ql-direction-ltr arbitrary">Details</p>'
                .'<img src="/assets/ring.webp" alt="نمای نگین"><img src="https://example.test/remote.png">'];
        $this->post('/admin/save/product', ['media' => json_encode([$gallery[0]])] + $data)->assertSessionHasErrors('media');
        $this->post('/admin/save/product', $data)->assertRedirect('/admin?tab=product&edit=mixed-gallery');
        $stored = Entry::getData('product', $data['slug']);
        $this->assertSame($gallery, $stored['media']);
        $this->assertSame(['/assets/ring-detail.webp', '/assets/ring.webp'], $stored['images']);
        $this->get('/product/mixed-gallery')->assertOk()->assertSee('id="product-image" src="/assets/ring-detail.webp"', false)
            ->assertSee('data-gallery="'.$video.'" data-type="video"', false)
            ->assertSee('class="ql-align-center ql-direction-ltr">Details', false)
            ->assertSee('alt="نمای نگین"', false)->assertDontSee('example.test/remote.png')->assertDontSee('arbitrary');
    }

    public function test_checkout_order_filters_csv_and_stock_restoration(): void
    {
        $this->signIn();
        auth()->user()->forceFill(['name' => 'مشتری نمونه', 'mobile' => '09120000000', 'national_id_hash' => str_repeat('a', 64)])->save();
        $this->post('/cart/leaf')->assertRedirect('/cart');
        $cart = $this->get('/cart')->assertOk();
        $this->from('/cart')->post('/order', [
            'delivery' => 'pickup', 'gateway' => 'manual',
            'checkout_key' => $cart->viewData('checkoutKey'),
        ])->assertRedirectContains('/orders/')->assertSessionHasNoErrors();
        $checkout = DB::table('orders')->first();
        $this->assertSame(2, Entry::getData('product', 'leaf')['stock']);

        for ($index = 0; $index < 20; $index++) {
            DB::table('orders')->insert([
                'reference' => (string) Str::uuid(), 'customer' => $checkout->customer, 'status' => 'pending',
                'lines' => $checkout->lines, 'total' => $checkout->total, 'created_at' => now(),
            ]);
        }
        $filters = ['tab' => 'finance', 'status' => 'pending', 'q' => 'مشتری نمونه', 'to' => today()->toDateString()];
        $orders = $this->get(route('admin.index', $filters))->assertOk()->viewData('orders');
        $this->assertSame(21, $orders->total());
        $this->assertStringContainsString('status=pending', $orders->nextPageUrl());
        $second = $this->get($orders->nextPageUrl())->assertOk()->viewData('orders');
        $this->assertSame($checkout->reference, $second->first()->reference);
        $csv = $this->get(route('admin.export', ['q' => $checkout->reference]))->assertDownload('barin-orders.csv');
        $this->assertCount(2, explode("\n", trim($csv->streamedContent())));
        $this->assertStringContainsString($checkout->reference, $csv->streamedContent());

        $statusUrl = '/admin/orders/'.$checkout->id;
        $this->from('/admin?tab=finance')->post($statusUrl, ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame(3, Entry::getData('product', 'leaf')['stock']);
        $this->from('/admin?tab=finance')->post($statusUrl, ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->assertSame(3, Entry::getData('product', 'leaf')['stock']);
    }

    public function test_settings_keep_existing_tokens_and_image_upload_uses_the_public_catalog(): void
    {
        $this->signIn();
        Entry::putData('settings', 'store', ['rate_token' => Crypt::encryptString('original-token')]);
        $this->post('/admin/save/settings', ['slug' => 'store', 'rate_unit' => 'toman', 'rate_token' => ''])
            ->assertRedirect('/admin?tab=settings');
        $remaining = Entry::getData('settings', 'store')['rate_token'];
        $this->assertSame('original-token', Crypt::decryptString($remaining));
        $this->get('/admin?tab=settings')->assertOk()->assertDontSee($remaining);

        Storage::fake('public');
        $reference = new UploadedFile(public_path('assets/p1.webp'), 'sample.webp', 'image/webp', null, true);
        $upload = $this->postJson('/admin/upload', ['image' => $reference])->assertOk()->json('path');
        $this->assertStringStartsWith('/storage/catalog/', $upload);
        Storage::disk('public')->assertExists(Str::after($upload, '/storage/'));

        Storage::fake('local');
        $file = Str::uuid().'.webp';
        Storage::disk('local')->put('studio/'.$file, file_get_contents(public_path('assets/p1.webp')));
        $this->from('/admin?tab=studio')->post('/admin/image/'.$file.'/publish')
            ->assertRedirect('/admin?tab=studio')->assertSessionHas('upload', '/storage/catalog/'.$file);
        Storage::disk('public')->assertExists('catalog/'.$file);
    }

    public function test_content_management_updates_the_store_and_category_seo(): void
    {
        $this->signIn();
        $this->post('/admin/save/page', ['slug' => 'returns', 'name' => 'شرایط مرجوعی'])
            ->assertRedirect('/admin?tab=page&edit=returns');
        $this->get('/page/returns')->assertOk()->assertSee('شرایط مرجوعی');
        $this->delete('/admin/entries/page/returns')->assertRedirect('/admin?tab=page');
        $this->get('/page/returns')->assertNotFound();
        $this->delete('/admin/entries/page/home')->assertUnprocessable();

        $this->post('/admin/save/campaign', [
            'slug' => 'ring-promo', 'name' => 'جشنواره انگشتر', 'product' => 'ring',
            'start' => today()->toDateString(), 'end' => today()->toDateString(), 'placement' => 'both',
            'theme' => 'default', 'discount_bps' => '500',
        ])->assertRedirect('/admin?tab=campaign&edit=ring-promo');
        $this->get('/product/ring')->assertOk()->assertSee('جشنواره انگشتر');
        $this->delete('/admin/entries/campaign/ring-promo')->assertRedirect('/admin?tab=campaign');
        $this->get('/product/ring')->assertOk()->assertDontSee('جشنواره انگشتر');

        $this->post('/admin/save/seo', ['slug' => 'category-ring', 'title' => 'سئوی انگشتر', 'noindex' => '1'])
            ->assertRedirect('/admin?tab=seo&edit=category-ring');
        $this->get('/shop/category/ring')->assertOk()->assertSee('سئوی انگشتر')->assertSee('noindex,follow');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/shop/category/ring');
    }

    public function test_rich_content_is_editable_and_rendered_with_plain_metadata(): void
    {
        $this->signIn();
        $create = '/admin?tab=page&create=1';
        $edit = '/admin?tab=page&edit=care';
        $this->get($create)->assertOk()->assertSee('data-rich-body', false);
        $this->from($create)->post('/admin/save/page', [
            'slug' => 'care', 'name' => 'نگهداری طلا',
            'description' => '<p class="draft"><b>مراقبت</b> روزانه</p><ul><li>پاک‌کردن با پارچه</li></ul>'
                .'<a href="/shop" target="_blank">ویترین</a> <a href="//other.test">متن ساده</a><iframe>حذف‌شدنی</iframe>',
            'description_html' => '1',
        ])->assertRedirect($edit);
        $this->get($edit)->assertOk()->assertSee('name="description_html" value="1"', false)
            ->assertSee('<strong>مراقبت</strong>', false);
        $this->get('/page/care')->assertOk()
            ->assertSee('<strong>مراقبت</strong>', false)
            ->assertSee('<ul><li>پاک‌کردن با پارچه</li></ul>', false)
            ->assertSee('<a href="/shop">ویترین</a>', false)
            ->assertSee('content="مراقبت روزانه پاک‌کردن با پارچه ویترین متن ساده"', false)
            ->assertDontSee('class="draft"', false)->assertDontSee('//other.test')->assertDontSee('حذف‌شدنی');

        $this->from($edit)->post('/admin/save/page', [
            'slug' => 'care', 'edit' => 'care', 'name' => 'نگهداری طلا',
            'description' => "سطر یک\nسطر دو با <نمونه>",
        ])->assertRedirect($edit);
        $this->get('/page/care')->assertOk()->assertSee("سطر یک<br>\nسطر دو با &lt;نمونه&gt;", false)
            ->assertDontSee('<strong>مراقبت</strong>', false);
    }

    public function test_product_upload_uses_the_selected_studio_preset_and_its_output_can_be_saved(): void
    {
        $this->signIn();
        Storage::fake('public');
        Storage::fake('local');
        $url = 'https://api.openai.com/v1/images/edits';
        Entry::putData('settings', 'store', ['ai_url' => $url, 'ai_model' => 'gpt-image-1', 'ai_token' => Crypt::encryptString('studio-test-token')]);
        $output = file_get_contents(public_path('assets/m2.webp'));
        Http::preventStrayRequests()->fake([$url => Http::response(['data' => [['b64_json' => base64_encode($output)]]])]);
        $reference = new UploadedFile(public_path('assets/p2.webp'), 'reference.webp', 'image/webp', null, true);

        foreach (['create=1', 'edit=ring'] as $query) {
            $this->get('/admin?tab=product&'.$query)->assertOk()->assertSee('data-upload-preset', false)->assertSee('data-rich-body', false);
        }
        $this->get('/admin?tab=settings')->assertOk()->assertSee('gpt-image-2.5-sunburst');
        $upload = $this->postJson('/admin/upload', ['image' => $reference, 'preset' => 7])->assertOk()->json('path');
        $this->assertStringStartsWith('/storage/catalog/', $upload);
        $this->assertSame($output, Storage::disk('public')->get(Str::after($upload, '/storage/')));
        Http::assertSent(fn (ClientRequest $request) => $request->url() === $url
            && $request->hasHeader('Authorization', 'Bearer studio-test-token')
            && $request->hasFile('image', null, 'product.webp')
            && collect($request->data())->firstWhere('name', 'model')['contents'] === 'gpt-image-2.5-sunburst'
            && str_contains(collect($request->data())->firstWhere('name', 'prompt')['contents'], 'Place the bracelet on an adult wrist'));

        $this->post('/admin/save/product', [
            'slug' => 'studio-piece', 'name' => 'عکس استودیو', 'category' => 'bracelet', 'milligrams' => 3000,
            'stock' => 1, 'labor' => 0, 'cost' => 0, 'profit_bps' => 0, 'discount_bps' => 0, 'tax_bps' => 0,
            'images' => $upload, 'description' => '<p><em>توضیح محصول</em></p>', 'description_html' => '1',
        ])->assertRedirect('/admin?tab=product&edit=studio-piece');
        $this->get('/product/studio-piece')->assertOk()->assertSee($upload)->assertSee('<em>توضیح محصول</em>', false);

        $this->from('/admin?tab=studio')->post('/admin/image', ['preset' => 7, 'image' => $reference])
            ->assertRedirect('/admin?tab=studio')->assertSessionHas('generated');
        $this->assertSame($output, Storage::disk('local')->get(session('generated')));
        $this->assertCount(1, Storage::disk('public')->files('catalog'));

        $result = $this->postJson('/admin/image', ['preset' => 7, 'image' => $reference])->assertOk()->json();
        $this->get($result['path'])->assertOk();
        $this->assertCount(1, Storage::disk('public')->files('catalog'));
        $published = $this->postJson($result['publish'])->assertOk()->json('path');
        $this->assertSame($output, Storage::disk('public')->get(Str::after($published, '/storage/')));
    }

    public function test_invalid_studio_requests_and_provider_failures_do_not_add_a_gallery_image(): void
    {
        $reference = new UploadedFile(public_path('assets/p1.webp'), 'reference.webp', 'image/webp', null, true);
        $this->postJson('/admin/upload', ['image' => $reference, 'preset' => 1])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/admin/upload', ['image' => $reference, 'preset' => 1])->assertForbidden();
        $this->signIn();
        Storage::fake('public');
        $url = 'https://api.openai.com/v1/images/edits';
        Http::preventStrayRequests()->fake([$url => Http::sequence()->pushStatus(429)->push(['data' => [['b64_json' => base64_encode('text')]]])]);
        $this->postJson('/admin/upload', ['image' => $reference, 'preset' => 1])->assertUnprocessable()->assertJsonValidationErrors('image');
        Entry::putData('settings', 'store', ['ai_url' => $url]);
        $this->postJson('/admin/upload', ['image' => $reference, 'preset' => 9999])->assertUnprocessable()->assertJsonValidationErrors('preset');
        Http::assertNothingSent();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson('/admin/upload', ['image' => $reference, 'preset' => 1])->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->assertSame([], Storage::disk('public')->files('catalog'));
    }
}
