@php($catalogPath = $category ? route('shop.category', $category, false) : route('shop.index', [], false))
<section class="wrap inner-section catalog-section">
    <nav class="breadcrumb" aria-label="مسیر صفحه">
        <a href="{{ route('home') }}">خانه</a>
        @if($category)
            <a href="{{ route('shop.index') }}">ویترین طلا</a>
            <span aria-current="page">{{ $categories[$category] }}</span>
        @else
            <span aria-current="page">ویترین طلا</span>
        @endif
    </nav>
    <div class="catalog-intro">
        <h1>{{ $heading }}</h1>
        @if(!$category && $catalogDescription)<p>{{ $catalogDescription }}</p>@endif
    </div>
    <div class="catalog-toolbar">
        <p class="catalog-count"><bdi>{{ number_format($pagination->total()) }}</bdi> محصول</p>
        <details class="catalog-controls" @if($filters) open @endif>
            <summary>فیلتر و مرتب‌سازی <span class="filter-chevron" aria-hidden="true"></span></summary>
            <form class="store-form catalog-filter" method="get" action="{{ $catalogPath }}">
                <label>نام محصول<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="جست‌وجو در این فهرست"></label>
                <label>سقف بودجه (تومان)<input type="number" name="max" min="1" value="{{ $filters['max'] ?? '' }}"></label>
                <label>وزن<select name="weight">
                    <option value="">همه وزن‌ها</option>
                    @foreach($weightRanges as $key => $range)
                        <option value="{{ $key }}" @selected(($filters['weight'] ?? '') === $key)>{{ $range['label'] }}</option>
                    @endforeach
                </select></label>
                <label>مرتب‌سازی<select name="sort">
                    <option value="">پیش‌فرض</option>
                    <option value="asc" @selected(($filters['sort'] ?? '') === 'asc')>کمترین قیمت</option>
                    <option value="desc" @selected(($filters['sort'] ?? '') === 'desc')>بیشترین قیمت</option>
                </select></label>
                <label class="catalog-stock"><input type="checkbox" name="in_stock" value="1" @checked(isset($filters['in_stock']))> فقط محصولات موجود</label>
                <div class="filter-actions">
                    @if($filters)<a class="text-link" href="{{ $catalogPath }}">پاک کردن فیلترها</a>@endif
                    <button class="button" type="submit">اعمال فیلتر</button>
                </div>
            </form>
        </details>
    </div>
    @include('products')
    {{ $pagination->onEachSide(1)->links('partials.pagination') }}
</section>
