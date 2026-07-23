<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\DriverStatus;
use App\Enums\ParcelStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Parcel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the dashboard widgets and chart series.
 *
 * Every query is scoped to the branch the viewer is allowed to see, so the same
 * methods serve the Super Admin (all branches) and a Branch Manager (one).
 */
class DashboardService
{
    /**
     * Headline counters shown as stat cards.
     *
     * @return array<string, mixed>
     */
    public function statistics(?int $branchId = null): array
    {
        $parcels = fn () => Parcel::query()->ofBranch($branchId);
        $deliveries = fn () => Delivery::query()->ofBranch($branchId);
        $drivers = fn () => Driver::query()->ofBranch($branchId);

        return [
            'todays_shipments' => $parcels()->createdToday()->count(),
            'todays_deliveries' => $deliveries()->completedToday()->count(),
            'pending_deliveries' => $parcels()->inTransit()->count(),
            'delivered_parcels' => $parcels()->status(ParcelStatus::Delivered)->count(),
            'failed_deliveries' => $parcels()->status(ParcelStatus::FailedDelivery)->count(),
            'returned_parcels' => $parcels()->status(ParcelStatus::Returned)->count(),
            'total_revenue' => (float) $parcels()->revenueCounted()->sum('delivery_charge'),
            'todays_revenue' => (float) $parcels()
                ->revenueCounted()
                ->whereDate('created_at', today())
                ->sum('delivery_charge'),
            'available_drivers' => $drivers()->status(DriverStatus::Available)->count(),
            'drivers_on_delivery' => $drivers()->status(DriverStatus::OnDelivery)->count(),
            'total_drivers' => $drivers()->count(),
            'total_customers' => Customer::query()->ofBranch($branchId)->count(),
            'total_parcels' => $parcels()->count(),
            'overdue_parcels' => $parcels()
                ->whereNotIn('status', [
                    ParcelStatus::Delivered->value,
                    ParcelStatus::Returned->value,
                    ParcelStatus::Cancelled->value,
                ])
                ->whereNotNull('expected_delivery_at')
                ->where('expected_delivery_at', '<', now())
                ->count(),
            'unassigned_parcels' => $parcels()->unassigned()->count(),
            'pending_cod' => (float) $parcels()
                ->where('payment_status', PaymentStatus::Pending)
                ->sum('delivery_charge'),
        ];
    }

    /**
     * Customers ranked by shipment volume.
     *
     * @return Collection<int, Customer>
     */
    public function topCustomers(?int $branchId = null, int $limit = 5): Collection
    {
        return Customer::query()
            ->ofBranch($branchId)
            ->withCount(['parcels as shipments_count' => fn ($q) => $q->when(
                $branchId,
                fn ($inner) => $inner->where('branch_id', $branchId)
            )])
            ->withSum(['parcels as revenue' => fn ($q) => $q
                ->where('payment_status', PaymentStatus::Paid)
                ->when($branchId, fn ($inner) => $inner->where('branch_id', $branchId)),
            ], 'delivery_charge')
            ->having('shipments_count', '>', 0)
            ->orderByDesc('shipments_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Drivers ranked by completed deliveries, with their success rate.
     *
     * @return Collection<int, Driver>
     */
    public function topDrivers(?int $branchId = null, int $limit = 5): Collection
    {
        return Driver::query()
            ->ofBranch($branchId)
            ->with('branch:id,name,code')
            ->withCount([
                'deliveries as completed_deliveries_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Completed),
                'deliveries as failed_deliveries_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Failed),
            ])
            ->having('completed_deliveries_count', '>', 0)
            ->orderByDesc('completed_deliveries_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Shipment counts for the last N months, ready for Chart.js.
     *
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function monthlyShipments(?int $branchId = null, int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $rows = Parcel::query()
            ->ofBranch($branchId)
            ->where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        return $this->fillMonthlySeries($start, $months, $rows, castTo: 'int');
    }

    /**
     * Settled revenue per month.
     *
     * @return array{labels: array<int, string>, data: array<int, float>}
     */
    public function monthlyRevenue(?int $branchId = null, int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $rows = Parcel::query()
            ->ofBranch($branchId)
            ->revenueCounted()
            ->where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, SUM(delivery_charge) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        return $this->fillMonthlySeries($start, $months, $rows, castTo: 'float');
    }

    /**
     * Delivered vs failed vs returned, as a share of finished parcels.
     *
     * @return array{
     *     labels: array<int, string>,
     *     data: array<int, int>,
     *     colors: array<int, string>,
     *     success_rate: float
     * }
     */
    public function deliverySuccessRate(?int $branchId = null): array
    {
        $counts = Parcel::query()
            ->ofBranch($branchId)
            ->whereIn('status', [
                ParcelStatus::Delivered->value,
                ParcelStatus::FailedDelivery->value,
                ParcelStatus::Returned->value,
            ])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $delivered = (int) ($counts[ParcelStatus::Delivered->value] ?? 0);
        $failed = (int) ($counts[ParcelStatus::FailedDelivery->value] ?? 0);
        $returned = (int) ($counts[ParcelStatus::Returned->value] ?? 0);

        $finished = $delivered + $failed + $returned;

        return [
            'labels' => ['Delivered', 'Failed', 'Returned'],
            'data' => [$delivered, $failed, $returned],
            'colors' => ['#198754', '#dc3545', '#6c757d'],
            'success_rate' => $finished === 0 ? 0.0 : round(($delivered / $finished) * 100, 1),
        ];
    }

    /**
     * Parcel counts grouped by lifecycle status, for the status breakdown card.
     *
     * @return array<int, array{label: string, value: string, count: int, color: string}>
     */
    public function statusBreakdown(?int $branchId = null): array
    {
        $counts = Parcel::query()
            ->ofBranch($branchId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(ParcelStatus::cases())
            ->map(fn (ParcelStatus $status): array => [
                'label' => $status->label(),
                'value' => $status->value,
                'count' => (int) ($counts[$status->value] ?? 0),
                'color' => $status->color(),
            ])
            ->all();
    }

    /**
     * Shipments booked per day over the last N days.
     *
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function dailyShipments(?int $branchId = null, int $days = 14): array
    {
        $start = today()->subDays($days - 1);

        $rows = Parcel::query()
            ->ofBranch($branchId)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as period, COUNT(*) as total')
            ->groupBy('period')
            ->pluck('total', 'period');

        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $labels[] = $day->format('d M');
            $data[] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * The newest parcels, for the dashboard activity table.
     *
     * @return Collection<int, Parcel>
     */
    public function recentParcels(?int $branchId = null, int $limit = 8): Collection
    {
        return Parcel::query()
            ->ofBranch($branchId)
            ->with(['customer:id,full_name,customer_code', 'branch:id,name,code'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Everything a single driver's dashboard needs.
     *
     * @return array<string, mixed>
     */
    public function driverStatistics(Driver $driver): array
    {
        $deliveries = fn () => Delivery::query()->where('driver_id', $driver->id);

        $completed = $deliveries()->where('status', DeliveryStatus::Completed)->count();
        $failed = $deliveries()->where('status', DeliveryStatus::Failed)->count();
        $finished = $completed + $failed;

        return [
            'pending_response' => $deliveries()->where('status', DeliveryStatus::Assigned)->count(),
            'accepted' => $deliveries()->where('status', DeliveryStatus::Accepted)->count(),
            'in_transit' => $deliveries()->where('status', DeliveryStatus::InTransit)->count(),
            'active_total' => $deliveries()->whereIn('status', DeliveryStatus::activeValues())->count(),
            'completed_today' => $deliveries()
                ->where('status', DeliveryStatus::Completed)
                ->whereDate('completed_at', today())
                ->count(),
            'completed_this_month' => $deliveries()
                ->where('status', DeliveryStatus::Completed)
                ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'completed_total' => $completed,
            'failed_total' => $failed,
            'success_rate' => $finished === 0 ? 0.0 : round(($completed / $finished) * 100, 1),
            'cod_collected_today' => (float) $deliveries()
                ->where('status', DeliveryStatus::Completed)
                ->whereDate('completed_at', today())
                ->sum('cod_collected'),
        ];
    }

    /**
     * A driver's completed deliveries per day over the last N days.
     *
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function driverDailyPerformance(Driver $driver, int $days = 14): array
    {
        $start = today()->subDays($days - 1);

        $rows = Delivery::query()
            ->where('driver_id', $driver->id)
            ->where('status', DeliveryStatus::Completed)
            ->where('completed_at', '>=', $start)
            ->selectRaw('DATE(completed_at) as period, COUNT(*) as total')
            ->groupBy('period')
            ->pluck('total', 'period');

        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $labels[] = $day->format('d M');
            $data[] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Per-branch summary table for the Super Admin dashboard.
     *
     * @return Collection<int, object>
     */
    public function branchPerformance(): Collection
    {
        return DB::table('branches')
            ->leftJoin('parcels', function ($join): void {
                $join->on('parcels.branch_id', '=', 'branches.id')
                    ->whereNull('parcels.deleted_at');
            })
            ->whereNull('branches.deleted_at')
            ->groupBy('branches.id', 'branches.name', 'branches.code', 'branches.city')
            ->orderByDesc('total_parcels')
            ->select([
                'branches.id',
                'branches.name',
                'branches.code',
                'branches.city',
                DB::raw('COUNT(parcels.id) as total_parcels'),
                DB::raw("SUM(CASE WHEN parcels.status = 'delivered' THEN 1 ELSE 0 END) as delivered"),
                DB::raw("SUM(CASE WHEN parcels.status = 'failed_delivery' THEN 1 ELSE 0 END) as failed"),
                DB::raw("SUM(CASE WHEN parcels.payment_status = 'paid' THEN parcels.delivery_charge ELSE 0 END) as revenue"),
            ])
            ->get();
    }

    /**
     * Turn a sparse `YYYY-MM => total` map into a dense 12-month series so the
     * chart shows zero months instead of skipping them.
     *
     * @param  Collection<string, mixed>  $rows
     * @return array{labels: array<int, string>, data: array<int, mixed>}
     */
    private function fillMonthlySeries(Carbon $start, int $months, Collection $rows, string $castTo): array
    {
        $labels = [];
        $data = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $labels[] = $month->format('M Y');
            $value = $rows[$key] ?? 0;
            $data[] = $castTo === 'int' ? (int) $value : round((float) $value, 2);
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
