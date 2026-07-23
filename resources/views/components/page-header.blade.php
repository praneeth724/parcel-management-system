@props(['title', 'subtitle' => null, 'back' => null])

<div class="page-header">
    <div>
        @if ($back)
            <a href="{{ $back }}" class="text-decoration-none small text-muted d-inline-flex align-items-center gap-1 mb-1">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        @endif

        <h1>{{ $title }}</h1>

        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="d-flex flex-wrap gap-2 no-print">
            {{ $actions }}
        </div>
    @endisset
</div>
