{{--
    Shared create/edit form for drivers.
    Expects: $driver (may be null), $statuses, $vehicleTypes, $branches
--}}

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Personal details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <x-form.field name="full_name"
                                      label="Full name"
                                      :value="$driver?->full_name"
                                      placeholder="Sunil Perera"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="phone"
                                      label="Phone number"
                                      :value="$driver?->phone"
                                      placeholder="0771234567"
                                      help="Sri Lankan mobile number"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="email"
                                      type="email"
                                      label="Email address"
                                      :value="$driver?->email"
                                      placeholder="driver@example.lk" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="branch_id"
                                      label="Assigned branch"
                                      :value="$driver?->branch_id"
                                      :options="$branches"
                                      placeholder="— Select a branch —"
                                      :required="true" />
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Vehicle &amp; licence</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <x-form.field name="vehicle_number"
                                      label="Vehicle number"
                                      :value="$driver?->vehicle_number"
                                      placeholder="WP CAB-1234"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="vehicle_type"
                                      label="Vehicle type"
                                      :value="$driver?->vehicle_type?->value"
                                      :options="$vehicleTypes"
                                      :required="true"
                                      help="Determines the maximum parcel weight this driver can carry." />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="license_number"
                                      label="Licence number"
                                      :value="$driver?->license_number"
                                      placeholder="B1234567"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="license_expiry"
                                      type="date"
                                      label="Licence expiry"
                                      :value="$driver?->license_expiry?->format('Y-m-d')"
                                      help="A driver with an expired licence cannot be assigned parcels." />
                    </div>
                    <div class="col-12">
                        <x-form.field name="notes"
                                      type="textarea"
                                      label="Internal notes"
                                      :value="$driver?->notes"
                                      :rows="2"
                                      placeholder="Optional — anything the dispatcher should know" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Profile photo</h6>
            </div>
            <div class="card-body text-center">
                <img src="{{ $driver?->photo_url ?? 'https://ui-avatars.com/api/?name=New+Driver&background=198754&color=fff&bold=true' }}"
                     alt=""
                     id="photoPreview"
                     class="avatar avatar--lg mb-3">

                <input type="file"
                       name="photo"
                       accept="image/*"
                       data-preview="#photoPreview"
                       class="form-control @error('photo') is-invalid @enderror">

                @error('photo')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @else
                    <div class="form-text">JPG, PNG or WebP, up to {{ round(config('courier.uploads.max_image_kb') / 1024) }} MB.</div>
                @enderror
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Availability</h6>
            </div>
            <div class="card-body">
                <x-form.field name="status"
                              label="Status"
                              :value="$driver?->status?->value"
                              :options="$statuses"
                              :required="true"
                              help="Only Available drivers can be handed new parcels." />

                @if ($driver)
                    <hr>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted fw-normal">Driver code</dt>
                        <dd class="col-7 tracking-code">{{ $driver->driver_code }}</dd>

                        <dt class="col-5 text-muted fw-normal">Login account</dt>
                        <dd class="col-7">{{ $driver->user?->email ?? 'Not linked' }}</dd>
                    </dl>
                @endif
            </div>
        </div>

        {{-- Optional login account, only offered when creating. --}}
        @unless ($driver)
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold">Dashboard access</h6>
                </div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input type="checkbox"
                               name="create_account"
                               id="create_account"
                               value="1"
                               class="form-check-input"
                               @checked(old('create_account'))>
                        <label for="create_account" class="form-check-label small">
                            Create a login account so this driver can use the driver dashboard
                        </label>
                    </div>

                    <x-form.field name="account_email"
                                  type="email"
                                  label="Login email"
                                  placeholder="driver@swifttrack.lk" />

                    <x-form.field name="account_password"
                                  type="password"
                                  label="Password"
                                  help="At least 8 characters." />

                    <x-form.field name="account_password_confirmation"
                                  type="password"
                                  label="Confirm password" />
                </div>
            </div>
        @endunless

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-check-lg me-1"></i>
                {{ $driver ? 'Save changes' : 'Add driver' }}
            </button>
            <a href="{{ $driver ? route('drivers.show', $driver) : route('drivers.index') }}"
               class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>
