<div class="admin-table-scroll" tabindex="0" role="region" aria-label="جدول سفارش‌ها">
    <table class="admin-table admin-order-table">
        <thead>
            <tr><th scope="col">سفارش</th><th scope="col">خریدار{{ $showDetails ? ' و تحویل' : '' }}</th>
            @if($showDetails)<th scope="col">ریز سفارش</th>@endif
            <th scope="col">مبلغ (تومان)</th><th scope="col">وضعیت</th>
            @if($showDetails)<th scope="col">اقدام</th>@endif</tr>
        </thead>
        <tbody>
        @forelse($orders as $order)
            @php
                $customer = $order->customer;
                $actions = $showDetails ? array_filter($transitions[$order->status] ?? [], fn ($status) => match ($status) {
                    'paid' => $can['finance'] && $order->gateway === 'manual',
                    'cancelled' => $can['finance'] && !$order->payments->contains('status', 'verify_pending'),
                    'fulfilled' => $can['fulfill'],
                }) : [];
                $tone = match ($order->status) {
                    'pending', 'review' => 'badge-warning',
                    'paid', 'fulfilled' => 'badge-success',
                    default => 'badge-neutral',
                };
            @endphp
            <tr>
                <th scope="row">
                    <bdi class="admin-code">{{ $order->reference }}</bdi>
                    <small class="admin-block">{{ \App\Support\PersianFormat::dateTime($order->created_at?->toIso8601String()) }}</small>
                </th>
                <td>
                    <strong>{{ $customer['name'] ?? '—' }}</strong>
                    @if($showDetails)
                        <bdi class="admin-block">{{ $customer['mobile'] ?? '' }}</bdi>
                        <small class="admin-block">{{ ($customer['delivery'] ?? '') === 'pickup' ? 'دریافت حضوری' : ($order->shipping_address ?? $customer['address'] ?? 'ارسال') }}</small>
                    @endif
                </td>
                @if($showDetails)
                    <td class="admin-order-lines">
                        <details>
                            <summary>اقلام و ریز قیمت</summary>
                            @foreach($order->lines as $line)
                                <strong>{{ $line['name'] }}</strong>
                                <dl class="admin-facts">
                                    @foreach(['gold' => 'ارزش طلا', 'labor' => 'اجرت', 'profit' => 'سود', 'discount' => 'تخفیف', 'tax' => 'مالیات', 'cost' => 'بهای تمام‌شده'] as $key => $label)
                                        @if($key !== 'cost' || $can['finance'])
                                            <div><dt>{{ $label }}</dt><dd><bdi>{{ $key === 'cost' && empty($line[$key]) ? '—' : number_format($line[$key] ?? 0) }}</bdi></dd></div>
                                        @endif
                                    @endforeach
                                </dl>
                            @endforeach
                        </details>
                        @if($can['finance'] && $events->has($order->id))
                            <details>
                                <summary>سابقهٔ سفارش</summary>
                                <ul>
                                    @foreach($events->get($order->id) as $event)
                                        <li>{{ ['created' => 'ثبت سفارش', 'manual_payment' => 'ثبت پرداخت دستی', 'payment_confirmed' => 'تأیید بانکی', 'payment_after_closure' => 'تأیید بانکی پس از بسته‌شدن سفارش'][$event->event] ?? $statuses[$event->event] ?? $event->event }}
                                            @if($event->actor) · {{ $event->actor }} @endif
                                            <small class="admin-block">{{ \App\Support\PersianFormat::dateTime($event->created_at) }}</small>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </td>
                @endif
                <td class="admin-numeric"><bdi>{{ number_format($order->total) }}</bdi></td>
                <td>
                    <span class="admin-badge {{ $tone }}">{{ $statuses[$order->status] ?? $order->status }}</span>
                    @if($showDetails)
                        <small class="admin-block">{{ $gatewayDefinitions[$order->gateway]['name'] ?? 'هماهنگی فروشگاه' }}</small>
                        @if($order->manual_reference)<bdi class="admin-block">{{ $order->manual_reference }}</bdi>@endif
                    @endif
                </td>
                @if($showDetails)
                    <td>
                        @if($actions)
                            <form class="admin-status-form" method="post" action="{{ route('admin.orders.status', $order->id, false) }}">
                                @csrf
                                <select name="status" aria-label="وضعیت جدید سفارش" required>
                                    <option value="">انتخاب اقدام</option>
                                    @foreach($actions as $status)
                                        <option value="{{ $status }}">{{ ['paid' => 'ثبت پرداخت دستی', 'fulfilled' => 'ثبت تحویل', 'cancelled' => 'لغو سفارش'][$status] }}</option>
                                    @endforeach
                                </select>
                                @if(in_array('paid', $actions, true))
                                    <input name="manual_reference" aria-label="شناسه پرداخت دستی" placeholder="شناسه پرداخت دستی" maxlength="120">
                                @endif
                                <button class="admin-button admin-button-small" type="submit">ثبت</button>
                            </form>
                        @else
                            <span class="admin-muted">—</span>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr><td class="admin-empty" colspan="{{ $showDetails ? 6 : 4 }}">سفارشی پیدا نشد.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
