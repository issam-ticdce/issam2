@props(['startup', 'size' => 56])
@if ($startup->logoUrl())
    <img src="{{ $startup->logoUrl() }}" alt="{{ $startup->name }}" class="startup-logo" width="{{ $size }}" height="{{ $size }}" style="width:{{ $size }}px;height:{{ $size }}px" loading="lazy">
@else
    <span class="startup-logo startup-logo-fallback" style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ $size / 2.6 }}px" aria-hidden="true">{{ $startup->initials() }}</span>
@endif
