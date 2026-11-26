@php($catalogPath = $category ? route('shop.category', $category, false) : route('shop.index', [], false))
<section class="wrap inner-section">
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
        @if($catalogDescription)<p>{{ $catalogDescription }}</p>@endif
    </div>
    <form class="store-form catalog-filter" method="get" action="{{ $catalogPath }}">
        <label>نام محصول<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="جست‌وجو در این فهرست"></label>
        <label>سقف بودجه (تومان)<input type="number" name="max" min="1" value="{{ $filters['max'] ?? '' }}"></label>
        <label>مرتب‌سازی<select name="sort">
            <option value="">پیش‌فرض</option>
            <option value="asc" @selected(($filters['sort'] ?? '') === 'asc')>کمترین قیمت</option>
            <option value="desc" @selected(($filters['sort'] ?? '') === 'desc')>بیشترین قیمت</option>
        </select></label>
        <button class="button" type="submit">اعمال فیلتر</button>
    </form>
    <div class="results-toolbar">
        <p><bdi>{{ number_format($pagination->total()) }}</bdi> محصول</p>
        @if($filters)<a class="text-link" href="{{ $catalogPath }}">پاک کردن فیلترها</a>@endif
    </div>
    @include('products')
    {{ $pagination->onEachSide(1)->links('partials.pagination') }}
</section>
