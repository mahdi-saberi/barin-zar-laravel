@php
    $catalogUrl = fn (array $query = []) => $category
        ? route('shop.category', ['category' => $category] + $query, false)
        : route('shop.index', $query, false);
    $quickFilters = [
        'in_stock' => ['label' => 'فقط موجود', 'value' => 1],
        'weight' => ['label' => 'کمتر از ۳ گرم', 'value' => 'under-3'],
        'sort' => ['label' => 'کمترین قیمت', 'value' => 'asc'],
    ];
    $catalogDescription = $category ? $catalogIntro : \App\Support\RichText::render($page, 'description', $catalogIntro);
@endphp
<div class="wrap catalog-section">
    <nav class="breadcrumb" aria-label="مسیر صفحه">
        <a href="{{ route('home') }}">خانه</a>
        @if($category)<a href="{{ route('shop.index') }}">ویترین طلا</a>@endif
        <span aria-current="page">{{ $category ? $categories[$category] : 'ویترین طلا' }}</span>
    </nav>
    <section @class(['catalog-feature', 'catalog-feature-text-only' => !$catalogCover]) aria-labelledby="catalog-heading">
        <div class="catalog-feature-copy">
            <p class="catalog-eyebrow"><span class="foil-mark" aria-hidden="true"></span> طلای ۱۸ عیار</p>
            <h1 id="catalog-heading">{{ $heading }}</h1>
            <div class="catalog-description rich-content">{{ $catalogDescription }}</div>
            <a class="button button-gold" href="#catalog-results">دیدن مدل‌ها <span aria-hidden="true">↓</span></a>
        </div>
        @if($catalogCover)
            <div class="catalog-feature-media" data-catalog-cover="{{ $category ?? 'all' }}">
                <x-store-media :path="$catalogCover" :alt="$category ? '' : ($page['hero_alt'] ?? '')"
                    :label="$heading" width="1100" height="650" fetchpriority="high" />
            </div>
        @endif
    </section>

    <section class="catalog-families" aria-labelledby="catalog-families-heading">
        <div class="section-heading">
            <h2 id="catalog-families-heading">انتخاب بر اساس نوع طلا</h2>
            @if($category)
                <a class="text-link" href="{{ route('shop.index') }}">همهٔ طلاها <span aria-hidden="true">←</span></a>
            @endif
        </div>
        <nav class="catalog-family-rail" aria-labelledby="catalog-families-heading" data-catalog-rail>
            @foreach($categories as $key => $label)
                <a class="catalog-family" href="{{ route('shop.category', $key) }}" @if($category === $key) aria-current="page" @endif>
                    <span class="catalog-family-photo">
                        @if($thumb = $catalogStory['collections'][$key]['thumb'] ?? null)
                            <img src="{{ $thumb }}" width="230" height="160" alt="" loading="lazy" decoding="async">
                        @else
                            <span class="foil-mark" aria-hidden="true"></span>
                        @endif
                    </span>
                    <span>{{ $label }} <span aria-hidden="true">←</span></span>
                </a>
            @endforeach
        </nav>
    </section>

    <section id="catalog-results" class="catalog-results" aria-labelledby="catalog-results-heading">
        <header class="catalog-results-heading">
            <div>
                <h2 id="catalog-results-heading">{{ $category ? 'مدل‌های '.$categories[$category] : 'همهٔ مدل‌های طلا' }}</h2>
                <p class="catalog-count">
                    <bdi>{{ \App\Support\PersianFormat::number($pagination->total()) }}</bdi> محصول
                    @if(isset($filters['q'])) برای «{{ $filters['q'] }}» @endif
                </p>
            </div>
            <a class="text-link catalog-guide-link" href="{{ route('page.show', 'guide') }}">راهنمای خرید طلا <span aria-hidden="true">←</span></a>
        </header>
        <div class="catalog-toolbar">
            <nav class="catalog-shortcuts" aria-label="فیلترهای پرکاربرد">
                @foreach($quickFilters as $name => $shortcut)
                    @php
                        $selected = ($filters[$name] ?? null) === $shortcut['value'];
                        $parameters = $selected ? \Illuminate\Support\Arr::except($filters, [$name]) : [$name => $shortcut['value']] + $filters;
                    @endphp
                    <a href="{{ $catalogUrl($parameters) }}#catalog-results" @if($selected) aria-current="true" @endif>
                        {{ $shortcut['label'] }} @if($selected)<span aria-hidden="true">×</span>@endif
                    </a>
                @endforeach
                @if(isset($filters['max']))
                    <a href="{{ $catalogUrl(\Illuminate\Support\Arr::except($filters, ['max'])) }}#catalog-results" aria-current="true" aria-label="برداشتن سقف بودجه">
                        تا <bdi>{{ \App\Support\PersianFormat::number($filters['max']) }}</bdi> تومان <span aria-hidden="true">×</span>
                    </a>
                @endif
            </nav>
            <details class="catalog-controls">
                <summary>
                    <span class="catalog-filter-icon" aria-hidden="true"></span>
                    فیلتر و مرتب‌سازی
                    @if($filters)<span class="catalog-filter-count">{{ \App\Support\PersianFormat::number(count($filters)) }}</span>@endif
                    <span class="filter-chevron" aria-hidden="true"></span>
                </summary>
                <form class="store-form catalog-filter" method="get" action="{{ $catalogUrl() }}#catalog-results">
                    <label>نام محصول<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="جست‌وجو در این فهرست"></label>
                    <label>سقف بودجه (تومان)<input type="number" name="max" min="1" value="{{ $filters['max'] ?? '' }}"></label>
                    <label>وزن<select name="weight">
                        <option value="">همهٔ وزن‌ها</option>
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
                        @if($filters)<a class="text-link" href="{{ $catalogUrl() }}#catalog-results">پاک کردن فیلترها</a>@endif
                        <button class="button" type="submit">اعمال فیلتر</button>
                    </div>
                </form>
            </details>
        </div>
        @if($products->isNotEmpty())
            @include('products')
            {{ $pagination->fragment('catalog-results')->onEachSide(1)->links('partials.pagination') }}
        @else
            <div class="empty-state catalog-empty">
                <span class="foil-mark" aria-hidden="true"></span>
                <h3>محصولی پیدا نشد</h3>
                @if($filters)
                    <p>برای دیدن مدل‌های بیشتر، وزن یا سقف بودجه را تغییر دهید.</p>
                    <a class="button" href="{{ $catalogUrl() }}#catalog-results">پاک کردن فیلترها</a>
                @else
                    <p>برای انتخاب یا اطلاع از موجودی با گالری تماس بگیرید.</p>
                    <a class="button" href="tel:{{ $contact['dial'] }}">تماس با گالری</a>
                @endif
            </div>
        @endif
    </section>

    <aside class="catalog-help" aria-label="راهنمای انتخاب قطعه">
        <span class="foil-mark" aria-hidden="true"></span>
        <div>
            <h2>بین چند مدل مرددید؟</h2>
            <p>برای انتخاب اندازه، مقایسهٔ مدل‌ها یا هماهنگی بازدید حضوری با گالری تماس بگیرید.</p>
        </div>
        <a class="button" href="tel:{{ $contact['dial'] }}">تماس با گالری <bdi>{{ $contact['phone'] }}</bdi></a>
    </aside>
</div>
