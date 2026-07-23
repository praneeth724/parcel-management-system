{{-- Shared create/edit form. Expects: $branch (may be null), $managers --}}

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Branch details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <x-form.field name="name"
                                      label="Branch name"
                                      :value="$branch?->name"
                                      placeholder="Colombo Head Office"
                                      :required="true" />
                    </div>
                    <div class="col-md-4">
                        <x-form.field name="code"
                                      label="Branch code"
                                      :value="$branch?->code"
                                      placeholder="CMB-01"
                                      help="Capitals, digits and hyphens."
                                      :required="true" />
                    </div>
                    <div class="col-12">
                        <x-form.field name="address"
                                      type="textarea"
                                      label="Address"
                                      :value="$branch?->address"
                                      :rows="2"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="city"
                                      label="City"
                                      :value="$branch?->city"
                                      placeholder="Colombo"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="postal_code"
                                      label="Postal code"
                                      :value="$branch?->postal_code"
                                      placeholder="00300" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="contact_number"
                                      label="Contact number"
                                      :value="$branch?->contact_number"
                                      placeholder="0112345678"
                                      help="10 digits starting with 0 — landline or mobile."
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="email"
                                      type="email"
                                      label="Email address"
                                      :value="$branch?->email"
                                      placeholder="colombo@swifttrack.lk" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Management</h6>
            </div>
            <div class="card-body">
                <x-form.field name="manager_id"
                              label="Branch manager"
                              :value="$branch?->manager_id"
                              :options="$managers"
                              placeholder="— Unassigned —"
                              help="Only users with the Branch Manager role who do not already run a branch are listed." />

                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox"
                           name="is_active"
                           id="is_active"
                           value="1"
                           class="form-check-input"
                           @checked(old('is_active', $branch?->is_active ?? true))>
                    <label for="is_active" class="form-check-label">Branch is active</label>
                </div>
                <div class="form-text">Inactive branches cannot take new bookings.</div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-check-lg me-1"></i>
                {{ $branch ? 'Save changes' : 'Create branch' }}
            </button>
            <a href="{{ $branch ? route('branches.show', $branch) : route('branches.index') }}"
               class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>
