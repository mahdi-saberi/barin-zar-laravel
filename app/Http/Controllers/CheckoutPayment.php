<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderPayments;
use App\Services\Payments\Gateways;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutPayment extends Controller
{
    public function __construct(private readonly OrderPayments $payments, private readonly Gateways $gateways) {}

    public function show(Request $request, Store $store, string $reference)
    {
        $order = $this->owned($request, $reference)->load('payments');

        return $store->show($request, 'order', extras: ['order' => $order, 'gatewayNames' => Gateways::DEFINITIONS]);
    }

    public function start(Request $request, string $reference)
    {
        $order = $this->owned($request, $reference);
        try {
            return $this->handoff($this->payments->start($order));
        } catch (ValidationException $error) {
            return redirect()->route('order.show', $reference)->withErrors($error->errors());
        }
    }

    public function handoff(Payment $payment)
    {
        $form = $this->gateways->handoff($payment);
        if (!$form['fields']) {
            return redirect()->away($form['url'], 303)->header('Cache-Control', 'no-store');
        }

        return response()->view('store.bank-handoff', $form)->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'origin');
    }

    public function returned(Request $request, string $reference)
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();
        try {
            $this->payments->verify($payment, $request->all());
        } catch (ValidationException) {
            // A simultaneous callback can be completing the same payment.
        }

        return redirect()->route('order.show', $payment->order->reference, 303)->header('Cache-Control', 'no-store');
    }

    public function check(Request $request, string $reference)
    {
        $order = $this->owned($request, $reference);
        $payment = $order->payments()->where('status', 'verify_pending')->latest('id')->firstOrFail();
        $this->payments->verify($payment);

        return redirect()->route('order.show', $reference);
    }

    private function owned(Request $request, string $reference): Order
    {
        return $request->user()->orders()->where('reference', $reference)->firstOrFail();
    }
}
