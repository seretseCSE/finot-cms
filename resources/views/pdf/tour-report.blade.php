<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tour Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1B4F72;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 22px;
            color: #1B4F72;
            margin: 0;
        }
        .header h2 {
            font-size: 14px;
            color: #666;
            margin: 5px 0;
        }
        .summary-cards {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 10px;
        }
        .summary-card {
            flex: 1;
            background: #f8f9fa;
            border-left: 4px solid #1B4F72;
            padding: 10px;
            text-align: center;
        }
        .summary-card .label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        .summary-card .value {
            font-size: 16px;
            font-weight: bold;
            color: #1B4F72;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #1B4F72;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .amount {
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-draft { background: #e5e7eb; color: #374151; }
        .badge-published { background: #dbeafe; color: #1e40af; }
        .badge-in-progress { background: #fef3c7; color: #92400e; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>FINOT Church - Tour Report</h1>
        <h2>Period: {{ $filters['date_range_label'] ?? 'All Time' }}</h2>
        <p>Generated on: {{ now()->format('M d, Y H:i') }} | Ethiopian: {{ \App\Helpers\EthiopianDateHelper::toEthiopian(now())['month_name_am'] }} {{ \App\Helpers\EthiopianDateHelper::toEthiopian(now())['day'] }}, {{ \App\Helpers\EthiopianDateHelper::toEthiopian(now())['year'] }}</p>
    </div>

    <div class="summary-cards">
        <div class="summary-card">
            <div class="label">Total Tours</div>
            <div class="value">{{ $metrics['total_tours'] }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Completed</div>
            <div class="value">{{ $metrics['completed_tours'] }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Total Passengers</div>
            <div class="value">{{ $metrics['total_passengers'] }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Confirmed</div>
            <div class="value">{{ $metrics['confirmed_passengers'] }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Attended</div>
            <div class="value">{{ $metrics['attended_passengers'] }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Revenue</div>
            <div class="value">ETB {{ number_format($metrics['total_revenue'], 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Avg Attendance</div>
            <div class="value">{{ round($metrics['average_attendance_rate'], 1) }}%</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tour Place</th>
                <th>Date</th>
                <th>Status</th>
                <th>Total</th>
                <th>Confirmed</th>
                <th>Attended</th>
                <th>Rate</th>
                <th>Cost/Person</th>
                <th>Revenue</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tours as $tour)
                @php
                    $total = $tour->passengers->sum('passenger_count');
                    $confirmed = $tour->passengers->where('status', 'Confirmed')->sum('passenger_count');
                    $attended = $tour->passengers->where('status', 'Attended')->sum('passenger_count');
                    $rate = $total > 0 ? round(($attended / $total) * 100, 1) : 0;
                    $revenue = $confirmed * ($tour->cost_per_person ?? 0);
                @endphp
                <tr>
                    <td>{{ $tour->place }}</td>
                    <td>{{ $tour->ethiopian_date }}</td>
                    <td>
                        <span class="badge badge-{{ strtolower(str_replace(' ', '-', $tour->status)) }}">
                            {{ $tour->status }}
                        </span>
                    </td>
                    <td>{{ $total }}</td>
                    <td>{{ $confirmed }}</td>
                    <td>{{ $attended }}</td>
                    <td>{{ $rate }}%</td>
                    <td class="amount">ETB {{ number_format($tour->cost_per_person ?? 0, 2) }}</td>
                    <td class="amount">ETB {{ number_format($revenue, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generated by: {{ auth()->user()->name ?? 'System' }} | FINOT Church Management System</p>
    </div>
</body>
</html>
