<section class="wrap inner-section account-form">
    <h1>{{ $heading }}</h1>
    <form class="store-form checkout-form" method="post" action="{{ route('account.save-profile', [], false) }}">
        @csrf
        <div class="checkout-fields">
            <label>نام<input name="first_name" autocomplete="given-name" value="{{ is_string(old('first_name')) ? old('first_name') : '' }}" required maxlength="60"></label>
            <label>نام خانوادگی<input name="last_name" autocomplete="family-name" value="{{ is_string(old('last_name')) ? old('last_name') : '' }}" required maxlength="60"></label>
        </div>
        <label>شماره همراه<input type="tel" dir="ltr" name="mobile" value="{{ is_string(old('mobile')) ? old('mobile') : (auth()->user()?->mobile ?? '') }}" autocomplete="tel" required maxlength="20"></label>
        <label>کد ملی<input name="national_id" dir="ltr" inputmode="numeric" maxlength="10" required></label>
        @guest
            <label>رمز عبور<input type="password" name="password" autocomplete="new-password" minlength="10" maxlength="72" required><small>حداقل ۱۰ نویسه</small></label>
            <label>تکرار رمز عبور<input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" maxlength="72" required></label>
        @endguest
        <button class="button" type="submit">{{ auth()->check() ? 'ذخیره اطلاعات' : 'ایجاد حساب' }}</button>
        @guest<a class="text-link" href="{{ route('account.login') }}">ورود به حساب موجود</a>@endguest
    </form>
</section>
