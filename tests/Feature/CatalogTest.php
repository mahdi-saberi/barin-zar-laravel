<?php

namespace Tests\Feature;

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_home_shows_three_previews_for_each_gold_category(): void
    {
        $response = $this->get('/')->assertOk();
        $collections = $response->viewData('collections');
        $this->assertSame(array_keys(config('barin.categories')), $collections->keys()->all());
        $this->assertSame(18, substr_count($response->getContent(), '<article class="product">'));

        foreach ($collections as $category => $products) {
            $this->assertCount(3, $products);
            $this->assertSame([$category], $products->pluck('data.category')->unique()->values()->all());
            $response->assertSee(route('shop.category', $category));
        }
    }

    public function test_default_home_banner_updates_and_custom_hero_is_preserved(): void
    {
        $home = Entry::where('kind', 'page')->where('slug', 'home')->firstOrFail();
        $home->update(['data' => ['hero' => '/assets/campaign.webp'] + $home->data]);
        $this->get('/')->assertOk()->assertSee('src="/assets/barinzar-hero.webp"', false);

        $home->update(['data' => ['hero_alt' => 'بنر انتخاب‌شده'] + $home->data]);
        $this->get('/')->assertOk()->assertSee('src="/assets/campaign.webp" alt="بنر انتخاب‌شده"', false)
            ->assertDontSee('src="/assets/barinzar-hero.webp"', false);

        $home->update(['data' => ['hero' => '/assets/p1.webp', 'hero_alt' => ''] + $home->data]);
        $this->get('/')->assertOk()->assertSee('src="/assets/p1.webp" alt="" fetchpriority="high"', false)
            ->assertDontSee('src="/assets/barinzar-hero.webp"', false);

        $home->update(['data' => ['hero' => null] + $home->data]);
        $this->get('/')->assertOk()->assertSee('hero-text-only')->assertDontSee('class="hero-media"', false);
    }

    public function test_category_and_price_filters_apply_before_pagination(): void
    {
        $filters = ['q' => 'انگشتر', 'max' => 100000000, 'sort' => 'asc'];
        $url = route('shop.category', ['category' => 'ring'] + $filters);
        $this->get(route('shop.index', ['category' => 'ring'] + $filters))->assertRedirect($url);

        $firstPage = $this->get($url)->assertOk();
        $first = $firstPage->viewData('pagination');
        $this->assertSame(17, $first->total());
        $this->assertCount(12, $first);
        parse_str(parse_url($first->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertEquals($filters + ['page' => 2], $query);

        $secondPage = $this->get($first->nextPageUrl())->assertOk();
        $second = $secondPage->viewData('pagination');
        $this->assertCount(5, $second);
        $this->assertSame(['ring'], $second->getCollection()->pluck('data.category')->unique()->values()->all());
        $this->assertEmpty($first->pluck('slug')->intersect($second->pluck('slug')));
        $firstQuotes = $firstPage->viewData('quotes')->pluck('total');
        $secondQuotes = $secondPage->viewData('quotes')->pluck('total');
        $totals = $firstQuotes->concat($secondQuotes);
        $this->assertSame($totals->all(), $totals->sort()->values()->all());

        $this->get('/shop/category/ring?page=2')->assertOk()->assertSee('rel="prev"', false);
        $this->get('/shop/category/ring?page=3')->assertNotFound();
        $this->get('/shop/category/missing')->assertNotFound();
    }

    public function test_weight_and_availability_filters_accept_existing_json_strings_and_preserve_pagination(): void
    {
        $rings = Entry::products()->where('data->category', 'ring')->orderBy('id')->get();
        foreach ($rings as $position => $ring) {
            $weight = match ($position) {
                13 => 5999,
                14 => 2999,
                15 => 6000,
                default => 3000 + $position,
            };
            $ring->update(['data' => [
                'milligrams' => (string) $weight,
                'stock' => $position === 16 ? '0' : '2',
            ] + $ring->data]);
        }

        $filters = ['sort' => 'asc', 'weight' => '3-to-6', 'in_stock' => 1];
        $url = route('shop.category', ['category' => 'ring'] + $filters);
        $this->get(route('shop.index', ['category' => 'ring'] + $filters))->assertRedirect($url);

        $first = $this->get($url)->assertOk()->viewData('pagination');
        $this->assertSame(14, $first->total());
        $this->assertCount(12, $first);
        parse_str(parse_url($first->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertEquals($filters + ['page' => 2], $query);

        $second = $this->get($first->nextPageUrl())->assertOk()->viewData('pagination');
        $this->assertEqualsCanonicalizing($rings->take(14)->pluck('slug')->all(), $first->pluck('slug')->merge($second->pluck('slug'))->all());
        $this->assertSame([$rings[14]->slug], $this->get('/shop/category/ring?weight=under-3')->assertOk()->viewData('pagination')->pluck('slug')->all());
        $this->assertSame([$rings[15]->slug], $this->get('/shop/category/ring?weight=6-and-up')->assertOk()->viewData('pagination')->pluck('slug')->all());
        $this->assertSame(17, $this->get('/shop/category/ring?weight[]=3-to-6&in_stock=false')->assertOk()->viewData('pagination')->total());
    }

    public function test_store_rate_displays_persian_date_after_the_price_in_tehran_time(): void
    {
        config(['app.timezone' => 'UTC']);
        $timestamp = '2026-03-20T20:45:00+00:00';
        Entry::putData('rate', 'latest', ['value' => 25778000, 'source' => 'api', 'timestamp' => $timestamp]);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['نرخ طلای ۱۸ عیار', '۲۵٬۷۷۸٬۰۰۰', '۱۴۰۵/۰۱/۰۱ ۰۰:۱۵'])
            ->assertSee('datetime="'.$timestamp.'"', false);
    }

    public function test_store_rate_identifies_sample_values_without_inventing_a_timestamp(): void
    {
        Entry::putData('rate', 'latest', ['value' => 10500000, 'source' => 'sample']);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['نرخ طلای ۱۸ عیار', '۱۰٬۵۰۰٬۰۰۰', 'نمونه'])
            ->assertDontSee('datetime=', false);
    }

    public function test_product_recommendations_fill_even_rows_with_available_alternatives(): void
    {
        $response = $this->get('/product/wave')->assertOk();
        $recommendations = $response->viewData('products');
        $this->assertCount(4, $recommendations);
        $this->assertSame('bracelet', $recommendations->first()->data['category']);
        $this->assertNotContains('wave', $recommendations->pluck('slug'));
        $this->assertCount(4, $recommendations->pluck('data.images.0')->unique());

        Entry::products()->whereNotIn('slug', ['wave', 'leaf', 'hoop', 'ring'])->delete();
        $this->assertCount(2, $this->get('/product/wave')->assertOk()->viewData('products'));

        Entry::products()->whereIn('slug', ['hoop', 'ring'])->delete();
        $this->get('/product/wave')->assertOk()->assertDontSee('class="wrap section product-recommendations"', false)
            ->assertSee('href="tel:+982155616452"', false);
    }

    public function test_drafts_stay_out_of_the_store_cart_and_sitemap(): void
    {
        $product = Entry::products()->where('slug', 'ring')->firstOrFail();
        $product->update(['data' => ['published' => false] + $product->data]);

        $productLink = 'href="'.route('product.show', 'ring').'"';
        $this->get('/')->assertOk()->assertDontSee($productLink, false);
        $this->get('/shop/category/ring')->assertOk()->assertDontSee($productLink, false);
        $this->get('/product/ring')->assertNotFound();
        $this->post('/cart/ring')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertSee('/shop/category/ring')->assertDontSee('/product/ring');

        $this->withSession(['cart' => ['ring', 'leaf']])->get('/cart')
            ->assertOk()->assertSessionHas('cart', ['leaf'])->assertSee('/product/leaf')->assertDontSee('/product/ring');
    }
}
