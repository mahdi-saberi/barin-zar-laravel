<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HttpsRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const INTERNAL = 'http://shop.example.test';

    private const PUBLIC = 'https://shop.example.test';

    public function test_cart_and_order_keep_the_public_https_origin_behind_an_http_proxy(): void
    {
        config(['app.url' => self::PUBLIC, 'session.secure' => null]);

        $product = $this->get(self::INTERNAL.'/product/leaf')->assertOk()
            ->assertSee('action="/cart/leaf"', false)
            ->assertSee('href="'.self::PUBLIC.'/assets/storefront.css"', false)
            ->assertDontSee(self::INTERNAL, false);
        $this->assertTrue($product->getCookie(config('session.cookie'), false)->isSecure());

        $this->post(self::INTERNAL.'/cart/leaf')->assertRedirect(self::PUBLIC.'/cart');
        $this->get(self::INTERNAL.'/cart')->assertOk()->assertSee(self::PUBLIC.'/account/register');
        $this->post(self::INTERNAL.'/order')->assertRedirect(self::PUBLIC.'/account/login');
        $this->actingAs(User::factory()->create(['mobile' => '09120000000', 'national_id_hash' => str_repeat('c', 64)]));
        $cart = $this->get(self::INTERNAL.'/cart')->assertOk()
            ->assertSee('action="/order"', false)
            ->assertDontSee(self::INTERNAL, false);
        $this->post(self::INTERNAL.'/order', [
            'delivery' => 'pickup', 'gateway' => 'manual',
            'checkout_key' => $cart->viewData('checkoutKey'),
        ])->assertSessionHasNoErrors()->assertRedirectContains(self::PUBLIC.'/orders/');
    }

    public function test_login_form_and_saved_redirects_keep_the_public_https_origin(): void
    {
        $user = User::factory()->create(['password' => 'test-admin-password']);
        config(['app.url' => self::PUBLIC, 'barin.admin_email' => $user->email]);

        $adminPath = '/admin?page=2&tab=product';
        $this->get(self::INTERNAL.$adminPath)->assertRedirect(self::PUBLIC.'/login');
        $this->get(self::INTERNAL.'/login/')->assertOk()
            ->assertSee('action="/login"', false)
            ->assertSee('href="'.self::PUBLIC.'/assets/admin.css"', false);

        $this->from(self::INTERNAL.':80/login')->post(self::INTERNAL.'/login', [
            'email' => $user->email,
            'password' => 'incorrect',
        ])->assertSessionHasErrors('email')->assertRedirect(self::PUBLIC.'/login');
        $this->post(self::INTERNAL.'/login', [
            'email' => $user->email,
            'password' => 'test-admin-password',
        ])->assertRedirect(self::PUBLIC.$adminPath);

        $this->get(self::INTERNAL.$adminPath)->assertOk()
            ->assertSee('action="/admin/logout"', false)
            ->assertDontSee(self::INTERNAL, false);
    }

    public function test_catalog_links_use_https_on_both_pagination_paths(): void
    {
        config(['app.url' => self::PUBLIC]);
        foreach (['', '?sort=asc'] as $query) {
            $response = $this->get(self::INTERNAL.'/shop/category/ring'.$query)->assertOk()
                ->assertSee('action="/shop/category/ring#catalog-results"', false)
                ->assertDontSee(self::INTERNAL, false);
            $this->assertStringStartsWith(self::PUBLIC.'/shop/category/ring?', $response->viewData('pagination')->nextPageUrl());
        }
    }

    public function test_configured_proxy_is_trusted_and_local_http_still_works(): void
    {
        config(['app.url' => 'http://localhost', 'trustedproxy.proxies' => '192.0.2.0/24']);

        $this->withHeader('X-Forwarded-Proto', 'https')->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get(self::INTERNAL.'/login')->assertOk()->assertSee(self::PUBLIC.'/assets/admin.css', false);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get(self::INTERNAL.'/login')->assertOk()
            ->assertSee(self::INTERNAL.'/assets/admin.css', false)
            ->assertSee('action="/login"', false);
    }

    public function test_production_uses_https_links_and_keeps_the_internal_health_check_available(): void
    {
        app()->instance('env', 'production');
        config(['app.url' => 'http://localhost']);

        $this->get(self::INTERNAL.'/login')->assertOk()->assertSee(self::PUBLIC.'/assets/admin.css', false);
        $this->get(self::INTERNAL.'/up')->assertOk();
    }
}
