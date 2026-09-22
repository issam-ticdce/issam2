@props(['product', 'showStartup' => true])
<article class="card product-card">
    <a href="{{ route('products.show', $product) }}" class="card-link">
        <div class="product-card-media">
            @if ($product->coverUrl())
                <img src="{{ $product->coverUrl() }}" alt="" loading="lazy">
            @else
                <div class="product-card-placeholder" aria-hidden="true"><img src="{{ asset('images/logo-mark.png') }}" alt=""></div>
            @endif
            <span class="badge">{{ $product->type->getLabel() }}</span>
        </div>
        <div class="card-body">
            <h3 class="card-title" dir="auto">{{ $product->tr('name') }}</h3>
            @if ($showStartup)<p class="muted small">{{ __('site.product.by') }} {{ $product->startup->name }}</p>@endif
            @if ($product->tr('summary'))<p class="card-text" dir="auto">{{ \Illuminate\Support\Str::limit($product->tr('summary'), 110) }}</p>@endif
            <p class="price">{{ $product->priceLabel() }}</p>
        </div>
    </a>
</article>
