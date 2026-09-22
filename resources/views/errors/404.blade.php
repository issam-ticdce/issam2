@php
    if (! in_array(app()->getLocale(), config('ticdce.locales'))) app()->setLocale(config('ticdce.locales')[0]);
    \Illuminate\Support\Facades\URL::defaults(['locale' => app()->getLocale()]);
@endphp
@extends('layouts.site', ['title' => __('site.not_found')])

@section('content')
<section class="section">
    <div class="container empty-page">
        <p class="eyebrow">404</p>
        <h1>{{ __('site.not_found') }}</h1>
        <a class="btn btn-primary" href="{{ route('home') }}">{{ __('site.back_home') }}</a>
    </div>
</section>
@endsection
