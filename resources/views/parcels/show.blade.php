@extends('layouts.app')

@section('title', $parcel->tracking_number)

@section('content')
    <x-page-header :title="$parcel->tracking_number"
                   :subtitle="$parcel->customer?->full_name.' → '.$parcel->receiver_name.', '.$parcel->receiver_city"
                   :back="route('parcels.index')">
        <x-slot:actions>
            @can('printLabel', $parcel)
                <a href="{{ route('parcels.label', $parcel) }}" target="_blank" class="btn btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i> Label
                </a>
                <a href="{{ route('parcels.label.pdf', $parcel) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                </a>
            @endcan

            @can('update', $parcel)
                <a href="{{ route('parcels.edit', $parcel) }}" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan

            @can('cancel', $parcel)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <i class="bi bi-x-circle me-1"></i> Cancel
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Status banner --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <x-status-badge :status="$parcel->status" class="fs-6" />
                <x-status-badge :status="$parcel->priority" />
                <x-status-badge :status="$parcel->payment_status" />

                @if ($parcel->is_overdue)
                    <span class="status-badge status-badge--warning">
                        <i class="bi bi-alarm"></i> Overdue
                    </span>
                @endif

                <div class="ms-auto text-end small text-muted">
                    <div>Booked {{ $parcel->created_at->format('d M Y, H:i') }} by {{ $parcel->creator?->name ?? 'System' }}</div>
                    @if ($parcel->expected_delivery_at)
                        <div>
                            {{ $parcel->delivered_at ? 'Delivered' : 'Expected' }}
                            {{ ($parcel->delivered_at ?? $parcel->expected_delivery_at)->format('d M Y') }}
                        </div>
                    @endif
                </div>
            </div>

            @if ($parcel->status === App\Enums\ParcelStatus::Cancelled && $parcel->cancellation_reason)
                <div class="alert alert-secondary mt-3 mb-0 small">
                    <strong>Cancelled:</strong> {{ $parcel->cancellation_reason }}
                    <span class="text-muted">({{ $parcel->cancelled_at?->format('d M Y, H:i') }})</span>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-4">
        {{-- Left: details --}}
        <div class="col-lg-8">
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-person text-primary"></i> Sender</h6>
                        </div>
                        <div class="card-body">
                            <dl class="row small mb-0">
                                <dt class="col-5 text-muted fw-normal">Customer</dt>
                                <dd class="col-7">
                                    <a href="{{ route('customers.show', $parcel->customer) }}" class="text-decoration-none fw-semibold">
                                        {{ $parcel->customer?->full_name }}
                                    </a>
                                    <div><small class="text-muted">{{ $parcel->customer?->customer_code }}</small></div>
                                </dd>

                                @if ($parcel->customer?->company_name)
                                    <dt class="col-5 text-muted fw-normal">Company</dt>
                                    <dd class="col-7">{{ $parcel->customer->company_name }}</dd>
                                @endif

                                <dt class="col-5 text-muted fw-normal">Mobile</dt>
                                <dd class="col-7">
                                    <a href="tel:{{ $parcel->customer?->mobile }}" class="text-decoration-none">
                                        {{ $parcel->customer?->mobile }}
                                    </a>
                                </dd>

                                <dt class="col-5 text-muted fw-normal">Pickup from</dt>
                                <dd class="col-7">{{ $parcel->pickup_address }}</dd>

                                <dt class="col-5 text-muted fw-normal">Branch</dt>
                                <dd class="col-7">{{ $parcel->branch?->name ?? '—' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt text-danger"></i> Receiver</h6>
                        </div>
                        <div class="card-body">
                            <dl class="row small mb-0">
                                <dt class="col-5 text-muted fw-normal">Name</dt>
                                <dd class="col-7 fw-semibold">{{ $parcel->receiver_name }}</dd>

                                <dt class="col-5 text-muted fw-normal">Phone</dt>
                                <dd class="col-7">
                                    <a href="tel:{{ $parcel->receiver_phone }}" class="text-decoration-none">
                                        {{ $parcel->receiver_phone }}
                                    </a>
                                </dd>

                                <dt class="col-5 text-muted fw-normal">Address</dt>
                                <dd class="col-7">{{ $parcel->receiver_full_address }}</dd>

                                @if ($parcel->special_instructions)
                                    <dt class="col-5 text-muted fw-normal">Instructions</dt>
                                    <dd class="col-7 text-warning-emphasis">{{ $parcel->special_instructions }}</dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Parcel & charges --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam text-warning"></i> Parcel &amp; charges</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 small">
                        <div class="col-6 col-md-3">
                            <div class="text-muted">Type</div>
                            <div class="fw-semibold">{{ $parcel->parcel_type->label() }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted">Weight</div>
                            <div class="fw-semibold">{{ rtrim(rtrim($parcel->weight, '0'), '.') }} kg</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted">Dimensions</div>
                            <div class="fw-semibold">{{ $parcel->dimensions ?? '—' }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted">Chargeable weight</div>
                            <div class="fw-semibold">{{ $parcel->chargeable_weight }} kg</div>
                        </div>

                        @can('view-revenue')
                            <div class="col-6 col-md-3">
                                <div class="text-muted">Delivery charge</div>
                                <div class="fw-semibold">@money($parcel->delivery_charge)</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-muted">Cash to collect</div>
                                <div class="fw-semibold">
                                    {{ $parcel->cod_amount > 0 ? 'Rs. '.number_format($parcel->cod_amount, 2) : '—' }}
                                </div>
                            </div>
                        @endcan

                        <div class="col-6 col-md-3">
                            <div class="text-muted">Payment method</div>
                            <div class="fw-semibold">{{ $parcel->payment_method->label() }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted">Delivery attempts</div>
                            <div class="fw-semibold">{{ $parcel->delivery_attempts }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tracking timeline --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history text-info"></i> Tracking history</h6>
                        <small class="text-muted">{{ $parcel->trackings->count() }} events recorded</small>
                    </div>

                    @can('addTracking', $parcel)
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#trackingModal">
                            <i class="bi bi-plus-lg"></i> Log event
                        </button>
                    @endcan
                </div>
                <div class="card-body">
                    @if ($parcel->trackings->isEmpty())
                        <x-empty-state icon="bi-clock-history" title="No tracking events yet" />
                    @else
                        <ul class="timeline">
                            @foreach ($parcel->trackings->sortByDesc('happened_at') as $event)
                                <li class="timeline__item">
                                    <span class="timeline__dot text-{{ $event->status->color() }}">
                                        <i class="bi {{ $event->status->icon() }}"></i>
                                    </span>

                                    <p class="timeline__title">{{ $event->status->label() }}</p>

                                    <p class="timeline__meta">
                                        {{ $event->happened_at->format('d M Y, H:i') }}
                                        @if ($event->location)
                                            &middot; <i class="bi bi-geo-alt"></i> {{ $event->location }}
                                        @endif
                                        &middot; by {{ $event->actor_name }}
                                    </p>

                                    @if ($event->remarks)
                                        <p class="timeline__body text-muted">{{ $event->remarks }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Delivery assignments --}}
            <div class="table-card">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center p-3">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-truck text-success"></i> Delivery assignments</h6>
                        <small class="text-muted">Every driver this parcel has been given to</small>
                    </div>

                    @can('assignDriver', $parcel)
                        @if ($assignableDrivers->isNotEmpty())
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
                                <i class="bi bi-person-check"></i> Assign driver
                            </button>
                        @endif
                    @endcan
                </div>

                @if ($parcel->deliveries->isEmpty())
                    <x-empty-state icon="bi-truck"
                                   title="Not assigned yet"
                                   message="This parcel is waiting for a driver." />
                @else
                    <div class="table-card__scroll">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Driver</th>
                                    <th>Status</th>
                                    <th>Assigned</th>
                                    <th>Closed</th>
                                    <th class="text-end">Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($parcel->deliveries as $delivery)
                                    <tr>
                                        <td class="text-muted">{{ $delivery->attempt_number }}</td>
                                        <td>
                                            <a href="{{ route('drivers.show', $delivery->driver) }}" class="text-decoration-none fw-semibold small">
                                                {{ $delivery->driver?->full_name }}
                                            </a>
                                            <div><small class="text-muted">{{ $delivery->driver?->vehicle_number }}</small></div>
                                        </td>
                                        <td>
                                            <x-status-badge :status="$delivery->status" />
                                            @if ($delivery->failure_reason || $delivery->rejection_reason)
                                                <div>
                                                    <small class="text-muted">
                                                        {{ $delivery->failure_reason ?? $delivery->rejection_reason }}
                                                    </small>
                                                </div>
                                            @endif
                                        </td>
                                        <td><small class="text-muted">{{ $delivery->assigned_at?->format('d M H:i') }}</small></td>
                                        <td>
                                            <small class="text-muted">
                                                {{ ($delivery->completed_at ?? $delivery->failed_at ?? $delivery->rejected_at)?->format('d M H:i') ?? '—' }}
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right: QR + photos --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-qr-code"></i> QR code</h6>
                </div>
                <div class="card-body text-center">
                    <div class="qr-frame mb-3">{!! $qrSvg !!}</div>
                    <p class="small text-muted mb-2">
                        Scanning opens the public tracking page, so the status shown is always current.
                    </p>
                    <a href="{{ route('track.show', $parcel->tracking_number) }}"
                       target="_blank"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right"></i> Open public page
                    </a>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-camera"></i> Parcel photos</h6>
                    <small class="text-muted">{{ $parcel->images->count() }} of {{ $maxImages }}</small>
                </div>
                <div class="card-body">
                    @if ($parcel->images->isEmpty())
                        <p class="small text-muted text-center mb-3">No photos uploaded yet.</p>
                    @else
                        <div class="row g-2 mb-3">
                            @foreach ($parcel->images as $image)
                                <div class="col-6">
                                    <div class="position-relative">
                                        <a href="{{ $image->url }}" target="_blank">
                                            <img src="{{ $image->url }}"
                                                 alt="{{ $image->original_name }}"
                                                 class="img-fluid rounded border"
                                                 style="aspect-ratio: 1; object-fit: cover;">
                                        </a>

                                        @can('uploadImages', $parcel)
                                            <form method="POST"
                                                  action="{{ route('parcels.images.destroy', $image) }}"
                                                  class="position-absolute top-0 end-0 m-1"
                                                  data-confirm="Remove this photo?">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger py-0 px-1" title="Remove">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                    <small class="text-muted d-block text-truncate">{{ $image->human_size }}</small>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @can('uploadImages', $parcel)
                        @if ($parcel->images->count() < $maxImages)
                            <form method="POST" action="{{ route('parcels.images.store', $parcel) }}" enctype="multipart/form-data">
                                @csrf
                                <input type="file" name="images[]" multiple accept="image/*" class="form-control form-control-sm mb-2">
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-upload"></i> Upload photos
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>

    {{-- ---------------- Modals ---------------- --}}

    @can('addTracking', $parcel)
        <div class="modal fade" id="trackingModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('parcels.tracking.store', $parcel) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Log a tracking event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <x-form.field name="status"
                                      label="Event"
                                      :options="$trackingOptions"
                                      placeholder="— Select an event —"
                                      :required="true"
                                      help="Events that imply a status change will move the parcel forward." />

                        <x-form.field name="location"
                                      label="Location"
                                      :placeholder="$parcel->branch?->city ?? 'Where did this happen?'" />

                        <x-form.field name="remarks"
                                      type="textarea"
                                      label="Remarks"
                                      :rows="2"
                                      placeholder="Optional note shown on the public tracking page" />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record event</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @can('assignDriver', $parcel)
        @if ($assignableDrivers->isNotEmpty())
            <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('deliveries.store') }}" class="modal-content">
                        @csrf
                        <input type="hidden" name="parcel_id" value="{{ $parcel->id }}">

                        <div class="modal-header">
                            <h5 class="modal-title">Assign a driver</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-light small">
                                This parcel weighs <strong>{{ $parcel->chargeable_weight }} kg</strong>.
                                A driver whose vehicle cannot carry it will be rejected.
                            </div>

                            <div class="mb-3">
                                <label for="driver_id" class="form-label required">Driver</label>
                                <select name="driver_id" id="driver_id" class="form-select" required>
                                    <option value="">— Select an available driver —</option>
                                    @foreach ($assignableDrivers as $driver)
                                        <option value="{{ $driver->id }}"
                                                @disabled($parcel->chargeable_weight > $driver->vehicle_type->capacityKg())>
                                            {{ $driver->full_name }} ({{ $driver->vehicle_type->label() }},
                                            up to {{ number_format($driver->vehicle_type->capacityKg()) }} kg)
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <x-form.field name="notes"
                                          type="textarea"
                                          label="Note for the driver"
                                          :rows="2"
                                          placeholder="Optional" />
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Assign</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan

    @can('cancel', $parcel)
        <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('parcels.cancel', $parcel) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Cancel this parcel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning small">
                            <i class="bi bi-exclamation-triangle"></i>
                            Cancelling releases any driver holding this parcel and, if it was already
                            paid for, marks the payment as refunded. This cannot be undone.
                        </div>

                        <x-form.field name="cancellation_reason"
                                      type="textarea"
                                      label="Reason for cancelling"
                                      :rows="3"
                                      :required="true"
                                      placeholder="This appears on the public tracking page." />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep parcel</button>
                        <button type="submit" class="btn btn-danger">Cancel parcel</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection
