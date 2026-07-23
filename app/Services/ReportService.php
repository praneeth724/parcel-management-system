<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\ParcelStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Parcel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query layer for the five business reports.
 *
 * Each report returns a plain Collection of rows so the same data can be
 * rendered on screen, streamed to CSV/Excel, or laid out in a PDF without
 * being recomputed differently in three places.
 */
class ReportService
{
    /**
     * Normalise the report filters coming off the query string.
     *
     * @param  array<string, mixed>  $input
     * @return array{from: Carbon, to: Carbon, branch_id: int|null, driver_id: int|null, customer_id: int|null}
     */
    public function filters(array $input, ?int $forcedBranchId = null): array
    {
        $from = filled($input['from'] ?? null)
            ? Carbon::parse($input['from'])->startOfDay()
            : now()->startOfMonth();

        $to = filled($input['to'] ?? null)
            ? Carbon::parse($input['to'])->endOfDay()
            : now()->endOfDay();

        // A reversed range is a typo, not an error worth stopping for.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [
            'from' => $from,
            'to' => $to,
            // A Branch Manager cannot widen the scope past their own branch.
            'branch_id' => $forcedBranchId ?? (filled($input['branch_id'] ?? null) ? (int) $input['branch_id'] : null),
            'driver_id' => filled($input['driver_id'] ?? null) ? (int) $input['driver_id'] : null,
            'customer_id' => filled($input['customer_id'] ?? null) ? (int) $input['customer_id'] : null,
        ];
    }

    /**
     * Shipments booked per day, with outcome counts and revenue.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function dailyShipments(array $filters): Collection
    {
        return Parcel::query()
            ->ofBranch($filters['branch_id'])
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as total_shipments')
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered")
            ->selectRaw("SUM(CASE WHEN status = 'failed_delivery' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->selectRaw("SUM(CASE WHEN status IN ('pending','picked_up','at_warehouse','out_for_delivery') THEN 1 ELSE 0 END) as in_transit")
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN delivery_charge ELSE 0 END) as revenue")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => [
                'day' => Carbon::parse($row->day)->format('Y-m-d'),
                'total_shipments' => (int) $row->total_shipments,
                'delivered' => (int) $row->delivered,
                'in_transit' => (int) $row->in_transit,
                'failed' => (int) $row->failed,
                'returned' => (int) $row->returned,
                'cancelled' => (int) $row->cancelled,
                'revenue' => round((float) $row->revenue, 2),
            ]);
    }

    /**
     * Revenue by month, split by payment method.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function monthlyRevenue(array $filters): Collection
    {
        return Parcel::query()
            ->ofBranch($filters['branch_id'])
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('COUNT(*) as shipments')
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN delivery_charge ELSE 0 END) as collected")
            ->selectRaw("SUM(CASE WHEN payment_status = 'pending' THEN delivery_charge ELSE 0 END) as outstanding")
            ->selectRaw("SUM(CASE WHEN payment_status = 'refunded' THEN delivery_charge ELSE 0 END) as refunded")
            ->selectRaw("SUM(CASE WHEN payment_method = 'cash_on_delivery' AND payment_status = 'paid' THEN delivery_charge ELSE 0 END) as cod_revenue")
            ->selectRaw("SUM(CASE WHEN payment_method != 'cash_on_delivery' AND payment_status = 'paid' THEN delivery_charge ELSE 0 END) as prepaid_revenue")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row): array => [
                'period' => Carbon::createFromFormat('Y-m', $row->period)->format('F Y'),
                'shipments' => (int) $row->shipments,
                'collected' => round((float) $row->collected, 2),
                'outstanding' => round((float) $row->outstanding, 2),
                'refunded' => round((float) $row->refunded, 2),
                'cod_revenue' => round((float) $row->cod_revenue, 2),
                'prepaid_revenue' => round((float) $row->prepaid_revenue, 2),
            ]);
    }

    /**
     * Per-driver delivery outcomes and success rate.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function driverPerformance(array $filters): Collection
    {
        return Driver::query()
            ->ofBranch($filters['branch_id'])
            ->when($filters['driver_id'], fn ($q) => $q->whereKey($filters['driver_id']))
            ->with('branch:id,name,code')
            ->withCount([
                'deliveries as assigned_count' => fn ($q) => $q
                    ->whereBetween('assigned_at', [$filters['from'], $filters['to']]),
                'deliveries as completed_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Completed)
                    ->whereBetween('assigned_at', [$filters['from'], $filters['to']]),
                'deliveries as failed_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Failed)
                    ->whereBetween('assigned_at', [$filters['from'], $filters['to']]),
                'deliveries as rejected_count' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Rejected)
                    ->whereBetween('assigned_at', [$filters['from'], $filters['to']]),
            ])
            ->withSum([
                'deliveries as cod_collected' => fn ($q) => $q
                    ->where('status', DeliveryStatus::Completed)
                    ->whereBetween('assigned_at', [$filters['from'], $filters['to']]),
            ], 'cod_collected')
            ->orderByDesc('completed_count')
            ->get()
            ->map(function (Driver $driver) use ($filters): array {
                $finished = $driver->completed_count + $driver->failed_count;

                return [
                    'driver_code' => $driver->driver_code,
                    'driver_name' => $driver->full_name,
                    'branch' => $driver->branch?->name ?? '—',
                    'vehicle' => $driver->vehicle_number,
                    'vehicle_type' => $driver->vehicle_type->label(),
                    'assigned' => (int) $driver->assigned_count,
                    'completed' => (int) $driver->completed_count,
                    'failed' => (int) $driver->failed_count,
                    'rejected' => (int) $driver->rejected_count,
                    'success_rate' => $finished === 0
                        ? 0.0
                        : round(($driver->completed_count / $finished) * 100, 1),
                    'cod_collected' => round((float) ($driver->cod_collected ?? 0), 2),
                    'avg_minutes' => $this->averageMinutesForDriver($driver->id, $filters),
                ];
            });
    }

    /**
     * Shipment volume and spend per customer.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function customerShipments(array $filters): Collection
    {
        $window = fn ($q) => $q->whereBetween('parcels.created_at', [$filters['from'], $filters['to']])
            ->when($filters['branch_id'], fn ($inner) => $inner->where('parcels.branch_id', $filters['branch_id']));

        return Customer::query()
            ->ofBranch($filters['branch_id'])
            ->when($filters['customer_id'], fn ($q) => $q->whereKey($filters['customer_id']))
            ->withCount([
                'parcels as total_shipments' => $window,
                'parcels as delivered_count' => fn ($q) => $window($q)
                    ->where('parcels.status', ParcelStatus::Delivered),
                'parcels as failed_count' => fn ($q) => $window($q)
                    ->where('parcels.status', ParcelStatus::FailedDelivery),
            ])
            ->withSum([
                'parcels as total_spend' => fn ($q) => $window($q)
                    ->where('parcels.payment_status', PaymentStatus::Paid),
            ], 'delivery_charge')
            ->having('total_shipments', '>', 0)
            ->orderByDesc('total_shipments')
            ->get()
            ->map(fn (Customer $customer): array => [
                'customer_code' => $customer->customer_code,
                'customer_name' => $customer->full_name,
                'company' => $customer->company_name ?? '—',
                'mobile' => $customer->mobile,
                'city' => $customer->city,
                'total_shipments' => (int) $customer->total_shipments,
                'delivered' => (int) $customer->delivered_count,
                'failed' => (int) $customer->failed_count,
                'total_spend' => round((float) ($customer->total_spend ?? 0), 2),
            ]);
    }

    /**
     * Row-per-delivery detail report.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function deliveries(array $filters): Collection
    {
        return Delivery::query()
            ->with([
                'parcel:id,tracking_number,receiver_name,receiver_city,delivery_charge,payment_method',
                'driver:id,full_name,driver_code',
            ])
            ->when($filters['branch_id'], fn ($q) => $q->ofBranch($filters['branch_id']))
            ->when($filters['driver_id'], fn ($q) => $q->where('driver_id', $filters['driver_id']))
            ->whereBetween('assigned_at', [$filters['from'], $filters['to']])
            ->latest('assigned_at')
            ->get()
            ->map(fn (Delivery $delivery): array => [
                'tracking_number' => $delivery->parcel?->tracking_number ?? '—',
                'driver' => $delivery->driver?->full_name ?? '—',
                'driver_code' => $delivery->driver?->driver_code ?? '—',
                'receiver' => $delivery->parcel?->receiver_name ?? '—',
                'destination' => $delivery->parcel?->receiver_city ?? '—',
                'status' => $delivery->status->label(),
                'attempt' => $delivery->attempt_number,
                'assigned_at' => $delivery->assigned_at?->format('Y-m-d H:i'),
                'completed_at' => $delivery->completed_at?->format('Y-m-d H:i') ?? '—',
                'duration' => $delivery->duration_for_humans,
                'received_by' => $delivery->received_by ?? '—',
                'cod_collected' => round((float) ($delivery->cod_collected ?? 0), 2),
                'notes' => $delivery->failure_reason ?? $delivery->rejection_reason ?? $delivery->notes ?? '—',
            ]);
    }

    /**
     * Totals row shown beneath each report and in the exports.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $numericColumns
     * @return array<string, float|int>
     */
    public function totals(Collection $rows, array $numericColumns): array
    {
        $totals = [];

        foreach ($numericColumns as $column) {
            $totals[$column] = round((float) $rows->sum($column), 2);
        }

        return $totals;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function averageMinutesForDriver(int $driverId, array $filters): ?int
    {
        $average = Delivery::query()
            ->where('driver_id', $driverId)
            ->where('status', DeliveryStatus::Completed)
            ->whereNotNull('accepted_at')
            ->whereNotNull('completed_at')
            ->whereBetween('assigned_at', [$filters['from'], $filters['to']])
            ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, accepted_at, completed_at)'));

        return $average === null ? null : (int) round((float) $average);
    }
}
