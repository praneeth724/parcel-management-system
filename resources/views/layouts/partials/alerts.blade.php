@php
    /**
     * Flash message channels, in the order they should appear.
     * Success/info messages self-dismiss; errors stay until closed.
     */
    $channels = [
        'success' => ['class' => 'success', 'icon' => 'bi-check-circle-fill', 'dismiss' => true],
        'status'  => ['class' => 'info',    'icon' => 'bi-info-circle-fill',  'dismiss' => true],
        'warning' => ['class' => 'warning', 'icon' => 'bi-exclamation-triangle-fill', 'dismiss' => false],
        'error'   => ['class' => 'danger',  'icon' => 'bi-x-octagon-fill',    'dismiss' => false],
    ];
@endphp

@foreach ($channels as $key => $config)
    @if (session()->has($key))
        <div class="alert alert-{{ $config['class'] }} alert-dismissible fade show d-flex align-items-start gap-2"
             role="alert"
             @if ($config['dismiss']) data-auto-dismiss @endif>
            <i class="bi {{ $config['icon'] }} flex-shrink-0 mt-1"></i>
            <div class="flex-grow-1">{{ session($key) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach

{{-- Validation errors that are not tied to a specific field still need to be
     visible, so the whole bag is surfaced on pages without inline errors. --}}
@if ($errors->any() && ! ($hideValidationSummary ?? false))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-x-octagon-fill flex-shrink-0 mt-1"></i>
            <div class="flex-grow-1">
                <strong>Please correct the following:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
@endif
