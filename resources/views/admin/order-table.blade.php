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
                $customer = json_decode($order->customer, true);
                $actions = $transitions[$order->status] ?? [];
                $tone = match ($order->status) {
                    'pending' => 'badge-warning',
                    'paid', 'fulfilled' => 'badge-success',
                    default => 'badge-neutral',
                };
            @endphp
            <tr>
                <th scope="row"><bdi class="admin-code">{{ $order->reference }}</bdi><small class="admin-block"><time dir="ltr">{{ $order->created_at }}</time></small></th>
                <td>
                    <strong>{{ $customer['name'] ?? '—' }}</strong>
                    @if($showDetails)
                        <bdi class="admin-block">{{ $customer['mobile'] ?? '' }}</bdi>
                        <small class="admin-block">{{ ($customer['delivery'] ?? '') === 'pickup' ? 'دریافت حضوری' : ($customer['address'] ?? 'ارسال') }}</small>
                    @endif
                </td>
                @if($showDetails)
                    <td class="admin-order-lines"><details>
                        <summary>اقلام و ریز قیمت</summary>
                        @foreach(json_decode($order->lines, true) as $line)
                            <strong>{{ $line['name'] }}</strong>
                            <dl class="admin-facts">
                                @foreach(['gold' => 'ارزش طلا', 'labor' => 'اجرت', 'profit' => 'سود', 'discount' => 'تخفیف', 'tax' => 'مالیات', 'cost' => 'بهای تمام‌شده'] as $key => $label)
                                    <div><dt>{{ $label }}</dt><dd><bdi>{{ number_format($line[$key]) }}</bdi></dd></div>
                                @endforeach
                            </dl>
                        @endforeach
                    </details></td>
                @endif
                <td class="admin-numeric"><bdi>{{ number_format($order->total) }}</bdi></td>
                <td><span class="admin-badge {{ $tone }}">{{ $statuses[$order->status] ?? $order->status }}</span></td>
                @if($showDetails)
                    <td>
                        @if($actions)
                            <form class="admin-status-form" method="post" action="{{ route('admin.orders.status', $order->id) }}">
                                @csrf
                                <select name="status" aria-label="وضعیت جدید سفارش" required>
                                    <option value="">انتخاب اقدام</option>
                                    @foreach($actions as $status)
                                        <option value="{{ $status }}">{{ ['paid' => 'تأیید پرداخت', 'fulfilled' => 'ثبت تحویل', 'cancelled' => 'لغو سفارش'][$status] }}</option>
                                    @endforeach
                                </select>
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
