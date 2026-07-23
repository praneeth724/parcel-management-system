{{--
    dompdf rendering of any report. Styles are inlined because dompdf cannot
    load the Vite bundle.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12mm 10mm; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1e293b;
        }

        .header {
            border-bottom: 2px solid #1a56db;
            padding-bottom: 4mm;
            margin-bottom: 4mm;
        }

        .brand { font-size: 14px; font-weight: bold; color: #1a56db; }
        .title { font-size: 16px; font-weight: bold; margin: 1mm 0; }
        .meta  { font-size: 9px; color: #64748b; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
        }

        th {
            background-color: #1a56db;
            color: #fff;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 2mm 1.5mm;
            text-align: left;
            border: 1px solid #1a56db;
        }

        td {
            padding: 1.5mm;
            border: 1px solid #cbd5e1;
            font-size: 8.5px;
        }

        tr:nth-child(even) td { background-color: #f8fafc; }

        tfoot td {
            background-color: #e2e8f0 !important;
            font-weight: bold;
        }

        .num { text-align: right; }

        .footer {
            position: fixed;
            bottom: -8mm;
            left: 0;
            right: 0;
            font-size: 7.5px;
            color: #94a3b8;
            text-align: center;
        }

        .empty {
            text-align: center;
            padding: 20mm;
            color: #94a3b8;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="brand">{{ config('app.name') }}</div>
    <div class="title">{{ $title }}</div>
    <div class="meta">
        Period: {{ $filters['from']->format('d M Y') }} — {{ $filters['to']->format('d M Y') }}
        &nbsp;|&nbsp; Generated: {{ now()->format('d M Y, H:i') }}
        &nbsp;|&nbsp; By: {{ $generatedBy }}
        &nbsp;|&nbsp; Rows: {{ $rows->count() }}
    </div>
</div>

@if ($rows->isEmpty())
    <div class="empty">No data was found for this period.</div>
@else
    <table>
        <thead>
            <tr>
                @foreach ($columns as $key => $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($columns as $key => $heading)
                        <td class="{{ is_numeric($row[$key] ?? null) ? 'num' : '' }}">
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
            <tfoot>
                <tr>
                    @foreach ($columns as $key => $heading)
                        <td class="{{ isset($totals[$key]) ? 'num' : '' }}">
                            @if ($loop->first)
                                TOTAL
                            @elseif (isset($totals[$key]))
                                {{ number_format($totals[$key], str_contains(strtolower($heading), 'lkr') ? 2 : 0) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
@endif

<div class="footer">
    {{ config('app.name') }} — Courier &amp; Parcel Management System
</div>

</body>
</html>
