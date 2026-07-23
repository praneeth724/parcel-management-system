@extends('layouts.app')

@section('title', 'Delivery '.$delivery->parcel?->tracking_number)

@php $parcel = $delivery->parcel; @endphp

@section('content')
    <x-page-header :title="'Delivery '.$parcel?->tracking_number"
                   :subtitle="'Attempt '.$delivery->attempt_number.' · assigned to '.$delivery->driver?->full_name"
                   :back="route('deliveries.index')">
        <x-slot:actions>
            <a href="{{ route('parcels.show', $parcel) }}" class="btn btn-outline-secondary">
                <i class="bi bi-box-seam me-1"></i> View parcel
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-7">
            {{-- Status + driver actions --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                        <x-status-badge :status="$delivery->status" class="fs-6" />
                        @if ($parcel)
                            <x-status-badge :status="$parcel->status" />
                            <x-status-badge :status="$parcel->priority" />
                        @endif
                    </div>

                    @can('accept', $delivery)
                        <div class="alert alert-primary">
                            <strong>New assignment.</strong>
                            Accept it to take responsibility for this parcel, or decline so the
                            dispatcher can hand it to someone else.
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('deliveries.accept', $delivery) }}">
                                @csrf
                                <button class="btn btn-success">
                                    <i class="bi bi-hand-thumbs-up me-1"></i> Accept delivery
                                </button>
                            </form>

                            <button type="button" class="btn btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bi bi-hand-thumbs-down me-1"></i> Decline
                            </button>
                        </div>
                    @endcan

                    @if ($delivery->status === App\Enums\DeliveryStatus::Accepted)
                        @can('update', $delivery)
                            <div class="alert alert-info">
                                You have accepted this delivery. Mark it in transit once the parcel
                                is on your vehicle — that also updates the customer's tracking page.
                            </div>

                            <form method="POST" action="{{ route('deliveries.in-transit', $delivery) }}" class="d-flex gap-2">
                                @csrf
                                <input type="text" name="location" class="form-control"
                                       placeholder="Current location (optional)" style="max-width: 20rem;">
                                <button class="btn btn-warning">
                                    <i class="bi bi-truck me-1"></i> Mark out for delivery
                                </button>
                            </form>
                        @endcan
                    @endif

                    @can('complete', $delivery)
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#completeModal">
                                <i class="bi bi-check2-circle me-1"></i> Complete delivery
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#failModal">
                                <i class="bi bi-x-octagon me-1"></i> Could not deliver
                            </button>
                        </div>
                    @endcan

                    @can('reassign', $delivery)
                        @if ($delivery->status->isOpen())
                            <div class="mt-3 pt-3 border-top">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#cancelAssignmentModal">
                                    <i class="bi bi-arrow-repeat me-1"></i> Pull back and reassign
                                </button>
                            </div>
                        @endif
                    @endcan

                    @if ($delivery->rejection_reason)
                        <div class="alert alert-danger mt-3 mb-0 small">
                            <strong>Declined:</strong> {{ $delivery->rejection_reason }}
                        </div>
                    @endif

                    @if ($delivery->failure_reason)
                        <div class="alert alert-danger mt-3 mb-0 small">
                            <strong>Delivery failed:</strong> {{ $delivery->failure_reason }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Delivery detail --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt text-danger"></i> Delivery address</h6>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-4 text-muted fw-normal">Receiver</dt>
                        <dd class="col-8 fw-semibold">{{ $parcel?->receiver_name }}</dd>

                        <dt class="col-4 text-muted fw-normal">Phone</dt>
                        <dd class="col-8">
                            <a href="tel:{{ $parcel?->receiver_phone }}" class="text-decoration-none">
                                <i class="bi bi-telephone"></i> {{ $parcel?->receiver_phone }}
                            </a>
                        </dd>

                        <dt class="col-4 text-muted fw-normal">Address</dt>
                        <dd class="col-8">{{ $parcel?->receiver_full_address }}</dd>

                        <dt class="col-4 text-muted fw-normal">Pickup from</dt>
                        <dd class="col-8">{{ $parcel?->pickup_address }}</dd>

                        <dt class="col-4 text-muted fw-normal">Payment</dt>
                        <dd class="col-8">
                            {{ $parcel?->payment_method->label() }}
                            @if ($parcel?->payment_method->isCollectedOnDelivery())
                                <span class="badge text-bg-warning">
                                    Collect @money($parcel->cod_amount ?: $parcel->delivery_charge)
                                </span>
                            @endif
                        </dd>

                        @if ($parcel?->special_instructions)
                            <dt class="col-4 text-muted fw-normal">Instructions</dt>
                            <dd class="col-8 text-warning-emphasis">{{ $parcel->special_instructions }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Proof of delivery, once completed --}}
            @if ($delivery->has_proof_of_delivery || $delivery->completed_at)
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-patch-check text-success"></i> Proof of delivery</h6>
                    </div>
                    <div class="card-body">
                        <dl class="row small">
                            <dt class="col-4 text-muted fw-normal">Received by</dt>
                            <dd class="col-8 fw-semibold">{{ $delivery->received_by ?? '—' }}</dd>

                            @if ($delivery->receiver_nic)
                                <dt class="col-4 text-muted fw-normal">Receiver NIC</dt>
                                <dd class="col-8">{{ $delivery->receiver_nic }}</dd>
                            @endif

                            <dt class="col-4 text-muted fw-normal">Delivered at</dt>
                            <dd class="col-8">{{ $delivery->completed_at?->format('d M Y, H:i') ?? '—' }}</dd>

                            <dt class="col-4 text-muted fw-normal">Location</dt>
                            <dd class="col-8">{{ $delivery->delivery_location ?? '—' }}</dd>

                            @if ($delivery->cod_collected !== null)
                                <dt class="col-4 text-muted fw-normal">Cash collected</dt>
                                <dd class="col-8">@money($delivery->cod_collected)</dd>
                            @endif
                        </dl>

                        <div class="row g-3">
                            @if ($delivery->signature_url)
                                <div class="col-md-6">
                                    <div class="small text-muted mb-1">Receiver's signature</div>
                                    <img src="{{ $delivery->signature_url }}" alt="Signature"
                                         class="img-fluid border rounded bg-white">
                                </div>
                            @endif

                            @if ($delivery->proof_image_url)
                                <div class="col-md-6">
                                    <div class="small text-muted mb-1">Delivery photo</div>
                                    <a href="{{ $delivery->proof_image_url }}" target="_blank">
                                        <img src="{{ $delivery->proof_image_url }}" alt="Delivery photo"
                                             class="img-fluid border rounded">
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">Assignment</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="{{ $delivery->driver?->photo_url }}" alt="" class="avatar avatar--md">
                        <div>
                            <a href="{{ route('drivers.show', $delivery->driver) }}" class="text-decoration-none fw-semibold">
                                {{ $delivery->driver?->full_name }}
                            </a>
                            <div class="small text-muted">
                                {{ $delivery->driver?->driver_code }} · {{ $delivery->driver?->vehicle_number }}
                            </div>
                        </div>
                    </div>

                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted fw-normal">Assigned by</dt>
                        <dd class="col-7">{{ $delivery->assignedBy?->name ?? 'System' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Assigned at</dt>
                        <dd class="col-7">{{ $delivery->assigned_at?->format('d M Y, H:i') }}</dd>

                        <dt class="col-5 text-muted fw-normal">Accepted</dt>
                        <dd class="col-7">{{ $delivery->accepted_at?->format('d M Y, H:i') ?? '—' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Picked up</dt>
                        <dd class="col-7">{{ $delivery->picked_up_at?->format('d M Y, H:i') ?? '—' }}</dd>

                        <dt class="col-5 text-muted fw-normal">Duration</dt>
                        <dd class="col-7">{{ $delivery->duration_for_humans }}</dd>

                        <dt class="col-5 text-muted fw-normal">Attempt</dt>
                        <dd class="col-7">{{ $delivery->attempt_number }}</dd>
                    </dl>

                    @if ($delivery->notes)
                        <hr>
                        <p class="small text-muted mb-0"><i class="bi bi-sticky"></i> {{ $delivery->notes }}</p>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">Parcel</h6>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted fw-normal">Tracking number</dt>
                        <dd class="col-7 tracking-code">{{ $parcel?->tracking_number }}</dd>

                        <dt class="col-5 text-muted fw-normal">Sender</dt>
                        <dd class="col-7">{{ $parcel?->customer?->full_name }}</dd>

                        <dt class="col-5 text-muted fw-normal">Type</dt>
                        <dd class="col-7">{{ $parcel?->parcel_type->label() }}</dd>

                        <dt class="col-5 text-muted fw-normal">Weight</dt>
                        <dd class="col-7">{{ $parcel ? rtrim(rtrim($parcel->weight, '0'), '.') : '—' }} kg</dd>

                        <dt class="col-5 text-muted fw-normal">Branch</dt>
                        <dd class="col-7">{{ $parcel?->branch?->name }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    {{-- ---------------- Modals ---------------- --}}

    @can('reject', $delivery)
        <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('deliveries.reject', $delivery) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Decline this delivery</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <x-form.field name="rejection_reason"
                                      type="textarea"
                                      label="Why can't you take this delivery?"
                                      :rows="3"
                                      :required="true"
                                      placeholder="e.g. Vehicle breakdown, or the route is outside my area." />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Decline delivery</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @can('complete', $delivery)
        {{-- Completion, with signature pad and proof photo (bonus features) --}}
        <div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST"
                      action="{{ route('deliveries.complete', $delivery) }}"
                      enctype="multipart/form-data"
                      id="completeForm"
                      class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Complete this delivery</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <x-form.field name="received_by"
                                              label="Received by"
                                              :value="$parcel?->receiver_name"
                                              :required="true"
                                              help="Name of the person who actually took the parcel." />
                            </div>
                            <div class="col-md-6">
                                <x-form.field name="receiver_nic"
                                              label="Receiver NIC"
                                              placeholder="Optional" />
                            </div>
                            <div class="col-md-6">
                                <x-form.field name="delivery_location"
                                              label="Delivery location"
                                              :value="$parcel?->receiver_full_address" />
                            </div>
                            <div class="col-md-6">
                                @if ($parcel?->payment_method->isCollectedOnDelivery())
                                    <x-form.field name="cod_collected"
                                                  type="number"
                                                  label="Cash collected"
                                                  step="0.01"
                                                  min="0"
                                                  :value="$parcel->cod_amount ?: $parcel->delivery_charge"
                                                  :prefix="config('courier.pricing.currency_symbol')" />
                                @endif
                            </div>
                        </div>

                        {{-- Signature pad --}}
                        <div class="mb-3">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                <span>Receiver's signature</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSignature">
                                    <i class="bi bi-eraser"></i> Clear
                                </button>
                            </label>
                            <canvas id="signaturePad" class="signature-pad"></canvas>
                            <input type="hidden" name="signature" id="signatureData">
                            <div class="form-text">Optional — ask the receiver to sign with a finger or mouse.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <label for="proof_image" class="form-label">Delivery photo</label>
                                <input type="file" name="proof_image" id="proof_image" accept="image/*"
                                       class="form-control" data-preview="#proofPreview">
                                <div class="form-text">Optional — a photo of the parcel at the door.</div>
                                <img id="proofPreview" class="img-fluid rounded border mt-2 d-none" alt="">
                            </div>
                            <div class="col-md-6">
                                <x-form.field name="notes"
                                              type="textarea"
                                              label="Notes"
                                              :rows="4"
                                              placeholder="Optional" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i> Mark as delivered
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="failModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('deliveries.fail', $delivery) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Delivery could not be completed</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning small">
                            After {{ config('courier.delivery.max_attempts') }} failed attempts the parcel is
                            automatically returned to the sender.
                            This parcel has had {{ $parcel?->delivery_attempts }} so far.
                        </div>

                        <x-form.field name="failure_reason"
                                      type="textarea"
                                      label="What went wrong?"
                                      :rows="3"
                                      :required="true"
                                      placeholder="e.g. Receiver not available at the address." />

                        <x-form.field name="location"
                                      label="Where were you?"
                                      :value="$parcel?->receiver_city" />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Record failed attempt</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @can('reassign', $delivery)
        @if ($delivery->status->isOpen())
            <div class="modal fade" id="cancelAssignmentModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('deliveries.cancel', $delivery) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Pull this parcel back</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">
                                The assignment is closed, the driver is freed, and the parcel returns
                                to the unassigned pool so it can be given to someone else.
                            </p>

                            <x-form.field name="reason"
                                          type="textarea"
                                          label="Reason"
                                          :rows="3"
                                          :required="true" />
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">Pull back</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
@endsection

@push('scripts')
@can('complete', $delivery)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('signaturePad');
    if (!canvas) return;

    let pad = null;

    // The canvas has zero size until the modal is shown, so it must be sized
    // (and the pad created) at that point rather than on page load.
    const resize = () => {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        pad?.clear();
    };

    document.getElementById('completeModal')?.addEventListener('shown.bs.modal', () => {
        if (!pad) {
            pad = new window.SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(15, 23, 42)',
            });
        }
        resize();
    });

    window.addEventListener('resize', () => { if (pad) resize(); });

    document.getElementById('clearSignature')?.addEventListener('click', () => pad?.clear());

    // Serialise the drawing into the hidden field just before submitting.
    document.getElementById('completeForm')?.addEventListener('submit', () => {
        if (pad && !pad.isEmpty()) {
            document.getElementById('signatureData').value = pad.toDataURL('image/png');
        }
    });
});
</script>
@endcan
@endpush
