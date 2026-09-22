@php
    $locale = app()->getLocale();
    $isRtl = in_array($locale, config('ticdce.rtl_locales'));
    $route = request()->route();
    $switchUrl = function (string $target) use ($route) {
        if ($route && $route->getName() && in_array('locale', $route->parameterNames())) {
            return route($route->getName(), array_merge($route->originalParameters(), ['locale' => $target])) . (request()->getQueryString() ? '?' . request()->getQueryString() : '');
        }
        return request()->fullUrlWithQuery(['lang' => $target]);
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title . ' · ' : '' }}TICDCE Marketplace</title>
    <meta name="description" content="{{ $description ?? __('site.meta_description') }}">
    <meta property="og:title" content="{{ $title ?? 'TICDCE Marketplace' }}">
    <meta property="og:description" content="{{ $description ?? __('site.meta_description') }}">
    @isset($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endisset
    @if ($route && in_array('locale', $route->parameterNames()))
        @foreach (config('ticdce.locales') as $alt)
            <link rel="alternate" hreflang="{{ $alt }}" href="{{ $switchUrl($alt) }}">
        @endforeach
    @endif
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
</head>
<body>
    <a class="skip-link" href="#main">{{ __('site.nav.home') }}</a>

    @if (!empty($preview))
        <div class="preview-banner">{{ __('site.preview') }}</div>
    @endif

    <header class="site-header">
        <div class="container header-inner">
            <a href="{{ route('home') }}" class="brand" aria-label="TICDCE">
                <img src="{{ asset('images/logo-mark.png') }}" alt="" width="40" height="33">
                <span class="brand-text"><strong>TICDCE</strong><small>Marketplace</small></span>
            </a>

            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-menu-toggle>
                <span class="sr-only">{{ __('site.nav.menu') }}</span>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>

            <nav id="site-nav" class="site-nav">
                <a href="{{ route('home') }}" @class(['active' => request()->routeIs('home')])>{{ __('site.nav.home') }}</a>
                <a href="{{ route('startups.index') }}" @class(['active' => request()->routeIs('startups.*')])>{{ __('site.nav.startups') }}</a>
                <a href="{{ route('products.index') }}" @class(['active' => request()->routeIs('products.*')])>{{ __('site.nav.products') }}</a>
                <div class="lang-switch" role="group" aria-label="{{ __('site.nav.language') }}">
                    @foreach (config('ticdce.locale_names') as $code => $name)
                        <a href="{{ $switchUrl($code) }}" lang="{{ $code }}" hreflang="{{ $code }}" @class(['current' => $code === $locale]) title="{{ $name }}">{{ $code === 'ar' ? 'ع' : strtoupper($code) }}</a>
                    @endforeach
                </div>
                <a href="{{ url('/espace') }}" class="btn btn-outline btn-sm">{{ __('site.nav.space') }}</a>
            </nav>
        </div>
    </header>

    <main id="main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <div class="footer-brand">
                <img src="{{ asset('images/logo-ticdce.png') }}" alt="TICDCE" width="120" height="162" loading="lazy">
                <div>
                    <p class="footer-name">{{ __('site.ticdce_full') }}</p>
                    <p>{{ __('site.footer.about') }}</p>
                </div>
            </div>
            <div>
                <h2 class="footer-title">{{ __('site.footer.contact') }}</h2>
                <ul class="footer-list">
                    <li><a href="mailto:{{ config('ticdce.contact.email') }}">{{ config('ticdce.contact.email') }}</a></li>
                    @if (config('ticdce.contact.phone'))<li dir="ltr">{{ config('ticdce.contact.phone') }}</li>@endif
                    @if (config('ticdce.contact.address'))<li>{{ config('ticdce.contact.address') }}</li>@endif
                    @if (config('ticdce.contact.website'))<li><a href="{{ config('ticdce.contact.website') }}" rel="noopener">{{ parse_url(config('ticdce.contact.website'), PHP_URL_HOST) }}</a></li>@endif
                </ul>
            </div>
            <div>
                <h2 class="footer-title">Marketplace</h2>
                <ul class="footer-list">
                    <li><a href="{{ route('startups.index') }}">{{ __('site.nav.startups') }}</a></li>
                    <li><a href="{{ route('products.index') }}">{{ __('site.nav.products') }}</a></li>
                    <li><a href="{{ url('/espace') }}">{{ __('site.nav.space') }}</a></li>
                </ul>
            </div>
        </div>
        <div class="container footer-bottom">© {{ date('Y') }} TICDCE. {{ __('site.footer.rights') }}</div>
    </footer>

    <script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}" defer></script>
</body>
</html>
