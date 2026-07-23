<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Parcel;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * One dashboard per role.
 *
 * Each role sees a different set of widgets scoped to what it is responsible
 * for: the Super Admin sees the whole network, a Branch Manager sees their
 * branch, a Dispatcher sees the day's dispatch workload, and a Driver sees
 * only their own runs.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Send the user to the dashboard that matches their role.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route(Auth::user()->role->dashboardRoute());
    }

    public function admin(): View
    {
        $this->authorizeRole(fn ($user) => $user->isSuperAdmin());

        return view('dashboard.admin', [
            'stats' => $this->dashboard->statistics(),
            'topCustomers' => $this->dashboard->topCustomers(),
            'topDrivers' => $this->dashboard->topDrivers(),
            'monthlyShipments' => $this->dashboard->monthlyShipments(),
            'monthlyRevenue' => $this->dashboard->monthlyRevenue(),
            'successRate' => $this->dashboard->deliverySuccessRate(),
            'statusBreakdown' => $this->dashboard->statusBreakdown(),
            'branchPerformance' => $this->dashboard->branchPerformance(),
            'recentParcels' => $this->dashboard->recentParcels(),
        ]);
    }

    public function manager(): View
    {
        $user = Auth::user();

        $this->authorizeRole(fn ($u) => $u->isBranchManager() || $u->isSuperAdmin());

        $branchId = $user->visibleBranchId();

        return view('dashboard.manager', [
            'branch' => $user->branch,
            'stats' => $this->dashboard->statistics($branchId),
            'topCustomers' => $this->dashboard->topCustomers($branchId),
            'topDrivers' => $this->dashboard->topDrivers($branchId),
            'monthlyShipments' => $this->dashboard->monthlyShipments($branchId),
            'monthlyRevenue' => $this->dashboard->monthlyRevenue($branchId),
            'successRate' => $this->dashboard->deliverySuccessRate($branchId),
            'statusBreakdown' => $this->dashboard->statusBreakdown($branchId),
            'recentParcels' => $this->dashboard->recentParcels($branchId),
        ]);
    }

    public function dispatcher(): View
    {
        $user = Auth::user();

        $this->authorizeRole(fn ($u) => $u->isDispatcher() || $u->isManagement());

        $branchId = $user->visibleBranchId();

        return view('dashboard.dispatcher', [
            'stats' => $this->dashboard->statistics($branchId),
            'dailyShipments' => $this->dashboard->dailyShipments($branchId),
            'statusBreakdown' => $this->dashboard->statusBreakdown($branchId),

            // The dispatcher's working queue: parcels waiting for a driver.
            'unassignedParcels' => Parcel::query()
                ->ofBranch($branchId)
                ->unassigned()
                ->with(['customer:id,full_name,customer_code', 'branch:id,name'])
                ->orderByRaw("FIELD(priority, 'same_day', 'express', 'normal')")
                ->oldest()
                ->limit(10)
                ->get(),

            // Assignments a driver has not yet accepted or rejected.
            'awaitingResponse' => Delivery::query()
                ->ofBranch($branchId)
                ->pendingResponse()
                ->with(['parcel:id,tracking_number,receiver_city,priority', 'driver:id,full_name,driver_code'])
                ->latest('assigned_at')
                ->limit(8)
                ->get(),

            'recentParcels' => $this->dashboard->recentParcels($branchId, 6),
        ]);
    }

    public function driver(): View
    {
        $user = Auth::user();

        $this->authorizeRole(fn ($u) => $u->isDriver());

        $driver = $user->driver;

        // A user with the Driver role but no linked driver record cannot work;
        // this is a data problem an administrator has to fix.
        if ($driver === null) {
            return view('dashboard.driver', [
                'driver' => null,
                'stats' => [],
                'activeDeliveries' => collect(),
                'recentDeliveries' => collect(),
                'performance' => ['labels' => [], 'data' => []],
            ]);
        }

        return view('dashboard.driver', [
            'driver' => $driver->loadCount([
                'deliveries as completed_deliveries_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Completed),
                'deliveries as failed_deliveries_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Failed),
            ]),
            'stats' => $this->dashboard->driverStatistics($driver),
            'performance' => $this->dashboard->driverDailyPerformance($driver),

            'activeDeliveries' => Delivery::query()
                ->where('driver_id', $driver->id)
                ->active()
                ->with(['parcel.customer:id,full_name,mobile'])
                ->orderByRaw("FIELD(status, 'assigned', 'accepted', 'in_transit')")
                ->get(),

            'recentDeliveries' => Delivery::query()
                ->where('driver_id', $driver->id)
                ->whereIn('status', [DeliveryStatus::Completed, DeliveryStatus::Failed])
                ->with('parcel:id,tracking_number,receiver_name,receiver_city')
                ->latest('completed_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /**
     * Small guard so a user cannot open another role's dashboard by URL.
     */
    private function authorizeRole(callable $check): void
    {
        if (! $check(Auth::user())) {
            throw new AccessDeniedHttpException('This dashboard is not available for your role.');
        }
    }
}
