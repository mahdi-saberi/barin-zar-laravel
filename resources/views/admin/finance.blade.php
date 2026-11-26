<section class="admin-card admin-filter-card" aria-label="فیلتر سفارش‌ها">
    <form class="admin-filters admin-order-filters" method="get" action="{{ route('admin.index', [], false) }}">
        <input type="hidden" name="tab" value="finance">
        <label>کد، نام یا شماره تماس<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120"></label>
        <label>وضعیت<select name="status">
            <option value="">همه وضعیت‌ها</option>
            @foreach($statuses as $key => $label)
                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select></label>
        <label>از تاریخ<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>تا تاریخ<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <div class="admin-actions">
            <button class="admin-button admin-primary" type="submit">اعمال فیلتر</button>
            @if($filters)<a class="admin-text-link" href="{{ route('admin.index', ['tab' => 'finance']) }}">پاک کردن</a>@endif
        </div>
    </form>
</section>
<div class="admin-metrics">
    @foreach(['total' => 'مبلغ پرداخت‌شده', 'count' => 'سفارش‌های پرداخت‌شده', 'margin' => 'حاشیه ناخالص', 'discount' => 'تخفیف سفارش‌های پرداخت‌شده'] as $key => $label)
        <div class="admin-metric">
            <span>{{ $label }}</span>
            <strong><bdi>{{ number_format($metrics[$key]) }}</bdi> <small>{{ $key === 'count' ? 'سفارش' : 'تومان' }}</small></strong>
        </div>
    @endforeach
</div>
<section class="admin-card" aria-labelledby="orders-heading">
    <div class="admin-card-heading">
        <h2 id="orders-heading">فهرست سفارش‌ها</h2>
        <p class="admin-muted">{{ number_format($orders->total()) }} سفارش</p>
    </div>
    @include('admin.order-table', ['showDetails' => true])
    {{ $orders->onEachSide(1)->links('partials.pagination') }}
</section>
