<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Enums\DriverStatus;
use App\Enums\UserRole;
use App\Enums\VehicleType;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Models\Branch;
use App\Models\Driver;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DriverController extends Controller
{
    public function __construct(private readonly FileUploadService $uploads) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Driver::class);

        $user = $request->user();

        $drivers = Driver::query()
            ->visibleTo($user)
            ->with(['branch:id,name,code', 'user:id,email,is_active'])
            ->withCount([
                'deliveries as completed_deliveries_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Completed),
                'deliveries as failed_deliveries_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Failed),
                'deliveries as active_deliveries_count' => fn ($q) => $q
                    ->whereIn('status', DeliveryStatus::activeValues()),
            ])
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString() ?: null)
            ->ofBranch($request->integer('branch_id') ?: null)
            ->when(
                $request->boolean('trashed') && $user->can('view-trash'),
                fn ($q) => $q->onlyTrashed()
            )
            ->orderBy('full_name')
            ->paginate(config('courier.pagination.web'))
            ->withQueryString();

        return view('drivers.index', [
            'drivers' => $drivers,
            'statuses' => DriverStatus::options(),
            'branches' => $this->visibleBranches($user),
            'filters' => $request->only(['search', 'status', 'branch_id', 'trashed']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Driver::class);

        return view('drivers.create', [
            'statuses' => DriverStatus::options(),
            'vehicleTypes' => VehicleType::options(),
            'branches' => $this->visibleBranches($request->user()),
        ]);
    }

    public function store(StoreDriverRequest $request): RedirectResponse
    {
        $driver = DB::transaction(function () use ($request): Driver {
            $data = $request->driverData();

            if ($request->hasFile('photo')) {
                $data['photo_path'] = $this->uploads->store($request->file('photo'), 'driver_photos');
            }

            // Optionally provision the login account in the same step, so the
            // driver can sign in to the driver dashboard immediately.
            if ($request->boolean('create_account')) {
                $account = User::create([
                    'name' => $data['full_name'],
                    'email' => $request->string('account_email')->toString(),
                    'password' => $request->string('account_password')->toString(),
                    'role' => UserRole::Driver,
                    'branch_id' => $data['branch_id'],
                    'phone' => $data['phone'],
                    'is_active' => true,
                ]);

                $data['user_id'] = $account->id;
            }

            return Driver::create($data);
        });

        return redirect()
            ->route('drivers.show', $driver)
            ->with('success', "Driver {$driver->full_name} was added with code {$driver->driver_code}.");
    }

    public function show(Request $request, Driver $driver): View
    {
        $this->authorize('view', $driver);

        $driver->load(['branch:id,name,code,city', 'user:id,email,is_active,last_login_at']);

        $driver->loadCount([
            'deliveries as completed_deliveries_count' => fn ($q) => $q
                ->where('status', DeliveryStatus::Completed),
            'deliveries as failed_deliveries_count' => fn ($q) => $q
                ->where('status', DeliveryStatus::Failed),
        ]);

        $deliveries = $driver->deliveries()
            ->with('parcel:id,tracking_number,receiver_name,receiver_city,status,priority')
            ->paginate(10, ['*'], 'deliveries');

        return view('drivers.show', [
            'driver' => $driver,
            'deliveries' => $deliveries,
            'performance' => [
                'total_assignments' => $driver->deliveries()->count(),
                'completed' => $driver->completed_deliveries_count,
                'failed' => $driver->failed_deliveries_count,
                'rejected' => $driver->deliveries()->where('status', DeliveryStatus::Rejected)->count(),
                'active' => $driver->deliveries()->whereIn('status', DeliveryStatus::activeValues())->count(),
                'success_rate' => $driver->success_rate,
                'completed_this_month' => $driver->deliveries()
                    ->where('status', DeliveryStatus::Completed)
                    ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'cod_collected' => (float) $driver->deliveries()
                    ->where('status', DeliveryStatus::Completed)
                    ->sum('cod_collected'),
                'average_minutes' => $this->averageDeliveryMinutes($driver),
            ],
        ]);
    }

    public function edit(Request $request, Driver $driver): View
    {
        $this->authorize('update', $driver);

        return view('drivers.edit', [
            'driver' => $driver,
            'statuses' => DriverStatus::options(),
            'vehicleTypes' => VehicleType::options(),
            'branches' => $this->visibleBranches($request->user()),
        ]);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): RedirectResponse
    {
        $data = $request->driverData();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->uploads->replace(
                $request->file('photo'),
                'driver_photos',
                $driver->photo_path
            );
        }

        $driver->update($data);

        return redirect()
            ->route('drivers.show', $driver)
            ->with('success', "Driver {$driver->full_name} was updated.");
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        $this->authorize('delete', $driver);

        // A driver mid-delivery cannot simply vanish — the parcels they are
        // holding would have no owner.
        $open = $driver->deliveries()->whereIn('status', DeliveryStatus::activeValues())->count();

        if ($open > 0) {
            return back()->with(
                'error',
                "{$driver->full_name} still has {$open} open ".str('delivery')->plural($open)
                    .'. Reassign them before archiving this driver.'
            );
        }

        $driver->delete();

        // Take away dashboard access without deleting the account, so the
        // audit trail of who did what stays intact.
        $driver->user?->update(['is_active' => false]);

        return redirect()
            ->route('drivers.index')
            ->with('success', "Driver {$driver->full_name} was archived.");
    }

    public function restore(Driver $driver): RedirectResponse
    {
        $this->authorize('restore', $driver);

        $driver->restore();
        $driver->user?->update(['is_active' => true]);

        return redirect()
            ->route('drivers.show', $driver)
            ->with('success', "Driver {$driver->full_name} was restored.");
    }

    /**
     * Activate / deactivate from the list screen.
     */
    public function toggleStatus(Driver $driver): RedirectResponse
    {
        $this->authorize('toggleStatus', $driver);

        $becomingInactive = $driver->status !== DriverStatus::Inactive;

        if ($becomingInactive
            && $driver->deliveries()->whereIn('status', DeliveryStatus::activeValues())->exists()) {
            return back()->with(
                'error',
                "{$driver->full_name} has open deliveries and cannot be deactivated yet."
            );
        }

        $driver->update([
            'status' => $becomingInactive ? DriverStatus::Inactive : DriverStatus::Available,
        ]);

        $driver->user?->update(['is_active' => ! $becomingInactive]);

        return back()->with(
            'success',
            "{$driver->full_name} is now {$driver->status->label()}."
        );
    }

    /**
     * Average minutes from accepting a job to closing it out.
     */
    private function averageDeliveryMinutes(Driver $driver): ?int
    {
        $average = $driver->deliveries()
            ->where('status', DeliveryStatus::Completed)
            ->whereNotNull('accepted_at')
            ->whereNotNull('completed_at')
            ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, accepted_at, completed_at)'));

        return $average === null ? null : (int) round((float) $average);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function visibleBranches(User $user): \Illuminate\Support\Collection
    {
        return Branch::query()
            ->visibleTo($user)
            ->active()
            ->orderBy('name')
            ->get()
            ->pluck('label', 'id');
    }
}
