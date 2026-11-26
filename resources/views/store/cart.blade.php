@php
    $profileComplete = auth()->user()?->checkoutProfileComplete() ?? false;
    $hasRate = !empty($rate['value']);
@endphp
<section class="wrap inner-section cart-page">
    <nav class="breadcrumb" aria-label="مسیر صفحه">
        <a href="{{ route('home') }}">خانه</a>
        <span aria-current="page">{{ $heading }}</span>
    </nav>
    <div class="section-heading">
        <h1>{{ $heading }}</h1>
        @if($cart->isNotEmpty())
            <a class="text-link" href="{{ route('shop.index') }}">ادامهٔ خرید از ویترین <span aria-hidden="true">←</span></a>
        @endif
    </div>
    @if($cart->isEmpty())
        <div class="cart-empty">
            <div class="cart-empty-copy">
                <span class="bag-mark" aria-hidden="true"></span>
                <h2>سبد خرید خالی است</h2>
                <p>دنبال یک قطعهٔ ظریف برای استفادهٔ روزمره هستید؟ از مدل‌های سبک شروع کنید.</p>
                <div class="cart-entry-actions">
                    <a class="button" href="{{ route('shop.index') }}">مشاهدهٔ ویترین</a>
                    <a class="text-link" href="{{ route('shop.index', ['weight' => 'under-3', 'in_stock' => 1]) }}#catalog-results">دیدن مدل‌های سبک <span aria-hidden="true">←</span></a>
                </div>
            </div>
            <img src="{{ asset('assets/m1.webp') }}" alt="" width="720" height="480" decoding="async">
        </div>
    @else
        @include('store.purchase-steps', ['purchaseStep' => $profileComplete ? 2 : 1])
        <div class="cart-layout">
            <div class="cart-list">
                <h2>قطعه‌های انتخاب‌شده <small>{{ \App\Support\PersianFormat::number($cart->count()) }} محصول</small></h2>
                @foreach($cart as $id)
                    @php($item = $products[$id]->data)
                    <article class="cart-item">
                        <a class="cart-product" href="{{ route('product.show', $id) }}">
                            <img src="{{ $item['images'][0] }}" width="88" height="88" alt="" loading="lazy">
                            <span>
                                <strong>{{ $item['name'] }}</strong>
                                <small><bdi>{{ number_format($item['milligrams'] / 1000, 3) }}</bdi> گرم · طلای ۱۸ عیار</small>
                                @if(!$item['stock'])<small class="cart-unavailable">ناموجود</small>@endif
                            </span>
                        </a>
                        <div class="cart-item-actions">
                            @if($hasRate)
                                <span class="cart-price"><strong><bdi>{{ number_format($quotes[$id]['total']) }}</bdi></strong> تومان</span>
                            @endif
                            <form method="post" action="{{ route('cart.change', $id, false) }}">
                                @csrf
                                <button class="text-link" type="submit" name="remove" value="1" aria-label="حذف {{ $item['name'] }} از سبد">حذف</button>
                            </form>
                        </div>
                    </article>
                @endforeach
                <a class="cart-pickup-link" href="#store-contact">
                    <span class="foil-mark" aria-hidden="true"></span>
                    <span>دریافت حضوری از گالری در سبزه میدان <span aria-hidden="true">←</span></span>
                </a>
            </div>
            <aside class="checkout-form cart-checkout" aria-labelledby="cart-checkout-heading">
                <div class="cart-checkout-summary">
                    <h2 id="cart-checkout-heading">خلاصهٔ خرید</h2>
                    @if($hasRate)
                        <p class="cart-total"><span>جمع سبد خرید</span><strong><bdi>{{ number_format($quotes->only($cart->all())->sum('total')) }}</bdi> تومان</strong></p>
                    @else
                        <p class="availability">جمع سبد پس از دریافت نرخ طلا قابل محاسبه است.</p>
                    @endif
                </div>
                @guest
                    <div class="cart-checkout-actions">
                        <h3>برای ثبت سفارش وارد شوید</h3>
                        <p>با شمارهٔ همراه حساب بسازید یا با حساب خود ادامه دهید.</p>
                        <a class="button" href="{{ route('account.register') }}">ثبت‌نام</a>
                        <a class="text-link" href="{{ route('account.login') }}">ورود به حساب</a>
                    </div>
                @elseif(!$profileComplete)
                    <div class="cart-checkout-actions">
                        <p>نام، شمارهٔ همراه و کد ملی را تکمیل کنید تا بتوانید سفارش را ثبت کنید.</p>
                        <a class="button" href="{{ route('account.register') }}">تکمیل اطلاعات خریدار</a>
                    </div>
                @else
                    <form class="store-form cart-checkout-form" method="post" action="{{ route('order.store', [], false) }}" data-checkout-form>
                        @csrf
                        <input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
                        <p class="cart-buyer">خریدار <strong>{{ auth()->user()->name }}</strong> <bdi>{{ auth()->user()->mobile }}</bdi></p>
                        <label>روش تحویل
                            <select name="delivery">
                                <option value="pickup">دریافت حضوری</option>
                                <option value="shipping" @selected(old('delivery') === 'shipping')>هماهنگی ارسال</option>
                            </select>
                        </label>
                        <p class="cart-pickup-note" data-pickup-note @if(old('delivery') === 'shipping') hidden @endif>{{ $contact['address'] }}</p>
                        <label data-shipping-address>نشانی برای ارسال<textarea name="address" autocomplete="street-address" maxlength="500" rows="3">{{ is_string(old('address')) ? old('address') : '' }}</textarea></label>
                        @if($gateways->isEmpty())
                            <input type="hidden" name="gateway" value="manual">
                            <p>هماهنگی پرداخت با فروشگاه</p>
                        @else
                            <label>درگاه پرداخت
                                <select name="gateway" required>
                                    @foreach($gateways as $provider => $label)
                                        <option value="{{ $provider }}" @selected(old('gateway') === $provider)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <button class="button" type="submit">ثبت سفارش و مشاهدهٔ رسید</button>
                    </form>
                @endguest
                <a class="cart-checkout-help" href="tel:{{ $contact['dial'] }}">برای خرید راهنمایی می‌خواهید؟ <bdi>{{ $contact['phone'] }}</bdi></a>
            </aside>
        </div>
    @endif
</section>
