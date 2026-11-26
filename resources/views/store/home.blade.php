@php
    $sections = array_unique(array_map('trim', explode(',', $page['sections'] ?? 'categories,products,campaigns,budget,guide')));
    $previews = $collections->filter(fn ($items) => $items->isNotEmpty());
@endphp
<section class="store-hero">
    <div @class(['hero-inner', 'hero-text-only' => !$homeHero])>
        <div class="hero-copy">
            <p class="hero-eyebrow"><span class="foil-mark" aria-hidden="true"></span>{{ $page['badge'] ?? 'گالری طلای ۱۸ عیار · بازار تهران' }}</p>
            <h1>{{ $heading }}</h1>
            <div class="hero-description rich-content">{{ \App\Support\RichText::render($page, 'description', 'قطعهٔ دلخواهتان را با دیدن جزئیات، عکس روی مدل و ریز قیمت انتخاب کنید.') }}</div>
            <div class="hero-actions">
                <a class="button button-gold" href="{{ route('shop.index') }}">{{ $page['button'] ?? 'مشاهده ویترین طلا' }} <span aria-hidden="true">←</span></a>
                @if(in_array('products', $sections, true) && $previews->isNotEmpty())
                    <a class="hero-link" href="#collection-heading">دیدن استایل‌ها</a>
                @elseif(in_array('categories', $sections, true))
                    <a class="hero-link" href="#gold-categories">انتخاب دسته</a>
                @endif
            </div>
        </div>
        @if($homeHero)
            <div class="hero-media">
                <img src="{{ $homeHero }}" alt="" fetchpriority="high" width="1280" height="853">
            </div>
        @endif
    </div>
</section>

@foreach($sections as $section)
    @switch($section)
        @case('categories')
            <section id="gold-categories" class="wrap section" aria-labelledby="category-heading" data-category-slider>
                <div class="section-heading">
                    <h2 id="category-heading">دسته‌های طلا</h2>
                    <div class="category-actions">
                        <a class="text-link" href="{{ route('shop.index') }}">همهٔ محصولات <span aria-hidden="true">←</span></a>
                        <div class="slider-controls" data-slider-controls hidden>
                            <button type="button" aria-label="دسته‌های قبلی" aria-controls="category-slider" data-slider-previous>→</button>
                            <button type="button" aria-label="دسته‌های بعدی" aria-controls="category-slider" data-slider-next>←</button>
                        </div>
                    </div>
                </div>
                <div class="category-gallery" id="category-slider" role="region" aria-labelledby="category-heading" tabindex="0">
                    @foreach($categories as $key => $label)
                        @php
                            $cover = $previews->get($key)?->first()?->data['images'][0] ?? null;
                        @endphp
                        <a class="category-card" href="{{ route('shop.category', $key) }}">
                            <span class="category-media">
                                @if($cover)
                                    <img src="{{ $cover }}" width="390" height="360" alt="" loading="lazy" decoding="async">
                                @else
                                    <span class="foil-mark" aria-hidden="true"></span>
                                @endif
                            </span>
                            <span class="category-label">{{ $label }} <span aria-hidden="true">←</span></span>
                        </a>
                    @endforeach
                </div>
            </section>
            @break

        @case('products')
            <section class="wrap section home-collections" aria-labelledby="collection-heading">
                <div class="section-heading">
                    <h2 id="collection-heading">{{ $page['products_heading'] ?? 'استایل‌های برین‌زر' }}</h2>
                    @if($previews->count() > 1)
                        <div class="slider-controls" data-collection-controls hidden>
                            <button type="button" aria-label="استایل قبلی" aria-controls="collection-panels" data-collection-step="-1">→</button>
                            <button type="button" aria-label="استایل بعدی" aria-controls="collection-panels" data-collection-step="1">←</button>
                            <span class="screen-reader-only" role="status"></span>
                        </div>
                    @endif
                </div>
                @if($previews->isNotEmpty())
                    <div class="collection-tabs" data-collection-tabs aria-label="انتخاب دسته برای پیش‌نمایش">
                        @foreach($previews as $key => $items)
                            <a id="preview-tab-{{ $key }}" href="#collection-{{ $key }}" data-collection-tab>{{ $categories[$key] }}</a>
                        @endforeach
                    </div>
                    <div id="collection-panels">
                        @foreach($previews as $key => $items)
                            @php
                                $feature = $items->first();
                                $featurePhoto = ($feature->data['images'][1] ?? null) ?: $feature->data['images'][0];
                            @endphp
                            <section class="collection-preview" id="collection-{{ $key }}" data-collection-panel aria-labelledby="collection-title-{{ $key }}">
                                <a class="collection-photo" href="{{ route('product.show', $feature->slug) }}" aria-label="دیدن {{ $feature->data['name'] }}">
                                    <img src="{{ $featurePhoto }}" width="600" height="600" alt="" loading="lazy" decoding="async">
                                    <span class="collection-photo-link"><span class="foil-mark" aria-hidden="true"></span> دیدن این قطعه <span aria-hidden="true">←</span></span>
                                </a>
                                <div class="collection-shelf">
                                    <div class="collection-heading">
                                        <h3 id="collection-title-{{ $key }}">{{ $categories[$key] }} طلا</h3>
                                        <a class="text-link" href="{{ route('shop.category', $key) }}">مشاهده همه <span class="screen-reader-only">{{ $categories[$key] }}</span><span aria-hidden="true">←</span></a>
                                    </div>
                                    @include('products', ['products' => $items, 'preview' => true])
                                </div>
                            </section>
                        @endforeach
                    </div>
                @else
                    <p class="empty-state">هنوز محصولی در ویترین منتشر نشده است.</p>
                @endif
            </section>
            @break

        @case('campaigns')
            @include('campaigns')
            @break

        @case('budget')
            <section class="wrap section" aria-labelledby="budget-heading">
                <div class="budget-band">
                    <h2 id="budget-heading">{{ $page['budget_heading'] ?? 'هدیه با بودجهٔ شما' }}</h2>
                    @foreach([$page['budget_low'] ?? 30000000, $page['budget_high'] ?? 50000000] as $budget)
                        <a href="{{ route('shop.index', ['max' => $budget]) }}">تا <bdi>{{ number_format($budget) }}</bdi> تومان <span aria-hidden="true">←</span></a>
                    @endforeach
                </div>
            </section>
            @break

        @case('guide')
            <section class="wrap section">
                <div class="guide-feature">
                    <img src="{{ $page['guide_image'] ?? '/assets/m2.webp' }}" width="640" height="520" alt="" loading="lazy" decoding="async">
                    <div class="guide-copy">
                        <h2>{{ $page['guide_heading'] ?? 'پیش از خرید' }}</h2>
                        <div class="guide-description rich-content">{{ \App\Support\RichText::render($page, 'guide_text', 'وزن، اندازه و ریز مبلغ هر قطعه را در صفحهٔ محصول ببینید.') }}</div>
                        <a class="text-link" href="{{ route('page.show', 'guide') }}">راهنمای خرید <span aria-hidden="true">←</span></a>
                    </div>
                </div>
            </section>
            @break
    @endswitch
@endforeach
