<div class="product-grid{{ !empty($preview) ? ' preview-grid' : '' }}">
    @forelse($products as $item)
        @php
            $piece = $item->data;
            $alternate = $mode === 'catalog' ? ($piece['images'][1] ?? null) : null;
        @endphp
        <article class="product">
            <a class="product-card-link" href="{{ route('product.show', $item->slug) }}">
                <div class="product-media">
                    <img src="{{ $piece['images'][0] }}" width="560" height="560" alt="{{ $piece['image_alts'][0] ?? '' }}" loading="lazy" decoding="async">
                    @if($alternate && $alternate !== $piece['images'][0])
                        <template data-product-alternate>
                            <img class="product-alternate" src="{{ $alternate }}" width="560" height="560" alt="" decoding="async">
                        </template>
                    @endif
                    @if(!$piece['stock'])<span class="product-unavailable">ناموجود</span>@endif
                </div>
                <div class="product-caption" data-quote-slug="{{ $item->slug }}">
                    @if(!empty($preview))
                        <h4>{{ $piece['name'] }}</h4>
                    @else
                        <h3>{{ $piece['name'] }}</h3>
                    @endif
                    <p class="product-spec"><bdi>{{ number_format($piece['milligrams'] / 1000, 3) }}</bdi> گرم <span aria-hidden="true">·</span> طلای ۱۸ عیار</p>
                    <p class="product-price" data-quote-price @if(empty($rate['value'])) hidden @endif><strong><bdi data-quote-field="total">{{ number_format($quotes[$item->slug]['total']) }}</bdi></strong> تومان</p>
                    <p class="product-spec" data-quote-missing @if(!empty($rate['value'])) hidden @endif>در انتظار نرخ طلا</p>
                    @if(empty($preview))
                        <span class="product-card-action">
                            {{ $piece['stock'] ? 'دیدن و خرید' : 'دیدن جزئیات' }} <span aria-hidden="true">←</span>
                        </span>
                    @endif
                </div>
            </a>
        </article>
    @empty
        <p class="empty-state">محصولی پیدا نشد.</p>
    @endforelse
</div>
