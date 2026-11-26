<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $heading }} | مدیریت برین‌زر</title>
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/modern.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin.css') }}">
    <script defer src="{{ asset('assets/store.js') }}"></script>
    <script defer src="{{ asset('assets/admin.js') }}"></script>
</head>
<body class="admin-page {{ isset($tabs) ? 'admin-authenticated' : 'admin-guest' }}">
<a class="skip" href="#main">رفتن به محتوا</a>
@isset($tabs)
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="{{ route('admin.index') }}">
            <img src="{{ asset('assets/logo.webp') }}" alt="" width="42" height="42">
            <strong>برین‌زر</strong>
        </a>
        <nav class="admin-nav" aria-label="منوی مدیریت">
            @foreach($tabs as $key => $label)
                <a href="{{ route('admin.index', $key === 'dashboard' ? [] : ['tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <a class="admin-store-link" href="{{ route('home') }}">باز کردن فروشگاه <span aria-hidden="true">↗</span></a>
    </aside>
    <div class="admin-workspace">
        <header class="admin-topbar">
            <span>مدیریت فروشگاه</span>
            <div class="admin-account">
                <strong>{{ auth()->user()->name }}</strong>
                <form method="post" action="{{ route('admin.logout', [], false) }}">
                    @csrf
                    <button class="admin-button admin-button-small" type="submit">خروج</button>
                </form>
            </div>
        </header>
        <main class="admin-main" id="main">
            @include('admin.feedback')
            @yield('content')
        </main>
    </div>
</div>
@else
<main class="admin-login" id="main">
    @include('admin.feedback')
    @yield('content')
</main>
@endisset
</body>
</html>
