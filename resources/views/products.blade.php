<div class="grid{{ !empty($preview) ? ' preview-grid' : '' }}" @if(!empty($preview)) role="region" aria-labelledby="{{ $headingId }}" tabindex="0" @endif>
@forelse($products as $item)
<?php $p = $item->data; ?>
<article class="product">
<a class="pic" href="/product/{{ $item->slug }}"><img src="{{ $p['images'][0] }}" width="560" height="500" alt="{{ ($p['image_alts'][0] ?? '') ?: $p['name'] }}" loading="lazy" decoding="async"></a>
<div class="split">
    @if(!empty($preview))<h4><a href="/product/{{ $item->slug }}">{{ $p['name'] }}</a></h4>
    @else<h3><a href="/product/{{ $item->slug }}">{{ $p['name'] }}</a></h3>@endif
    <span class="price">{{ number_format($quotes[$item->slug]['total']) }} تومان</span>
</div>
<small>
    @if(empty($preview)){{ $categories[$p['category']] ?? '' }} · @endif
    طلای ۱۸ عیار @if(!$p['stock']) · ناموجود @endif
</small>
</article>
@empty
<p class="catalog-empty">محصولی پیدا نشد.</p>
@endforelse
</div>
