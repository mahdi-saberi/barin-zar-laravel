<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_assigns_roles_and_staff_cannot_cross_permissions(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create(['mobile' => '09120000000']);
        config(['barin.admin_email' => $owner->email]);
        $this->actingAs($owner)->get('/admin?tab=users')->assertOk()->assertSee($staff->name);
        $this->post(route('admin.users.role', $staff), ['role' => 'catalog'])->assertRedirect();
        $this->assertSame('catalog', $staff->fresh()->role);
        $this->post(route('admin.users.role', $owner), ['role' => 'customer'])->assertUnprocessable();

        $this->actingAs($staff->fresh())->get('/admin?tab=product')->assertOk();
        $this->get('/admin?tab=customers')->assertForbidden();
        $this->get('/admin?tab=finance')->assertForbidden();
        $this->get('/admin?tab=gateways')->assertForbidden();
        $this->post('/admin/save/settings')->assertForbidden();
        $this->post(route('admin.users.role', $staff), ['role' => 'admin'])->assertForbidden();

        $this->actingAs($owner)->post(route('admin.users.role', $staff), ['role' => 'orders'])->assertRedirect();
        $this->actingAs($staff->fresh())->get('/admin?tab=finance')->assertOk()->assertDontSee('خروجی CSV');
        $this->get('/admin/export')->assertForbidden();
        $this->post('/admin/orders/1', ['status' => 'paid'])->assertForbidden();
        $this->get('/admin?tab=customers&q=۰۹۱۲۰۰۰۰۰۰۰')->assertOk()->assertSee($staff->name);
    }

    public function test_gateway_credentials_are_encrypted_and_activation_requires_a_complete_configuration(): void
    {
        $owner = User::factory()->create();
        config(['barin.admin_email' => $owner->email]);
        $this->actingAs($owner)->get('/admin?tab=gateways')->assertOk()
            ->assertSee('زرین‌پال')->assertSee('بانک سامان')->assertSee('بانک ملت')->assertSee('بانک ملی')->assertSee('بانک شهر');
        $route = route('admin.gateways.save', 'mellat');
        $this->from('/admin?tab=gateways')->post($route, ['enabled' => 1, 'credentials' => ['terminal_id' => '1234']])
            ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.credentials');
        $this->assertDatabaseCount('payment_gateways', 0);
        $credentials = ['terminal_id' => '1234', 'username' => 'x-fixture-user', 'password' => 'fixture-bank-password'];
        $this->post($route, ['enabled' => 1, 'credentials' => $credentials])->assertSessionHasNoErrors()->assertRedirect('/admin?tab=gateways');
        $gateway = PaymentGateway::findOrFail('mellat');
        $this->assertTrue($gateway->enabled);
        $this->assertSame($credentials, $gateway->credentials);
        $this->assertStringNotContainsString($credentials['password'], $gateway->getRawOriginal('credentials'));
        $this->get('/admin?tab=gateways')->assertOk()->assertDontSee($credentials['password'])->assertDontSee($credentials['username']);
        $this->post($route, ['enabled' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($gateway->fresh()->enabled);
        $this->assertSame($credentials, $gateway->fresh()->credentials);
    }
}
