<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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

    public function test_checkout_order_filters_csv_and_stock_restoration(): void
    {
        $this->signIn();
        $this->post('/cart/leaf')->assertRedirect('/cart');
        $cart = $this->get('/cart')->assertOk();
        $this->from('/cart')->post('/order', [
            'name' => 'مشتری نمونه', 'mobile' => '09120000000', 'delivery' => 'pickup',
            'checkout_key' => $cart->viewData('checkoutKey'),
        ])->assertRedirect('/cart')->assertSessionHasNoErrors();
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
}
