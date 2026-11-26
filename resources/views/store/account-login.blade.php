<section class="wrap inner-section account-form">
    <h1>{{ $heading }}</h1>
    <form class="store-form checkout-form" method="post" action="{{ route('account.authenticate', [], false) }}">
        @csrf
        <label>شماره همراه<input type="tel" dir="ltr" name="mobile" value="{{ is_string(old('mobile')) ? old('mobile') : '' }}" required maxlength="20" autocomplete="username"></label>
        <label>رمز عبور<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="button" type="submit">ورود</button>
        <a class="text-link" href="{{ route('account.register') }}">ساخت حساب کاربری</a>
    </form>
</section>
