<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CustomerResource;
use App\Http\Resources\DriverResource;
use App\Http\Resources\ParcelResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Dashboard figures scoped to whatever the caller is allowed to see.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // A driver gets their own figures rather than the branch's.
        if ($user->isDriver()) {
            $driver = $user->driver;

            if ($driver === null) {
                return $this->error(
                    'Your account is not linked to a driver record yet. Contact your Branch Manager.',
                    \Illuminate\Http\Response::HTTP_CONFLICT
                );
            }

            return $this->success([
                'scope' => 'driver',
                'statistics' => $this->dashboard->driverStatistics($driver),
                'charts' => [
                    'daily_completed' => $this->dashboard->driverDailyPerformance($driver),
                ],
            ]);
        }

        $branchId = $user->visibleBranchId();

        $payload = [
            'scope' => $user->isSuperAdmin() ? 'network' : 'branch',
            'branch' => $user->branch?->only(['id', 'name', 'code', 'city']),
            'statistics' => $this->dashboard->statistics($branchId),
            'status_breakdown' => $this->dashboard->statusBreakdown($branchId),
            'charts' => [
                'monthly_shipments' => $this->dashboard->monthlyShipments($branchId),
                'delivery_success_rate' => $this->dashboard->deliverySuccessRate($branchId),
                'daily_shipments' => $this->dashboard->dailyShipments($branchId),
            ],
            'top_customers' => CustomerResource::collection(
                $this->dashboard->topCustomers($branchId)
            ),
            'top_drivers' => DriverResource::collection(
                $this->dashboard->topDrivers($branchId)
            ),
            'recent_parcels' => ParcelResource::collection(
                $this->dashboard->recentParcels($branchId)
            ),
        ];

        // Revenue is commercially sensitive: dispatchers get counts, not money.
        if ($request->user()->can('view-revenue')) {
            $payload['charts']['monthly_revenue'] = $this->dashboard->monthlyRevenue($branchId);
        } else {
            unset($payload['statistics']['total_revenue'], $payload['statistics']['todays_revenue']);
        }

        if ($user->isSuperAdmin()) {
            $payload['branch_performance'] = $this->dashboard->branchPerformance();
        }

        return $this->success($payload);
    }
}
