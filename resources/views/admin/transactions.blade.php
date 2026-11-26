<section class="admin-card" id="transactions" aria-labelledby="transactions-heading">
    <div class="admin-card-heading"><h2 id="transactions-heading">تراکنش‌های درگاه</h2><p>{{ number_format($payments->total()) }} تراکنش</p></div>
    <div class="admin-table-scroll" role="region" aria-label="جدول تراکنش‌ها" tabindex="0">
        <table class="admin-table">
            <thead><tr><th scope="col">تراکنش / سفارش</th><th scope="col">درگاه</th><th scope="col">مبلغ (ریال)</th><th scope="col">کارمزد پذیرنده (ریال)</th><th scope="col">وضعیت</th><th scope="col">شناسهٔ بانکی</th><th scope="col">تاریخ</th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <th scope="row"><a class="admin-text-link" href="{{ route('admin.index', ['tab' => 'finance', 'q' => $payment->order->reference]) }}">#{{ $payment->id }} · سفارش</a></th>
                    <td>{{ $gatewayDefinitions[$payment->provider]['name'] ?? $payment->provider }}</td>
                    <td class="admin-numeric"><bdi>{{ number_format($payment->amount_rial) }}</bdi></td>
                    <td class="admin-numeric"><bdi>{{ number_format($payment->fee_rial) }}</bdi></td>
                    <td>
                        <span class="admin-badge {{ $payment->status === 'confirmed' ? 'badge-success' : 'badge-warning' }}">{{ \App\Models\Payment::LABELS[$payment->status] }}</span>
                        @if($payment->code)<small class="admin-block"><bdi>{{ $payment->code }}</bdi></small>@endif
                        @if($payment->status === 'verify_pending')
                            <form method="post" action="{{ route('admin.payments.check', $payment, false) }}">
                                @csrf
                                <button type="submit" class="admin-button admin-button-small">استعلام دوباره</button>
                            </form>
                        @endif
                    </td>
                    <td><bdi>{{ $payment->bank_reference ?? '—' }}</bdi></td>
                    <td>{{ \App\Support\PersianFormat::dateTime(($payment->confirmed_at ?? $payment->created_at)->toIso8601String()) }}</td>
                </tr>
            @empty
                <tr><td class="admin-empty" colspan="7">تراکنشی پیدا نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $payments->onEachSide(1)->fragment('transactions')->links('partials.pagination') }}
</section>
