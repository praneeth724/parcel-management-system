@extends('layouts.app')

@section('title', 'Edit '.$parcel->tracking_number)

@section('content')
    <x-page-header :title="'Edit '.$parcel->tracking_number"
                   :subtitle="'Sender: '.$parcel->customer?->full_name.' — the sender cannot be changed after booking.'"
                   :back="route('parcels.show', $parcel)" />

    <form method="POST" action="{{ route('parcels.update', $parcel) }}" novalidate>
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt text-danger"></i> Receiver &amp; pickup</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <x-form.field name="receiver_name"
                                              label="Receiver name"
                                              :value="$parcel->receiver_name"
                                              :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-form.field name="receiver_phone"
                                              label="Receiver phone"
                                              :value="$parcel->receiver_phone"
                                              :required="true" />
                            </div>
                            <div class="col-12">
                                <x-form.field name="receiver_address"
                                              type="textarea"
                                              label="Delivery address"
                                              :value="$parcel->receiver_address"
                                              :rows="2"
                                              :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-form.field name="receiver_city"
                                              label="City"
                                              :value="$parcel->receiver_city"
                                              :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-form.field name="receiver_postal_code"
                                              label="Postal code"
                                              :value="$parcel->receiver_postal_code" />
                            </div>
                            <div class="col-12">
                                <x-form.field name="pickup_address"
                                              type="textarea"
                                              label="Pickup address"
                                              :value="$parcel->pickup_address"
                                              :rows="2"
                                              :required="true" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam text-warning"></i> Parcel</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <x-form.field name="parcel_type"
                                              label="Parcel type"
                                              :value="$parcel->parcel_type->value"
                                              :options="$parcelTypes"
                                              :required="true" />
                            </div>
                            <div class="col-md-4">
                                <x-form.field name="weight"
                                              type="number"
                                              label="Weight (kg)"
                                              :value="$parcel->weight"
                                              step="0.001"
                                              min="0.001"
                                              :required="true" />
                            </div>
                            <div class="col-md-4">
                                <x-form.field name="priority"
                                              label="Delivery priority"
                                              :value="$parcel->priority->value"
                                              :options="$priorities"
                                              :required="true" />
                            </div>

                            <div class="col-12">
                                <label class="form-label">Dimensions (cm)</label>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <x-form.field name="length_cm" type="number" :value="$parcel->length_cm" step="0.1" min="0.1" placeholder="Length" />
                                    </div>
                                    <div class="col-4">
                                        <x-form.field name="width_cm" type="number" :value="$parcel->width_cm" step="0.1" min="0.1" placeholder="Width" />
                                    </div>
                                    <div class="col-4">
                                        <x-form.field name="height_cm" type="number" :value="$parcel->height_cm" step="0.1" min="0.1" placeholder="Height" />
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <x-form.field name="special_instructions"
                                              type="textarea"
                                              label="Special instructions"
                                              :value="$parcel->special_instructions"
                                              :rows="2" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 fw-bold">Charges &amp; payment</h6>
                    </div>
                    <div class="card-body">
                        <x-form.field name="delivery_charge"
                                      type="number"
                                      label="Delivery charge"
                                      :value="$parcel->delivery_charge"
                                      step="0.01"
                                      min="0"
                                      :prefix="config('courier.pricing.currency_symbol')"
                                      :required="true" />

                        <x-form.field name="payment_method"
                                      id="payment_method"
                                      label="Payment method"
                                      :value="$parcel->payment_method->value"
                                      :options="$paymentMethods"
                                      :required="true" />

                        <x-form.field name="payment_status"
                                      label="Payment status"
                                      :value="$parcel->payment_status->value"
                                      :options="$paymentStatuses"
                                      :required="true" />

                        <x-form.field name="cod_amount"
                                      id="cod_amount"
                                      type="number"
                                      label="Cash to collect"
                                      :value="$parcel->cod_amount"
                                      step="0.01"
                                      min="0"
                                      :prefix="config('courier.pricing.currency_symbol')" />

                        <hr>

                        <dl class="row small mb-0">
                            <dt class="col-6 text-muted fw-normal">Tracking number</dt>
                            <dd class="col-6 tracking-code">{{ $parcel->tracking_number }}</dd>

                            <dt class="col-6 text-muted fw-normal">Current status</dt>
                            <dd class="col-6"><x-status-badge :status="$parcel->status" /></dd>

                            <dt class="col-6 text-muted fw-normal">Booked</dt>
                            <dd class="col-6">{{ $parcel->created_at->format('d M Y') }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-check-lg me-1"></i> Save changes
                    </button>
                    <a href="{{ route('parcels.show', $parcel) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const method = document.getElementById('payment_method');
    const cod = document.getElementById('cod_amount');

    const syncCod = () => {
        const isCod = method.value === 'cash_on_delivery';
        cod.disabled = !isCod;
        if (!isCod) cod.value = 0;
    };

    method?.addEventListener('change', syncCod);
    syncCod();
});
</script>
@endpush
