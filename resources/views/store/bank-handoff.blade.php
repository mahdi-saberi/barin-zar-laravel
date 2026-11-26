<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>انتقال به درگاه پرداخت</title>
    <link rel="stylesheet" href="{{ asset('assets/storefront.css') }}">
    <script defer src="{{ asset('assets/bank-handoff.js') }}"></script>
</head>
<body>
<main class="wrap inner-section account-form">
    <h1>انتقال به درگاه پرداخت</h1>
    <form method="post" action="{{ $url }}" data-bank-handoff>
        @foreach($fields as $field => $value)
            <input type="hidden" name="{{ $field }}" value="{{ $value }}">
        @endforeach
        <button type="submit" class="button">ادامه به درگاه</button>
    </form>
</main>
</body>
</html>
