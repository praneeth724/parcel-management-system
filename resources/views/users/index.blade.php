@extends('layouts.app')

@section('title', 'Staff accounts')

@section('content')
    <x-page-header title="Staff accounts"
                   :subtitle="$users->total().' '.str('account')->plural($users->total()).' across the roles you manage'">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <a href="{{ route('users.create') }}" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i> New account
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('users.index') }}" class="filter-bar">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="search" class="form-label small fw-semibold">Search</label>
                <input type="search"
                       id="search"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       class="form-control"
                       placeholder="Name, email or phone">
            </div>

            <div class="col-6 col-md-3">
                <label for="role" class="form-label small fw-semibold">Role</label>
                <select id="role" name="role" class="form-select" data-auto-submit>
                    <option value="">All roles</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($branches->count() > 1)
                <div class="col-6 col-md-2">
                    <label for="branch_id" class="form-label small fw-semibold">Branch</label>
                    <select id="branch_id" name="branch_id" class="form-select" data-auto-submit>
                        <option value="">All</option>
                        @foreach ($branches as $id => $label)
                            <option value="{{ $id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-6 col-md-2">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select id="status" name="status" class="form-select" data-auto-submit>
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Deactivated</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary" title="Clear filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>

        @can('view-trash')
            <div class="form-check mt-3">
                <input type="checkbox"
                       name="trashed"
                       id="trashed"
                       value="1"
                       class="form-check-input"
                       data-auto-submit
                       @checked($filters['trashed'] ?? false)>
                <label for="trashed" class="form-check-label small">Show archived accounts only</label>
            </div>
        @endcan
    </form>

    <div class="table-card">
        @if ($users->isEmpty())
            <x-empty-state icon="bi-shield-lock"
                           title="No accounts found"
                           message="Try widening your filters." />
        @else
            <div class="table-card__scroll">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Last sign-in</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ $user->avatar_url }}" alt="" class="avatar avatar--sm">
                                        <div>
                                            <a href="{{ route('users.show', $user) }}" class="text-decoration-none fw-semibold">
                                                {{ $user->name }}
                                            </a>
                                            @if ($user->id === auth()->id())
                                                <span class="badge text-bg-light">you</span>
                                            @endif
                                            @if ($user->phone)
                                                <div><small class="text-muted">{{ $user->phone }}</small></div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small>{{ $user->email }}</small>
                                    @unless ($user->hasVerifiedEmail())
                                        <div><small class="text-warning"><i class="bi bi-exclamation-circle"></i> unverified</small></div>
                                    @endunless
                                </td>
                                <td><x-status-badge :status="$user->role" /></td>
                                <td><small>{{ $user->branch?->name ?? 'All branches' }}</small></td>
                                <td>
                                    <small class="text-muted">
                                        {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                                    </small>
                                </td>
                                <td>
                                    <span class="status-badge status-badge--{{ $user->is_active ? 'success' : 'secondary' }}">
                                        {{ $user->is_active ? 'Active' : 'Deactivated' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        @if ($user->trashed())
                                            @can('restore', $user)
                                                <form method="POST" action="{{ route('users.restore', $user) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-success" title="Restore">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @else
                                            <a href="{{ route('users.show', $user) }}"
                                               class="btn btn-sm btn-outline-secondary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            @can('update', $user)
                                                <a href="{{ route('users.edit', $user) }}"
                                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endcan

                                            @can('toggleStatus', $user)
                                                <form method="POST"
                                                      action="{{ route('users.toggle-status', $user) }}"
                                                      data-confirm="{{ $user->is_active ? 'Deactivate' : 'Reactivate' }} {{ $user->name }}'s account?">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-{{ $user->is_active ? 'warning' : 'success' }}"
                                                            title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                                        <i class="bi bi-{{ $user->is_active ? 'toggle-on' : 'toggle-off' }}"></i>
                                                    </button>
                                                </form>
                                            @endcan

                                            @can('delete', $user)
                                                <form method="POST"
                                                      action="{{ route('users.destroy', $user) }}"
                                                      data-confirm="Archive {{ $user->name }}'s account?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" title="Archive">
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
