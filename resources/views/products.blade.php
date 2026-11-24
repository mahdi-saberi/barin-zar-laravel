<div class="grid">
@forelse($products as $item)
<?php $p = $item->data; ?>
<article class="product">
<a class="pic" href="/product/{{ $item->slug }}"><img src="{{ $p['images'][0] }}" width="560" height="500" alt="{{ ($p['image_alts'][0] ?? '') ?: $p['name'] }}" loading="lazy" decoding="async"></a>
<div class="split"><h3><a href="/product/{{ $item->slug }}">{{ $p['name'] }}</a></h3><span class="price">{{ number_format($quotes[$item->slug]['total']) }} تومان</span></div>
<small>{{ $categories[$p['category']] ?? '' }} · طلای ۱۸ عیار @if(!$p['stock']) · ناموجود @endif</small>
</article>
@empty
<p>محصولی در این دسته پیدا نشد.</p>
@endforelse
</div>
