@extends('layouts.app')

@section('title', 'Branches')

@section('content')
    <x-page-header title="Branches"
                   :subtitle="$branches->total().' '.str('branch')->plural($branches->total()).' in the network'">
        <x-slot:actions>
            @can('create', App\Models\Branch::class)
                <a href="{{ route('branches.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New branch
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('branches.index') }}" class="filter-bar">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="search" class="form-label small fw-semibold">Search</label>
                <input type="search"
                       id="search"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       class="form-control"
                       placeholder="Branch name, code, city or contact number">
            </div>

            <div class="col-6 col-md-3">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select id="status" name="status" class="form-select" data-auto-submit>
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary" title="Clear filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="table-card">
        @if ($branches->isEmpty())
            <x-empty-state icon="bi-building"
                           title="No branches found"
                           message="Add a branch to start booking parcels.">
                @can('create', App\Models\Branch::class)
                    <a href="{{ route('branches.create') }}" class="btn btn-primary btn-sm">Add a branch</a>
                @endcan
            </x-empty-state>
        @else
            <div class="table-card__scroll">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>City</th>
                            <th>Manager</th>
                            <th>Contact</th>
                            <th class="text-end">Staff</th>
                            <th class="text-end">Drivers</th>
                            <th class="text-end">Parcels</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($branches as $branch)
                            <tr>
                                <td>
                                    <a href="{{ route('branches.show', $branch) }}" class="text-decoration-none fw-semibold">
                                        {{ $branch->name }}
                                    </a>
                                    <div><small class="text-muted tracking-code">{{ $branch->code }}</small></div>
                                </td>
                                <td>{{ $branch->city }}</td>
                                <td><small>{{ $branch->manager?->name ?? '— Unassigned —' }}</small></td>
                                <td>
                                    <a href="tel:{{ $branch->contact_number }}" class="text-decoration-none small">
                                        {{ $branch->contact_number }}
                                    </a>
                                </td>
                                <td class="text-end">{{ $branch->staff_count }}</td>
                                <td class="text-end">{{ $branch->drivers_count }}</td>
                                <td class="text-end fw-semibold">{{ number_format($branch->parcels_count) }}</td>
                                <td>
                                    <span class="status-badge status-badge--{{ $branch->is_active ? 'success' : 'secondary' }}">
                                        {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('branches.shipments', $branch) }}"
                                           class="btn btn-sm btn-outline-secondary" title="Branch shipments">
                                            <i class="bi bi-box-seam"></i>
                                        </a>

                                        @can('update', $branch)
                                            <a href="{{ route('branches.edit', $branch) }}"
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $branch)
                                            <form method="POST"
                                                  action="{{ route('branches.destroy', $branch) }}"
                                                  data-confirm="Archive {{ $branch->name }}? Its staff and history are kept.">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" title="Archive">
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top">
                {{ $branches->links() }}
            </div>
        @endif
    </div>
@endsection
