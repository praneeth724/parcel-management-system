@props([
    'icon' => 'bi-inbox',
    'title' => 'Nothing here yet',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <i class="bi {{ $icon }}"></i>
    <h5>{{ $title }}</h5>

    @if ($message)
        <p>{{ $message }}</p>
    @endif

    {{ $slot }}
</div>
