@props(['startup'])
<article class="card startup-card">
    <a href="{{ route('startups.show', $startup) }}" class="card-link">
        <div class="startup-card-cover" @if ($startup->coverUrl()) style="background-image:url('{{ $startup->coverUrl() }}')" @endif></div>
        <div class="card-body">
            <div class="startup-card-head">
                <x-startup-logo :startup="$startup" size="56" />
                <div>
                    <h3 class="card-title" dir="auto">{{ $startup->name }}</h3>
                    @if ($startup->sector)<p class="muted small">{{ $startup->sector->tr('name') }}</p>@endif
                </div>
            </div>
            @if ($startup->tr('tagline'))<p class="card-text" dir="auto">{{ $startup->tr('tagline') }}</p>@endif
            <div class="tags">
                @if ($startup->stage)<span class="tag">{{ $startup->stage->getLabel() }}</span>@endif
                @foreach (array_slice($startup->needs ?? [], 0, 2) as $need)
                    <span class="tag tag-accent">{{ __('site.needs.' . $need) }}</span>
                @endforeach
            </div>
        </div>
    </a>
</article>
