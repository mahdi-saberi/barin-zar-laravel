<div class="admin-metrics">
    @if($can['content'])
        <a class="admin-metric" href="{{ route('admin.index', ['tab' => 'product', 'visibility' => 'published']) }}">
            <span>محصولات منتشرشده</span><strong>{{ number_format($metrics['products']) }}</strong>
        </a>
        <a class="admin-metric admin-metric-warning" href="{{ route('admin.index', ['tab' => 'product', 'stock' => 'low', 'visibility' => 'published']) }}">
            <span>محصولات رو به اتمام</span><strong>{{ number_format($metrics['lowStock']) }}</strong><small>موجودی ۱ تا ۳ قطعه</small>
        </a>
    @endif
    @if($can['orders'])
        <a class="admin-metric" href="{{ route('admin.index', ['tab' => 'finance', 'status' => 'pending']) }}">
            <span>سفارش‌های در انتظار پرداخت</span><strong>{{ number_format($metrics['pending']) }}</strong>
        </a>
    @endif
    @if($can['finance'])
        <a class="admin-metric" href="{{ route('admin.index', ['tab' => 'finance']) }}">
            <span>فروش پرداخت‌شده</span><strong><bdi>{{ number_format($metrics['sales']) }}</bdi> <small>تومان</small></strong>
        </a>
    @endif
</div>
<section class="admin-card admin-rate" aria-labelledby="dashboard-rate">
    <div>
        <h2 id="dashboard-rate">نرخ هر گرم طلای ۱۸ عیار</h2>
        @include('admin.rate-value')
        @if($can['settings']) @include('admin.rate-health') @endif
    </div>
    @if($can['settings'])<a class="admin-button" href="{{ route('admin.index', ['tab' => 'settings']) }}">مدیریت نرخ</a>@endif
</section>
@if($can['orders'])
    <section class="admin-card" aria-labelledby="recent-orders">
        <div class="admin-card-heading">
            <h2 id="recent-orders">آخرین سفارش‌ها</h2>
            <a class="admin-text-link" href="{{ route('admin.index', ['tab' => 'finance']) }}">همه سفارش‌ها</a>
        </div>
        @include('admin.order-table', ['showDetails' => false])
    </section>
@endif
