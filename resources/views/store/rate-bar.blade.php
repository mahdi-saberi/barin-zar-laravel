@php($display = \App\Support\GoldRateDisplay::from($rate))

<div class="rate-bar" data-rate-url="{{ route('shop.gold-rate', [], false) }}" data-rate-value="{{ $display['value'] }}" @if(!$display['value']) hidden @endif>
    <span>نرخ طلای ۱۸ عیار</span>
    <strong><bdi data-rate-price>{{ $display['price'] }}</bdi> تومان / گرم</strong>
    <time dir="ltr" data-rate-time title="آخرین به‌روزرسانی به وقت تهران" @if($display['datetime']) datetime="{{ $display['datetime'] }}" @else hidden @endif>{{ $display['updated'] }}</time>
    <small class="rate-source" data-rate-source @if(!$display['source']) hidden @endif>{{ $display['source'] }}</small>
    @if($quotes->isNotEmpty())
        <a class="rate-reload" data-rate-reload href="{{ request()->fullUrlWithoutQuery('page') }}" hidden>قیمت‌های به‌روز محصولات</a>
    @endif
</div>
