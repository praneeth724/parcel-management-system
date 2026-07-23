@props(['status'])

{{--
    Renders any of the domain enums (ParcelStatus, DeliveryStatus, UserRole, …)
    as a pill. Every one of them exposes label() and color(); icon() is optional,
    so it is only called when the enum actually defines it.
--}}

@php
    $hasIcon = method_exists($status, 'icon');
@endphp

<span {{ $attributes->merge(['class' => 'status-badge status-badge--'.$status->color()]) }}>
    @if ($hasIcon)
        <i class="bi {{ $status->icon() }}"></i>
    @endif
    {{ $status->label() }}
</span>
