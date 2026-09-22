@extends('layouts.site', [
    'title' => $startup->name,
    'description' => $startup->tr('tagline') ?? __('site.meta_description'),
    'ogImage' => $startup->coverUrl() ?? $startup->logoUrl(),
])

@php($video = \App\Support\Video::embedUrl($startup->video_url))

@section('content')
<x-flash />

<section class="profile-hero" @if ($startup->coverUrl()) style="--cover:url('{{ $startup->coverUrl() }}')" @endif>
    <div class="container profile-hero-inner">
        <x-startup-logo :startup="$startup" size="112" />
        <div class="profile-hero-text">
            <h1>{{ $startup->name }}</h1>
            @if ($startup->tr('tagline'))<p class="lead" dir="auto">{{ $startup->tr('tagline') }}</p>@endif
            <div class="tags">
                @if ($startup->sector)<span class="tag">{{ $startup->sector->tr('name') }}</span>@endif
                @if ($startup->stage)<span class="tag">{{ $startup->stage->getLabel() }}</span>@endif
                @if ($startup->city)<span class="tag">{{ $startup->city }}</span>@endif
            </div>
        </div>
        @unless ($preview)
            <div class="profile-actions">
                <button type="button" class="btn btn-accent" data-inquiry="contact">{{ __('site.actions.contact') }}</button>
                <button type="button" class="btn btn-light" data-inquiry="investment">{{ __('site.actions.invest') }}</button>
            </div>
        @endunless
    </div>
</section>

<section class="section">
    <div class="container profile-layout">
        <div class="profile-main">
            @if ($startup->tr('description'))
                <h2 class="section-title">{{ __('site.startup.about') }}</h2>
                <div class="prose" dir="auto">{!! nl2br(e($startup->tr('description'))) !!}</div>
            @endif

            @if ($video)
                <h2 class="section-title">{{ __('site.startup.video') }}</h2>
                <div class="video"><iframe src="{{ $video }}" title="{{ $startup->name }}" loading="lazy" allowfullscreen allow="encrypted-media; picture-in-picture"></iframe></div>
            @endif

            <h2 class="section-title">{{ __('site.startup.products') }}</h2>
            @if ($products->isEmpty())
                <p class="muted">{{ __('site.startup.no_products') }}</p>
            @else
                <div class="grid grid-2">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" :show-startup="false" />
                    @endforeach
                </div>
            @endif

            @if (!empty($startup->team))
                <h2 class="section-title">{{ __('site.startup.team') }}</h2>
                <ul class="team">
                    @foreach ($startup->team as $member)
                        <li>
                            @if (!empty($member['photo']))
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($member['photo']) }}" alt="" width="64" height="64" loading="lazy">
                            @else
                                <span class="avatar" aria-hidden="true">{{ mb_substr($member['name'] ?? '?', 0, 1) }}</span>
                            @endif
                            <div><strong>{{ $member['name'] ?? '' }}</strong><span class="muted small">{{ $member['role'] ?? '' }}</span></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <aside class="profile-side">
            @if (!empty($startup->needs))
                <div class="panel panel-accent">
                    <h2 class="panel-title">{{ __('site.startup.needs') }}</h2>
                    <div class="tags">
                        @foreach ($startup->needs as $need)
                            <span class="tag tag-accent">{{ __('site.needs.' . $need) }}</span>
                        @endforeach
                    </div>
                    @if ($startup->tr('needs_details'))<p class="small">{{ $startup->tr('needs_details') }}</p>@endif
                    @unless ($preview)
                        @if (in_array('funding', $startup->needs))
                            <button type="button" class="btn btn-primary btn-block" data-inquiry="investment">{{ __('site.actions.invest') }}</button>
                        @endif
                    @endunless
                </div>
            @endif

            <div class="panel">
                <dl class="facts">
                    @if ($startup->sector)<div><dt>{{ __('site.startup.sector') }}</dt><dd>{{ $startup->sector->tr('name') }}</dd></div>@endif
                    @if ($startup->stage)<div><dt>{{ __('site.startup.stage') }}</dt><dd>{{ $startup->stage->getLabel() }}</dd></div>@endif
                    @if ($startup->founded_year)<div><dt>{{ __('site.startup.founded') }}</dt><dd>{{ $startup->founded_year }}</dd></div>@endif
                    @if ($startup->city)<div><dt>{{ __('site.startup.city') }}</dt><dd>{{ $startup->city }}</dd></div>@endif
                    @if ($startup->website)<div><dt>{{ __('site.startup.website') }}</dt><dd><a href="{{ $startup->website }}" rel="noopener nofollow" target="_blank" dir="ltr">{{ preg_replace('~^https?://(www\.)?~', '', rtrim($startup->website, '/')) }}</a></dd></div>@endif
                </dl>
                @php($socials = array_filter(['Facebook' => $startup->facebook, 'LinkedIn' => $startup->linkedin, 'Instagram' => $startup->instagram]))
                @if ($socials)
                    <div class="socials">
                        @foreach ($socials as $label => $url)
                            <a href="{{ $url }}" rel="noopener nofollow" target="_blank">{{ $label }}</a>
                        @endforeach
                    </div>
                @endif
                @unless ($preview)
                    <button type="button" class="btn btn-outline btn-block" data-inquiry="contact">{{ __('site.actions.contact') }}</button>
                @endunless
            </div>
        </aside>
    </div>
</section>

@unless ($preview)
    <x-inquiry-dialog :startup="$startup" />
@endunless
@endsection
