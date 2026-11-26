<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\OrderPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function customer(): User
    {
        return User::factory()->create(['mobile' => '09120000000', 'national_id_hash' => str_repeat('a', 64)]);
    }

    private function order(string $provider, array $credentials): Order
    {
        $this->actingAs($this->customer());
        PaymentGateway::create(['provider' => $provider, 'enabled' => true, 'credentials' => $credentials]);
        $this->post('/cart/leaf')->assertRedirect('/cart');
        $key = $this->get('/cart')->assertOk()->viewData('checkoutKey');
        $this->from('/cart')->post('/order', ['delivery' => 'pickup', 'gateway' => $provider, 'checkout_key' => $key])
            ->assertSessionHasNoErrors()->assertRedirectContains('/orders/');
        $order = Order::firstOrFail();
        $this->assertSame(2, Entry::getData('product', 'leaf')['stock']);

        return $order;
    }

    private function start(Order $order)
    {
        return $this->post(route('payment.start', $order->reference));
    }

    private function bankReturn(Payment $payment, array $data)
    {
        return $this->post(route('payment.return', $payment->reference), $data)->assertRedirect(route('order.show', $payment->order->reference));
    }

    public function test_account_is_required_and_identity_and_delivery_are_saved_safely(): void
    {
        $this->post('/cart/leaf');
        $cart = $this->get('/cart')->assertOk()->assertSee('ثبت‌نام')->assertDontSee('action="/order"', false);
        $this->post('/order')->assertRedirect('/account/login');
        $this->assertDatabaseCount('orders', 0);
        $data = [
            'first_name' => 'نوشین', 'last_name' => 'آزمایشی', 'mobile' => '۰۹۱۲۰۰۰۰۰۰۰', 'national_id' => '۱۲۳۴۵۶۷۸۹۱',
            'password' => 'customer-only-password', 'password_confirmation' => 'customer-only-password', 'role' => 'admin',
        ];
        $this->from('/account/register')->post('/account/register', ['national_id' => '1111111111'] + $data)
            ->assertSessionHasErrors('national_id')->assertSessionMissing('_old_input.national_id');
        $this->post('/account/register', $data)->assertRedirect('/cart');
        $user = User::firstOrFail();
        $this->assertSame('09120000000', $user->mobile);
        $this->assertSame('1234567891', $user->national_id);
        $this->assertSame('customer', $user->role);
        $this->assertNotSame($user->national_id, $user->getRawOriginal('national_id'));

        $input = ['delivery' => 'shipping', 'gateway' => 'manual', 'checkout_key' => $cart->viewData('checkoutKey')];
        $this->from('/cart')->post('/order', $input)->assertSessionHasErrors('address');
        $this->post('/order', $input + ['address' => 'نشانی آزمون', 'name' => 'تغییر نام', 'total' => 1])
            ->assertRedirectContains('/orders/');
        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('نوشین آزمایشی', $order->customer['name']);
        $this->assertSame('نشانی آزمون', $order->shipping_address);
        $this->assertNotSame($order->shipping_address, $order->getRawOriginal('shipping_address'));
        $this->assertGreaterThan(1, $order->total);
        $this->get(route('order.show', $order->reference))->assertOk()->assertSee('نشانی آزمون')->assertDontSee('1234567891');
        $this->actingAs(User::factory()->create())->get(route('order.show', $order->reference))->assertNotFound();
    }

    public function test_zarinpal_confirms_the_exact_amount_once_and_preserves_in_flight_credentials(): void
    {
        $product = Entry::products()->where('slug', 'leaf')->firstOrFail();
        $product->update(['data' => ['cost' => 20000000] + $product->data]);
        $merchant = '27add7a5-58c1-42c7-9f2f-6ce920a3cf21';
        $order = $this->order('zarinpal', ['merchant_id' => $merchant]);
        $start = 'https://payment.zarinpal.com/pg/v4/payment/request.json';
        $verify = 'https://payment.zarinpal.com/pg/v4/payment/verify.json';
        Http::preventStrayRequests()->fake([
            $start => Http::response(['data' => ['code' => 100, 'authority' => 'A-token']]),
            $verify => Http::response(['data' => ['code' => 101, 'ref_id' => 74521, 'fee' => 2123, 'fee_type' => 'Merchant']]),
        ]);
        $this->start($order)->assertRedirect('https://payment.zarinpal.com/pg/StartPay/A-token');
        $this->start($order)->assertRedirect('https://payment.zarinpal.com/pg/StartPay/A-token');
        $payment = $order->payments()->firstOrFail();
        Http::assertSent(fn (Request $req) => $req->url() === $start && $req['currency'] === 'IRR'
            && $req['amount'] === $order->total * 10 && str_ends_with($req['callback_url'], $payment->reference));
        PaymentGateway::find('zarinpal')->update(['enabled' => false, 'credentials' => ['merchant_id' => 'rotated']]);
        auth()->logout();
        $this->bankReturn($payment, ['Authority' => 'different-token', 'Status' => 'OK']);
        Http::assertSentCount(1);
        $this->assertSame('pending', $order->fresh()->status);
        $this->bankReturn($payment, ['Authority' => 'A-token', 'Status' => 'OK', 'amount' => 1]);
        $this->bankReturn($payment, ['Authority' => 'A-token', 'Status' => 'OK']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $req) => $req->url() === $verify && $req['merchant_id'] === $merchant && $req['amount'] === $order->total * 10);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('74521', $payment->fresh()->bank_reference);
        $this->assertSame(2123, $payment->fresh()->fee_rial);
        $this->assertNull($payment->fresh()->credentials);
        $this->assertSame(1, DB::table('order_events')->where('event', 'payment_confirmed')->count());
        $owner = User::factory()->create();
        config(['barin.admin_email' => $owner->email]);
        $metrics = $this->actingAs($owner)->get('/admin?tab=finance&gateway=zarinpal')->assertOk()->viewData('metrics');
        $this->assertSame($order->total, $metrics['total']);
        $this->assertSame($order->total - $metrics['tax'] - 20000000, $metrics['margin']);
        $this->assertSame($order->total * 10 - 2123, $metrics['net_rial']);
    }

    public function test_saman_checks_amount_then_retries_using_the_saved_bank_reference(): void
    {
        $order = $this->order('saman', ['terminal_id' => '123456']);
        $verify = 'https://sep.shaparak.ir/verifyTxnRandomSessionkey/ipg/VerifyTransaction';
        $detail = ['TerminalNumber' => 123456, 'RefNum' => 'SEP-ref'];
        Http::preventStrayRequests()->fake([
            'https://sep.shaparak.ir/OnlinePG/OnlinePG' => Http::response(['status' => 1, 'token' => 'sep-token']),
            $verify => Http::sequence()
                ->push(['Success' => true, 'ResultCode' => 0, 'TransactionDetail' => $detail + ['OrginalAmount' => 200]])
                ->push(['Success' => true, 'ResultCode' => 2, 'TransactionDetail' => $detail + ['OrginalAmount' => $order->total * 10]]),
        ]);
        $this->start($order)->assertOk()->assertSee('name="Token" value="sep-token"', false);
        $payment = $order->payments()->firstOrFail();
        $this->bankReturn($payment, ['State' => 'OK', 'Status' => '2', 'ResNum' => $payment->id, 'TerminalId' => 123456, 'RefNum' => 'SEP-ref']);
        $this->assertSame('verify_pending', $payment->fresh()->status);
        $this->assertSame('unmatched_amount', $payment->fresh()->code);
        $this->assertSame('pending', $order->fresh()->status);
        $this->post(route('payment.check', $order->reference))->assertRedirect();
        Http::assertSent(fn (Request $req) => $req->url() === $verify && $req['RefNum'] === 'SEP-ref' && $req['TerminalNumber'] === 123456);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_a_bank_reference_cannot_be_allocated_to_two_orders(): void
    {
        $order = $this->order('zarinpal', ['merchant_id' => 'fixture-merchant']);
        $previous = $order->replicate(['reference']);
        $previous->fill(['reference' => (string) \Illuminate\Support\Str::uuid(), 'status' => 'paid'])->save();
        $previous->payments()->create([
            'reference' => (string) \Illuminate\Support\Str::uuid(), 'provider' => 'zarinpal', 'status' => 'confirmed',
            'amount_rial' => $order->total * 10, 'bank_reference' => '8912', 'confirmed_at' => now(),
        ]);
        Http::preventStrayRequests()->fake([
            'https://payment.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'new-token']]),
            'https://payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 8912]]),
        ]);
        $this->start($order)->assertRedirect();
        $payment = $order->payments()->firstOrFail();
        $this->bankReturn($payment, ['Authority' => 'new-token', 'Status' => 'OK']);
        $this->assertSame('duplicate_reference', $payment->fresh()->code);
        $this->assertNull($payment->fresh()->confirmed_at);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(1, Payment::whereNotNull('confirmed_at')->count());
    }

    public function test_mellat_requires_verify_and_settle_with_matching_order_number(): void
    {
        $order = $this->order('mellat', ['terminal_id' => '17', 'username' => 'test-user', 'password' => 'fixture-pass']);
        $url = 'https://bpm.shaparak.ir/pgwchannel/services/pgw';
        Http::preventStrayRequests()->fake([
            $url => function (Request $req) use ($order) {
                $xml = $req->body();
                $id = $order->payments()->firstOrFail()->id;
                $this->assertStringContainsString('<orderId>'.$id.'</orderId>', $xml);
                foreach (['bpPayRequest' => '0,BEH-REF', 'bpVerifyRequest' => '43', 'bpSettleRequest' => '45'] as $method => $result) {
                    if (str_contains($xml, '<p:'.$method.'>')) {
                        $payload = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><'.$method.'Response><return>'
                            .$result.'</return></'.$method.'Response></s:Body></s:Envelope>';

                        return Http::response($payload, 200, ['Content-Type' => 'text/xml']);
                    }
                }
                $this->fail('Unexpected SOAP operation.');
            },
        ]);
        $this->start($order)->assertOk()->assertSee('name="RefId" value="BEH-REF"', false);
        $payment = $order->payments()->firstOrFail();
        $this->bankReturn($payment, ['RefId' => 'BEH-REF', 'ResCode' => 0, 'SaleOrderId' => $payment->id, 'SaleReferenceId' => '9182736450']);
        Http::assertSentCount(3);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('9182736450', $payment->fresh()->bank_reference);
    }

    public function test_melli_signs_the_purchase_and_verifies_the_issued_token(): void
    {
        $key = '1234567887654321test-key';
        $order = $this->order('melli', ['terminal_id' => '19', 'merchant_id' => '21', 'key' => base64_encode($key)]);
        $base = 'https://sadad.shaparak.ir/VPG';
        Http::preventStrayRequests()->fake([
            $base.'/api/v0/Request/PaymentRequest' => function (Request $req) use ($key, $order) {
                $this->assertSame('19;'.$req['OrderId'].';'.$order->total * 10, openssl_decrypt(base64_decode($req['SignData']), 'des-ede3', $key, OPENSSL_RAW_DATA));

                return Http::response(['ResCode' => 0, 'Token' => 'sadad-token']);
            },
            $base.'/api/v0/Advice/Verify' => function (Request $req) use ($key, $order) {
                $this->assertSame('sadad-token', openssl_decrypt(base64_decode($req['SignData']), 'des-ede3', $key, OPENSSL_RAW_DATA));

                return Http::response(['ResCode' => 0, 'Amount' => $order->total * 10, 'OrderId' => $order->payments()->firstOrFail()->id, 'RetrivalRefNo' => '56123']);
            },
        ]);
        $this->start($order)->assertRedirect($base.'/Purchase?Token=sadad-token');
        $payment = $order->payments()->firstOrFail();
        $this->bankReturn($payment, ['token' => 'sadad-token', 'ResCode' => 0]);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('56123', $payment->fresh()->bank_reference);
    }

    public function test_shahr_uses_the_asanpardakht_merchant_invoice_for_verification(): void
    {
        $order = $this->order('shahr', ['merchant_config_id' => '1234', 'username' => 'merchant-user', 'password' => 'fixture-pass']);
        $base = 'https://ipgrest.asanpardakht.ir/v1/';
        Http::preventStrayRequests()->fake([
            $base.'Time' => Http::response(json_encode('2026/11/26 18:00:00'), 200, ['Content-Type' => 'application/json']),
            $base.'Token' => Http::response(json_encode('ap-token'), 200, ['Content-Type' => 'application/json']),
            $base.'TranResult*' => Http::response(['refID' => 'ap-token', 'amountInRials' => $order->total * 10, 'payGateTranID' => 30001]),
            $base.'Verify' => Http::response('', 200),
            $base.'Settlement' => Http::response('', 200),
        ]);
        $this->start($order)->assertOk()->assertSee('name="RefID" value="ap-token"', false);
        $payment = $order->payments()->firstOrFail();
        $this->bankReturn($payment, []);
        Http::assertSentCount(5);
        Http::assertSent(fn (Request $req) => str_starts_with($req->url(), $base.'TranResult?')
            && str_contains($req->url(), 'localInvoiceId='.$payment->id) && $req->hasHeader('usr', 'merchant-user'));
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_late_bank_confirmation_is_visible_for_reconciliation_and_never_reallocates_stock(): void
    {
        $order = $this->order('zarinpal', ['merchant_id' => 'fixture-merchant']);
        Http::preventStrayRequests()->fake([
            'https://payment.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'late-token']]),
            'https://payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 8912]]),
        ]);
        $this->start($order)->assertRedirect();
        $payment = $order->payments()->firstOrFail();
        $this->travel(21)->minutes();
        app(OrderPayments::class)->expireOrders();
        app(OrderPayments::class)->expireOrders();
        $this->assertSame('expired', $order->fresh()->status);
        $this->assertSame(3, Entry::getData('product', 'leaf')['stock']);
        $this->bankReturn($payment, ['Authority' => 'late-token', 'Status' => 'OK']);
        $this->assertSame('review', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->confirmed_at);
        $this->assertSame('review', $order->fresh()->status);
        $this->assertSame(3, Entry::getData('product', 'leaf')['stock']);
    }
}
