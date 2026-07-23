{{--
    Shared create/edit form for staff accounts.
    Expects: $user (may be null), $roles, $branches. Optional: $canAssignRole
--}}

@php $canAssignRole = $canAssignRole ?? true; @endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Account details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <x-form.field name="name"
                                      label="Full name"
                                      :value="$user?->name"
                                      placeholder="Nimal Perera"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="email"
                                      type="email"
                                      label="Email address"
                                      :value="$user?->email"
                                      placeholder="name@swifttrack.lk"
                                      :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="phone"
                                      label="Mobile number"
                                      :value="$user?->phone"
                                      placeholder="0771234567"
                                      help="Sri Lankan mobile number" />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="branch_id"
                                      label="Branch"
                                      :value="$user?->branch_id"
                                      :options="$branches"
                                      placeholder="— None (Super Admin only) —"
                                      help="Every role except Super Admin must belong to a branch." />
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold mb-3">
                    {{ $user ? 'Change password' : 'Password' }}
                </h6>

                @if ($user)
                    <p class="small text-muted">
                        Leave both fields blank to keep the current password unchanged.
                    </p>
                @endif

                <div class="row">
                    <div class="col-md-6">
                        <x-form.field name="password"
                                      type="password"
                                      label="Password"
                                      autocomplete="new-password"
                                      :required="! $user"
                                      help="At least 8 characters, mixed case, with a number." />
                    </div>
                    <div class="col-md-6">
                        <x-form.field name="password_confirmation"
                                      type="password"
                                      label="Confirm password"
                                      autocomplete="new-password"
                                      :required="! $user" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0 fw-bold">Role &amp; access</h6>
            </div>
            <div class="card-body">
                @if ($canAssignRole)
                    <x-form.field name="role"
                                  label="Role"
                                  :value="$user?->role?->value"
                                  :options="$roles"
                                  :required="true"
                                  help="Determines which dashboard and features this account can reach." />
                @else
                    {{-- A Branch Manager cannot change roles, but the field must
                         still be submitted for validation to pass. --}}
                    <input type="hidden" name="role" value="{{ $user->role->value }}">
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <div><x-status-badge :status="$user->role" /></div>
                        <div class="form-text">Only a Super Admin can change a user's role.</div>
                    </div>
                @endif

                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox"
                           name="is_active"
                           id="is_active"
                           value="1"
                           class="form-check-input"
                           @checked(old('is_active', $user?->is_active ?? true))>
                    <label for="is_active" class="form-check-label">Account is active</label>
                </div>
                <div class="form-text">
                    Deactivating signs the user out immediately and revokes their API tokens.
                </div>

                @if ($user)
                    <hr>
                    <dl class="row small mb-0">
                        <dt class="col-6 text-muted fw-normal">Email verified</dt>
                        <dd class="col-6">{{ $user->hasVerifiedEmail() ? 'Yes' : 'No' }}</dd>

                        <dt class="col-6 text-muted fw-normal">Last sign-in</dt>
                        <dd class="col-6">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</dd>

                        <dt class="col-6 text-muted fw-normal">Created</dt>
                        <dd class="col-6">{{ $user->created_at->format('d M Y') }}</dd>
                    </dl>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-check-lg me-1"></i>
                {{ $user ? 'Save changes' : 'Create account' }}
            </button>
            <a href="{{ $user ? route('users.show', $user) : route('users.index') }}"
               class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>
