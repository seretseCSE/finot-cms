<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Statement - {{ $data['period_description'] }}</title>
    <style>
        @page {
            size: A4;
            orientation: landscape;
            margin: 20mm 20mm 20mm 20mm;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #4472C4;
            padding-bottom: 15px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            width: 80px;
            height: 80px;
        }
        
        .title-section {
            text-align: center;
            flex: 1;
        }
        
        .title-section h1 {
            font-size: 24px;
            font-weight: bold;
            color: #4472C4;
            margin: 0;
        }
        
        .title-section h2 {
            font-size: 16px;
            font-weight: 600;
            color: #666;
            margin: 5px 0;
        }
        
        .church-info {
            text-align: right;
            font-size: 11px;
            color: #666;
        }
        
        .summary-section {
            margin-bottom: 25px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #4472C4;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .table-section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #4472C4;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th {
            background-color: #4472C4;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
        }
        
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .amount {
            text-align: right;
            font-weight: 600;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        
        .footer-left {
            float: left;
        }
        
        .footer-right {
            float: right;
        }
        
        .ethiopian-date {
            font-weight: bold;
            color: #4472C4;
        }
    </style>
</head>
<body>
    <!-- Header with Church Logo and Information -->
    <div class="header">
        <div class="header-content">
            @if($data['church_info']['logo'])
                <div class="logo">
                    <img src="{{ asset('storage/' . $data['church_info']['logo']) }}" 
                         alt="{{ $data['church_info']['name_en'] }}" 
                         style="width: 100%; height: auto;">
                </div>
            @endif
            
            <div class="title-section">
                <h1>{{ $data['church_info']['name_en'] }}</h1>
                <h2>{{ $data['church_info']['name_am'] }}</h2>
                <h3>Financial Statement</h3>
                <p><strong>Period:</strong> {{ $data['period_description'] }}</p>
                <p><strong>Ethiopian Period:</strong> <span class="ethiopian-date">{{ $data['ethiopian_period'] }}</span></p>
            </div>
            
            <div class="church-info">
                <p><strong>{{ $data['church_info']['name_en'] }}</strong></p>
                <p>{{ $data['church_info']['address'] }}</p>
                <p>{{ $data['church_info']['phone'] }}</p>
                @if($data['church_info']['email'])
                    <p>{{ $data['church_info']['email'] }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Contributions</div>
                <div class="summary-value">ETB {{ number_format($data['summary']['total_contributions'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Donations</div>
                <div class="summary-value">ETB {{ number_format($data['summary']['total_donations'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Grand Total</div>
                <div class="summary-value">ETB {{ number_format($data['summary']['grand_total'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Outstanding</div>
                <div class="summary-value">ETB {{ number_format($data['summary']['total_outstanding'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Contributors</div>
                <div class="summary-value">{{ $data['summary']['unique_contributors'] }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Donors</div>
                <div class="summary-value">{{ $data['summary']['unique_donors'] }}</div>
            </div>
        </div>
    </div>

    <!-- Monthly/Quarterly Summary -->
    @if(count($data['contributions_by_month']) > 1)
    <div class="table-section">
        <h3 class="section-title">Period Breakdown</h3>
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Contributions</th>
                    <th>Donations</th>
                    <th>Total</th>
                    <th>Contributors</th>
                    <th>Donors</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['contributions_by_month'] as $period)
                <tr>
                    <td>{{ $period['period'] }}</td>
                    <td class="amount">ETB {{ number_format($period['contributions'], 2) }}</td>
                    <td class="amount">ETB {{ number_format($period['donations'], 2) }}</td>
                    <td class="amount">ETB {{ number_format($period['total'], 2) }}</td>
                    <td>{{ $period['contribution_count'] }}</td>
                    <td>{{ $period['donation_count'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Group Performance Summary -->
    @if(count($data['contributions_by_group']) > 0)
    <div class="table-section">
        <h3 class="section-title">Group Performance Summary</h3>
        <table>
            <thead>
                <tr>
                    <th>Group</th>
                    <th>Total Amount</th>
                    <th>Contributions</th>
                    <th>Average</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['contributions_by_group'] as $group)
                <tr>
                    <td>{{ $group['group_name'] }}</td>
                    <td class="amount">ETB {{ number_format($group['total_amount'], 2) }}</td>
                    <td>{{ $group['contribution_count'] }}</td>
                    <td class="amount">ETB {{ number_format($group['average_amount'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Outstanding Contributions -->
    @if(count($data['outstanding_contributions']) > 0)
    <div class="table-section">
        <h3 class="section-title">Outstanding Contributions</h3>
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Month</th>
                    <th>Expected</th>
                    <th>Paid</th>
                    <th>Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['outstanding_contributions'] as $outstanding)
                <tr>
                    <td>{{ $outstanding['member']->full_name }}</td>
                    <td>{{ $outstanding['month'] }}</td>
                    <td class="amount">ETB {{ number_format($outstanding['expected'], 2) }}</td>
                    <td class="amount">ETB {{ number_format($outstanding['paid'], 2) }}</td>
                    <td class="amount">ETB {{ number_format($outstanding['outstanding'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <div class="footer-left">
            <p><strong>Generated by:</strong> {{ $data['generated_by'] }}</p>
            <p><strong>Generated on:</strong> {{ $data['generated_at']->format('Y-m-d H:i:s') }}</p>
        </div>
        <div class="footer-right">
            <p><strong>Ethiopian Date:</strong> {{ \App\Helpers\EthiopianDateHelper::toEthiopian($data['generated_at']) }}</p>
            <p>{{ $data['church_info']['footer_text'] }}</p>
        </div>
        <div style="clear: both;"></div>
    </div>
</body>
</html>
