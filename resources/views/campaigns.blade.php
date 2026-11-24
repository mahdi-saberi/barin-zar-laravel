<section class="section" id="offers"><div class="split"><h2>پیشنهادهای گالری</h2></div>
@forelse($active as $offer)
<div class="{{ empty($offer->data['image']) ? 'panel' : 'editorial' }}"><div class="copy"><h3>{{ $offer->data['name'] }}</h3><p>{{ $offer->data['description'] ?? '' }}</p>
@if($offer->data['discount_bps'])<p>{{ $offer->data['discount_bps'] / 100 }}٪ تخفیف روی اجرت و سود</p>@endif
@if(!empty($offer->data['link']))<a class="link" href="{{ $offer->data['link'] }}">مشاهده پیشنهاد</a>@endif</div>
@if(!empty($offer->data['image']))<img src="{{ $offer->data['image'] }}" width="720" height="480" alt="" loading="lazy">@endif</div>
@empty
<p>پیشنهادهای تازه به‌زودی در این بخش قرار می‌گیرند.</p>
@endforelse
</section>
