<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The five business reports, each viewable on screen and exportable to
 * CSV, Excel and PDF.
 */
class ReportController extends Controller
{
    /**
     * Column key => heading, per report. Drives the on-screen table, the
     * spreadsheet headings and the PDF layout from a single definition.
     *
     * @var array<string, array{title: string, columns: array<string, string>, totals: array<int, string>}>
     */
    private const REPORTS = [
        'daily-shipments' => [
            'title' => 'Daily Shipment Report',
            'columns' => [
                'day' => 'Date',
                'total_shipments' => 'Shipments',
                'delivered' => 'Delivered',
                'in_transit' => 'In Transit',
                'failed' => 'Failed',
                'returned' => 'Returned',
                'cancelled' => 'Cancelled',
                'revenue' => 'Revenue (LKR)',
            ],
            'totals' => ['total_shipments', 'delivered', 'in_transit', 'failed', 'returned', 'cancelled', 'revenue'],
        ],
        'monthly-revenue' => [
            'title' => 'Monthly Revenue Report',
            'columns' => [
                'period' => 'Month',
                'shipments' => 'Shipments',
                'collected' => 'Collected (LKR)',
                'outstanding' => 'Outstanding (LKR)',
                'refunded' => 'Refunded (LKR)',
                'cod_revenue' => 'Cash on Delivery (LKR)',
                'prepaid_revenue' => 'Prepaid (LKR)',
            ],
            'totals' => ['shipments', 'collected', 'outstanding', 'refunded', 'cod_revenue', 'prepaid_revenue'],
        ],
        'driver-performance' => [
            'title' => 'Driver Performance Report',
            'columns' => [
                'driver_code' => 'Driver Code',
                'driver_name' => 'Driver',
                'branch' => 'Branch',
                'vehicle' => 'Vehicle',
                'vehicle_type' => 'Vehicle Type',
                'assigned' => 'Assigned',
                'completed' => 'Completed',
                'failed' => 'Failed',
                'rejected' => 'Rejected',
                'success_rate' => 'Success Rate (%)',
                'avg_minutes' => 'Avg Minutes',
                'cod_collected' => 'COD Collected (LKR)',
            ],
            'totals' => ['assigned', 'completed', 'failed', 'rejected', 'cod_collected'],
        ],
        'customer-shipments' => [
            'title' => 'Customer Shipment Report',
            'columns' => [
                'customer_code' => 'Customer ID',
                'customer_name' => 'Customer',
                'company' => 'Company',
                'mobile' => 'Mobile',
                'city' => 'City',
                'total_shipments' => 'Shipments',
                'delivered' => 'Delivered',
                'failed' => 'Failed',
                'total_spend' => 'Total Spend (LKR)',
            ],
            'totals' => ['total_shipments', 'delivered', 'failed', 'total_spend'],
        ],
        'deliveries' => [
            'title' => 'Delivery Report',
            'columns' => [
                'tracking_number' => 'Tracking Number',
                'driver' => 'Driver',
                'driver_code' => 'Driver Code',
                'receiver' => 'Receiver',
                'destination' => 'Destination',
                'status' => 'Status',
                'attempt' => 'Attempt',
                'assigned_at' => 'Assigned',
                'completed_at' => 'Completed',
                'duration' => 'Duration',
                'received_by' => 'Received By',
                'cod_collected' => 'COD (LKR)',
                'notes' => 'Notes',
            ],
            'totals' => ['cod_collected'],
        ],
    ];

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $this->authorize('view-reports');

        return view('reports.index', $this->sharedViewData($request));
    }

    public function dailyShipments(Request $request): View
    {
        return $this->renderReport($request, 'daily-shipments');
    }

    public function monthlyRevenue(Request $request): View
    {
        return $this->renderReport($request, 'monthly-revenue');
    }

    public function driverPerformance(Request $request): View
    {
        return $this->renderReport($request, 'driver-performance');
    }

    public function customerShipments(Request $request): View
    {
        return $this->renderReport($request, 'customer-shipments');
    }

    public function deliveries(Request $request): View
    {
        return $this->renderReport($request, 'deliveries');
    }

    /**
     * Download any report in any of the three formats.
     */
    public function export(Request $request, string $report, string $format): BinaryFileResponse|HttpResponse
    {
        $this->authorize('view-reports');

        $definition = self::REPORTS[$report];
        $filters = $this->resolveFilters($request);
        $rows = $this->rowsFor($report, $filters);

        $filename = sprintf(
            '%s_%s_%s',
            str_replace('-', '_', $report),
            $filters['from']->format('Ymd'),
            $filters['to']->format('Ymd')
        );

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf', [
                'title' => $definition['title'],
                'columns' => $definition['columns'],
                'rows' => $rows,
                'totals' => $this->reports->totals($rows, $definition['totals']),
                'filters' => $filters,
                'generatedBy' => $request->user()->name,
            ])->setPaper('a4', 'landscape');

            return $pdf->download("{$filename}.pdf");
        }

        $export = new ReportExport($rows, $definition['columns'], $definition['title']);

        return $format === 'csv'
            ? Excel::download($export, "{$filename}.csv", ExcelFormat::CSV)
            : Excel::download($export, "{$filename}.xlsx", ExcelFormat::XLSX);
    }

    /**
     * Shared render path for all five on-screen reports.
     */
    private function renderReport(Request $request, string $report): View
    {
        $this->authorize('view-reports');

        $definition = self::REPORTS[$report];
        $filters = $this->resolveFilters($request);
        $rows = $this->rowsFor($report, $filters);

        return view("reports.{$report}", [
            ...$this->sharedViewData($request),
            'report' => $report,
            'title' => $definition['title'],
            'columns' => $definition['columns'],
            'rows' => $rows,
            'totals' => $this->reports->totals($rows, $definition['totals']),
            'filters' => $filters,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsFor(string $report, array $filters): Collection
    {
        return match ($report) {
            'daily-shipments' => $this->reports->dailyShipments($filters),
            'monthly-revenue' => $this->reports->monthlyRevenue($filters),
            'driver-performance' => $this->reports->driverPerformance($filters),
            'customer-shipments' => $this->reports->customerShipments($filters),
            'deliveries' => $this->reports->deliveries($filters),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        // A Branch Manager's reports are pinned to their own branch, whatever
        // the query string says.
        return $this->reports->filters(
            $request->only(['from', 'to', 'branch_id', 'driver_id', 'customer_id']),
            $request->user()->visibleBranchId()
        );
    }

    /**
     * Filter dropdown data shared by every report screen.
     *
     * @return array<string, mixed>
     */
    private function sharedViewData(Request $request): array
    {
        $user = $request->user();

        return [
            'branches' => $this->visibleBranches($user),
            'driverOptions' => Driver::query()
                ->visibleTo($user)
                ->orderBy('full_name')
                ->get()
                ->pluck('label', 'id'),
            'customerOptions' => Customer::query()
                ->visibleTo($user)
                ->orderBy('full_name')
                ->limit(500)
                ->get()
                ->mapWithKeys(fn (Customer $c): array => [
                    $c->id => "{$c->full_name} ({$c->customer_code})",
                ]),
            'reportLinks' => collect(self::REPORTS)->map(fn (array $r, string $key): array => [
                'key' => $key,
                'title' => $r['title'],
            ])->values(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function visibleBranches(User $user): \Illuminate\Support\Collection
    {
        return Branch::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get()
            ->pluck('label', 'id');
    }
}
