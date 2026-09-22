@extends('layouts.site', ['title' => __('site.products.title')])

@section('content')
<x-page-head :title="__('site.products.title')" :subtitle="__('site.products.subtitle')" />

<section class="section section-tight">
    <div class="container">
        <nav class="type-tabs" aria-label="{{ __('site.product.type') }}">
            <a href="{{ route('products.index', request()->except(['type', 'page'])) }}" @class(['active' => !request('type')])>{{ __('site.filters.all_types') }}</a>
            @foreach (\App\Enums\ProductType::options() as $value => $label)
                <a href="{{ route('products.index', array_merge(request()->except('page'), ['type' => $value])) }}" @class(['active' => request('type') === $value])>{{ $label }}</a>
            @endforeach
        </nav>

        <form method="GET" class="filters" role="search">
            @if (request('type'))<input type="hidden" name="type" value="{{ request('type') }}">@endif
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
            <button class="btn btn-primary" type="submit">{{ __('site.filters.apply') }}</button>
            @if (request()->hasAny(['q', 'sector', 'type']))
                <a class="btn btn-ghost" href="{{ route('products.index') }}">{{ __('site.filters.reset') }}</a>
            @endif
        </form>

        <p class="muted results-count">{{ trans_choice('site.filters.results', $products->total()) }}</p>

        @if ($products->isEmpty())
            <p class="empty">{{ __('site.filters.empty') }}</p>
        @else
            <div class="grid grid-4">
                @foreach ($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
            {{ $products->links('site.pagination') }}
        @endif
    </div>
</section>
@endsection
