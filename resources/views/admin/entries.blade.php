<section class="admin-card admin-filter-card" aria-label="فیلتر فهرست">
    <form class="admin-filters {{ $kind === 'product' ? 'admin-product-filters' : 'admin-content-filters' }}" method="get" action="{{ route('admin.index', [], false) }}">
        <input type="hidden" name="tab" value="{{ $kind }}">
        <label>{{ $kind === 'seo' ? 'عنوان یا شناسه' : 'نام یا شناسه' }}<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="140"></label>
        @if($kind === 'product')
            <label>نوع طلا<select name="category"><option value="">همه دسته‌ها</option>
                @foreach($categories as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select></label>
            <label>موجودی<select name="stock"><option value="">همه</option>
                @foreach(['available' => 'موجود', 'low' => 'رو به اتمام', 'out' => 'ناموجود'] as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['stock'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select></label>
            <label>انتشار<select name="visibility"><option value="">همه</option>
                <option value="published" @selected(($filters['visibility'] ?? '') === 'published')>منتشرشده</option>
                <option value="hidden" @selected(($filters['visibility'] ?? '') === 'hidden')>پنهان</option>
            </select></label>
        @endif
        <div class="admin-actions">
            <button class="admin-button admin-primary" type="submit">اعمال فیلتر</button>
            @if($filters)<a class="admin-text-link" href="{{ route('admin.index', ['tab' => $kind]) }}">پاک کردن</a>@endif
        </div>
    </form>
</section>

<section class="admin-card" aria-label="{{ $tabs[$kind] }}">
    <div class="admin-card-heading admin-list-summary">
        <p class="admin-muted">
            @if($items->count())
                نمایش {{ number_format($items->firstItem()) }} تا {{ number_format($items->lastItem()) }} از {{ number_format($items->total()) }}
            @else
                {{ number_format($items->total()) }} مورد
            @endif
        </p>
        @if($kind === 'product' && !empty($rate['value']))
            <small>نرخ مبنا <bdi>{{ number_format($rate['value']) }}</bdi> تومان / گرم @if(($rate['source'] ?? '') === 'sample') · نمونه @endif</small>
        @endif
    </div>
    @if($kind === 'product')
        @include('admin.product-table')
    @else
        @include('admin.content-table')
    @endif
    {{ $items->onEachSide(1)->links('partials.pagination') }}
</section>
