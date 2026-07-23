{{--
    Shared shell for all five reports: filter bar, export buttons, data table
    and totals row. Each report view supplies only $title, $columns and $rows.
--}}

@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title"
                   :subtitle="$filters['from']->format('d M Y').' — '.$filters['to']->format('d M Y')"
                   :back="route('reports.index')">
        <x-slot:actions>
            <div class="btn-group">
                <a href="{{ route('reports.export', [$report, 'csv']).'?'.http_build_query(request()->query()) }}"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-filetype-csv me-1"></i> CSV
                </a>
                <a href="{{ route('reports.export', [$report, 'xlsx']).'?'.http_build_query(request()->query()) }}"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                </a>
                <a href="{{ route('reports.export', [$report, 'pdf']).'?'.http_build_query(request()->query()) }}"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                </a>
            </div>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="filter-bar no-print">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label for="from" class="form-label small fw-semibold">From</label>
                <input type="date" id="from" name="from"
                       value="{{ $filters['from']->format('Y-m-d') }}" class="form-control">
            </div>

            <div class="col-6 col-md-2">
                <label for="to" class="form-label small fw-semibold">To</label>
                <input type="date" id="to" name="to"
                       value="{{ $filters['to']->format('Y-m-d') }}" class="form-control">
            </div>

            @if (auth()->user()->isSuperAdmin() && $branches->isNotEmpty())
                <div class="col-md-3">
                    <label for="branch_id" class="form-label small fw-semibold">Branch</label>
                    <select id="branch_id" name="branch_id" class="form-select">
                        <option value="">All branches</option>
                        @foreach ($branches as $id => $label)
                            <option value="{{ $id }}" @selected((string) $filters['branch_id'] === (string) $id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if (in_array($report, ['driver-performance', 'deliveries'], true))
                <div class="col-md-3">
                    <label for="driver_id" class="form-label small fw-semibold">Driver</label>
                    <select id="driver_id" name="driver_id" class="form-select">
                        <option value="">All drivers</option>
                        @foreach ($driverOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) $filters['driver_id'] === (string) $id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($report === 'customer-shipments')
                <div class="col-md-3">
                    <label for="customer_id" class="form-label small fw-semibold">Customer</label>
                    <select id="customer_id" name="customer_id" class="form-select">
                        <option value="">All customers</option>
                        @foreach ($customerOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) $filters['customer_id'] === (string) $id)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Apply
                </button>
                <a href="{{ url()->current() }}" class="btn btn-outline-secondary" title="Reset">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>

    {{-- Optional per-report summary block --}}
    @hasSection('summary')
        @yield('summary')
    @endif

    <div class="table-card">
        @if ($rows->isEmpty())
            <x-empty-state icon="bi-file-earmark-bar-graph"
                           title="No data for this period"
                           message="Try a wider date range or a different filter." />
        @else
            <div class="table-card__scroll">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach ($columns as $key => $heading)
                                <th class="{{ is_numeric($rows->first()[$key] ?? null) ? 'text-end' : '' }}">
                                    {{ $heading }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($columns as $key => $heading)
                                    <td class="{{ is_numeric($row[$key] ?? null) ? 'text-end' : '' }}">
                                        @if (str_contains(strtolower($heading), 'lkr'))
                                            {{ number_format((float) ($row[$key] ?? 0), 2) }}
                                        @else
                                            {{ $row[$key] ?? '—' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>

                    @if ($totals !== [])
                        <tfoot class="table-light fw-bold">
                            <tr>
                                @foreach ($columns as $key => $heading)
                                    <td class="{{ isset($totals[$key]) ? 'text-end' : '' }}">
                                        @if ($loop->first)
                                            Total ({{ $rows->count() }} rows)
                                        @elseif (isset($totals[$key]))
                                            {{ number_format($totals[$key], str_contains(strtolower($heading), 'lkr') ? 2 : 0) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
    </div>
@endsection
