{{--
    Shared create/edit form.
    Expects: $customer (may be null), $statuses, $branches, $action, $method
--}}

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Customer details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <x-form.field name="full_name"
                                      label="Full name"
                                      :value="$customer?->full_name"
                                      placeholder="Nimal Perera"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="nic_passport"
                                      label="NIC / Passport number"
                                      :value="$customer?->nic_passport"
                                      placeholder="199012345678"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="mobile"
                                      label="Mobile number"
                                      :value="$customer?->mobile"
                                      placeholder="0771234567"
                                      help="Sri Lankan mobile number"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="email"
                                      type="email"
                                      label="Email address"
                                      :value="$customer?->email"
                                      placeholder="customer@example.lk" />
                    </div>
                    <div class="col-12">
                        <x-form.field name="address"
                                      type="textarea"
                                      label="Address"
                                      :value="$customer?->address"
                                      :rows="2"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="city"
                                      label="City"
                                      :value="$customer?->city"
                                      placeholder="Colombo"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="postal_code"
                                      label="Postal code"
                                      :value="$customer?->postal_code"
                                      placeholder="10100" />
                    </div>
                    <div class="col-12">
                        <x-form.field name="company_name"
                                      label="Company name"
                                      :value="$customer?->company_name"
                                      placeholder="Optional — for business customers" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Account settings</h6>
            </div>
            <div class="card-body">
                <x-form.field name="status"
                              label="Status"
                              :value="$customer?->status?->value"
                              :options="$statuses"
                              :required="true"
                              help="Only Active customers can book new shipments." />

                @if ($branches->isNotEmpty())
                    <x-form.field name="branch_id"
                                  label="Home branch"
                                  :value="$customer?->branch_id"
                                  :options="$branches"
                                  placeholder="— Shared across all branches —"
                                  help="Leave blank to make this customer visible to every branch." />
                @endif

                @if ($customer)
                    <hr>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted fw-normal">Customer ID</dt>
                        <dd class="col-7 tracking-code">{{ $customer->customer_code }}</dd>

                        <dt class="col-5 text-muted fw-normal">Registered</dt>
                        <dd class="col-7">{{ $customer->created_at->format('d M Y') }}</dd>
                    </dl>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-check-lg me-1"></i>
                {{ $customer ? 'Save changes' : 'Create customer' }}
            </button>
            <a href="{{ $customer ? route('customers.show', $customer) : route('customers.index') }}"
               class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>
