@php $user = auth()->user(); @endphp

<header class="app-topbar">
    <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-sidebar-toggle aria-label="Toggle navigation">
        <i class="bi bi-list fs-3"></i>
    </button>

    {{-- Global tracking-number lookup, available from every screen. --}}
    <form action="{{ route('track.lookup') }}" method="GET" class="d-none d-md-block flex-grow-1" style="max-width: 26rem;">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text"
                   name="tracking_number"
                   class="form-control border-start-0 ps-0"
                   placeholder="Search a tracking number…"
                   aria-label="Tracking number">
        </div>
    </form>

    <div class="ms-auto d-flex align-items-center gap-3">
        @if ($user->branch)
            <span class="badge text-bg-light d-none d-sm-inline-flex align-items-center gap-1">
                <i class="bi bi-building"></i> {{ $user->branch->name }}
            </span>
        @elseif ($user->isSuperAdmin())
            <span class="badge text-bg-light d-none d-sm-inline-flex align-items-center gap-1">
                <i class="bi bi-globe2"></i> All branches
            </span>
        @endif

        @unless ($user->hasVerifiedEmail())
            <a href="{{ route('verification.notice') }}"
               class="badge text-bg-warning text-decoration-none d-none d-sm-inline-flex align-items-center gap-1"
               data-bs-toggle="tooltip"
               title="Your email address is not verified yet">
                <i class="bi bi-exclamation-triangle"></i> Unverified
            </a>
        @endunless

        @can('create', App\Models\Parcel::class)
            <a href="{{ route('parcels.create') }}" class="btn btn-primary btn-sm d-none d-sm-inline-flex align-items-center gap-1">
                <i class="bi bi-plus-lg"></i> New Parcel
            </a>
        @endcan

        <div class="dropdown">
            <button class="btn btn-link p-0 d-flex align-items-center gap-2 text-decoration-none text-dark"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <img src="{{ $user->avatar_url }}" alt="" class="avatar avatar--sm">
                <span class="d-none d-lg-block small fw-semibold">{{ $user->name }}</span>
                <i class="bi bi-chevron-down small text-muted"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li class="px-3 py-2">
                    <div class="fw-semibold small">{{ $user->name }}</div>
                    <div class="text-muted" style="font-size: 0.775rem;">{{ $user->email }}</div>
                    <span class="badge text-bg-{{ $user->role->color() }} mt-1">{{ $user->role->label() }}</span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('profile.edit') }}">
                        <i class="bi bi-person"></i> My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('password.change') }}">
                        <i class="bi bi-key"></i> Change Password
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                            <i class="bi bi-box-arrow-right"></i> Sign Out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
