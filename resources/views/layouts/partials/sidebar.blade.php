@php
    /**
     * Role-aware navigation.
     *
     * Each link is gated by the same policies the controllers use, so the menu
     * can never offer a page the user would be refused on arrival.
     */
    $user = auth()->user();
@endphp

<aside class="app-sidebar">
    <a href="{{ route('dashboard') }}" class="app-sidebar__brand">
        <i class="bi bi-box-seam-fill"></i>
        <span>{{ config('app.name') }}</span>
    </a>

    <nav class="app-sidebar__nav">
        <a href="{{ route('dashboard') }}"
           class="app-nav-link @active('dashboard*')">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        {{-- ---------------- Operations ---------------- --}}
        <div class="app-sidebar__heading">Operations</div>

        @can('viewAny', App\Models\Parcel::class)
            <a href="{{ route('parcels.index') }}" class="app-nav-link @active('parcels*')">
                <i class="bi bi-box-seam"></i>
                <span>Parcels</span>
            </a>
        @endcan

        @can('viewAny', App\Models\Delivery::class)
            <a href="{{ route('deliveries.index') }}" class="app-nav-link @active('deliveries*')">
                <i class="bi bi-truck"></i>
                <span>{{ $user->isDriver() ? 'My Deliveries' : 'Deliveries' }}</span>
                @if ($pendingAssignments ?? false)
                    <span class="badge rounded-pill bg-warning text-dark">{{ $pendingAssignments }}</span>
                @endif
            </a>
        @endcan

        @can('assign-deliveries')
            <a href="{{ route('deliveries.assign') }}" class="app-nav-link @active('deliveries/assign')">
                <i class="bi bi-person-check"></i>
                <span>Assign Parcels</span>
            </a>
        @endcan

        {{-- ---------------- Directory ---------------- --}}
        @unless ($user->isDriver())
            <div class="app-sidebar__heading">Directory</div>

            @can('viewAny', App\Models\Customer::class)
                <a href="{{ route('customers.index') }}" class="app-nav-link @active('customers*')">
                    <i class="bi bi-people"></i>
                    <span>Customers</span>
                </a>
            @endcan

            @can('viewAny', App\Models\Driver::class)
                <a href="{{ route('drivers.index') }}" class="app-nav-link @active('drivers*')">
                    <i class="bi bi-person-badge"></i>
                    <span>Drivers</span>
                </a>
            @endcan

            @can('viewAny', App\Models\Branch::class)
                <a href="{{ route('branches.index') }}" class="app-nav-link @active('branches*')">
                    <i class="bi bi-building"></i>
                    <span>Branches</span>
                </a>
            @endcan
        @endunless

        {{-- ---------------- Insight ---------------- --}}
        @can('view-reports')
            <div class="app-sidebar__heading">Insight</div>

            <a href="{{ route('reports.index') }}" class="app-nav-link @active('reports*')">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span>Reports</span>
            </a>
        @endcan

        {{-- ---------------- Administration ---------------- --}}
        @can('manage-users')
            <div class="app-sidebar__heading">Administration</div>

            <a href="{{ route('users.index') }}" class="app-nav-link @active('users*')">
                <i class="bi bi-shield-lock"></i>
                <span>Staff Accounts</span>
            </a>
        @endcan

        <div class="app-sidebar__heading">Public</div>

        <a href="{{ route('track.index') }}" class="app-nav-link @active('track*')" target="_blank">
            <i class="bi bi-search"></i>
            <span>Track a Parcel</span>
            <i class="bi bi-box-arrow-up-right ms-auto small opacity-50"></i>
        </a>
    </nav>

    <div class="app-sidebar__footer">
        <div class="d-flex align-items-center gap-2">
            <img src="{{ $user->avatar_url }}" alt="" class="avatar avatar--sm">
            <div class="min-w-0 flex-grow-1">
                <div class="text-white small fw-semibold text-truncate">{{ $user->name }}</div>
                <div class="small text-truncate" style="font-size: 0.75rem;">{{ $user->role->label() }}</div>
            </div>
        </div>
    </div>
</aside>
