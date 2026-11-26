@if($active->isNotEmpty())
    <section class="wrap section" id="offers" aria-labelledby="offers-heading">
        <div class="section-heading"><h2 id="offers-heading">پیشنهادهای گالری</h2></div>
        @foreach($active as $offer)
            @php($promotion = $offer->data)
            <article @class(['campaign-card', 'campaign-with-image' => !empty($promotion['image'])])>
                @if(!empty($promotion['image']))
                    <img src="{{ $promotion['image'] }}" width="720" height="480" alt="" loading="lazy" decoding="async">
                @endif
                <div class="campaign-copy">
                    <h3>{{ $promotion['name'] }}</h3>
                    @if(!empty($promotion['description']))
                        <div class="rich-content">{{ \App\Support\RichText::render($promotion, 'description') }}</div>
                    @endif
                    @if(!empty($promotion['discount_bps']))<p class="campaign-discount">{{ $promotion['discount_bps'] / 100 }}٪ تخفیف روی اجرت و سود</p>@endif
                    @if(!empty($promotion['link']))<a class="text-link" href="{{ $promotion['link'] }}">مشاهده پیشنهاد <span aria-hidden="true">←</span></a>@endif
                </div>
            </article>
        @endforeach
    </section>
@endif
