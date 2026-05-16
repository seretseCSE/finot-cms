 <x-filament-panels::page>
@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
@endpush

<style>
.fa-page { font-family: 'DM Sans', sans-serif; }
.stat-card {
    background: var(--color-background-primary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-lg);
    padding: 1.25rem;
    position: relative;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.stat-card:hover { border-color: var(--color-border-secondary); }
.stat-icon {
    width: 40px; height: 40px;
    border-radius: var(--border-radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: 0.75rem;
}
.stat-label { font-size: 12px; font-weight: 500; letter-spacing: 0.05em; text-transform: uppercase; color: var(--color-text-secondary); margin-bottom: 4px; }
.stat-value { font-size: 26px; font-weight: 600; color: var(--color-text-primary); line-height: 1.1; }
.stat-sub { font-size: 12px; color: var(--color-text-secondary); margin-top: 4px; }
.stat-badge { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 500; padding: 2px 7px; border-radius: 99px; margin-top: 6px; }
.section-card {
    background: var(--color-background-primary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
}
.section-title { font-size: 14px; font-weight: 500; color: var(--color-text-primary); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px; }
.section-title i { font-size: 16px; color: var(--color-text-secondary); }
.row-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 12px;
    border-radius: var(--border-radius-md);
    transition: background 0.15s;
}
.row-item:hover { background: var(--color-background-secondary); }
.row-label { font-size: 13px; color: var(--color-text-primary); font-weight: 500; }
.row-sub { font-size: 11px; color: var(--color-text-secondary); margin-top: 1px; }
.row-amount { font-size: 13px; font-weight: 600; color: var(--color-text-primary); text-align: right; }
.rank-badge {
    width: 26px; height: 26px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 600; flex-shrink: 0;
    background: var(--color-background-secondary);
    color: var(--color-text-secondary);
    border: 0.5px solid var(--color-border-tertiary);
}
.rank-badge.top { background: #faeeda; color: #633806; border-color: #fac775; }
.progress-bar-wrap { height: 4px; background: var(--color-background-secondary); border-radius: 99px; margin-top: 6px; overflow: hidden; }
.progress-bar-fill { height: 100%; border-radius: 99px; transition: width 0.6s ease; }
.pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 500; }
.divider { border: none; border-top: 0.5px solid var(--color-border-tertiary); margin: 1rem 0; }
.filter-section {
    background: var(--color-background-secondary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-lg);
    padding: 1.25rem 1.5rem;
}
.action-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: var(--border-radius-md);
    font-size: 13px; font-weight: 500; cursor: pointer;
    border: 0.5px solid var(--color-border-secondary);
    background: var(--color-background-primary);
    color: var(--color-text-primary);
    transition: all 0.15s;
}
.action-btn:hover { background: var(--color-background-secondary); }
.action-btn.primary { background: #185FA5; color: #fff; border-color: #185FA5; }
.action-btn.primary:hover { background: #0C447C; }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem 1rem; gap: 8px; color: var(--color-text-secondary); }
.empty-state i { font-size: 28px; }
.empty-state p { font-size: 13px; }
.chart-wrap { position: relative; width: 100%; }
.two-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
.three-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.four-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
</style>

<div class="fa-page" style="display: flex; flex-direction: column; gap: 1.25rem; padding-bottom: 2rem;">

    {{-- KPI Row 1: Revenue Overview --}}
    <div class="four-col">

        <div class="stat-card">
            <div class="stat-icon" style="background:#e6f1fb; color:#185FA5;">
                <i class="ti ti-chart-line" aria-hidden="true"></i>
            </div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">
                Birr {{ number_format($analyticsData['overview']['total_revenue'] ?? 0, 2) }}
            </div>
            @php $growth = $analyticsData['overview']['revenue_growth'] ?? 0; @endphp
            <span class="stat-badge" style="{{ $growth >= 0 ? 'background:#eaf3de;color:#3B6D11' : 'background:#fcebeb;color:#A32D2D' }}">
                <i class="ti {{ $growth >= 0 ? 'ti-trending-up' : 'ti-trending-down' }}" style="font-size:12px;" aria-hidden="true"></i>
                {{ abs($growth) }}% vs prev period
            </span>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda; color:#155724;">
                <i class="ti ti-coin" aria-hidden="true"></i>
            </div>
            <div class="stat-label">Income (Transactions)</div>
            <div class="stat-value">
                Birr {{ number_format($analyticsData['overview']['total_income'] ?? 0, 2) }}
            </div>
            @php $ig = $analyticsData['overview']['income_growth'] ?? 0; @endphp
            <span class="stat-badge" style="{{ $ig >= 0 ? 'background:#eaf3de;color:#3B6D11' : 'background:#fcebeb;color:#A32D2D' }}">
                <i class="ti {{ $ig >= 0 ? 'ti-trending-up' : 'ti-trending-down' }}" style="font-size:12px;" aria-hidden="true"></i>
                {{ abs($ig) }}% vs prev period
            </span>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#f8d7da; color:#721c24;">
                <i class="ti ti-shopping-cart" aria-hidden="true"></i>
            </div>
            <div class="stat-label">Expenses</div>
            <div class="stat-value">
                Birr {{ number_format($analyticsData['overview']['total_all_expenses'] ?? 0, 2) }}
            </div>
            @php $eg = $analyticsData['overview']['expense_growth'] ?? 0; @endphp
            <span class="stat-badge" style="{{ $eg >= 0 ? 'background:#fcebeb;color:#A32D2D' : 'background:#eaf3de;color:#3B6D11' }}">
                <i class="ti {{ $eg >= 0 ? 'ti-trending-up' : 'ti-trending-down' }}" style="font-size:12px;" aria-hidden="true"></i>
                {{ abs($eg) }}% vs prev period
            </span>
            <div class="stat-sub">Txns: Birr {{ number_format($analyticsData['overview']['total_expenses'] ?? 0, 2) }} &middot; Aid: Birr {{ number_format($analyticsData['overview']['total_aid'] ?? 0, 2) }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff; color:#004085;">
                <i class="ti ti-report-money" aria-hidden="true"></i>
            </div>
            <div class="stat-label">Net Income</div>
            <div class="stat-value">
                Birr {{ number_format($analyticsData['overview']['net_income'] ?? 0, 2) }}
            </div>
            @php $ni = $analyticsData['overview']['net_income'] ?? 0; @endphp
            <span class="stat-badge" style="{{ $ni >= 0 ? 'background:#eaf3de;color:#3B6D11' : 'background:#fcebeb;color:#A32D2D' }}">
                <i class="ti {{ $ni >= 0 ? 'ti-circle-check' : 'ti-alert-circle' }}" style="font-size:12px;" aria-hidden="true"></i>
                {{ $ni >= 0 ? 'Surplus' : 'Deficit' }}
            </span>
        </div>
    </div>

    {{-- KPI Row 2: Detailed Breakdown --}}
    <div class="four-col">

        <div class="stat-card">
            <div class="stat-icon" style="background:#eaf3de; color:#3B6D11;">
                <i class="ti ti-arrows-exchange" aria-hidden="true"></i>
            </div>
            <div class="stat-label">Contributions</div>
            <div class="stat-value">
                Birr {{ number_format($analyticsData['overview']['total_contributions'] ?? 0, 2) }}
            </div>
            <div class="stat-sub">Avg Birr {{ number_format($analyticsData['overview']['average_contribution'] ?? 0, 2) }} per member</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#fbeaf0; color:#993556;">
                <i class="ti ti-heart-handshake" aria-hidden="true"></i>
            </div>
            <div class="stat-label">Donations</div>
            <div class="stat-value">
                Birr {{ number_format($analyticsData['overview']['total_donations'] ?? 0, 2) }}
            </div>
            <div class="stat-sub">One-time contributions</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd; color:#856404;">
                <i class="ti ti-gift" aria-hidden="true"></i>
            </div>
            <div class="stat-label">Aid Distributions</div>
            <div class="stat-value">
                Birr {{ number_format($analyticsData['overview']['total_aid'] ?? 0, 2) }}
            </div>
            <div class="stat-sub">Charity &amp; disbursements</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#faeeda; color:#633806;">
                <i class="ti ti-users-group" aria-hidden="true"></i>
            </div>
            <div class="stat-label">Participation Rate</div>
            <div class="stat-value">
                {{ number_format($analyticsData['overview']['participation_rate'] ?? 0, 1) }}%
            </div>
            <div class="stat-sub">
                {{ $analyticsData['overview']['contributing_members'] ?? 0 }} of {{ $analyticsData['overview']['active_members'] ?? 0 }} members
            </div>
            <div class="progress-bar-wrap" style="margin-top: 10px;">
                <div class="progress-bar-fill" style="width: {{ min($analyticsData['overview']['participation_rate'] ?? 0, 100) }}%; background: #BA7517;"></div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-section">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:1rem;">
            <i class="ti ti-adjustments-horizontal" style="font-size:15px; color:var(--color-text-secondary);" aria-hidden="true"></i>
            <span style="font-size:13px; font-weight:500; color:var(--color-text-secondary); text-transform:uppercase; letter-spacing:0.05em;">Filters</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{ $this->form }}
        </div>
    </div>

    {{-- Trends + Group Performance --}}
    <div class="two-col">

        {{-- Income vs Expense Trends (from Financial Transactions) --}}
        <div class="section-card">
            <div class="section-title">
                <i class="ti ti-chart-line" aria-hidden="true"></i>
                Income vs Expense Trends
            </div>

            @php $incomeMonthly = $analyticsData['financial_trends']['income_monthly'] ?? []; @endphp
            @php $expenseMonthly = $analyticsData['financial_trends']['expense_monthly'] ?? []; @endphp

            @if(!empty($incomeMonthly) || !empty($expenseMonthly))
                @php
                    $allMonths = collect(array_merge($incomeMonthly, $expenseMonthly))->pluck('month')->unique()->sort()->values();
                    $maxFin = collect(array_merge($incomeMonthly, $expenseMonthly))->max('total') ?: 1;
                @endphp
                <div style="display:flex; flex-direction:column; gap:8px;">
                    @foreach($allMonths as $m)
                        @php
                            $inc = collect($incomeMonthly)->firstWhere('month', $m);
                            $exp = collect($expenseMonthly)->firstWhere('month', $m);
                            $incTotal = $inc['total'] ?? 0;
                            $expTotal = $exp['total'] ?? 0;
                            $incPct = ($incTotal / $maxFin) * 100;
                            $expPct = ($expTotal / $maxFin) * 100;
                        @endphp
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                <span style="font-size:12px; font-weight:500; color:var(--color-text-secondary);">{{ date('F', mktime(0,0,0,$m,1)) }}</span>
                                <div style="text-align:right;">
                                    <span style="font-size:12px; font-weight:600; color:#155724;">+Birr {{ number_format($incTotal,2) }}</span>
                                    <span style="font-size:12px; font-weight:600; color:#721c24; margin-left:8px;">-Birr {{ number_format($expTotal,2) }}</span>
                                </div>
                            </div>
                            <div style="display:flex; gap:4px;">
                                <div class="progress-bar-wrap" style="flex:1;">
                                    <div class="progress-bar-fill" style="width:{{ $incPct }}%; background:#28a745;"></div>
                                </div>
                                <div class="progress-bar-wrap" style="flex:1;">
                                    <div class="progress-bar-fill" style="width:{{ $expPct }}%; background:#dc3545;"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <hr class="divider">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:12px; color:var(--color-text-secondary);">Net: Birr {{ number_format(collect($incomeMonthly)->sum('total') - collect($expenseMonthly)->sum('total'), 2) }}</span>
                    @php $gr = $analyticsData['trends']['growth_rate'] ?? 0; @endphp
                    <span class="pill" style="{{ $gr >= 0 ? 'background:#eaf3de;color:#3B6D11' : 'background:#fcebeb;color:#A32D2D' }}">
                        <i class="ti {{ $gr >= 0 ? 'ti-arrow-up-right' : 'ti-arrow-down-right' }}" style="font-size:11px;" aria-hidden="true"></i>
                        {{ number_format($gr, 1) }}% contribution growth
                    </span>
                </div>
            @else
                <div class="empty-state">
                    <i class="ti ti-chart-bar" aria-hidden="true"></i>
                    <p>No income/expense data available</p>
                </div>
            @endif
        </div>

        {{-- Group Performance --}}
        <div class="section-card">
            <div class="section-title">
                <i class="ti ti-building-community" aria-hidden="true"></i>
                Group Performance
            </div>

            @if(!empty($analyticsData['group_performance']))
                @php
                    $groups = array_slice($analyticsData['group_performance'], 0, 5);
                    $maxGroup = max(array_column($groups, 'total_contributions')) ?: 1;
                @endphp
                <div style="display:flex; flex-direction:column; gap:6px;">
                    @foreach($groups as $i => $group)
                        @php $gpct = ($group['total_contributions'] / $maxGroup) * 100; @endphp
                        <div style="padding:10px 12px; border-radius:var(--border-radius-md); border:0.5px solid var(--color-border-tertiary);">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                                <div>
                                    <div class="row-label">{{ $group['group_name'] }}</div>
                                    <div class="row-sub">{{ $group['contributing_members'] }}/{{ $group['member_count'] }} contributing</div>
                                </div>
                                <div style="text-align:right;">
                                    <div class="row-amount">Birr {{ number_format($group['total_contributions'],2) }}</div>
                                    <span class="pill" style="background:var(--color-background-secondary); color:var(--color-text-secondary);">
                                        {{ number_format($group['participation_rate'],1) }}%
                                    </span>
                                </div>
                            </div>
                            <div class="progress-bar-wrap">
                                <div class="progress-bar-fill" style="width:{{ $gpct }}%; background:#1D9E75;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if(count($analyticsData['group_performance']) > 5)
                    <div style="margin-top:12px; text-align:center;">
                        <span style="font-size:12px; color:var(--color-text-secondary);">+{{ count($analyticsData['group_performance']) - 5 }} more groups</span>
                    </div>
                @endif
            @else
                <div class="empty-state">
                    <i class="ti ti-users" aria-hidden="true"></i>
                    <p>No group performance data</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Charts --}}
    <script id="financial-chart-data" type="application/json">{
        "contribTrend": @json(array_values($analyticsData['trends']['monthly_trends'] ?? [])),
        "incomeTrend": @json(array_values($analyticsData['financial_trends']['income_monthly'] ?? [])),
        "expenseTrend": @json(array_values($analyticsData['financial_trends']['expense_monthly'] ?? [])),
        "groupData": @json(array_slice($analyticsData['group_performance'] ?? [], 0, 6)),
        "monthlyData": @json(array_values($analyticsData['monthly_breakdown'] ?? []))
    }</script>
    <div class="three-col">
        <div class="section-card">
            <div class="section-title">
                <i class="ti ti-chart-line" aria-hidden="true"></i>
                Revenue Trend
            </div>
            <div class="chart-wrap" style="height:200px;">
                <canvas id="revenueTrendChart" role="img" aria-label="Monthly revenue trend chart">Monthly revenue data.</canvas>
            </div>
        </div>
        <div class="section-card">
            <div class="section-title">
                <i class="ti ti-chart-bar" aria-hidden="true"></i>
                Group Comparison
            </div>
            <div class="chart-wrap" style="height:200px;">
                <canvas id="groupComparisonChart" role="img" aria-label="Group contribution comparison chart">Group comparison data.</canvas>
            </div>
        </div>
        <div class="section-card">
            <div class="section-title">
                <i class="ti ti-chart-donut" aria-hidden="true"></i>
                Monthly Distribution
            </div>
            <div class="chart-wrap" style="height:200px;">
                <canvas id="monthlyDistChart" role="img" aria-label="Monthly distribution donut chart">Monthly distribution data.</canvas>
            </div>
        </div>
    </div>

</div>

@push('scripts')
    @vite('resources/js/financial-charts.js')
@endpush
</x-filament-panels::page>
