@php
    $productCategory = $data['category'] ?? null;
    $gallery = \App\Support\Media::productGallery($data);
    $inCart = $cart->contains($slug);
    $available = $data['stock'] > 0;
    $hasRate = !empty($rate['value']);
    $moreProducts = isset($categories[$productCategory]) ? route('shop.category', $productCategory) : route('shop.index');
@endphp
<section class="wrap product-page">
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
                <video id="product-video" aria-label="ویدئوی {{ $heading }}" controls preload="metadata" playsinline hidden></video>
            </div>
            @if(count($gallery) > 1)
                <div class="gallery-thumbnails" aria-label="گالری محصول">
                    @foreach($gallery as $item)
                        @php
                            $isVideo = \App\Support\Media::type($item['path']) === 'video';
                            $alt = $item['alt'] ?: ($isVideo ? 'ویدئو ' : 'تصویر ').$loop->iteration;
                        @endphp
                        <button type="button" data-gallery="{{ $item['path'] }}" data-type="{{ $isVideo ? 'video' : 'image' }}"
                            data-alt="{{ $alt }}" aria-label="{{ $alt }}" aria-pressed="{{ $item['path'] === $ogImage ? 'true' : 'false' }}">
                            @if($isVideo)
                                <span class="gallery-video-thumb" aria-hidden="true">▶</span>
                            @else
                                <img src="{{ $item['path'] }}" alt="" width="72" height="72" loading="lazy">
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="purchase-panel" data-quote-slug="{{ $slug }}">
            <p class="detail-type">طلای ۱۸ عیار <span aria-hidden="true">·</span> <bdi>{{ number_format($data['milligrams'] / 1000, 3) }}</bdi> گرم</p>
            <h1>{{ $heading }}</h1>
            <div class="product-buy-card">
                <div class="product-buy-top">
                    <div class="detail-price" data-quote-price @if(!$hasRate) hidden @endif>
                        <strong><bdi data-quote-field="total">{{ number_format($quote['total']) }}</bdi></strong> تومان
                    </div>
                    <p class="availability" data-quote-missing @if($hasRate) hidden @endif>در انتظار دریافت نرخ طلا</p>
                    <span @class(['product-stock', 'product-in-stock' => $available])>{{ $available ? 'موجود' : 'ناموجود' }}</span>
                </div>
                @if($inCart)
                    <a class="button purchase-button product-inline-action" href="{{ route('cart.show') }}">رفتن به سبد خرید <span aria-hidden="true">←</span></a>
                @elseif($available)
                    <form id="buy-product" method="post" action="{{ route('cart.change', $slug, false) }}" data-quote-price @if(!$hasRate) hidden @endif>
                        @csrf
                        <button class="button purchase-button product-inline-action" type="submit">افزودن به سبد خرید <span aria-hidden="true">←</span></button>
                    </form>
                @else
                    <a class="button purchase-button" href="{{ $moreProducts }}">دیدن مدل‌های دیگر <span aria-hidden="true">←</span></a>
                @endif
                @if($available)
                    <div class="product-pickup">
                        <a href="#store-contact">امکان دریافت حضوری از گالری</a>
                        <a href="tel:{{ $contact['dial'] }}">تماس برای راهنمایی</a>
                    </div>
                @endif
            </div>
            <details class="price-details" data-quote-price @if(!$hasRate) hidden @endif>
                <summary>ریز قیمت و اجرت</summary>
                <dl>
                    @foreach(['gold' => 'ارزش طلا', 'labor' => 'اجرت', 'profit' => 'سود فروش', 'discount' => 'تخفیف', 'tax' => 'مالیات'] as $key => $label)
                        <div><dt>{{ $label }}</dt><dd><bdi data-quote-field="{{ $key }}">{{ number_format($quote[$key]) }}</bdi> تومان</dd></div>
                    @endforeach
                </dl>
            </details>
            @if(!empty($data['description']))
                <div class="product-about">
                    <h2>دربارهٔ این قطعه</h2>
                    <div class="detail-description rich-content">{{ \App\Support\RichText::render($data, 'description') }}</div>
                </div>
            @endif
        </div>
    </div>
</section>
@if($inCart || $available)
    <div class="purchase-dock" data-quote-slug="{{ $slug }}">
        <div class="purchase-dock-inner" @if(!$inCart) data-quote-price @if(!$hasRate) hidden @endif @endif>
            <span class="purchase-dock-price" data-quote-price @if(!$hasRate) hidden @endif>
                <strong><bdi data-quote-field="total">{{ number_format($quote['total']) }}</bdi></strong> تومان
            </span>
            @if($inCart)
                <span class="purchase-dock-price" data-quote-missing @if($hasRate) hidden @endif>در سبد شما</span>
                <a class="button" href="{{ route('cart.show') }}">رفتن به سبد خرید</a>
            @else
                <button class="button" form="buy-product" type="submit">افزودن به سبد خرید</button>
            @endif
        </div>
    </div>
@endif
@include('campaigns')
@if($products->isNotEmpty())
    <section class="wrap section product-recommendations" aria-labelledby="recommendations-heading">
        <div class="section-heading">
            <h2 id="recommendations-heading">مدل‌های پیشنهادی</h2>
            <a class="text-link" href="{{ $moreProducts }}">مدل‌های بیشتر <span aria-hidden="true">←</span></a>
        </div>
        @include('products')
    </section>
@endif
