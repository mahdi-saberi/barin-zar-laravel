@extends('admin.layout', ['heading' => 'ورود به مدیریت'])

@section('content')
<div class="admin-card admin-login-card">
    <a class="admin-brand" href="{{ route('home') }}">
        <img src="{{ asset('assets/logo.webp') }}" alt="" width="52" height="52">
        <strong>برین‌زر</strong>
    </a>
    <h1>ورود به مدیریت</h1>
    <form class="admin-login-form" method="post" action="{{ route('login.attempt', [], false) }}">
        @csrf
        <label>ایمیل یا شماره همراه<input name="email" type="text" value="{{ is_string(old('email')) ? old('email') : '' }}" dir="ltr" autocomplete="username" required autofocus></label>
        <label>رمز عبور<input name="password" type="password" dir="ltr" autocomplete="current-password" required></label>
        <button class="admin-button admin-primary" type="submit">ورود</button>
    </form>
    <a class="admin-text-link" href="{{ route('home') }}">بازگشت به فروشگاه</a>
</div>
@endsection
