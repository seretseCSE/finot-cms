<x-filament-panels::page>

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
.cr-page { font-family: 'DM Sans', sans-serif; }

.cr-filter-bar {
    background: var(--color-background-secondary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-lg);
    padding: 1.25rem 1.5rem;
}
.cr-filter-label {
    font-size: 11px; font-weight: 500; letter-spacing: 0.06em;
    text-transform: uppercase; color: var(--color-text-secondary);
    display: flex; align-items: center; gap: 6px; margin-bottom: 1rem;
}
.cr-filter-actions { display: flex; gap: 8px; margin-top: 1rem; }
.cr-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: var(--border-radius-md);
    font-size: 13px; font-weight: 500; cursor: pointer;
    border: 0.5px solid var(--color-border-secondary);
    background: var(--color-background-primary);
    color: var(--color-text-primary); transition: all 0.15s;
}
.cr-btn:hover { background: var(--color-background-secondary); }
.cr-btn.primary { background: #185FA5; color: #fff; border-color: #185FA5; }
.cr-btn.primary:hover { background: #0C447C; border-color: #0C447C; }

.cr-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.cr-kpi {
    background: var(--color-background-primary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-lg);
    padding: 1.1rem 1.25rem;
}
.cr-kpi-icon {
    width: 34px; height: 34px; border-radius: var(--border-radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; margin-bottom: 10px;
}
.cr-kpi-label { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-secondary); margin-bottom: 5px; }
.cr-kpi-value { font-size: 22px; font-weight: 600; color: var(--color-text-primary); line-height: 1.1; }
.cr-kpi-sub { font-size: 11px; color: var(--color-text-secondary); margin-top: 4px; }

.cr-section {
    background: var(--color-background-primary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
}
.cr-section-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 0.5px solid var(--color-border-tertiary);
    background: var(--color-background-secondary);
}
.cr-section-title { font-size: 13px; font-weight: 500; color: var(--color-text-primary); display: flex; align-items: center; gap: 8px; }
.cr-section-title i { font-size: 15px; color: var(--color-text-secondary); }
.cr-count-pill {
    font-size: 11px; font-weight: 500; padding: 3px 10px;
    border-radius: 99px; background: var(--color-background-primary);
    border: 0.5px solid var(--color-border-secondary);
    color: var(--color-text-secondary);
}

.cr-table { width: 100%; border-collapse: collapse; }
.cr-table thead tr { border-bottom: 0.5px solid var(--color-border-tertiary); }
.cr-table th {
    padding: 10px 14px; text-align: left;
    font-size: 10px; font-weight: 500; letter-spacing: 0.07em;
    text-transform: uppercase; color: var(--color-text-secondary);
    white-space: nowrap;
}
.cr-table th.right { text-align: right; }
.cr-table tbody tr { border-bottom: 0.5px solid var(--color-border-tertiary); transition: background 0.12s; }
.cr-table tbody tr:last-child { border-bottom: none; }
.cr-table tbody tr:hover { background: var(--color-background-secondary); }
.cr-table td { padding: 10px 14px; font-size: 13px; color: var(--color-text-primary); vertical-align: middle; }
.cr-table td.muted { color: var(--color-text-secondary); }
.cr-table td.right { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }

.avatar-circle {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 600;
    background: #e6f1fb; color: #185FA5;
}
.group-pill {
    display: inline-flex; align-items: center;
    padding: 2px 8px; border-radius: 99px;
    font-size: 11px; font-weight: 500;
    background: var(--color-background-secondary);
    color: var(--color-text-secondary);
    border: 0.5px solid var(--color-border-secondary);
    white-space: nowrap;
}
.method-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px; border-radius: 99px;
    font-size: 11px; font-weight: 500; white-space: nowrap;
}
.method-cash    { background: #eaf3de; color: #3B6D11; }
.method-bank    { background: #e6f1fb; color: #185FA5; }
.method-mobile  { background: #eeedfe; color: #534AB7; }
.method-other   { background: var(--color-background-secondary); color: var(--color-text-secondary); border: 0.5px solid var(--color-border-secondary); }

.status-active   { background: #eaf3de; color: #3B6D11; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 500; }
.status-archived { background: var(--color-background-secondary); color: var(--color-text-secondary); padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 500; border: 0.5px solid var(--color-border-secondary); }

.progress-bar-wrap { height: 3px; background: var(--color-background-secondary); border-radius: 99px; margin-top: 6px; overflow: hidden; }
.progress-bar-fill { height: 100%; border-radius: 99px; }

.two-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }

.cr-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 1rem; gap: 10px; }
.cr-empty i { font-size: 32px; color: var(--color-text-secondary); }
.cr-empty p { font-size: 14px; font-weight: 500; color: var(--color-text-primary); }
.cr-empty span { font-size: 12px; color: var(--color-text-secondary); }

.dist-row {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding: 10px 1.5rem;
    border-bottom: 0.5px solid var(--color-border-tertiary);
    transition: background 0.12s;
}
.dist-row:last-child { border-bottom: none; }
.dist-row:hover { background: var(--color-background-secondary); }

.top-card {
    background: var(--color-background-primary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-lg);
    padding: 1rem;
    display: flex; flex-direction: column; align-items: center; text-align: center;
    transition: border-color 0.15s;
}
.top-card:hover { border-color: var(--color-border-secondary); }
.top-card.gold { border-color: #fac775; }
.rank-num { font-size: 10px; font-weight: 500; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
.rank-num.gold { color: #633806; }
.top-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 600;
    background: #e6f1fb; color: #185FA5; margin-bottom: 8px;
}
.top-avatar.gold { background: #faeeda; color: #633806; }
</style>
@endpush

<div class="cr-page" style="display:flex; flex-direction:column; gap:1.25rem; padding-bottom:2rem;">

    {{-- Filters --}}
    <div class="cr-filter-bar">
        <div class="cr-filter-label">
            <i class="ti ti-adjustments-horizontal" aria-hidden="true"></i>
            Report Filters
        </div>
        <form wire:submit.prevent="applyFilters">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{ $this->form }}
            </div>
            <div class="cr-filter-actions">
                <button type="submit" class="cr-btn primary">
                    <i class="ti ti-search" aria-hidden="true"></i> Apply Filters
                </button>
                <button type="button" wire:click="resetFilters" class="cr-btn">
                    <i class="ti ti-refresh" aria-hidden="true"></i> Reset
                </button>
            </div>
        </form>
    </div>

    @php
        $contributions = collect($reportData['contributions'] ?? [])->map(fn ($c) => is_array($c) ? (object) $c : $c);
    @endphp

    @if($contributions->isNotEmpty())
    @php
        $totalAmount    = $contributions->sum('amount');
        $avgAmount      = $contributions->avg('amount') ?? 0;
        $uniqueMembers  = $contributions->pluck('member_id')->filter()->unique()->count();
        $totalCount     = $contributions->count();
    @endphp

    {{-- KPI Row --}}
    <div class="cr-kpi-grid">
        <div class="cr-kpi">
            <div class="cr-kpi-icon" style="background:#e6f1fb; color:#185FA5;">
                <i class="ti ti-receipt" aria-hidden="true"></i>
            </div>
            <div class="cr-kpi-label">Total Records</div>
            <div class="cr-kpi-value">{{ number_format($totalCount) }}</div>
            <div class="cr-kpi-sub">Contribution entries</div>
        </div>
        <div class="cr-kpi">
            <div class="cr-kpi-icon" style="background:#eaf3de; color:#3B6D11;">
                <i class="ti ti-currency-dollar" aria-hidden="true"></i>
            </div>
            <div class="cr-kpi-label">Total Amount</div>
            <div class="cr-kpi-value">Birr {{ number_format($totalAmount, 0) }}</div>
            <div class="cr-kpi-sub">Across all records</div>
        </div>
        <div class="cr-kpi">
            <div class="cr-kpi-icon" style="background:#faeeda; color:#633806;">
                <i class="ti ti-chart-bar" aria-hidden="true"></i>
            </div>
            <div class="cr-kpi-label">Average</div>
            <div class="cr-kpi-value">Birr {{ number_format($avgAmount, 0) }}</div>
            <div class="cr-kpi-sub">Per contribution</div>
        </div>
        <div class="cr-kpi">
            <div class="cr-kpi-icon" style="background:#eeedfe; color:#534AB7;">
                <i class="ti ti-users" aria-hidden="true"></i>
            </div>
            <div class="cr-kpi-label">Contributors</div>
            <div class="cr-kpi-value">{{ number_format($uniqueMembers) }}</div>
            <div class="cr-kpi-sub">Unique members</div>
        </div>
    </div>

    {{-- Payment Methods + Group Distribution --}}
    <div class="two-col">

        {{-- Payment Methods --}}
        <div class="cr-section">
            <div class="cr-section-header">
                <div class="cr-section-title">
                    <i class="ti ti-credit-card" aria-hidden="true"></i>
                    Payment Methods
                </div>
            </div>
            @php $paymentMethods = $contributions->groupBy('payment_method'); @endphp
            @foreach($paymentMethods as $method => $methodContribs)
                @php
                    $mpct = $totalAmount > 0 ? ($methodContribs->sum('amount') / $totalAmount) * 100 : 0;
                    $methodClass = match(strtolower($method)) {
                        'cash'   => 'method-cash',
                        'bank'   => 'method-bank',
                        'mobile' => 'method-mobile',
                        default  => 'method-other',
                    };
                    $methodIcon = match(strtolower($method)) {
                        'cash'   => 'ti-cash',
                        'bank'   => 'ti-building-bank',
                        'mobile' => 'ti-device-mobile',
                        default  => 'ti-credit-card',
                    };
                @endphp
                <div class="dist-row">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="method-pill {{ $methodClass }}">
                            <i class="ti {{ $methodIcon }}" style="font-size:11px;" aria-hidden="true"></i>
                            {{ $methodContribs->first()->formatted_payment_method }}
                        </span>
                        <span style="font-size:11px; color:var(--color-text-secondary);">{{ $methodContribs->count() }} transactions</span>
                    </div>
                    <div style="text-align:right; min-width:120px;">
                        <div style="font-size:13px; font-weight:600; color:var(--color-text-primary);">Birr {{ number_format($methodContribs->sum('amount'), 0) }}</div>
                        <div class="progress-bar-wrap" style="margin-top:5px;">
                            <div class="progress-bar-fill" style="width:{{ $mpct }}%; background:#378ADD;"></div>
                        </div>
                        <div style="font-size:10px; color:var(--color-text-secondary); margin-top:3px;">{{ number_format($mpct,1) }}%</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Group Distribution --}}
        <div class="cr-section">
            <div class="cr-section-header">
                <div class="cr-section-title">
                    <i class="ti ti-building-community" aria-hidden="true"></i>
                    Group Distribution
                </div>
            </div>
            @php
                $groups = $contributions->groupBy(function($c) {
                    return $c->group_name ?: 'Unknown';
                })->take(5);
                $maxGroupAmt = $groups->map(fn($g) => $g->sum('amount'))->max() ?: 1;
            @endphp
            @foreach($groups as $groupName => $groupContribs)
                @php $gpct = ($groupContribs->sum('amount') / $totalAmount) * 100; @endphp
                <div class="dist-row">
                    <div>
                        <div style="font-size:13px; font-weight:500; color:var(--color-text-primary);">{{ $groupName }}</div>
                        <div style="font-size:11px; color:var(--color-text-secondary);">{{ $groupContribs->pluck('member_id')->unique()->count() }} members · {{ $groupContribs->count() }} contributions</div>
                    </div>
                    <div style="text-align:right; min-width:120px;">
                        <div style="font-size:13px; font-weight:600; color:var(--color-text-primary);">Birr {{ number_format($groupContribs->sum('amount'), 0) }}</div>
                        <div class="progress-bar-wrap" style="margin-top:5px;">
                            <div class="progress-bar-fill" style="width:{{ $gpct }}%; background:#1D9E75;"></div>
                        </div>
                        <div style="font-size:10px; color:var(--color-text-secondary); margin-top:3px;">{{ number_format($gpct,1) }}%</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @endif

    {{-- Contributions Table --}}
    <div class="cr-section">
        <div class="cr-section-header">
            <div class="cr-section-title">
                <i class="ti ti-table" aria-hidden="true"></i>
                Contribution Details
                @if($selectedAcademicYear && $selectedAcademicYear !== 'all')
                    <span style="font-size:12px; font-weight:400; color:var(--color-text-secondary);">— {{ $academicYears[$selectedAcademicYear] ?? '' }}</span>
                @endif
            </div>
            <span class="cr-count-pill">{{ $contributions->count() }} records</span>
        </div>

        @if($contributions->isEmpty())
            <div class="cr-empty">
                <i class="ti ti-file-off" aria-hidden="true"></i>
                <p>No contributions found</p>
                <span>Try adjusting your filters or date range</span>
            </div>
        @else
            <div style="overflow-x:auto;">
                <table class="cr-table">
                    <thead>
                        <tr>
                            <th style="width:32px;">#</th>
                            <th>Member</th>
                            <th>Group</th>
                            <th>Month</th>
                            <th class="right">Amount</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th>Recorded By</th>
                            @if($selectedAcademicYear && $selectedAcademicYear !== 'all')
                                <th>Status</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($contributions as $i => $contribution)
                            <tr>
                                <td class="muted" style="font-size:11px;">{{ $i + 1 }}</td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <div class="avatar-circle">
                                            {{ $contribution->member_initial ?? '?' }}
                                        </div>
                                        <span style="font-weight:500; white-space:nowrap;">
                                            {{ $contribution->member_name ?? 'Unknown Member' }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="group-pill">
                                        {{ $contribution->group_name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="muted">{{ $contribution->month_name }}</td>
                                <td class="right">Birr {{ number_format($contribution->amount, 2) }}</td>
                                <td>
                                    @php
                                        $pm = strtolower($contribution->payment_method ?? '');
                                        $pmClass = match($pm) { 'cash' => 'method-cash', 'bank' => 'method-bank', 'mobile' => 'method-mobile', default => 'method-other' };
                                        $pmIcon  = match($pm) { 'cash' => 'ti-cash', 'bank' => 'ti-building-bank', 'mobile' => 'ti-device-mobile', default => 'ti-credit-card' };
                                    @endphp
                                    <span class="method-pill {{ $pmClass }}">
                                        <i class="ti {{ $pmIcon }}" style="font-size:11px;" aria-hidden="true"></i>
                                        {{ $contribution->formatted_payment_method ?? $contribution->payment_method ?? 'Cash' }}
                                    </span>
                                </td>
                                <td class="muted" style="white-space:nowrap;">
                                    @php
                                        $ethDate = app(\App\Helpers\EthiopianDateHelper::class)->toEthiopian($contribution->payment_date);
                                    @endphp
                                    {{ $ethDate['month_name_am'] . ' ' . $ethDate['day'] . ', ' . $ethDate['year'] }}
                                </td>
                                <td class="muted" style="white-space:nowrap;">{{ $contribution->recorded_by_name ?? '—' }}</td>
                                @if($selectedAcademicYear && $selectedAcademicYear !== 'all')
                                    <td>
                                        @if($contribution->is_archived)
                                            <span class="status-archived">Archived</span>
                                        @else
                                            <span class="status-active">Active</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div> 

</div>

</x-filament-panels::page>
