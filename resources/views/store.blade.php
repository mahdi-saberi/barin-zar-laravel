@php
    $data = $product?->data ?? $page;
    $catalogTitle = $category ? $categories[$category].' طلا' : ($page['name'] ?? 'ویترین طلا');
    $heading = match ($mode) {
        'catalog' => $catalogTitle,
        'cart' => 'سبد خرید',
        default => $data['name'] ?? 'گالری طلا',
    };
    $pageNumber = $pagination?->currentPage() ?? 1;
    $title = ($seo['title'] ?? $heading.' | برین‌زر').($pageNumber > 1 ? ' · صفحه '.number_format($pageNumber) : '');
    $catalogDescription = $category ? 'مدل‌های '.$categories[$category].' طلای ۱۸ عیار در برین‌زر.' : ($page['description'] ?? '');
    $description = $seo['description'] ?? ($category ? $catalogDescription : ($data['description'] ?? 'خرید طلای ۱۸ عیار از برین‌زر در بازار تهران.'));
    $canonical = $seo['canonical'] ?? (url()->current().($pageNumber > 1 ? '?page='.$pageNumber : ''));
    $settings = \App\Models\Entry::getData('settings', 'store');
    $active = $campaigns->filter(fn($c) => in_array($c->data['placement'], [$mode, 'both'], true) && ($mode !== 'product' || empty($c->data['product']) || $c->data['product'] === $slug));
    $quote = $product ? $quotes[$slug] : null;
    $ogImage = $data['images'][0] ?? $data['hero'] ?? '/assets/campaign.webp';
    $schema = $product ? ['@context'=>'https://schema.org','@type'=>'Product','name'=>$data['name'],'sku'=>$slug,'description'=>$description,'image'=>array_map(fn($path) => url($path), array_filter($data['images']))] : null;
    if ($schema && ($rate['source'] ?? '') !== 'sample' && $quote['total'] > 0) {
        $schema['offers'] = ['@type'=>'Offer','priceCurrency'=>'IRR','price'=>$quote['total'] * 10,'url'=>url()->current(),'availability'=>'https://schema.org/'.($data['stock'] ? 'InStock' : 'OutOfStock')];
    }
@endphp
@extends('layout', ['title'=>$title,'description'=>$description,'canonical'=>$canonical,'noindex'=>$mode==='cart' || !empty($seo['noindex']),'theme'=>$active->first()?->data['theme'] ?? null,'brand'=>$settings['brand'] ?? null,'footer'=>$settings['footer'] ?? null])
@section('content')
@if($mode === 'home')
<section class="hero campaign">
@if(!empty($page['hero']))<img src="{{ $page['hero'] }}" alt="" fetchpriority="high" width="1440" height="960">@endif
<div class="copy"><div class="kicker">{{ $page['badge'] ?? '' }}</div><h1>{{ $page['name'] ?? 'برین‌زر' }}</h1><p>{{ $page['description'] ?? '' }}</p><a class="btn" href="/shop">{{ $page['button'] ?? 'مشاهده ویترین طلا' }}</a></div>
</section>
@foreach(array_unique(array_map('trim', explode(',', $page['sections'] ?? 'categories,products,campaigns,budget,guide'))) as $section)
    @switch($section)
    @case('categories')
    <nav class="rail category-photos" aria-label="دسته‌های طلا">
    @foreach($categories as $key => $label)
       @php($image = $collections->get($key)?->first()?->data['images'][0] ?? null)
       <a href="{{ route('shop.category', $key) }}">@if($image)<img src="{{ $image }}" alt="" width="90" height="90" loading="lazy">@endif<span>{{ $label }}</span></a>
    @endforeach
    </nav>
    @break
    @case('products')
    <section class="section home-collections" aria-labelledby="collection-heading">
        <div class="split"><h2 id="collection-heading">{{ $page['products_heading'] ?? 'مجموعه‌های طلا' }}</h2><a href="{{ route('shop.index') }}" class="link">همه قطعات</a></div>
        @forelse($collections->filter(fn ($items) => $items->isNotEmpty()) as $key => $items)
            <section class="collection-preview" aria-labelledby="collection-{{ $key }}">
                <div class="collection-heading">
                    <h3 id="collection-{{ $key }}">{{ $categories[$key] }} طلا</h3>
                    <a class="link" href="{{ route('shop.category', $key) }}">مشاهده همه {{ $categories[$key] }}</a>
                </div>
                @include('products', ['products' => $items, 'preview' => true, 'headingId' => 'collection-'.$key])
            </section>
        @empty
            <p class="panel">هنوز محصولی در ویترین منتشر نشده است.</p>
        @endforelse
    </section>
    @break
    @case('campaigns')
    @include('campaigns')
    @break
    @case('budget')
    <section class="section"><div class="split"><h2>{{ $page['budget_heading'] ?? 'هدیه با بودجه شما' }}</h2></div><div class="tiles">
    @foreach([$page['budget_low'] ?? 30000000, $page['budget_high'] ?? 50000000] as $budget)<a class="panel" href="/shop?max={{ $budget }}">تا {{ number_format($budget) }} تومان</a>@endforeach
    </div></section>
    @break
    @case('guide')
    <section class="section editorial"><div class="copy"><h2>{{ $page['guide_heading'] ?? 'پیش از خرید' }}</h2><p>{{ $page['guide_text'] ?? 'وزن، اندازه و ریز مبلغ هر قطعه را در صفحه محصول ببینید.' }}</p><a href="/page/guide" class="link">راهنمای خرید</a></div><img src="{{ $page['guide_image'] ?? '/assets/m2.webp' }}" alt=""  loading="lazy" width="640" height="540"></section>
    @break
    @endswitch
@endforeach
@elseif($mode === 'catalog')
<section class="section catalog">
    <nav class="crumb" aria-label="مسیر صفحه">
        <a href="{{ route('home') }}">خانه</a> <span aria-hidden="true">/</span>
        @if($category)
            <a href="{{ route('shop.index') }}">ویترین طلا</a> <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $categories[$category] }}</span>
        @else
            <span aria-current="page">ویترین طلا</span>
        @endif
    </nav>
    <h1 class="title">{{ $catalogTitle }}</h1>
    @if($catalogDescription)<p>{{ $catalogDescription }}</p>@endif
    <nav class="category-switcher" aria-label="دسته‌های طلا">
        <a href="{{ route('shop.index', $filters) }}" @if(!$category) aria-current="page" @endif>همه طلاها</a>
        @foreach($categories as $key => $label)
            <a href="{{ route('shop.category', ['category' => $key] + $filters) }}" @if($category === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form class="store-form panel filter catalog-filter" method="get" action="{{ url()->current() }}">
        <label>جست‌وجو<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="نام قطعه"></label>
        <label>سقف بودجه (تومان)<input type="number" name="max" min="1" value="{{ $filters['max'] ?? '' }}"></label>
        <label>مرتب‌سازی<select name="sort"><option value="">پیش‌فرض</option><option value="asc" @selected(($filters['sort'] ?? '') === 'asc')>کمترین قیمت</option><option value="desc" @selected(($filters['sort'] ?? '') === 'desc')>بیشترین قیمت</option></select></label>
        <button class="btn">اعمال فیلتر</button>
    </form>
    <div class="catalog-results">
        <p>{{ number_format($pagination->total()) }} محصول</p>
        @if($filters)<a class="link" href="{{ url()->current() }}">پاک کردن فیلترها</a>@endif
    </div>
    @include('products')
    {{ $pagination->onEachSide(1)->links('partials.pagination') }}
</section>
@elseif($mode === 'product')
<section class="section twocol">
<div class="gallery">
<img id="product-image" src="{{ $ogImage }}" alt="{{ $data['image_alts'][0] ?? $data['name'] }}" width="700" height="620" fetchpriority="high">
<div class="thumbnails">@foreach(array_filter($data['images']) as $i=>$image)
<button class="secondary" type="button" data-gallery="{{ $image }}" data-alt="{{ $data['image_alts'][$i] ?? $data['name'] }}" aria-pressed="{{ $i===0 ? 'true' : 'false' }}"><img src="{{ $image }}" alt="{{ $data['image_alts'][$i] ?? 'تصویر '.($i+1) }}" width="72" height="72"></button>
@endforeach</div></div>
<div><div class="kicker">{{ $categories[$data['category']] ?? '' }} · طلای ۱۸ عیار</div><h1 class="title">{{ $data['name'] }}</h1><p>{{ $data['description'] ?? '' }}</p><p>وزن {{ number_format($data['milligrams']/1000, 3) }} گرم</p>
<dl class="breakdown">@foreach(['gold'=>'ارزش طلا','labor'=>'اجرت','profit'=>'سود فروش','discount'=>'تخفیف','tax'=>'مالیات','total'=>'مبلغ نهایی'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ number_format($quote[$key]) }} تومان</dd></div>@endforeach</dl>
@if($data['stock']>0 && !empty($rate['value']))<form method="post" action="/cart/{{ $slug }}">@csrf<button class="btn">افزودن به سبد خرید</button></form>@else<p>فعلاً قابل سفارش نیست.</p>@endif
</div></section>
@include('campaigns')
<section class="section"><div class="split"><h2>قطعات دیگر گالری</h2></div>@include('products',['products'=>$products->except($slug)->take(3)])</section>
@elseif($mode === 'cart')
<section class="section"><h1 class="title">سبد خرید</h1>
@if($cart->isEmpty())<p>سبد خرید خالی است.</p><a class="btn" href="/shop">مشاهده ویترین</a>
@else
<div class="twocol"><div>
@foreach($cart as $id)
<div class="panel split"><a href="/product/{{ $id }}">{{ $products[$id]->data['name'] }}</a><strong>{{ number_format($quotes[$id]['total']) }} تومان</strong>
<form method="post" action="/cart/{{ $id }}">@csrf <button class="link" name="remove" value="1">حذف</button></form></div>
@endforeach
<p class="price">جمع {{ number_format($quotes->only($cart->all())->sum('total')) }} تومان</p>
</div><form class="store-form panel" method="post" action="/order">@csrf
<input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
<label>نام و نام خانوادگی<input name="name" value="{{ old('name') }}" autocomplete="name" required></label>
<label>شماره تماس<input name="mobile" value="{{ old('mobile') }}" type="tel" autocomplete="tel" required></label>
<label>روش تحویل<select name="delivery"><option value="pickup">دریافت حضوری</option><option value="shipping" @selected(old('delivery')==='shipping')>هماهنگی ارسال</option></select></label>
<label>نشانی برای ارسال<textarea name="address" autocomplete="street-address">{{ old('address') }}</textarea></label>
<button class="btn">ثبت درخواست خرید</button><small>تأیید پرداخت و تحویل پس از هماهنگی فروشگاه انجام می‌شود.</small></form></div>
@endif
</section>
@else
<section class="section panel"><h1 class="title">{{ $page['name'] }}</h1>@if(!empty($page['hero']))<img src="{{ $page['hero'] }}" alt="" loading="lazy">@endif<p class="prose">{{ $page['description'] ?? '' }}</p></section>
@endif
@endsection
