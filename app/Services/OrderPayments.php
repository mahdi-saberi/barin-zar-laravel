<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payments\GatewayFailure;
use App\Services\Payments\Gateways;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderPayments
{
    public function __construct(private readonly Gateways $gateways) {}

    public function start(Order $order): Payment
    {
        return $this->lock($order->id, function () use ($order) {
            $order->refresh();
            if ($order->gateway === 'manual' || $order->status !== 'pending' || $order->expires_at?->isPast()) {
                throw ValidationException::withMessages(['payment' => 'مهلت پرداخت این سفارش پایان یافته یا سفارش تغییر وضعیت داده است.']);
            }
            $last = $order->payments()->latest('id')->first();
            if ($last?->status === 'verify_pending') {
                throw ValidationException::withMessages(['payment' => 'این پرداخت در انتظار استعلام از بانک است.']);
            }
            if ($last?->status === 'redirected') {
                return $last;
            }
            if ($last?->status === 'created') {
                $last->update(['status' => 'failed', 'code' => 'interrupted', 'credentials' => null]);
            }

            $gateway = PaymentGateway::find($order->gateway);
            if (!$gateway?->enabled) {
                throw ValidationException::withMessages(['payment' => 'درگاه انتخابی در دسترس نیست.']);
            }
            $base = rtrim((string) config('app.url'), '/');
            if (app()->isProduction() && parse_url($base, PHP_URL_SCHEME) !== 'https') {
                throw ValidationException::withMessages(['payment' => 'نشانی عمومی HTTPS فروشگاه برای بازگشت از بانک لازم است.']);
            }
            $payment = $order->payments()->create([
                'reference' => (string) Str::uuid(), 'provider' => $order->gateway, 'status' => 'created',
                'amount_rial' => $order->total * 10, 'credentials' => $gateway->credentials,
            ]);
            try {
                $callback = $base.route('payment.return', $payment->reference, false);
                $payment->update(['token' => $this->gateways->issue($payment, $callback), 'status' => 'redirected']);
            } catch (GatewayFailure $error) {
                $payment->update(['status' => 'failed', 'code' => $error->getMessage(), 'credentials' => null]);
                throw ValidationException::withMessages(['payment' => 'درگاه توکن پرداخت صادر نکرد. جزئیات در گزارش پرداخت ثبت شد.']);
            }

            return $payment;
        });
    }

    public function verify(Payment $payment, ?array $incoming = null): void
    {
        $this->lock($payment->order_id, function () use ($payment, $incoming) {
            $payment->refresh();
            if ($payment->confirmed_at || !$payment->token) {
                return;
            }
            $returned = $incoming !== null ? $this->gateways->returned($payment, $incoming) : $payment->return_data;
            if ($returned === null) {
                return;
            }
            $payment->update(['status' => 'verify_pending', 'return_data' => $returned]);
            try {
                $receipt = $this->gateways->verify($payment, $returned);
            } catch (GatewayFailure $error) {
                $payment->update(['code' => $error->getMessage()]);

                return;
            }

            try {
                DB::transaction(function () use ($payment, $receipt) {
                    $order = Order::lockForUpdate()->findOrFail($payment->order_id);
                    $allocatable = $order->status === 'pending';
                    Payment::lockForUpdate()->findOrFail($payment->id)->update([
                        'status' => $allocatable ? 'confirmed' : 'review', 'bank_reference' => $receipt['reference'],
                        'fee_rial' => $receipt['fee_rial'] ?? 0, 'confirmed_at' => now(),
                        'return_data' => null, 'credentials' => null, 'token' => null,
                        'code' => $allocatable ? null : 'payment_after_closure',
                    ]);
                    $order->update(['status' => $allocatable ? 'paid' : 'review', 'paid_at' => now()]);
                    $order->record($allocatable ? 'payment_confirmed' : 'payment_after_closure');
                }, 3);
            } catch (UniqueConstraintViolationException) {
                $payment->update(['code' => 'duplicate_reference']);
            }
        });
    }

    public function changeStatus(Order $order, string $status, int $actor, ?string $manualReference = null): void
    {
        $this->lock($order->id, function () use ($order, $status, $actor, $manualReference) {
            DB::transaction(function () use ($order, $status, $actor, $manualReference) {
                $order = Order::lockForUpdate()->findOrFail($order->id);
                if (!in_array($status, Order::TRANSITIONS[$order->status] ?? [], true)
                    || ($status === 'paid' && ($order->gateway !== 'manual' || !$manualReference))
                    || ($status === 'cancelled' && $order->payments()->where('status', 'verify_pending')->exists())) {
                    throw ValidationException::withMessages(['status' => 'این تغییر وضعیت برای سفارش مجاز نیست. پرداخت و وضعیت سفارش را دوباره بررسی کنید.']);
                }
                if ($status === 'cancelled') {
                    $order->releaseStock();
                }
                $order->update(['status' => $status] + ($status === 'paid' ? ['paid_at' => now(), 'manual_reference' => $manualReference] : []));
                $order->record($status === 'paid' ? 'manual_payment' : $status, $actor);
            }, 3);
        });
    }

    public function expireOrders(): void
    {
        Order::where('status', 'pending')->where('expires_at', '<=', now())
            ->whereDoesntHave('payments', fn ($query) => $query->where('status', 'verify_pending'))
            ->select('id')->chunkById(50, function ($orders) {
                foreach ($orders as $order) {
                    Cache::store('database')->lock('barin-order:'.$order->id, 120)->get(function () use ($order) {
                        DB::transaction(function () use ($order) {
                            $order = Order::lockForUpdate()->findOrFail($order->id);
                            if ($order->status !== 'pending' || !$order->expires_at?->isPast()
                                || $order->payments()->where('status', 'verify_pending')->exists()) {
                                return;
                            }
                            $order->releaseStock();
                            $order->update(['status' => 'expired']);
                            $order->record('expired');
                        }, 3);
                    });
                }
            });
    }

    private function lock(int $id, Closure $callback): mixed
    {
        try {
            return Cache::store('database')->lock('barin-order:'.$id, 120)->block(3, $callback);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['payment' => 'یک عملیات برای این سفارش در حال انجام است. دوباره بررسی کنید.']);
        }
    }
}
