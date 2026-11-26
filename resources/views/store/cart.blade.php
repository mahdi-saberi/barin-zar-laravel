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
                        <form method="post" action="{{ url('/cart/'.$id) }}">
                            @csrf
                            <button class="text-link" type="submit" name="remove" value="1" aria-label="حذف {{ $item['name'] }} از سبد">حذف</button>
                        </form>
                    </article>
                @endforeach
                <p class="cart-total"><span>جمع سبد خرید</span><strong><bdi>{{ number_format($quotes->only($cart->all())->sum('total')) }}</bdi> تومان</strong></p>
            </div>
            <form class="store-form checkout-form" method="post" action="{{ url('/order') }}">
                @csrf
                <input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
                <h2>اطلاعات خریدار</h2>
                <label>نام و نام خانوادگی<input name="name" value="{{ is_string(old('name')) ? old('name') : '' }}" autocomplete="name" maxlength="120" required></label>
                <label>شماره تماس<input name="mobile" value="{{ is_string(old('mobile')) ? old('mobile') : '' }}" type="tel" autocomplete="tel" maxlength="24" required></label>
                <label>روش تحویل<select name="delivery">
                    <option value="pickup">دریافت حضوری</option>
                    <option value="shipping" @selected(old('delivery') === 'shipping')>هماهنگی ارسال</option>
                </select></label>
                <label>نشانی برای ارسال<textarea name="address" autocomplete="street-address" maxlength="500" rows="3">{{ is_string(old('address')) ? old('address') : '' }}</textarea></label>
                <button class="button" type="submit">ثبت درخواست خرید</button>
                <small>فروشگاه برای تأیید پرداخت و تحویل با شما هماهنگ می‌کند.</small>
            </form>
        </div>
    @endif
</section>
