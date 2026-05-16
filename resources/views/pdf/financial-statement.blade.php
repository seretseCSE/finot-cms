<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Statement — {{ $data['period_description'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 14mm 14mm 14mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            line-height: 1.55;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        /* ── HEADER ─────────────────────────────────────────────────────────── */
        .header {
            width: 100%;
            padding-bottom: 12px;
            margin-bottom: 14px;
            border-bottom: 2.5px solid #2563eb;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td {
            padding: 0;
            vertical-align: middle;
            border: none;
            background: none;
        }

        .logo-cell { width: 68px; }
        .logo-cell img { width: 58px; height: 58px; object-fit: contain; }

        .title-cell { text-align: center; }
        .title-cell h1 {
            font-size: 17px; font-weight: 700;
            color: #1d4ed8; margin: 0 0 2px;
        }
        .title-cell h2 {
            font-size: 12px; font-weight: 600;
            color: #475569; margin: 0 0 4px;
        }
        .title-cell .period-line {
            font-size: 10.5px; color: #64748b;
        }
        .title-cell .period-line strong { color: #1e293b; }
        .title-cell .eth-period {
            display: inline-block;
            padding: 1px 7px;
            background: #eff6ff;
            color: #2563eb;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }

        .meta-cell {
            text-align: right;
            width: 165px;
            font-size: 9.5px;
            color: #64748b;
            line-height: 1.7;
        }
        .meta-cell strong { color: #374151; }

        /* ── SECTION TITLES ─────────────────────────────────────────────────── */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #1d4ed8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-left: 3px solid #2563eb;
            padding: 2px 0 2px 8px;
            margin: 18px 0 8px;
        }

        /* ── KPI SUMMARY BOXES ──────────────────────────────────────────────── */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
            margin-bottom: 6px;
        }
        .kpi-table td {
            width: 25%;
            text-align: center;
            padding: 10px 6px;
            border-radius: 6px;
            vertical-align: middle;
        }
        .kpi-label {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.1;
        }
        .kpi-sub {
            font-size: 8px;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* KPI colour variants */
        .kpi--green  { background: #f0fdf4; }
        .kpi--green  .kpi-value { color: #15803d; }
        .kpi--blue   { background: #eff6ff; }
        .kpi--blue   .kpi-value { color: #1d4ed8; }
        .kpi--amber  { background: #fffbeb; }
        .kpi--amber  .kpi-value { color: #b45309; }
        .kpi--slate  { background: #f8fafc; }
        .kpi--slate  .kpi-value { color: #334155; }

        /* ── DATA TABLES ────────────────────────────────────────────────────── */
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10px;
        }
        table.data thead th {
            background: #1d4ed8;
            color: #fff;
            padding: 7px 9px;
            text-align: left;
            font-weight: 600;
            font-size: 9.5px;
            letter-spacing: 0.03em;
        }
        table.data thead th:first-child { border-radius: 4px 0 0 0; }
        table.data thead th:last-child  { border-radius: 0 4px 0 0; }

        table.data tbody td {
            padding: 7px 9px;
            border-bottom: 1px solid #e2e8f0;
            color: #374151;
        }
        table.data tbody tr:nth-child(even) td { background: #f8fafc; }
        table.data tbody tr:last-child td { border-bottom: none; }

        /* ── TOTALS ROW ─────────────────────────────────────────────────────── */
        .totals-row td {
            background: #eff6ff !important;
            font-weight: 700;
            color: #1e3a8a !important;
            border-top: 1.5px solid #bfdbfe;
            border-bottom: none;
        }

        /* ── OUTSTANDING HIGHLIGHT ──────────────────────────────────────────── */
        .outstanding-row .amt-outstanding {
            color: #dc2626;
            font-weight: 700;
        }

        /* ── DIVIDER ────────────────────────────────────────────────────────── */
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 14px 0;
        }

        /* ── FOOTER ─────────────────────────────────────────────────────────── */
        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
        }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td {
            border: none;
            background: none;
            padding: 0;
            vertical-align: top;
            font-size: 8.5px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .footer-table strong { color: #64748b; }
        .footer-note {
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
            margin-top: 6px;
            font-style: italic;
        }

        /* ── UTILITIES ──────────────────────────────────────────────────────── */
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .fw-700      { font-weight: 700; }
        .color-red   { color: #dc2626; }
        .color-green { color: #15803d; }
    </style>
</head>
<body>

@php
    /* ── Normalise Ethiopian date to a display string ── */
    $ethDate = \App\Helpers\EthiopianDateHelper::toEthiopian($data['generated_at']);
    if (is_array($ethDate)) {
        $y = $ethDate['year']  ?? ($ethDate[0] ?? '');
        $m = $ethDate['month'] ?? ($ethDate[1] ?? '');
        $d = $ethDate['day']   ?? ($ethDate[2] ?? '');
        $ethDateStr = "{$d}/{$m}/{$y}";
    } else {
        $ethDateStr = (string) $ethDate;
    }

    $footerText = $data['church_info']['footer_text'] ?? '';
    if (is_array($footerText)) { $footerText = implode(' ', $footerText); }
    $footerText = (string) $footerText;

    $summary = $data['summary'];
@endphp

{{-- ════════════════════════════════════════════════════════ HEADER ══════ --}}
<div class="header">
    <table class="header-table">
        <tr>
            @if(!empty($data['church_info']['logo']))
                <td class="logo-cell">
                    <img src="{{ public_path('storage/' . $data['church_info']['logo']) }}"
                         alt="{{ $data['church_info']['name_en'] }}">
                </td>
            @endif

            <td class="title-cell">
                <h1>{{ $data['church_info']['name_en'] }}</h1>
                <h2>{{ $data['church_info']['name_am'] }}</h2>
                <div class="period-line">
                    Financial Statement &mdash; <strong>{{ $data['period_description'] }}</strong>
                    &nbsp;&nbsp;
                    <span class="eth-period">{{ $data['ethiopian_period'] }}</span>
                </div>
            </td>

            <td class="meta-cell">
                @if(!empty($data['church_info']['address']))
                    <div>{{ $data['church_info']['address'] }}</div>
                @endif
                @if(!empty($data['church_info']['phone']))
                    <div><strong>Tel:</strong> {{ $data['church_info']['phone'] }}</div>
                @endif
                @if(!empty($data['church_info']['email']))
                    <div>{{ $data['church_info']['email'] }}</div>
                @endif
                <div><strong>Generated:</strong> {{ $data['generated_at']->format('d M Y') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ════════════════════════════════════════════════════ SUMMARY BOXES ═══ --}}

{{-- Row 1: Financial totals --}}
<table class="kpi-table">
    <tr>
        <td class="kpi--green">
            <div class="kpi-label">Total Contributions</div>
            <div class="kpi-value">ETB {{ number_format($summary['total_contributions'], 2) }}</div>
            <div class="kpi-sub">{{ $summary['contribution_count'] }} payment(s)</div>
        </td>
        <td class="kpi--green">
            <div class="kpi-label">Total Donations</div>
            <div class="kpi-value">ETB {{ number_format($summary['total_donations'], 2) }}</div>
            <div class="kpi-sub">{{ $summary['donation_count'] }} donation(s)</div>
        </td>
        <td class="kpi--blue">
            <div class="kpi-label">Grand Total</div>
            <div class="kpi-value">ETB {{ number_format($summary['grand_total'], 2) }}</div>
            <div class="kpi-sub">All revenue combined</div>
        </td>
        <td class="kpi--amber">
            <div class="kpi-label">Outstanding</div>
            <div class="kpi-value">ETB {{ number_format($summary['total_outstanding'], 2) }}</div>
            <div class="kpi-sub">Unpaid dues</div>
        </td>
    </tr>
</table>

{{-- Row 2: Income & Expenses --}}
<table class="kpi-table" style="margin-top:0">
    <tr>
        <td class="kpi--green">
            <div class="kpi-label">Total Income</div>
            <div class="kpi-value">ETB {{ number_format($summary['total_income'], 2) }}</div>
            <div class="kpi-sub">{{ $summary['transaction_count'] }} transaction(s)</div>
        </td>
        <td class="kpi--amber">
            <div class="kpi-label">Total Expenses</div>
            <div class="kpi-value">ETB {{ number_format($summary['total_expenses'], 2) }}</div>
            <div class="kpi-sub">{{ $summary['transaction_count'] }} transaction(s)</div>
        </td>
        <td class="kpi--blue">
            <div class="kpi-label">Net Income</div>
            <div class="kpi-value">ETB {{ number_format($summary['net_income'], 2) }}</div>
            <div class="kpi-sub">Income − Expenses</div>
        </td>
        <td class="kpi--slate">
            <div class="kpi-label">Transaction Records</div>
            <div class="kpi-value" style="font-size:18px">{{ $summary['transaction_count'] }}</div>
        </td>
    </tr>
</table>

{{-- ═════════════════════════════════════════════════ PERIOD BREAKDOWN ═══ --}}
@if(count($data['period_breakdown']) > 0)
    <div class="section-title">Period Breakdown</div>
    <table class="data">
        <thead>
            <tr>
                <th>Period</th>
                <th class="text-right">Contributions (ETB)</th>
                <th class="text-right">Donations (ETB)</th>
                <th class="text-right">Income (ETB)</th>
                <th class="text-right">Expenses (ETB)</th>
                <th class="text-right">Total (ETB)</th>
                <th class="text-center"># Con.</th>
                <th class="text-center"># Don.</th>
            </tr>
        </thead>
        <tbody>
            @php
                $periodTotals = ['contributions' => 0, 'donations' => 0, 'income' => 0, 'expenses' => 0, 'total' => 0, 'contribution_count' => 0, 'donation_count' => 0];
            @endphp

            @foreach($data['period_breakdown'] as $row)
                @php
                    $hasActivity = $row['total'] > 0 || $row['contribution_count'] > 0 || $row['donation_count'] > 0 || $row['income'] > 0 || $row['expenses'] > 0;
                    $periodTotals['contributions']      += $row['contributions'];
                    $periodTotals['donations']          += $row['donations'];
                    $periodTotals['income']             += $row['income'];
                    $periodTotals['expenses']           += $row['expenses'];
                    $periodTotals['total']              += $row['total'];
                    $periodTotals['contribution_count'] += $row['contribution_count'];
                    $periodTotals['donation_count']     += $row['donation_count'];
                @endphp

                @if($hasActivity || $data['period_type'] !== 'annual')
                    <tr>
                        <td class="fw-700">{{ $row['period'] }}</td>
                        <td class="text-right color-green">{{ number_format($row['contributions'], 2) }}</td>
                        <td class="text-right color-green">{{ number_format($row['donations'], 2) }}</td>
                        <td class="text-right color-green">{{ number_format($row['income'], 2) }}</td>
                        <td class="text-right color-red">{{ number_format($row['expenses'], 2) }}</td>
                        <td class="text-right fw-700">{{ number_format($row['total'], 2) }}</td>
                        <td class="text-center">{{ $row['contribution_count'] }}</td>
                        <td class="text-center">{{ $row['donation_count'] }}</td>
                    </tr>
                @endif
            @endforeach

            {{-- Totals row (only meaningful for multi-row breakdowns) --}}
            @if(count($data['period_breakdown']) > 1)
                <tr class="totals-row">
                    <td class="fw-700">Total</td>
                    <td class="text-right">{{ number_format($periodTotals['contributions'], 2) }}</td>
                    <td class="text-right">{{ number_format($periodTotals['donations'], 2) }}</td>
                    <td class="text-right">{{ number_format($periodTotals['income'], 2) }}</td>
                    <td class="text-right">{{ number_format($periodTotals['expenses'], 2) }}</td>
                    <td class="text-right">{{ number_format($periodTotals['total'], 2) }}</td>
                    <td class="text-center">{{ $periodTotals['contribution_count'] }}</td>
                    <td class="text-center">{{ $periodTotals['donation_count'] }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endif

{{-- ══════════════════════════════════════════════ GROUP PERFORMANCE ═══════ --}}
@if(count($data['group_performance']) > 0)
    <div class="section-title">Group Performance Summary</div>
    <table class="data">
        <thead>
            <tr>
                <th>Group</th>
                <th class="text-right">Total Collected (ETB)</th>
                <th class="text-center">No. of Payments</th>
                <th class="text-right">Average per Payment (ETB)</th>
                <th class="text-right">Share of Total (%)</th>
            </tr>
        </thead>
        <tbody>
            @php $grandForShare = max($summary['total_contributions'], 0.01); @endphp
            @foreach($data['group_performance'] as $group)
                <tr>
                    <td class="fw-700">{{ $group['group_name'] }}</td>
                    <td class="text-right fw-700">{{ number_format($group['total_amount'], 2) }}</td>
                    <td class="text-center">{{ $group['contribution_count'] }}</td>
                    <td class="text-right">{{ number_format($group['average_amount'], 2) }}</td>
                    <td class="text-right">{{ number_format(($group['total_amount'] / $grandForShare) * 100, 1) }}%</td>
                </tr>
            @endforeach

            <tr class="totals-row">
                <td class="fw-700">Total</td>
                <td class="text-right">{{ number_format($summary['total_contributions'], 2) }}</td>
                <td class="text-center">{{ $summary['contribution_count'] }}</td>
                <td class="text-right">—</td>
                <td class="text-right">100.0%</td>
            </tr>
        </tbody>
    </table>
@endif

{{-- ══════════════════════════════════════════ OUTSTANDING BY GROUP ════════ --}}
@if(count($data['outstanding_by_group']) > 0)
    <div class="section-title">Outstanding Contributions — Group Summary</div>
    <table class="data">
        <thead>
            <tr>
                <th>Group</th>
                <th class="text-center">Members with Dues</th>
                <th class="text-right">Total Expected (ETB)</th>
                <th class="text-right">Total Paid (ETB)</th>
                <th class="text-right">Outstanding (ETB)</th>
                <th class="text-right">Collection Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $outTotals = ['members' => 0, 'expected' => 0, 'paid' => 0, 'outstanding' => 0];
            @endphp
            @foreach($data['outstanding_by_group'] as $row)
                @php
                    $rate = $row['total_expected'] > 0
                        ? ($row['total_paid'] / $row['total_expected']) * 100
                        : 0;
                    $outTotals['members']     += $row['members_with_dues'];
                    $outTotals['expected']    += $row['total_expected'];
                    $outTotals['paid']        += $row['total_paid'];
                    $outTotals['outstanding'] += $row['total_outstanding'];
                @endphp
                <tr class="outstanding-row">
                    <td class="fw-700">{{ $row['group_name'] }}</td>
                    <td class="text-center">{{ $row['members_with_dues'] }}</td>
                    <td class="text-right">{{ number_format($row['total_expected'], 2) }}</td>
                    <td class="text-right color-green">{{ number_format($row['total_paid'], 2) }}</td>
                    <td class="text-right amt-outstanding">{{ number_format($row['total_outstanding'], 2) }}</td>
                    <td class="text-right {{ $rate >= 80 ? 'color-green' : 'color-red' }}">{{ number_format($rate, 1) }}%</td>
                </tr>
            @endforeach

            <tr class="totals-row">
                <td class="fw-700">Total</td>
                <td class="text-center">{{ $outTotals['members'] }}</td>
                <td class="text-right">{{ number_format($outTotals['expected'], 2) }}</td>
                <td class="text-right">{{ number_format($outTotals['paid'], 2) }}</td>
                <td class="text-right">{{ number_format($outTotals['outstanding'], 2) }}</td>
                @php
                    $overallRate = $outTotals['expected'] > 0
                        ? ($outTotals['paid'] / $outTotals['expected']) * 100
                        : 0;
                @endphp
                <td class="text-right">{{ number_format($overallRate, 1) }}%</td>
            </tr>
        </tbody>
    </table>
@endif

{{-- ════════════════════════════════════════════ TRANSACTION LIST ════════ --}}
@if(count($data['transactions']) > 0)
    <div class="section-title">Transaction Register (Income &amp; Expenses)</div>
    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th>Ref #</th>
                <th>Title</th>
                <th>Category</th>
                <th>Type</th>
                <th class="text-right">Amount (ETB)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $txTotals = ['income' => 0, 'expense' => 0];
            @endphp
            @foreach($data['transactions'] as $tx)
                @php
                    $isIncome = ($tx['type'] ?? '') === 'income';
                    $isIncome ? $txTotals['income'] += $tx['amount'] : $txTotals['expense'] += $tx['amount'];
                @endphp
                <tr>
                    <td>{{ \Carbon\Carbon::parse($tx['transaction_date'])->format('d M Y') }}</td>
                    <td>{{ $tx['transaction_id'] }}</td>
                    <td>{{ $tx['title'] }}</td>
                    <td>{{ $tx['category'] ?? '—' }}</td>
                    <td class="{{ $isIncome ? 'color-green' : 'color-red' }}">{{ ucfirst($tx['type'] ?? '') }}</td>
                    <td class="text-right {{ $isIncome ? 'color-green' : 'color-red' }}">
                        {{ $isIncome ? '' : '(' }}{{ number_format($tx['amount'], 2) }}{{ $isIncome ? '' : ')' }}
                    </td>
                </tr>
            @endforeach
            <tr class="totals-row">
                <td colspan="5" class="fw-700">Total Income &amp; Expenses</td>
                <td class="text-right">
                    Income: ETB {{ number_format($txTotals['income'], 2) }}<br>
                    Expenses: ETB {{ number_format($txTotals['expense'], 2) }}
                </td>
            </tr>
        </tbody>
    </table>
@endif

{{-- ════════════════════════════════════════════════════════ FOOTER ════════ --}}
<div class="footer">
    <table class="footer-table">
        <tr>
            <td>
                <strong>Generated by:</strong> {{ $data['generated_by'] }}<br>
                <strong>Date (GC):</strong> {{ $data['generated_at']->format('d M Y, H:i') }}<br>
                <strong>Date (ET):</strong> {{ $ethDateStr }}
            </td>
            <td style="text-align:right;">
                @if($footerText)
                    {{ $footerText }}<br>
                @endif
                <strong>Period:</strong> {{ $data['period_description'] }}
                &nbsp;|&nbsp;
                <strong>Type:</strong> {{ ucfirst($data['period_type']) }} Statement
            </td>
        </tr>
    </table>
    <div class="footer-note">
        This is a summarised financial report. Individual transaction records are maintained in the system.
    </div>
</div>

</body>
</html>
