@php
    $payment = $order->payments->sortByDesc('id')->first();
    $parts = $order->financials();
@endphp
<section class="wrap inner-section">
    <nav class="breadcrumb" aria-label="مسیر صفحه">
        <a href="{{ route('account.index') }}">حساب کاربری</a>
        <span aria-current="page">جزئیات سفارش</span>
    </nav>
    <div class="section-heading">
        <h1>{{ $heading }}</h1>
        <strong>{{ \App\Models\Order::STATUSES[$order->status] }}</strong>
    </div>
    <div class="cart-layout">
        <div class="checkout-form order-receipt">
            <h2>اقلام سفارش</h2>
            <dl class="price-details">
                @foreach($order->lines as $line)
                    <div><dt>{{ $line['name'] }}</dt><dd><bdi>{{ number_format($line['total']) }}</bdi> تومان</dd></div>
                @endforeach
            </dl>
            <h2>ریز مبلغ</h2>
            <dl class="price-details">
                @foreach(['gold' => 'ارزش طلا', 'labor' => 'اجرت', 'profit' => 'سود فروش', 'discount' => 'تخفیف', 'tax' => 'مالیات'] as $key => $label)
                    <div><dt>{{ $label }}</dt><dd><bdi>{{ number_format($parts[$key]) }}</bdi> تومان</dd></div>
                @endforeach
            </dl>
            <p class="cart-total"><span>مبلغ سفارش</span><strong><bdi>{{ number_format($order->total) }}</bdi> تومان</strong></p>
        </div>
        <aside class="checkout-form order-receipt">
            <h2>پرداخت و تحویل</h2>
            <dl class="receipt-details">
                <div><dt>کد سفارش</dt><dd><bdi>{{ $order->reference }}</bdi></dd></div>
                <div><dt>خریدار</dt><dd>{{ $order->customer['name'] }} · <bdi>{{ $order->customer['mobile'] }}</bdi></dd></div>
                <div><dt>تحویل</dt><dd>{{ $order->customer['delivery'] === 'shipping' ? 'هماهنگی ارسال' : 'دریافت حضوری' }}</dd></div>
                @if($order->shipping_address)<div><dt>نشانی</dt><dd>{{ $order->shipping_address }}</dd></div>@endif
                <div><dt>روش پرداخت</dt><dd>{{ $gatewayNames[$order->gateway]['name'] ?? 'هماهنگی با فروشگاه' }}</dd></div>
                @if($payment?->bank_reference || $order->manual_reference)
                    <div><dt>شناسهٔ پرداخت</dt><dd><bdi>{{ $payment?->bank_reference ?? $order->manual_reference }}</bdi></dd></div>
                @endif
            </dl>
            @if($payment?->status === 'verify_pending')
                <p>نتیجهٔ پرداخت در دست بررسی است.</p>
                <form method="post" action="{{ route('payment.check', $order->reference, false) }}">
                    @csrf
                    <button class="button" type="submit">استعلام پرداخت</button>
                </form>
            @elseif($order->status === 'pending')
                @if($order->gateway === 'manual')
                    <p>فروشگاه برای هماهنگی پرداخت با شما تماس می‌گیرد.</p>
                @elseif($order->expires_at?->isFuture())
                    <small>مهلت پرداخت {{ \App\Support\PersianFormat::dateTime($order->expires_at->toIso8601String()) }}</small>
                    <form method="post" action="{{ route('payment.start', $order->reference, false) }}">
                        @csrf
                        <button class="button" type="submit">{{ $payment?->status === 'redirected' ? 'ادامهٔ پرداخت' : 'پرداخت سفارش' }}</button>
                    </form>
                @else
                    <p>مهلت پرداخت این سفارش پایان یافته است.</p>
                @endif
            @elseif($order->status === 'review')
                <p>پرداخت در بانک ثبت شده و فروشگاه نتیجهٔ تطبیق سفارش را پیگیری می‌کند.</p>
            @endif
        </aside>
    </div>
</section>
