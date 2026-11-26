<section class="admin-card admin-filter-card" aria-label="فیلتر سفارش‌ها">
    <form class="admin-filters admin-order-filters" method="get" action="{{ route('admin.index', [], false) }}">
        <input type="hidden" name="tab" value="finance">
        <label>کد سفارش، شماره پرداخت یا خریدار<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120"></label>
        <label>وضعیت<select name="status">
            <option value="">همه وضعیت‌ها</option>
            @foreach($statuses as $key => $label)
                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select></label>
        <label>درگاه<select name="gateway">
            <option value="">همه روش‌ها</option>
            <option value="manual" @selected(($filters['gateway'] ?? '') === 'manual')>هماهنگی فروشگاه</option>
            @foreach($gatewayDefinitions as $key => $definition)
                <option value="{{ $key }}" @selected(($filters['gateway'] ?? '') === $key)>{{ $definition['name'] }}</option>
            @endforeach
        </select></label>
        <label>از تاریخ ثبت سفارش<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>تا تاریخ ثبت سفارش<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <div class="admin-actions">
            <button class="admin-button admin-primary" type="submit">اعمال فیلتر</button>
            @if($filters)<a class="admin-text-link" href="{{ route('admin.index', ['tab' => 'finance']) }}">پاک کردن</a>@endif
        </div>
    </form>
</section>
@if($can['finance'])
    <div class="admin-metrics">
        @foreach(['total' => 'فروش پرداخت‌شده', 'pending' => 'مبلغ در انتظار پرداخت', 'margin' => 'حاشیهٔ ناخالص پیش از کارمزد', 'tax' => 'مالیات فروش پرداخت‌شده'] as $key => $label)
            <div class="admin-metric">
                <span>{{ $label }}</span>
                <strong><bdi>{{ $key === 'margin' && !$metrics['cost_count'] ? '—' : number_format($metrics[$key]) }}</bdi> <small>تومان</small></strong>
                @if($key === 'total')<small>{{ $metrics['count'] }} سفارش پرداخت‌شده</small>@endif
                @if($key === 'margin')<small>بهای ثبت‌شده برای {{ $metrics['cost_count'] }} از {{ $metrics['count'] }} سفارش</small>@endif
            </div>
        @endforeach
    </div>
    <section class="admin-card">
        <h2>تطبیق مالی</h2>
        <div class="admin-studio-grid">
            <dl class="admin-facts">
                @foreach(['gold' => 'ارزش طلا در فروش', 'labor' => 'اجرت فروش', 'discount' => 'تخفیف فروش', 'cost' => 'بهای ثبت‌شدهٔ سفارش‌ها'] as $key => $label)
                    <div><dt>{{ $label }}</dt><dd><bdi>{{ number_format($metrics[$key]) }}</bdi> تومان</dd></div>
                @endforeach
            </dl>
            <dl class="admin-facts">
                @foreach(['captured_rial' => 'دریافت تأییدشدهٔ بانک', 'fee_rial' => 'کارمزد اعلام‌شدهٔ پذیرنده', 'net_rial' => 'دریافت پس از کارمزد اعلام‌شده', 'review_rial' => 'وجه تأییدشده نیازمند تطبیق سفارش'] as $key => $label)
                    <div><dt>{{ $label }}</dt><dd><bdi>{{ number_format($metrics[$key]) }}</bdi> ریال</dd></div>
                @endforeach
            </dl>
        </div>
    </section>
@endif
<section class="admin-card" aria-labelledby="orders-heading">
    <div class="admin-card-heading">
        <h2 id="orders-heading">فهرست سفارش‌ها</h2>
        <p class="admin-muted">{{ number_format($orders->total()) }} سفارش</p>
    </div>
    @include('admin.order-table', ['showDetails' => true])
    {{ $orders->onEachSide(1)->links('partials.pagination') }}
</section>
@if($payments)
    @include('admin.transactions')
@endif
