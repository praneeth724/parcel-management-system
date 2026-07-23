@extends('layouts.public')

@section('title', 'Tracking '.$parcel->tracking_number)

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">

            <a href="{{ route('track.index') }}" class="text-decoration-none small text-muted d-inline-flex align-items-center gap-1 mb-3">
                <i class="bi bi-arrow-left"></i> Track another parcel
            </a>

            {{-- Headline status --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="row align-items-center g-4">
                        <div class="col-md-8">
                            <div class="text-muted small text-uppercase fw-semibold">Tracking number</div>
                            <div class="tracking-code fs-4 mb-2">{{ $parcel->tracking_number }}</div>

                            <x-status-badge :status="$parcel->status" class="fs-6" />

                            @if ($parcel->is_overdue)
                                <span class="status-badge status-badge--warning ms-1">
                                    <i class="bi bi-alarm"></i> Running late
                                </span>
                            @endif

                            <div class="row g-3 mt-2">
                                <div class="col-sm-6">
                                    <div class="text-muted small">Booked on</div>
                                    <div class="fw-semibold">{{ $parcel->created_at->format('d M Y, H:i') }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">
                                        {{ $parcel->delivered_at ? 'Delivered on' : 'Expected by' }}
                                    </div>
                                    <div class="fw-semibold">
                                        {{ ($parcel->delivered_at ?? $parcel->expected_delivery_at)?->format('d M Y') ?? '—' }}
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Service</div>
                                    <div class="fw-semibold">{{ $parcel->priority->label() }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted small">Destination</div>
                                    <div class="fw-semibold">{{ $parcel->receiver_city }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 text-center">
                            <div class="qr-frame">{!! $qrSvg !!}</div>
                            <div class="small text-muted mt-2">Scan to reopen this page</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                {{-- Timeline --}}
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0 fw-bold">Shipment history</h6>
                            <small class="text-muted">Newest event first</small>
                        </div>
                        <div class="card-body">
                            @if ($timeline->isEmpty())
                                <x-empty-state icon="bi-clock-history" title="No tracking events recorded yet" />
                            @else
                                <ul class="timeline">
                                    @foreach ($timeline as $event)
                                        <li class="timeline__item">
                                            <span class="timeline__dot text-{{ $event['color'] }}">
                                                <i class="bi {{ $event['icon'] }}"></i>
                                            </span>

                                            <p class="timeline__title">{{ $event['label'] }}</p>

                                            <p class="timeline__meta">
                                                {{ $event['happened_at']->format('d M Y, H:i') }}
                                                @if ($event['location'])
                                                    &middot; <i class="bi bi-geo-alt"></i> {{ $event['location'] }}
                                                @endif
                                            </p>

                                            @if ($event['remarks'])
                                                <p class="timeline__body text-muted">{{ $event['remarks'] }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Shipment summary. Deliberately partial: full addresses and
                     phone numbers are not shown to an anonymous visitor. --}}
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0 fw-bold">Shipment details</h6>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0 small">
                                <dt class="col-5 text-muted fw-normal">Sender</dt>
                                <dd class="col-7 fw-semibold">
                                    {{ $parcel->customer?->company_name ?: Str::mask($parcel->customer?->full_name ?? '—', '*', 3) }}
                                </dd>

                                <dt class="col-5 text-muted fw-normal">From</dt>
                                <dd class="col-7">{{ $parcel->customer?->city ?? '—' }}</dd>

                                <dt class="col-5 text-muted fw-normal">Receiver</dt>
                                <dd class="col-7 fw-semibold">{{ Str::mask($parcel->receiver_name, '*', 3) }}</dd>

                                <dt class="col-5 text-muted fw-normal">To</dt>
                                <dd class="col-7">{{ $parcel->receiver_city }}</dd>

                                <dt class="col-5 text-muted fw-normal">Type</dt>
                                <dd class="col-7">{{ $parcel->parcel_type->label() }}</dd>

                                <dt class="col-5 text-muted fw-normal">Weight</dt>
                                <dd class="col-7">{{ rtrim(rtrim($parcel->weight, '0'), '.') }} kg</dd>

                                @if ($parcel->dimensions)
                                    <dt class="col-5 text-muted fw-normal">Dimensions</dt>
                                    <dd class="col-7">{{ $parcel->dimensions }}</dd>
                                @endif

                                <dt class="col-5 text-muted fw-normal">Payment</dt>
                                <dd class="col-7">
                                    {{ $parcel->payment_method->label() }}
                                    <x-status-badge :status="$parcel->payment_status" class="ms-1" />
                                </dd>

                                <dt class="col-5 text-muted fw-normal">Handled by</dt>
                                <dd class="col-7">{{ $parcel->branch?->name ?? '—' }}</dd>

                                @if ($parcel->delivery_attempts > 0)
                                    <dt class="col-5 text-muted fw-normal">Attempts</dt>
                                    <dd class="col-7">{{ $parcel->delivery_attempts }}</dd>
                                @endif
                            </dl>

                            <hr>

                            <p class="small text-muted mb-0">
                                <i class="bi bi-shield-lock"></i>
                                Names are partly hidden and full addresses are withheld to protect
                                the privacy of the sender and receiver. Sign in as staff to see
                                the complete record.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
