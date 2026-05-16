<x-filament-panels::page>

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    .oc-page { font-family: 'DM Sans', sans-serif; }

    /* ── KPI Cards ───────────────────────────────────────────── */
    .oc-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
    }
    .oc-kpi {
        position: relative;
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: var(--border-radius-lg);
        padding: 1.25rem 1.4rem 1.1rem;
        overflow: hidden;
    }
    /* coloured left accent bar */
    .oc-kpi::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 3px;
        border-radius: 99px 0 0 99px;
    }
    .oc-kpi.kpi-blue::before  { background: #378ADD; }
    .oc-kpi.kpi-green::before { background: #1D9E75; }
    .oc-kpi.kpi-red::before   { background: #D94040; }
    .oc-kpi.kpi-purple::before{ background: #7C6FD8; }

    .oc-kpi-top {
        display: flex; align-items: flex-start; justify-content: space-between;
        margin-bottom: 0.85rem;
    }
    .oc-kpi-icon {
        width: 36px; height: 36px; border-radius: var(--border-radius-md);
        display: flex; align-items: center; justify-content: center;
        font-size: 17px; flex-shrink: 0;
    }
    .oc-kpi-label {
        font-size: 11px; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.07em; color: var(--color-text-secondary);
    }
    .oc-kpi-value {
        font-size: 21px; font-weight: 700; color: var(--color-text-primary);
        line-height: 1.15; margin-top: 2px; font-variant-numeric: tabular-nums;
    }
    .oc-kpi-value.danger { color: #D94040; }
    .oc-kpi-sub {
        font-size: 11px; color: var(--color-text-secondary); margin-top: 5px;
    }
    .collection-bar-wrap {
        height: 4px; background: var(--color-background-secondary);
        border-radius: 99px; margin-top: 10px; overflow: hidden;
    }
    .collection-bar-fill {
        height: 100%; border-radius: 99px; background: #1D9E75; transition: width 0.5s ease;
    }

    /* ── Filter Bar ───────────────────────────────────────────── */
    .oc-filter-bar {
        background: var(--color-background-secondary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: var(--border-radius-lg);
        padding: 0.9rem 1.25rem;
        display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap;
    }
    .oc-filter-icon-label {
        display: flex; align-items: center; gap: 5px;
        font-size: 11px; font-weight: 600; letter-spacing: 0.06em;
        text-transform: uppercase; color: var(--color-text-secondary);
        padding-bottom: 2px; /* optically align with select bottom */
        flex-shrink: 0;
    }
    .oc-filter-group { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 160px; }
    .oc-field-label { font-size: 12px; font-weight: 500; color: var(--color-text-secondary); }
    .oc-select-wrap { position: relative; }
    .oc-select {
        width: 100%; padding: 8px 30px 8px 10px;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: var(--border-radius-md);
        background: var(--color-background-primary);
        color: var(--color-text-primary);
        font-size: 13px; font-family: inherit; appearance: none; cursor: pointer;
    }
    .oc-select:focus { outline: none; border-color: #378ADD; }
    .oc-select-wrap::after {
        content: ''; position: absolute; right: 10px; top: 50%;
        transform: translateY(-50%);
        width: 0; height: 0;
        border-left: 4px solid transparent; border-right: 4px solid transparent;
        border-top: 5px solid var(--color-text-secondary); pointer-events: none;
    }
    .oc-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 14px; border-radius: var(--border-radius-md);
        font-size: 13px; font-weight: 500; cursor: pointer;
        border: 0.5px solid var(--color-border-secondary);
        background: var(--color-background-primary);
        color: var(--color-text-primary); transition: all 0.15s; font-family: inherit;
        white-space: nowrap; flex-shrink: 0;
    }
    .oc-btn:hover { background: var(--color-background-secondary); }

    /* ── Table Section ────────────────────────────────────────── */
    .oc-section {
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: var(--border-radius-lg);
        overflow: hidden;
    }
    .oc-section-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1rem 1.5rem;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-secondary);
    }
    .oc-section-title {
        font-size: 13px; font-weight: 500; color: var(--color-text-primary);
        display: flex; align-items: center; gap: 8px;
    }
    .oc-section-title i { font-size: 15px; color: var(--color-text-secondary); }
    .oc-count-pill {
        font-size: 11px; font-weight: 500; padding: 3px 10px;
        border-radius: 99px; background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-secondary);
        color: var(--color-text-secondary);
    }

    .oc-table { width: 100%; border-collapse: collapse; }
    .oc-table thead tr { border-bottom: 0.5px solid var(--color-border-tertiary); }
    .oc-table th {
        padding: 10px 14px; text-align: left;
        font-size: 10px; font-weight: 600; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--color-text-secondary);
        white-space: nowrap;
    }
    .oc-table th.right { text-align: right; }
    .oc-table tbody tr {
        border-bottom: 0.5px solid var(--color-border-tertiary);
        transition: background 0.12s;
    }
    .oc-table tbody tr:last-child { border-bottom: none; }
    .oc-table tbody tr:hover { background: var(--color-background-secondary); }
    .oc-table td {
        padding: 10px 14px; font-size: 13px; color: var(--color-text-primary);
        vertical-align: middle;
    }
    .oc-table td.muted { color: var(--color-text-secondary); }
    .oc-table td.right { text-align: right; font-variant-numeric: tabular-nums; }

    .code-pill {
        display: inline-flex; padding: 2px 8px; border-radius: var(--border-radius-md);
        font-size: 11px; font-weight: 500; font-family: monospace;
        background: var(--color-background-secondary); color: var(--color-text-secondary);
        border: 0.5px solid var(--color-border-secondary);
    }
    .group-pill {
        display: inline-flex; padding: 2px 9px; border-radius: 99px;
        font-size: 11px; font-weight: 500;
        background: var(--color-background-secondary); color: var(--color-text-secondary);
        border: 0.5px solid var(--color-border-secondary); white-space: nowrap;
    }
    .month-tag {
        display: inline-flex; align-items: center;
        padding: 2px 7px; border-radius: 99px;
        font-size: 11px; font-weight: 500;
        background: #fcebeb; color: #A32D2D; margin: 1px;
    }

    .mini-bar-wrap {
        height: 3px; background: var(--color-background-secondary);
        border-radius: 99px; margin-top: 0; overflow: hidden;
        width: 64px; display: inline-block; vertical-align: middle; margin-left: 8px;
    }
    .mini-bar-fill { height: 100%; border-radius: 99px; background: #D94040; }

    .oc-empty {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 4rem 1rem; gap: 10px;
    }
    .oc-empty i { font-size: 36px; color: #1D9E75; }
    .oc-empty p { font-size: 15px; font-weight: 500; color: var(--color-text-primary); }
    .oc-empty span { font-size: 13px; color: var(--color-text-secondary); }

    .oc-no-year {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 5rem 2rem; gap: 12px; text-align: center;
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: var(--border-radius-lg);
    }
    .oc-no-year i { font-size: 36px; color: #BA7517; }
    .oc-no-year h3 { font-size: 16px; font-weight: 600; color: var(--color-text-primary); }
    .oc-no-year p { font-size: 13px; color: var(--color-text-secondary); max-width: 380px; }

    /* pagination wrapper */
    .oc-pagination {
        padding: 0.85rem 1.5rem;
        border-top: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-secondary);
    }
</style>
@endpush

<div class="oc-page" style="display:flex; flex-direction:column; gap:1.25rem; padding-bottom:2rem;">

@if(!$activeYear)
    <div class="oc-no-year">
        <i class="ti ti-calendar-off" aria-hidden="true"></i>
        <h3>No Active Academic Year</h3>
        <p>There is currently no active academic year. Please contact the Education Head to activate one.</p>
    </div>
@else

    {{-- ── KPI Cards ── --}}
    <div class="oc-kpi-grid">
        <div class="oc-kpi kpi-blue">
            <div class="oc-kpi-top">
                <div>
                    <div class="oc-kpi-label">Total Expected</div>
                    <div class="oc-kpi-value">Birr {{ number_format($summaryData['total_expected'], 2) }}</div>
                    <div class="oc-kpi-sub">For {{ $activeYear->name }}</div>
                </div>
                <div class="oc-kpi-icon" style="background:#e6f1fb; color:#185FA5;">
                    <i class="ti ti-calculator" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="oc-kpi kpi-green">
            <div class="oc-kpi-top">
                <div>
                    <div class="oc-kpi-label">Total Collected</div>
                    <div class="oc-kpi-value">Birr {{ number_format($summaryData['total_collected'], 2) }}</div>
                    <div class="oc-kpi-sub">Payments received</div>
                </div>
                <div class="oc-kpi-icon" style="background:#eaf3de; color:#3B6D11;">
                    <i class="ti ti-cash" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="oc-kpi kpi-red">
            <div class="oc-kpi-top">
                <div>
                    <div class="oc-kpi-label">Outstanding</div>
                    <div class="oc-kpi-value danger">Birr {{ number_format($summaryData['total_outstanding'], 2) }}</div>
                    <div class="oc-kpi-sub">Remaining to collect</div>
                </div>
                <div class="oc-kpi-icon" style="background:#fcebeb; color:#A32D2D;">
                    <i class="ti ti-alert-circle" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="oc-kpi kpi-purple">
            <div class="oc-kpi-top">
                <div>
                    <div class="oc-kpi-label">Collection Rate</div>
                    <div class="oc-kpi-value">{{ $summaryData['collection_rate'] }}%</div>
                    <div class="oc-kpi-sub">Of expected collected</div>
                </div>
                <div class="oc-kpi-icon" style="background:#eeedfe; color:#534AB7;">
                    <i class="ti ti-chart-pie" aria-hidden="true"></i>
                </div>
            </div>
            <div class="collection-bar-wrap">
                <div class="collection-bar-fill" style="width: {{ min($summaryData['collection_rate'], 100) }}%;"></div>
            </div>
        </div>
    </div>

    {{-- ── Horizontal Filters ── --}}
    <div class="oc-filter-bar">
        <div class="oc-filter-icon-label">
            <i class="ti ti-adjustments-horizontal" aria-hidden="true"></i>
            Filters
        </div>

        <div class="oc-filter-group">
            <div class="oc-field-label">Member Group</div>
            <div class="oc-select-wrap">
                <select wire:model.live="group_id" class="oc-select">
                    <option value="">All Groups</option>
                    @foreach(\App\Models\MemberGroup::pluck('name', 'id') as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="oc-filter-group">
            <div class="oc-field-label">Ethiopian Month</div>
            <div class="oc-select-wrap">
                <select wire:model.live="month" class="oc-select">
                    <option value="">All Months</option>
                    @php
                        $ethMonths = ['Meskerem','Tikimt','Hidar','Tahsas','Tir','Yekatit','Megabit','Miazia','Ginbot','Sene','Hamle','Nehasse','Pagume'];
                    @endphp
                    @foreach($ethMonths as $i => $mname)
                        <option value="{{ $i + 1 }}">{{ $mname }} (Month {{ $i + 1 }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="oc-filter-group" style="flex:0 0 auto; min-width:120px;">
            <div class="oc-field-label">Per Page</div>
            <div class="oc-select-wrap">
                <select wire:model.live="perPage" class="oc-select">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <button type="button" wire:click="resetFilters" class="oc-btn">
            <i class="ti ti-refresh" aria-hidden="true"></i> Reset
        </button>
    </div>

    {{-- ── Outstanding Table ── --}}
    <div class="oc-section">
        <div class="oc-section-header">
            <div class="oc-section-title">
                <i class="ti ti-clock-exclamation" aria-hidden="true"></i>
                Outstanding Contributions — {{ $activeYear->name }}
                @if(!$this->month)
                    <span style="font-size:11px; font-weight:400; color:var(--color-text-secondary);">Annual View</span>
                @endif
            </div>
            @php $paginator = $this->getTableDataPaginator(); @endphp
            <span class="oc-count-pill">
                {{ $paginator->total() }} members
            </span>
        </div>

        @php $rows = $paginator->items(); @endphp

        @if(empty($rows))
            <div class="oc-empty">
                <i class="ti ti-circle-check" aria-hidden="true"></i>
                <p>All caught up!</p>
                <span>No outstanding contributions for the selected criteria.</span>
            </div>
        @else
            @php $maxOutstanding = collect($rows)->max('outstanding') ?: 1; @endphp
            <div style="overflow-x:auto;">
                <table class="oc-table">
                    <thead>
                        <tr>
                            <th style="width:32px;">#</th>
                            <th>Member</th>
                            <th>Code</th>
                            <th>Group</th>
                            <th>{{ $this->month ? 'Month' : 'Outstanding Months' }}</th>
                            <th class="right">Expected</th>
                            <th class="right">Paid</th>
                            <th class="right">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $i => $row)
                            @php $outPct = ($row['outstanding'] / $maxOutstanding) * 100; @endphp
                            <tr>
                                <td class="muted" style="font-size:11px;">
                                    {{ $paginator->firstItem() + $i }}
                                </td>
                                <td style="font-weight:500; white-space:nowrap;">{{ $row['member_name'] }}</td>
                                <td><span class="code-pill">{{ $row['member_code'] }}</span></td>
                                <td><span class="group-pill">{{ $row['group_name'] }}</span></td>
                                <td>
                                    @if($this->month)
                                        <span style="font-size:13px; color:var(--color-text-secondary);">{{ $row['month'] }}</span>
                                    @else
                                        <div style="display:flex; flex-wrap:wrap; gap:3px; max-width:260px;">
                                            @foreach(explode(', ', $row['month_name']) as $mn)
                                                <span class="month-tag">{{ trim($mn) }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="right muted">Birr {{ number_format($row['expected'], 2) }}</td>
                                <td class="right" style="color:#1D9E75;">Birr {{ number_format($row['paid'], 2) }}</td>
                                <td>
                                    <div style="display:flex; align-items:center; justify-content:flex-end; gap:8px;">
                                        <span style="font-size:13px; font-weight:600; color:#D94040; font-variant-numeric:tabular-nums;">
                                            Birr {{ number_format($row['outstanding'], 2) }}
                                        </span>
                                        <div class="mini-bar-wrap">
                                            <div class="mini-bar-fill" style="width:{{ $outPct }}%;"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($paginator->hasPages())
                <div class="oc-pagination">
                    {{ $paginator->links() }}
                </div>
            @endif
        @endif
    </div>

@endif
</div>

</x-filament-panels::page>
