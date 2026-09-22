@extends('layouts.site', [
    'title' => $product->tr('name'),
    'description' => $product->tr('summary') ?? __('site.meta_description'),
    'ogImage' => $product->coverUrl(),
])

@php($startup = $product->startup)
@php($images = $product->imageUrls())
@php($video = \App\Support\Video::embedUrl($product->video_url))

@section('content')
<x-flash />

<section class="section">
    <div class="container">
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="{{ route('products.index') }}">{{ __('site.nav.products') }}</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('products.index', ['type' => $product->type->value]) }}">{{ $product->type->getLabel() }}</a>
        </nav>

        <div class="product-layout">
            <div class="gallery" data-gallery>
                @if ($images)
                    <div class="gallery-main"><img src="{{ $images[0] }}" alt="{{ $product->tr('name') }}" data-gallery-main></div>
                    @if (count($images) > 1)
                        <div class="gallery-thumbs">
                            @foreach ($images as $i => $url)
                                <button type="button" @class(['active' => $i === 0]) data-gallery-thumb="{{ $url }}"><img src="{{ $url }}" alt="" loading="lazy"></button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="gallery-main gallery-empty"><img src="{{ asset('images/logo-mark.png') }}" alt=""></div>
                @endif
            </div>

            <div class="product-info">
                <span class="badge badge-static">{{ $product->type->getLabel() }}</span>
                <h1 dir="auto">{{ $product->tr('name') }}</h1>
                <a class="product-startup" href="{{ route('startups.show', $startup) }}">
                    <x-startup-logo :startup="$startup" size="36" />
                    <span>{{ __('site.product.by') }} <strong>{{ $startup->name }}</strong></span>
                </a>
                @if ($product->tr('summary'))<p class="lead" dir="auto">{{ $product->tr('summary') }}</p>@endif
                <p class="price price-lg">{{ $product->priceLabel() }}</p>

                @unless ($preview)
                    <div class="product-actions">
                        <button type="button" class="btn btn-primary" data-inquiry="quote">{{ __('site.actions.quote') }}</button>
                        <button type="button" class="btn btn-outline" data-inquiry="contact">{{ __('site.actions.contact') }}</button>
                    </div>
                @endunless
                @if ($product->link_url)
                    <a class="link-arrow" href="{{ $product->link_url }}" rel="noopener nofollow" target="_blank">{{ __('site.product.link') }}</a>
                @endif
            </div>
        </div>

        <div class="product-body">
            @if ($product->tr('description'))
                <div class="prose" dir="auto">{!! nl2br(e($product->tr('description'))) !!}</div>
            @endif
            @if ($video)
                <div class="video"><iframe src="{{ $video }}" title="{{ $product->tr('name') }}" loading="lazy" allowfullscreen allow="encrypted-media; picture-in-picture"></iframe></div>
            @endif
        </div>

        @if ($related->isNotEmpty())
            <h2 class="section-title">{{ __('site.product.more_from', ['startup' => $startup->name]) }}</h2>
            <div class="grid grid-4">
                @foreach ($related as $item)
                    <x-product-card :product="$item" :show-startup="false" />
                @endforeach
            </div>
        @endif
    </div>
</section>

@unless ($preview)
    <x-inquiry-dialog :startup="$startup" :product="$product" />
@endunless
@endsection
