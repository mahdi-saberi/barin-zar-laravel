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
        <div class="purchase-panel" data-quote-slug="{{ $slug }}">
            <p class="detail-type">طلای ۱۸ عیار <span aria-hidden="true">·</span> <bdi>{{ number_format($data['milligrams'] / 1000, 3) }}</bdi> گرم</p>
            <h1>{{ $heading }}</h1>
            @if(!empty($data['description']))
                <div class="detail-description rich-content">{{ \App\Support\RichText::render($data, 'description') }}</div>
            @endif
            <div class="detail-price" data-quote-price @if(empty($rate['value'])) hidden @endif><strong><bdi data-quote-field="total">{{ number_format($quote['total']) }}</bdi></strong> تومان</div>
            @if($data['stock'] > 0)
                <form method="post" action="{{ route('cart.change', $slug, false) }}" data-quote-price @if(empty($rate['value'])) hidden @endif>
                    @csrf
                    <button class="button purchase-button" type="submit">افزودن به سبد خرید</button>
                </form>
                <p class="availability" data-quote-missing @if(!empty($rate['value'])) hidden @endif>قیمت در انتظار دریافت نرخ طلاست.</p>
            @else
                <p class="availability">در حال حاضر ناموجود است.</p>
            @endif
                <details class="price-details" open data-quote-price @if(empty($rate['value'])) hidden @endif>
                    <summary>ریز قیمت</summary>
                    <dl>
                        @foreach(['gold' => 'ارزش طلا', 'labor' => 'اجرت', 'profit' => 'سود فروش', 'discount' => 'تخفیف', 'tax' => 'مالیات'] as $key => $label)
                            <div><dt>{{ $label }}</dt><dd><bdi data-quote-field="{{ $key }}">{{ number_format($quote[$key]) }}</bdi> تومان</dd></div>
                        @endforeach
                    </dl>
                </details>
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
