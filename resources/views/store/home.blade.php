@php($sections = array_unique(array_map('trim', explode(',', $page['sections'] ?? 'categories,products,campaigns,budget,guide'))))
<section class="store-hero">
    <div @class(['hero-inner', 'hero-text-only' => !$homeHero])>
        <div class="hero-copy">
            <p class="hero-eyebrow"><span class="foil-mark" aria-hidden="true"></span>{{ $page['badge'] ?? 'گالری طلای ۱۸ عیار · بازار تهران' }}</p>
            <h1>{{ $heading }}</h1>
            <p class="hero-description">{{ $page['description'] ?? 'قطعهٔ دلخواهتان را با دیدن جزئیات، عکس روی مدل و ریز قیمت انتخاب کنید.' }}</p>
            <div class="hero-actions">
                <a class="button button-gold" href="{{ route('shop.index') }}">{{ $page['button'] ?? 'مشاهده ویترین طلا' }} <span aria-hidden="true">←</span></a>
                @if(in_array('categories', $sections, true))
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
            <section id="gold-categories" class="wrap section" aria-labelledby="category-heading">
                <div class="section-heading">
                    <h2 id="category-heading">دسته‌های طلا</h2>
                    <a class="text-link" href="{{ route('shop.index') }}">همهٔ محصولات <span aria-hidden="true">←</span></a>
                </div>
                <div class="category-gallery">
                    @foreach($categories as $key => $label)
                        @php($cover = $collections->get($key)?->first()?->data['images'][0] ?? null)
                        <a class="category-card" href="{{ route('shop.category', $key) }}">
                            <span class="category-media">
                                @if($cover)
                                    <img src="{{ $cover }}" width="390" height="455" alt="" loading="lazy" decoding="async">
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
            @php($previews = $collections->filter(fn ($items) => $items->isNotEmpty()))
            <section class="wrap section home-collections" aria-labelledby="collection-heading">
                <div class="section-heading">
                    <h2 id="collection-heading">{{ $page['products_heading'] ?? 'منتخب گالری' }}</h2>
                </div>
                @if($previews->isNotEmpty())
                    <div class="collection-tabs" data-collection-tabs aria-label="انتخاب دسته برای پیش‌نمایش">
                        @foreach($previews as $key => $items)
                            <a id="preview-tab-{{ $key }}" href="#collection-{{ $key }}" data-collection-tab>{{ $categories[$key] }}</a>
                        @endforeach
                    </div>
                    @foreach($previews as $key => $items)
                        <section class="collection-preview" id="collection-{{ $key }}" data-collection-panel aria-labelledby="collection-title-{{ $key }}">
                            <div class="collection-heading">
                                <h3 id="collection-title-{{ $key }}">{{ $categories[$key] }} طلا</h3>
                                <a class="text-link" href="{{ route('shop.category', $key) }}">مشاهده همه {{ $categories[$key] }} <span aria-hidden="true">←</span></a>
                            </div>
                            @include('products', ['products' => $items, 'preview' => true, 'headingId' => 'collection-title-'.$key])
                        </section>
                    @endforeach
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
                        <p>{{ $page['guide_text'] ?? 'وزن، اندازه و ریز مبلغ هر قطعه را در صفحهٔ محصول ببینید.' }}</p>
                        <a class="text-link" href="{{ route('page.show', 'guide') }}">راهنمای خرید <span aria-hidden="true">←</span></a>
                    </div>
                </div>
            </section>
            @break
    @endswitch
@endforeach
