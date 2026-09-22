@props(['title', 'subtitle' => null])
<section class="page-head">
    <div class="container">
        <h1>{{ $title }}</h1>
        @if ($subtitle)<p>{{ $subtitle }}</p>@endif
    </div>
</section>
