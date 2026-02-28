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
            <p><strong>Ethiopian Date:</strong> {{ EthiopianDateHelper::toEthiopian($data['generated_at']) }}</p>
            <p>{{ $data['church_info']['footer_text'] }}</p>
        </div>
        <div style="clear: both;"></div>
    </div>
</body>
</html>
            color: #666;
            margin: 5px 0 0 0;
        }
        
        .section {
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
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .table th {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
        }
        
        .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        
        .table td.amount {
            text-align: right;
            font-weight: 600;
        }
        
        .table td.total-row {
            background-color: #f8f9fa;
            font-weight: bold;
            border-top: 2px solid #4472C4;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .summary-item h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .summary-item .value {
            font-size: 18px;
            font-weight: bold;
            color: #4472C4;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        .confidential {
            font-weight: bold;
            color: #d32f2f;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <!-- Church logo placeholder -->
                <div style="width: 80px; height: 80px; background-color: #f0f0f0; border: 2px solid #4472C4; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #4472C4;">
                    FINOT
                </div>
            </div>
            
            <div class="title-section">
                <h1>FINOT Church</h1>
                <h2>Financial Statement</h2>
                <h2>{{ $data['period'] }}</h2>
            </div>
            
            <div class="text-right">
                <div><strong>Generated Date:</strong></div>
                <div>{{ $data['ethiopianDate']['month_name_am'] }} {{ $data['ethiopianDate']['day'] }}, {{ $data['ethiopianDate']['year'] }}</div>
                <div><strong>Generated By:</strong></div>
                <div>{{ $data['generatedBy'] }}</div>
            </div>
        </div>
    </div>

    <!-- Section 1: Total Contributions -->
    @if(!empty($data['contributions']))
        <div class="section">
            <div class="section-title">Section 1: Total Contributions</div>
            
            <!-- Contributions by Group -->
            <h3 style="margin-bottom: 15px;">Contributions by Group</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Total Amount</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['contributionsByGroup'] as $group => $contributions)
                        <tr>
                            <td>{{ $contributions->first()->member->memberGroup->name }}</td>
                            <td class="amount">Birr {{ number_format($contributions->sum('amount'), 2) }}</td>
                            <td>{{ $contributions->count() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            <!-- Contributions by Month -->
            <h3 style="margin-bottom: 15px;">Contributions by Month</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Total Amount</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['contributionsByMonth'] as $month => $contributions)
                        <tr>
                            <td>{{ $month }}</td>
                            <td class="amount">Birr {{ number_format($contributions->sum('amount'), 2) }}</td>
                            <td>{{ $contributions->count() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Section 2: Total Donations -->
    @if(!empty($data['donations']))
        <div class="section">
            <div class="section-title">Section 2: Total Donations</div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Donation Type</th>
                        <th>Total Amount</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['donations']->groupBy('donation_type') as $type => $donations)
                        <tr>
                            <td>{{ $donations->first()->formatted_donation_type }}</td>
                            <td class="amount">Birr {{ number_format($donations->sum('amount'), 2) }}</td>
                            <td>{{ $donations->count() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Section 3: Outstanding Contributions -->
    @if(!empty($data['outstandingContributions']))
        <div class="section">
            <div class="section-title">Section 3: Outstanding Contributions (Current Academic Year)</div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Group</th>
                        <th>Month</th>
                        <th>Expected</th>
                        <th>Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['outstandingContributions'] as $outstanding)
                        <tr>
                            <td>{{ $outstanding['member'] }}</td>
                            <td>{{ $outstanding['group'] }}</td>
                            <td>{{ $outstanding['month'] }}</td>
                            <td class="amount">Birr {{ number_format($outstanding['expected'], 2) }}</td>
                            <td class="amount">Birr {{ number_format($outstanding['outstanding'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Section 4: Collection Trends -->
    @if(!empty($data['monthlyTrends']))
        <div class="section">
            <div class="section-title">Section 4: Collection Trends</div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Total Collected</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['monthlyTrends'] as $trend)
                        <tr>
                            <td>{{ $trend['month'] }}</td>
                            <td class="amount">Birr {{ number_format($trend['amount'], 2) }}</td>
                            <td>{{ $trend['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <div class="confidential">CONFIDENTIAL</div>
        <div>Generated on {{ $data['generatedDate'] }}</div>
    </div>
</body>
</html>
