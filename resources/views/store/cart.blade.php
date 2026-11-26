<section class="wrap inner-section">
    <nav class="breadcrumb" aria-label="مسیر صفحه">
        <a href="{{ route('home') }}">خانه</a>
        <span aria-current="page">{{ $heading }}</span>
    </nav>
    <div class="section-heading"><h1>{{ $heading }}</h1></div>
    @if($cart->isEmpty())
        <div class="empty-state cart-empty">
            <span class="bag-mark" aria-hidden="true"></span>
            <p>سبد خرید خالی است.</p>
            <a class="button" href="{{ route('shop.index') }}">مشاهده ویترین</a>
        </div>
    @else
        <div class="cart-layout">
            <div class="cart-list">
                @foreach($cart as $id)
                    @php($item = $products[$id]->data)
                    <article class="cart-item">
                        <a class="cart-product" href="{{ route('product.show', $id) }}">
                            <img src="{{ $item['images'][0] }}" width="88" height="88" alt="" loading="lazy">
                            <span><strong>{{ $item['name'] }}</strong><br><small><bdi>{{ number_format($item['milligrams'] / 1000, 3) }}</bdi> گرم</small></span>
                        </a>
                        <div class="cart-price"><strong><bdi>{{ number_format($quotes[$id]['total']) }}</bdi> تومان</strong></div>
                        <form method="post" action="{{ route('cart.change', $id, false) }}">
                            @csrf
                            <button class="text-link" type="submit" name="remove" value="1" aria-label="حذف {{ $item['name'] }} از سبد">حذف</button>
                        </form>
                    </article>
                @endforeach
                <p class="cart-total"><span>جمع سبد خرید</span><strong><bdi>{{ number_format($quotes->only($cart->all())->sum('total')) }}</bdi> تومان</strong></p>
            </div>
            @guest
                <div class="checkout-form order-receipt">
                    <h2>حساب خریدار</h2>
                    <p>برای ثبت سفارش با شمارهٔ همراه وارد شوید یا حساب بسازید.</p>
                    <a class="button" href="{{ route('account.register') }}">ثبت‌نام</a>
                    <a class="text-link" href="{{ route('account.login') }}">ورود به حساب</a>
                </div>
            @elseif(!auth()->user()->checkoutProfileComplete())
                <div class="checkout-form order-receipt">
                    <h2>اطلاعات خریدار</h2>
                    <a class="button" href="{{ route('account.register') }}">تکمیل اطلاعات خریدار</a>
                </div>
            @else
            <form class="store-form checkout-form" method="post" action="{{ route('order.store', [], false) }}" data-checkout-form>
                @csrf
                <input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
                <h2>اطلاعات خریدار</h2>
                <p>{{ auth()->user()->name }} · <bdi>{{ auth()->user()->mobile }}</bdi></p>
                <h2>روش تحویل</h2>
                <label>انتخاب روش<select name="delivery">
                    <option value="pickup">دریافت حضوری</option>
                    <option value="shipping" @selected(old('delivery') === 'shipping')>هماهنگی ارسال</option>
                </select></label>
                <label data-shipping-address>نشانی برای ارسال<textarea name="address" autocomplete="street-address" maxlength="500" rows="3">{{ is_string(old('address')) ? old('address') : '' }}</textarea></label>
                <h2>روش پرداخت</h2>
                @if($gateways->isEmpty())
                    <input type="hidden" name="gateway" value="manual">
                    <p>هماهنگی پرداخت با فروشگاه</p>
                @else
                    <label>درگاه پرداخت<select name="gateway" required>
                        @foreach($gateways as $provider => $label)
                            <option value="{{ $provider }}" @selected(old('gateway') === $provider)>{{ $label }}</option>
                        @endforeach
                    </select></label>
                @endif
                <button class="button" type="submit">ثبت سفارش و مشاهدهٔ رسید</button>
            </form>
            @endguest
        </div>
    @endif
</section>
