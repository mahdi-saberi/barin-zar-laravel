@php($productCategory = $data['category'] ?? null)
<section class="wrap inner-section">
    <nav class="breadcrumb" aria-label="مسیر صفحه">
        <a href="{{ route('home') }}">خانه</a>
        <a href="{{ route('shop.index') }}">ویترین طلا</a>
        @if(isset($categories[$productCategory]))
            <a href="{{ route('shop.category', $productCategory) }}">{{ $categories[$productCategory] }}</a>
        @endif
        <span aria-current="page">{{ $heading }}</span>
    </nav>
    <div class="product-detail">
        <div class="product-gallery">
            <div class="gallery-main">
                <img id="product-image" src="{{ $ogImage }}" alt="{{ ($data['image_alts'][0] ?? '') ?: $heading }}" width="700" height="620" fetchpriority="high">
            </div>
            @if(count(array_filter($data['images'])) > 1)
                <div class="gallery-thumbnails" aria-label="تصاویر محصول">
                    @foreach(array_filter($data['images']) as $index => $image)
                        @php($imageAlt = ($data['image_alts'][$index] ?? '') ?: 'تصویر '.($index + 1))
                        <button type="button" data-gallery="{{ $image }}" data-alt="{{ $imageAlt }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                            <img src="{{ $image }}" alt="{{ $imageAlt }}" width="72" height="72" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="purchase-panel">
            <p class="detail-type">طلای ۱۸ عیار <span aria-hidden="true">·</span> <bdi>{{ number_format($data['milligrams'] / 1000, 3) }}</bdi> گرم</p>
            <h1>{{ $heading }}</h1>
            @if(!empty($data['description']))<p class="detail-description">{{ $data['description'] }}</p>@endif
            @if(!empty($rate['value']))
                <div class="detail-price"><strong><bdi>{{ number_format($quote['total']) }}</bdi></strong> تومان</div>
            @endif
            @if($data['stock'] > 0 && !empty($rate['value']))
                <form method="post" action="{{ url('/cart/'.$slug) }}">
                    @csrf
                    <button class="button purchase-button" type="submit">افزودن به سبد خرید</button>
                </form>
            @else
                <p class="availability">{{ $data['stock'] <= 0 ? 'در حال حاضر ناموجود است.' : 'قیمت در انتظار دریافت نرخ طلاست.' }}</p>
            @endif
            @if(!empty($rate['value']))
                <details class="price-details" open>
                    <summary>ریز قیمت</summary>
                    <dl>
                        @foreach(['gold' => 'ارزش طلا', 'labor' => 'اجرت', 'profit' => 'سود فروش', 'discount' => 'تخفیف', 'tax' => 'مالیات'] as $key => $label)
                            <div><dt>{{ $label }}</dt><dd><bdi>{{ number_format($quote[$key]) }}</bdi> تومان</dd></div>
                        @endforeach
                    </dl>
                </details>
            @endif
        </div>
    </div>
</section>
@include('campaigns')
@if($products->isNotEmpty())
    <section class="wrap section">
        <div class="section-heading">
            <h2>قطعات دیگر گالری</h2>
            <a class="text-link" href="{{ route('shop.index') }}">همهٔ محصولات <span aria-hidden="true">←</span></a>
        </div>
        @include('products')
    </section>
@endif
