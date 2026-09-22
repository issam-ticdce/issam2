@extends('layouts.site')

@section('content')
<section class="hero">
    <div class="container hero-inner">
        <div class="hero-text">
            <p class="eyebrow">{{ __('site.home.eyebrow') }}</p>
            <h1>{{ __('site.home.title') }}</h1>
            <p class="lead">{{ __('site.home.subtitle') }}</p>
            <form action="{{ route('products.index') }}" method="GET" class="hero-search" role="search">
                <label class="sr-only" for="hero-q">{{ __('site.home.search') }}</label>
                <input id="hero-q" type="search" name="q" placeholder="{{ __('site.home.search_placeholder') }}">
                <button type="submit" class="btn btn-accent">{{ __('site.home.search') }}</button>
            </form>
            <dl class="stats">
                <div><dt>{{ __('site.home.stats_startups') }}</dt><dd>{{ $stats['startups'] }}</dd></div>
                <div><dt>{{ __('site.home.stats_products') }}</dt><dd>{{ $stats['products'] }}</dd></div>
                <div><dt>{{ __('site.home.stats_sectors') }}</dt><dd>{{ $stats['sectors'] }}</dd></div>
            </dl>
        </div>
        <div class="hero-art" aria-hidden="true">
            <img src="{{ asset('images/logo-ticdce.png') }}" alt="" width="280" height="378">
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title">{{ __('site.home.audience_title') }}</h2>
        <div class="audience">
            @foreach (['clients' => 'M3 3h2l2.4 12.2a2 2 0 002 1.8h8.2a2 2 0 002-1.6L21 8H6', 'investors' => 'M3 17l6-6 4 4 8-8M14 7h7v7', 'partners' => 'M8 12l3 3 5-6M12 21a9 9 0 100-18 9 9 0 000 18z'] as $key => $path)
                <div class="audience-item">
                    <span class="audience-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg></span>
                    <h3>{{ __("site.home.audience.$key.title") }}</h3>
                    <p>{{ __("site.home.audience.$key.text") }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

@if ($featured->isNotEmpty())
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <h2 class="section-title">{{ __('site.home.featured_startups') }}</h2>
            <a href="{{ route('startups.index') }}" class="link-arrow">{{ __('site.home.see_all') }}</a>
        </div>
        <div class="grid grid-3">
            @foreach ($featured as $startup)
                <x-startup-card :startup="$startup" />
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($products->isNotEmpty())
<section class="section">
    <div class="container">
        <div class="section-head">
            <h2 class="section-title">{{ __('site.home.latest_products') }}</h2>
            <a href="{{ route('products.index') }}" class="link-arrow">{{ __('site.home.see_all') }}</a>
        </div>
        <div class="grid grid-4">
            @foreach ($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($sectors->isNotEmpty())
<section class="section section-alt">
    <div class="container">
        <h2 class="section-title">{{ __('site.home.sectors') }}</h2>
        <div class="chips">
            @foreach ($sectors as $sector)
                <a class="chip" href="{{ route('startups.index', ['sector' => $sector->slug]) }}">{{ $sector->tr('name') }} <span>{{ $sector->startups_count }}</span></a>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="section">
    <div class="container">
        <div class="cta">
            <div>
                <h2>{{ __('site.home.join_title') }}</h2>
                <p>{{ __('site.home.join_text') }}</p>
            </div>
            <a href="{{ url('/espace') }}" class="btn btn-accent">{{ __('site.home.join_cta') }}</a>
        </div>
    </div>
</section>
@endsection
