@props([
    'label',
    'value',
    'icon' => 'bi-graph-up',
    'variant' => 'primary',
    'meta' => null,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => 'stat-card stat-card--'.$variant.($href ? ' text-decoration-none text-reset' : ''),
    ]) }}
>
    <div class="stat-card__icon">
        <i class="bi {{ $icon }}"></i>
    </div>

    <div class="min-w-0">
        <div class="stat-card__label">{{ $label }}</div>
        <div class="stat-card__value">{{ $value }}</div>
        @if ($meta)
            <div class="stat-card__meta">{{ $meta }}</div>
        @endif
    </div>
</{{ $tag }}>
