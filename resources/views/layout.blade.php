<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ $noindex ? 'noindex,follow' : 'index,follow' }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    @if($ogImage)<meta property="og:image" content="{{ url($ogImage) }}">@endif
    <link rel="stylesheet" href="{{ asset('assets/storefront.css') }}">
    <script defer src="{{ asset('assets/store.js') }}"></script>
    @if($schema)<script type="application/ld+json">{!! json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>@endif
</head>
<body @class(['home-page' => $mode === 'home', 'theme-'.$theme => $theme])>
<a class="skip" href="#main">رفتن به محتوا</a>
<header class="store-header">
    @include('store.rate-bar')
    <div class="site-masthead wrap">
        <a class="site-brand" href="{{ route('home') }}">
            <img src="{{ asset('assets/logo.webp') }}" alt="{{ $brand ?? 'برین‌زر' }}" width="125" height="91">
        </a>
        <nav class="store-nav" aria-label="انواع طلا">
            <a href="{{ route('shop.index', $filters) }}" @if($mode === 'catalog' && !$category) aria-current="page" @endif>همهٔ طلاها</a>
            @foreach($categories as $key => $label)
                <a href="{{ route('shop.category', ['category' => $key] + $filters) }}" @if($mode === 'catalog' && $category === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <form class="site-search" role="search" method="get" action="{{ route('shop.index', [], false) }}">
            <input type="search" name="q" aria-label="جست‌وجو در محصولات" value="{{ $filters['q'] ?? '' }}" placeholder="جست‌وجو در محصولات">
            <button type="submit" aria-label="جست‌وجو"><span class="search-mark" aria-hidden="true"></span></button>
        </form>
        <a class="site-basket" href="{{ url('/cart') }}" aria-label="سبد خرید، {{ $cart->count() }} محصول">
            <span class="bag-mark" aria-hidden="true"></span>
            <span class="basket-label">سبد خرید</span>
            <span class="basket-count" aria-hidden="true">{{ $cart->count() }}</span>
        </a>
    </div>
</header>
<main id="main">
    @if(session('notice') || $errors->any())
        <div class="wrap feedback">
            @if(session('notice'))<p class="notice" role="status">{{ session('notice') }}</p>@endif
            @if($errors->any())
                <ul class="notice errors" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @endif
        </div>
    @endif
    @yield('content')
</main>
<footer class="store-footer">
    <div class="wrap footer-inner">
        <div class="footer-brand">
            <span class="foil-mark" aria-hidden="true"></span>
            <strong>{{ $brand ?? 'گالری طلای برین‌زر' }}</strong>
            <p>{{ $footer ?? 'گالری طلای ۱۸ عیار در بازار تهران' }}</p>
        </div>
        <nav class="footer-links" aria-label="پیوندهای فروشگاه">
            <a href="{{ route('shop.index') }}">ویترین طلا</a>
            <a href="{{ route('page.show', 'guide') }}">راهنمای خرید</a>
            <a href="{{ route('admin.index') }}">مدیریت</a>
        </nav>
    </div>
</footer>
</body>
</html>
