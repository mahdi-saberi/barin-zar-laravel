<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'برین‌زر' }}</title>
    <meta name="description" content="{{ $description ?? '' }}">
    <meta name="robots" content="{{ !empty($noindex) ? 'noindex,follow' : 'index,follow' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta property="og:title" content="{{ $title ?? 'برین‌زر' }}">
    <meta property="og:description" content="{{ $description ?? '' }}">
    @if(!empty($ogImage))<meta property="og:image" content="{{ url($ogImage) }}">@endif
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/modern.css') }}">
    <script defer src="{{ asset('assets/store.js') }}"></script>
    @if(!empty($schema))<script type="application/ld+json">{!! json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>@endif
</head>
<body class="{{ !empty($theme) ? 'theme-'.$theme : '' }}">
<a class="skip" href="#main">رفتن به محتوا</a>
<div class="top">{{ $brand ?? 'گالری طلای برین‌زر' }} · بازار تهران
@if(!empty($rate['value'])) · {{ match($rate['source']) {'api' => 'آخرین نرخ دریافت‌شده', 'manual' => 'نرخ دستی', default => 'نرخ نمونه'} }} ۱۸ عیار {{ number_format($rate['value']) }} تومان / گرم
@if($rate['source'] === 'api') · <time datetime="{{ $rate['timestamp'] }}">{{ \Illuminate\Support\Carbon::parse($rate['timestamp'])->timezone(config('app.timezone'))->format('Y/m/d H:i') }}</time>@endif
@endif</div>
<header class="head wrap">
<a class="brand" href="/"><img src="{{ asset('assets/logo.webp') }}" alt="{{ $brand ?? 'برین‌زر' }}" width="76" height="56"></a>
<nav class="nav" aria-label="منوی اصلی"><a href="/shop">ویترین طلا</a><a href="/#offers">پیشنهادها</a><a href="/page/guide">راهنمای خرید</a></nav>
<a class="basket" href="/cart">سبد خرید {{ isset($cart) ? '('.$cart->count().')' : '' }}</a>
</header>
<main class="wrap" id="main">
@if(session('notice'))<p role="status" class="notice">{{ session('notice') }}</p>@endif
@if($errors->any())<ul class="notice errors" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
@yield('content')
</main>
<footer class="foot"><div class="wrap split"><div><strong>{{ $brand ?? 'گالری برین‌زر' }}</strong><br><small>{{ $footer ?? 'گالری طلای بازار تهران' }}</small></div><div class="row"><a href="/page/guide">راهنمای خرید</a><a href="/admin">مدیریت</a></div></div></footer>
</body>
</html>
