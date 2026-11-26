<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\IranianIdentity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Account extends Controller
{
    public function index(Request $request, Store $store)
    {
        return $store->show($request, 'account', extras: [
            'orders' => $request->user()->orders()->latest('id')->paginate(12),
        ]);
    }

    public function loginForm(Request $request, Store $store)
    {
        return $request->user() ? redirect()->route('account.index') : $store->show($request, 'account-login');
    }

    public function registerForm(Request $request, Store $store)
    {
        return $request->user()?->checkoutProfileComplete() ? redirect()->route('account.index')
            : $store->show($request, 'account-register');
    }

    public function login(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('account.index');
        }
        $request->merge(['mobile' => IranianIdentity::mobile($request->input('mobile'))]);
        $credentials = $request->validate(['mobile' => ['required', 'regex:/\A09[0-9]{9}\z/'], 'password' => 'required|string|max:72']);
        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['mobile' => 'شماره همراه یا رمز عبور درست نیست.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route($request->session()->has('cart') ? 'cart.show' : 'account.index'));
    }

    public function register(Request $request)
    {
        $user = $request->user();
        if ($user?->checkoutProfileComplete()) {
            return redirect()->route('account.index');
        }
        $nationalId = IranianIdentity::digits($request->input('national_id'));
        $request->merge(['mobile' => IranianIdentity::mobile($request->input('mobile')), 'national_id' => $nationalId]);
        $uniqueMobile = Rule::unique('users', 'mobile');
        if ($user) {
            $uniqueMobile->ignore($user);
        }
        $data = $request->validate([
            'first_name' => 'required|string|max:60', 'last_name' => 'required|string|max:60',
            'mobile' => ['required', 'regex:/\A09[0-9]{9}\z/', $uniqueMobile],
            'national_id' => ['required', 'string', function ($field, $value, $fail) {
                if (!IranianIdentity::validNationalId($value)) {
                    $fail('کد ملی ۱۰ رقمی معتبر وارد کنید.');
                }
            }],
        ] + ($user ? [] : ['password' => 'required|string|min:10|max:72|confirmed']), [
            'mobile.regex' => 'شماره همراه ۱۱ رقمی با ۰۹ وارد کنید.',
            'mobile.unique' => 'با این شماره همراه قبلاً ثبت‌نام شده است.',
        ], ['first_name' => 'نام', 'last_name' => 'نام خانوادگی', 'national_id' => 'کد ملی', 'mobile' => 'شماره همراه', 'password' => 'رمز عبور']);

        if (!$user && strlen($data['password']) > 72) {
            throw ValidationException::withMessages(['password' => 'رمز عبور کوتاه‌تری وارد کنید.']);
        }
        try {
            $profile = [
                'name' => $data['first_name'].' '.$data['last_name'], 'mobile' => $data['mobile'],
                'national_id' => $data['national_id'], 'national_id_hash' => IranianIdentity::fingerprint($data['national_id']),
            ];
            if ($user) {
                $user->update($profile);
            } else {
                $user = User::create($profile + ['password' => $data['password']]);
                Auth::login($user);
            }
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['mobile' => 'شماره همراه یا کد ملی قبلاً ثبت شده است.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route($request->session()->has('cart') ? 'cart.show' : 'account.index'))
            ->with('notice', 'اطلاعات حساب ذخیره شد.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
