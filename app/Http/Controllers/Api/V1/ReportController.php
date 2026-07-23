<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only JSON access to the same five reports the web UI renders.
 */
class ReportController extends ApiController
{
    public function __construct(private readonly ReportService $reports) {}

    public function dailyShipments(Request $request): JsonResponse
    {
        return $this->report($request, 'daily_shipments');
    }

    public function monthlyRevenue(Request $request): JsonResponse
    {
        return $this->report($request, 'monthly_revenue');
    }

    public function driverPerformance(Request $request): JsonResponse
    {
        return $this->report($request, 'driver_performance');
    }

    public function customerShipments(Request $request): JsonResponse
    {
        return $this->report($request, 'customer_shipments');
    }

    public function deliveries(Request $request): JsonResponse
    {
        return $this->report($request, 'deliveries');
    }

    private function report(Request $request, string $method): JsonResponse
    {
        $this->authorize('view-reports');

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'driver_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        // A Branch Manager's scope is forced to their own branch.
        $filters = $this->reports->filters(
            $request->only(['from', 'to', 'branch_id', 'driver_id', 'customer_id']),
            $request->user()->visibleBranchId()
        );

        $rows = $this->reports->{\Illuminate\Support\Str::camel($method)}($filters);

        return $this->success($rows, meta: [
            'report' => $method,
            'from' => $filters['from']->toDateString(),
            'to' => $filters['to']->toDateString(),
            'branch_id' => $filters['branch_id'],
            'row_count' => $rows->count(),
        ]);
    }
}
