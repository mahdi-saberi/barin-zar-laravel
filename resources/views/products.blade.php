<div class="product-grid{{ !empty($preview) ? ' preview-grid' : '' }}">
    @forelse($products as $item)
        @php
            $piece = $item->data;
            $alternate = $mode === 'catalog' ? ($piece['images'][1] ?? null) : null;
        @endphp
        <article class="product">
            <a class="product-media" href="{{ route('product.show', $item->slug) }}" aria-label="{{ $piece['name'] }}{{ $piece['stock'] ? '' : '، ناموجود' }}">
                <img src="{{ $piece['images'][0] }}" width="560" height="560" alt="{{ ($piece['image_alts'][0] ?? '') ?: $piece['name'] }}" loading="lazy" decoding="async">
                @if($alternate && $alternate !== $piece['images'][0])
                    <template data-product-alternate>
                        <img class="product-alternate" src="{{ $alternate }}" width="560" height="560" alt="" decoding="async">
                    </template>
                @endif
                @if(!$piece['stock'])<span class="product-unavailable">ناموجود</span>@endif
            </a>
            <div class="product-caption">
                @if(!empty($preview))
                    <h4><a href="{{ route('product.show', $item->slug) }}">{{ $piece['name'] }}</a></h4>
                @elseif($mode === 'catalog')
                    <h2><a href="{{ route('product.show', $item->slug) }}">{{ $piece['name'] }}</a></h2>
                @else
                    <h3><a href="{{ route('product.show', $item->slug) }}">{{ $piece['name'] }}</a></h3>
                @endif
                <p class="product-spec"><bdi>{{ number_format($piece['milligrams'] / 1000, 3) }}</bdi> گرم <span aria-hidden="true">·</span> طلای ۱۸ عیار</p>
                @if(!empty($rate['value']))
                    <p class="product-price"><strong><bdi>{{ number_format($quotes[$item->slug]['total']) }}</bdi></strong> تومان</p>
                @else
                    <p class="product-spec">در انتظار نرخ طلا</p>
                @endif
            </div>
        </article>
    @empty
        <p class="empty-state">محصولی پیدا نشد.</p>
    @endforelse
</div>
