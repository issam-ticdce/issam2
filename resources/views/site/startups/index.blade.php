@extends('layouts.site', ['title' => __('site.startups.title')])

@section('content')
<x-page-head :title="__('site.startups.title')" :subtitle="__('site.startups.subtitle')" />

<section class="section section-tight">
    <div class="container">
        <form method="GET" class="filters" role="search">
            <label class="field field-grow">
                <span class="sr-only">{{ __('site.filters.search') }}</span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('site.filters.search') }}">
            </label>
            <label class="field">
                <span class="sr-only">{{ __('site.startup.sector') }}</span>
                <select name="sector" data-autosubmit>
                    <option value="">{{ __('site.filters.all_sectors') }}</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->slug }}" @selected(request('sector') === $sector->slug)>{{ $sector->tr('name') }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="sr-only">{{ __('site.startup.stage') }}</span>
                <select name="stage" data-autosubmit>
                    <option value="">{{ __('site.filters.all_stages') }}</option>
                    @foreach (\App\Enums\Stage::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('stage') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="sr-only">{{ __('site.startup.needs') }}</span>
                <select name="need" data-autosubmit>
                    <option value="">{{ __('site.filters.all_needs') }}</option>
                    @foreach (\App\Enums\Need::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('need') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn btn-primary" type="submit">{{ __('site.filters.apply') }}</button>
            @if (request()->hasAny(['q', 'sector', 'stage', 'need']))
                <a class="btn btn-ghost" href="{{ route('startups.index') }}">{{ __('site.filters.reset') }}</a>
            @endif
        </form>

        <p class="muted results-count">{{ trans_choice('site.filters.results', $startups->total()) }}</p>

        @if ($startups->isEmpty())
            <p class="empty">{{ __('site.filters.empty') }}</p>
        @else
            <div class="grid grid-3">
                @foreach ($startups as $startup)
                    <x-startup-card :startup="$startup" />
                @endforeach
            </div>
            {{ $startups->links('site.pagination') }}
        @endif
    </div>
</section>
@endsection
