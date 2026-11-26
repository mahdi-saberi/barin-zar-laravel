@php
    $data = $product?->data ?? $page;
    $contact = config('barin.contact');
    $catalogStory = $mode === 'catalog' ? config('barin.catalog') : null;
    $catalogFamily = $catalogStory['collections'][$category] ?? [];
    $catalogCover = $mode === 'catalog'
        ? ($category ? ($catalogFamily['cover'] ?? $catalogStory['cover']) : ($page + ['hero' => $catalogStory['cover']])['hero'])
        : null;
    $catalogIntro = $catalogFamily['intro'] ?? ($catalogStory['intro'] ?? '');
    $catalogTitle = $category ? $categories[$category].' طلا' : ($page['name'] ?? 'ویترین طلا');
    $heading = match ($mode) {
        'home' => $page['name'] ?? 'طلایی که با شما می‌ماند',
        'catalog' => $catalogTitle,
        'cart' => 'سبد خرید',
        'account' => 'حساب کاربری',
        'account-login' => 'ورود به حساب',
        'account-register' => auth()->check() ? 'تکمیل اطلاعات خریدار' : 'ثبت‌نام مشتری',
        'order' => 'جزئیات سفارش',
        default => $data['name'],
    };
    $pageNumber = $pagination?->currentPage() ?? 1;
    $title = ($seo['title'] ?? $heading.' | برین‌زر').($pageNumber > 1 ? ' · صفحه '.number_format($pageNumber) : '');
    $description = $seo['description'] ?? ($category
        ? $catalogTitle.' در برین‌زر. '.$catalogIntro
        : \App\Support\RichText::text($data, 'description', $catalogIntro ?: 'خرید طلای ۱۸ عیار از برین‌زر در بازار تهران.'));
    $canonical = $seo['canonical'] ?? (url()->current().($pageNumber > 1 ? '?page='.$pageNumber : ''));
    $settings = \App\Models\Entry::getData('settings', 'store');
    $active = $campaigns->filter(fn ($campaign) =>
        in_array($campaign->data['placement'], [$mode, 'both'], true)
        && ($mode !== 'product' || empty($campaign->data['product']) || $campaign->data['product'] === $slug)
    );
    $quote = $product ? $quotes[$slug] : null;
    $homeHero = $mode === 'home' ? \App\Support\Media::homeHero($page) : ($page['hero'] ?? null);
    $cover = $mode === 'catalog' ? $catalogCover : $homeHero;
    $ogImage = $data['images'][0] ?? ($cover && \App\Support\Media::type($cover) !== 'video' ? $cover : null);
    $noindex = in_array($mode, ['cart', 'account', 'account-login', 'account-register', 'order'], true) || !empty($seo['noindex']);
    $theme = $active->first()?->data['theme'] ?? null;
    $brand = $settings['brand'] ?? null;
    $footer = \App\Support\RichText::render($settings, 'footer', 'گالری طلای ۱۸ عیار در بازار تهران');
    $schema = $product ? [
        '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $data['name'],
        'sku' => $slug, 'description' => $description,
        'image' => array_map(fn ($path) => url($path), array_filter($data['images'])),
    ] : null;
    if ($schema && !empty($rate['value']) && ($rate['source'] ?? '') !== 'sample' && $quote['total'] > 0) {
        $schema['offers'] = [
            '@type' => 'Offer', 'priceCurrency' => 'IRR', 'price' => $quote['total'] * 10,
            'url' => url()->current(), 'availability' => 'https://schema.org/'.($data['stock'] ? 'InStock' : 'OutOfStock'),
        ];
    }
@endphp
@extends('layout')

@section('content')
    @include('store.'.$mode)
@endsection
