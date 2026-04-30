<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Statement - {{ $data['period_description'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #1a1a2e;
            margin: 0;
            padding: 0;
        }

        /* ── Header ──────────────────────────────────────────────── */
        .header {
            width: 100%;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { padding: 0; vertical-align: middle; border: none; background: none; }

        .logo-cell { width: 70px; }
        .logo-cell img { width: 60px; height: 60px; object-fit: contain; }

        .title-cell { text-align: center; }
        .title-cell h1 { font-size: 18px; font-weight: 700; color: #2563eb; margin: 0 0 2px; }
        .title-cell h2 { font-size: 13px; font-weight: 600; color: #4b5563; margin: 0 0 4px; }
        .title-cell p  { font-size: 11px; color: #6b7280; margin: 0; }

        .meta-cell { text-align: right; width: 170px; font-size: 10px; color: #6b7280; line-height: 1.6; }
        .meta-cell strong { color: #374151; }

        /* ── Summary boxes ───────────────────────────────────────── */
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 16px; }

        .summary td {
            width: 25%;
            text-align: center;
            padding: 10px 6px;
            border: 1px solid #e5e7eb;
            background: #f8faff;
            vertical-align: middle;
        }

        .box-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin-bottom: 3px;
        }

        .box-value        { font-size: 15px; font-weight: 700; color: #1e3a8a; }
        .box-value.green  { color: #15803d; }
        .box-value.orange { color: #c2410c; }
        .box-value.count  { color: #374151; font-size: 17px; }

        /* ── Section titles ──────────────────────────────────────── */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #2563eb;
            border-bottom: 1px solid #bfdbfe;
            padding-bottom: 4px;
            margin: 16px 0 8px;
        }

        /* ── Data tables ─────────────────────────────────────────── */
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 10px; }

        table.data thead th {
            background-color: #2563eb;
            color: #fff;
            padding: 6px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 10px;
        }

        table.data tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
        }

        table.data tbody tr:nth-child(even) td { background-color: #f9fafb; }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .fw-600      { font-weight: 600; }
        .color-red   { color: #dc2626; }

        /* ── Footer ──────────────────────────────────────────────── */
        .footer {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #d1d5db;
        }

        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { border: none; background: none; padding: 0; vertical-align: top; font-size: 9px; color: #9ca3af; }
        .footer-table strong { color: #6b7280; }
    </style>
</head>
<body>

@php
    // ── Safe helpers ──────────────────────────────────────────────────────────
    // toEthiopian() may return an array ['year','month','day'] or [y,m,d] or a string.
    // We normalise everything to a display string here, once, so every {{ }} below
    // is guaranteed to receive a scalar value.
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
@endphp

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
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
                    <p>
                        Financial Statement &mdash;
                        <strong>{{ $data['period_description'] }}</strong>
                        &nbsp;|&nbsp;
                        <span style="color:#2563eb;">{{ $data['ethiopian_period'] }}</span>
                    </p>
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
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Summary Boxes ───────────────────────────────────────────────── --}}
    <table class="summary">
        <tr>
            <td>
                <div class="box-label">Total Contributions</div>
                <div class="box-value green">ETB {{ number_format($data['summary']['total_contributions'], 2) }}</div>
            </td>
            <td>
                <div class="box-label">Total Donations</div>
                <div class="box-value green">ETB {{ number_format($data['summary']['total_donations'], 2) }}</div>
            </td>
            <td>
                <div class="box-label">Grand Total</div>
                <div class="box-value">ETB {{ number_format($data['summary']['grand_total'], 2) }}</div>
            </td>
            <td>
                <div class="box-label">Outstanding</div>
                <div class="box-value orange">ETB {{ number_format($data['summary']['total_outstanding'], 2) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="box-label">Contributions</div>
                <div class="box-value count">{{ $data['summary']['contribution_count'] }}</div>
            </td>
            <td>
                <div class="box-label">Donations</div>
                <div class="box-value count">{{ $data['summary']['donation_count'] }}</div>
            </td>
            <td>
                <div class="box-label">Unique Contributors</div>
                <div class="box-value count">{{ $data['summary']['unique_contributors'] }}</div>
            </td>
            <td>
                <div class="box-label">Unique Donors</div>
                <div class="box-value count">{{ $data['summary']['unique_donors'] }}</div>
            </td>
        </tr>
    </table>

    {{-- ── Period Breakdown ────────────────────────────────────────────── --}}
    @if(count($data['contributions_by_month']) > 0)
        <div class="section-title">Period Breakdown</div>
        <table class="data">
            <thead>
                <tr>
                    <th>Period</th>
                    <th class="text-right">Contributions (ETB)</th>
                    <th class="text-right">Donations (ETB)</th>
                    <th class="text-right">Total (ETB)</th>
                    <th class="text-center"># Contributions</th>
                    <th class="text-center"># Donations</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['contributions_by_month'] as $period)
                    <tr>
                        <td>{{ $period['period'] }}</td>
                        <td class="text-right">{{ number_format($period['contributions'], 2) }}</td>
                        <td class="text-right">{{ number_format($period['donations'], 2) }}</td>
                        <td class="text-right fw-600">{{ number_format($period['total'], 2) }}</td>
                        <td class="text-center">{{ $period['contribution_count'] }}</td>
                        <td class="text-center">{{ $period['donation_count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── Group Performance ───────────────────────────────────────────── --}}
    @if(count($data['contributions_by_group']) > 0)
        <div class="section-title">Group Performance Summary</div>
        <table class="data">
            <thead>
                <tr>
                    <th>Group</th>
                    <th class="text-right">Total Amount (ETB)</th>
                    <th class="text-center">Contributions</th>
                    <th class="text-right">Average (ETB)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['contributions_by_group'] as $group)
                    <tr>
                        <td>{{ $group['group_name'] }}</td>
                        <td class="text-right fw-600">{{ number_format($group['total_amount'], 2) }}</td>
                        <td class="text-center">{{ $group['contribution_count'] }}</td>
                        <td class="text-right">{{ number_format($group['average_amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── Contributions Detail ────────────────────────────────────────── --}}
    @if($data['contributions']->count() > 0)
        <div class="section-title">Contributions Detail</div>
        <table class="data">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Group</th>
                    <th>Month</th>
                    <th>Payment Date</th>
                    <th class="text-right">Amount (ETB)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['contributions'] as $i => $contribution)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $contribution->member->full_name ?? '—' }}</td>
                        <td>{{ $contribution->member->currentGroupAssignment?->group?->name ?? '—' }}</td>
                        <td>{{ $contribution->month_name ?? '—' }}</td>
                        <td>{{ \Carbon\Carbon::parse($contribution->payment_date)->format('d M Y') }}</td>
                        <td class="text-right fw-600">{{ number_format($contribution->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── Donations Detail ───────────────────────────────────────────── --}}
    @if($data['donations']->count() > 0)
        <div class="section-title">Donations Detail</div>
        <table class="data">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Donor</th>
                    <th>Date</th>
                    <th>Notes</th>
                    <th class="text-right">Amount (ETB)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['donations'] as $i => $donation)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $donation->donor_name ?? '—' }}</td>
                        <td>{{ \Carbon\Carbon::parse($donation->donation_date)->format('d M Y') }}</td>
                        <td>{{ $donation->notes ?? '' }}</td>
                        <td class="text-right fw-600">{{ number_format($donation->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── Outstanding Contributions ──────────────────────────────────── --}}
    @if(count($data['outstanding_contributions']) > 0)
        <div class="section-title">Outstanding Contributions</div>
        <table class="data">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Month</th>
                    <th class="text-right">Expected (ETB)</th>
                    <th class="text-right">Paid (ETB)</th>
                    <th class="text-right">Outstanding (ETB)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['outstanding_contributions'] as $item)
                    <tr>
                        <td>{{ $item['member']->full_name ?? '—' }}</td>
                        <td>{{ $item['month'] }}</td>
                        <td class="text-right">{{ number_format($item['expected'], 2) }}</td>
                        <td class="text-right">{{ number_format($item['paid'], 2) }}</td>
                        <td class="text-right fw-600 color-red">{{ number_format($item['outstanding'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ── Footer ─────────────────────────────────────────────────────── --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>
                    <strong>Generated by:</strong> {{ $data['generated_by'] }}<br>
                    <strong>Date:</strong> {{ $data['generated_at']->format('d M Y, H:i') }}
                </td>
                <td style="text-align:right;">
                    <strong>Ethiopian Date:</strong> {{ $ethDateStr }}<br>
                    {{ $footerText }}
                </td>
            </tr>
        </table>
    </div>

</body>
</html>