<section class="wrap inner-section">
    <div class="section-heading">
        <h1>{{ $heading }}</h1>
        <form method="post" action="{{ route('account.logout', [], false) }}">
            @csrf
            <button class="text-link" type="submit">خروج از حساب</button>
        </form>
    </div>
    <div class="checkout-form account-summary">
        <strong>{{ auth()->user()->name }}</strong>
        @if(auth()->user()->mobile)<bdi>{{ auth()->user()->mobile }}</bdi>@endif
        @if(!auth()->user()->checkoutProfileComplete())
            <a class="text-link" href="{{ route('account.register') }}">تکمیل اطلاعات خریدار</a>
        @endif
        @if(\App\Support\StaffAccess::allows(auth()->user(), 'dashboard'))
            <a class="text-link" href="{{ route('admin.index') }}">مدیریت فروشگاه</a>
        @endif
    </div>
    <h2>سفارش‌های من</h2>
    <div class="account-orders">
        @forelse($orders as $order)
            <a href="{{ route('order.show', $order->reference) }}">
                <span>{{ \App\Models\Order::STATUSES[$order->status] }}</span>
                <bdi>{{ number_format($order->total) }} تومان</bdi>
                <small>{{ \App\Support\PersianFormat::dateTime($order->created_at?->toIso8601String()) }}</small>
            </a>
        @empty
            <p class="empty-state">سفارشی ثبت نشده است.</p>
        @endforelse
    </div>
    {{ $orders->onEachSide(1)->links('partials.pagination') }}
</section>
