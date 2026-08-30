<x-filament-panels::page>
<style>
    .oc-wrap { display: flex; flex-direction: column; gap: 1.15rem; }

    .oc-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1.15rem;
        padding: 1.4rem 1.6rem 1.35rem;
        color: #fff;
        background: linear-gradient(125deg, #1A44F7 0%, #1638c9 48%, #0f1f6b 100%);
        box-shadow: 0 12px 28px rgba(26, 68, 247, 0.22);
    }
    .oc-hero::after {
        content: '';
        position: absolute;
        right: -40px; top: -50px;
        width: 220px; height: 220px;
        border-radius: 50%;
        background: rgba(243, 186, 21, 0.18);
    }
    .oc-hero-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; position: relative; z-index: 1; flex-wrap: wrap; }
    .oc-hero-kicker { font-size: 11px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.72); }
    .oc-hero h2 { margin: 6px 0 4px; font-size: 1.45rem; font-weight: 700; letter-spacing: -0.02em; }
    .oc-hero p { margin: 0; font-size: 13px; color: rgba(255,255,255,0.78); max-width: 460px; }
    .oc-hero-stat { text-align: right; position: relative; z-index: 1; }
    .oc-hero-stat span { display: block; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #F3BA15; }
    .oc-hero-stat strong { display: block; margin-top: 2px; font-size: 1.65rem; font-weight: 800; font-variant-numeric: tabular-nums; }

    .oc-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    @media (max-width: 1100px) { .oc-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .oc-kpi-grid { grid-template-columns: 1fr; } }

    .oc-kpi {
        position: relative;
        background: #fff;
        border: 1px solid #e6ebf4;
        border-radius: 1rem;
        padding: 1.05rem 1.15rem 1rem 1.2rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }
    .oc-kpi::before {
        content: '';
        position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
    }
    .oc-kpi.blue::before { background: #1A44F7; }
    .oc-kpi.green::before { background: #1E8449; }
    .oc-kpi.red::before { background: #C0392B; }
    .oc-kpi.gold::before { background: #F3BA15; }
    .oc-kpi-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
    .oc-kpi-label { font-size: 11px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: #64748b; }
    .oc-kpi-value { margin-top: 6px; font-size: 1.28rem; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; line-height: 1.15; }
    .oc-kpi-value.danger { color: #C0392B; }
    .oc-kpi-sub { margin-top: 5px; font-size: 12px; color: #64748b; }
    .oc-kpi-icon {
        width: 38px; height: 38px; border-radius: 11px;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .oc-kpi.blue .oc-kpi-icon { background: #e8edff; color: #1A44F7; }
    .oc-kpi.green .oc-kpi-icon { background: #e6f6ee; color: #1E8449; }
    .oc-kpi.red .oc-kpi-icon { background: #fde8e6; color: #C0392B; }
    .oc-kpi.gold .oc-kpi-icon { background: #fff6d9; color: #b45309; }
    .oc-bar { height: 5px; margin-top: 12px; background: #eef2f7; border-radius: 99px; overflow: hidden; }
    .oc-bar > span { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #F3BA15, #1E8449); }

    .oc-filters {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px;
        background: #fff;
        border: 1px solid #e6ebf4;
        border-radius: 1rem;
        padding: 14px 16px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .oc-filters-title {
        display: flex; align-items: center; gap: 6px;
        font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
        color: #1A44F7; padding-bottom: 8px; margin-right: 4px;
    }
    .oc-field { display: flex; flex-direction: column; gap: 5px; min-width: 170px; flex: 1; }
    .oc-field.small { flex: 0 0 110px; min-width: 110px; }
    .oc-field label { font-size: 12px; font-weight: 600; color: #334155; }
    .oc-select {
        width: 100%;
        height: 40px;
        padding: 0 32px 0 12px;
        border: 1px solid #d7deed;
        border-radius: 10px;
        background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%2364748b' d='M1.4.6 6 5.2 10.6.6 12 2 6 8 0 2z'/%3E%3C/svg%3E") no-repeat right 12px center;
        color: #0f172a;
        font-size: 13.5px;
        appearance: none;
    }
    .oc-select:focus { outline: none; border-color: #1A44F7; box-shadow: 0 0 0 3px rgba(26, 68, 247, 0.15); background-color: #fff; }
    .oc-reset {
        height: 40px; padding: 0 14px; border-radius: 10px;
        border: 1px solid #d7deed; background: #fff; color: #334155;
        font-size: 13px; font-weight: 600; cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .oc-reset:hover { background: #f1f5f9; }

    .oc-panel {
        background: #fff;
        border: 1px solid #e6ebf4;
        border-radius: 1rem;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .oc-panel-head {
        display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 14px 18px;
        background: linear-gradient(180deg, #f8faff 0%, #ffffff 100%);
        border-bottom: 1px solid #e6ebf4;
    }
    .oc-panel-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; color: #0f172a; }
    .oc-chip {
        font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 99px;
        background: #e8edff; color: #1A44F7;
    }
    .oc-count {
        font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 99px;
        background: #0f172a; color: #fff;
    }

    .oc-table { width: 100%; border-collapse: collapse; }
    .oc-table th {
        padding: 10px 14px; text-align: left;
        font-size: 10.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
        color: #64748b; background: #f8fafc; white-space: nowrap;
    }
    .oc-table th.right, .oc-table td.right { text-align: right; }
    .oc-table td {
        padding: 12px 14px; font-size: 13.5px; color: #0f172a; border-top: 1px solid #eef2f7; vertical-align: middle;
    }
    .oc-table tbody tr:hover td { background: #f7f9ff; }
    .oc-name { font-weight: 650; }
    .oc-code { font-family: ui-monospace, monospace; font-size: 11px; padding: 2px 7px; border-radius: 6px; background: #f1f5f9; color: #475569; }
    .oc-group { font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 99px; background: #e8edff; color: #1A44F7; }
    .oc-month { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: #fde8e6; color: #C0392B; }
    .oc-paid { color: #1E8449; font-weight: 700; font-variant-numeric: tabular-nums; }
    .oc-out { color: #C0392B; font-weight: 800; font-variant-numeric: tabular-nums; }
    .oc-mini { width: 56px; height: 4px; background: #f1f5f9; border-radius: 99px; overflow: hidden; display: inline-block; vertical-align: middle; margin-left: 8px; }
    .oc-mini > span { display: block; height: 100%; background: #C0392B; }

    .oc-empty, .oc-none {
        text-align: center; padding: 3.5rem 1.5rem;
    }
    .oc-empty h3, .oc-none h3 { margin: 0 0 6px; font-size: 16px; color: #0f172a; }
    .oc-empty p, .oc-none p { margin: 0 auto; max-width: 380px; font-size: 13px; color: #64748b; }
    .oc-pager { padding: 12px 16px; border-top: 1px solid #e6ebf4; background: #f8fafc; }

    .dark .oc-kpi, .dark .oc-filters, .dark .oc-panel { background: #111827; border-color: #334155; }
    .dark .oc-kpi-value, .dark .oc-panel-title, .dark .oc-table td, .dark .oc-empty h3, .dark .oc-none h3 { color: #f8fafc; }
    .dark .oc-kpi-label, .dark .oc-kpi-sub, .dark .oc-field label, .dark .oc-table th { color: #94a3b8; }
    .dark .oc-select, .dark .oc-reset { background: #0f172a; border-color: #334155; color: #f8fafc; }
    .dark .oc-table th, .dark .oc-panel-head, .dark .oc-pager { background: #0f172a; border-color: #334155; }
    .dark .oc-table td { border-top-color: #1e293b; }
    .dark .oc-table tbody tr:hover td { background: #1e293b; }
    .dark .oc-code { background: #1e293b; color: #cbd5e1; }
    .dark .oc-count { background: #e8edff; color: #1A44F7; }
</style>

<div class="oc-wrap">
    @if(! $activeYear)
        <div class="oc-none oc-panel">
            <h3>No active academic year</h3>
            <p>Ask the Education Head to activate a year before outstanding contributions can be calculated.</p>
        </div>
    @else
        <div class="oc-hero">
            <div class="oc-hero-top">
                <div>
                    <div class="oc-hero-kicker">{{ $activeYear->name }}</div>
                    <h2>Outstanding contributions</h2>
                    <p>Track who still owes for this year, filter by group or Ethiopian month, and see how collection is moving.</p>
                </div>
                <div class="oc-hero-stat">
                    <span>Still to collect</span>
                    <strong>Birr {{ number_format($summaryData['total_outstanding'], 2) }}</strong>
                </div>
            </div>
        </div>

        <div class="oc-kpi-grid">
            <div class="oc-kpi blue">
                <div class="oc-kpi-row">
                    <div>
                        <div class="oc-kpi-label">Expected</div>
                        <div class="oc-kpi-value">Birr {{ number_format($summaryData['total_expected'], 2) }}</div>
                        <div class="oc-kpi-sub">Full year target</div>
                    </div>
                    <div class="oc-kpi-icon">
                        <x-filament::icon icon="heroicon-o-calculator" class="h-5 w-5" />
                    </div>
                </div>
            </div>
            <div class="oc-kpi green">
                <div class="oc-kpi-row">
                    <div>
                        <div class="oc-kpi-label">Collected</div>
                        <div class="oc-kpi-value">Birr {{ number_format($summaryData['total_collected'], 2) }}</div>
                        <div class="oc-kpi-sub">Payments received</div>
                    </div>
                    <div class="oc-kpi-icon">
                        <x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5" />
                    </div>
                </div>
            </div>
            <div class="oc-kpi red">
                <div class="oc-kpi-row">
                    <div>
                        <div class="oc-kpi-label">Outstanding</div>
                        <div class="oc-kpi-value danger">Birr {{ number_format($summaryData['total_outstanding'], 2) }}</div>
                        <div class="oc-kpi-sub">Still unpaid</div>
                    </div>
                    <div class="oc-kpi-icon">
                        <x-filament::icon icon="heroicon-o-exclamation-circle" class="h-5 w-5" />
                    </div>
                </div>
            </div>
            <div class="oc-kpi gold">
                <div class="oc-kpi-row">
                    <div>
                        <div class="oc-kpi-label">Collection rate</div>
                        <div class="oc-kpi-value">{{ $summaryData['collection_rate'] }}%</div>
                        <div class="oc-kpi-sub">Of expected collected</div>
                    </div>
                    <div class="oc-kpi-icon">
                        <x-filament::icon icon="heroicon-o-chart-pie" class="h-5 w-5" />
                    </div>
                </div>
                <div class="oc-bar"><span style="width: {{ min($summaryData['collection_rate'], 100) }}%"></span></div>
            </div>
        </div>

        <div class="oc-filters">
            <div class="oc-filters-title">
                <x-filament::icon icon="heroicon-o-funnel" class="h-4 w-4" />
                Filters
            </div>
            <div class="oc-field">
                <label for="oc-group">Member group</label>
                <select id="oc-group" wire:model.live="group_id" class="oc-select">
                    <option value="">All groups</option>
                    @foreach(\App\Models\MemberGroup::query()->orderBy('name')->pluck('name', 'id') as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="oc-field">
                <label for="oc-month">Ethiopian month</label>
                <select id="oc-month" wire:model.live="month" class="oc-select">
                    <option value="">All months</option>
                    @foreach($ethiopianMonths as $num => $name)
                        <option value="{{ $num }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="oc-field small">
                <label for="oc-per-page">Per page</label>
                <select id="oc-per-page" wire:model.live="perPage" class="oc-select">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <button type="button" wire:click="resetFilters" class="oc-reset">
                <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4" />
                Reset
            </button>
        </div>

        @php $paginator = $this->getTableDataPaginator(); $rows = $paginator->items(); @endphp
        <div class="oc-panel">
            <div class="oc-panel-head">
                <div class="oc-panel-title">
                    Members who still owe
                    @if(! $this->month)
                        <span class="oc-chip">Annual view</span>
                    @endif
                </div>
                <span class="oc-count">{{ $paginator->total() }} members</span>
            </div>

            @if(empty($rows))
                <div class="oc-empty">
                    <h3>All caught up</h3>
                    <p>No outstanding contributions for these filters.</p>
                </div>
            @else
                @php $maxOutstanding = collect($rows)->max('outstanding') ?: 1; @endphp
                <div style="overflow-x:auto;">
                    <table class="oc-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th>Code</th>
                                <th>Group</th>
                                <th>{{ $this->month ? 'Month' : 'Outstanding months' }}</th>
                                <th class="right">Expected</th>
                                <th class="right">Paid</th>
                                <th class="right">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $i => $row)
                                @php $outPct = ($row['outstanding'] / $maxOutstanding) * 100; @endphp
                                <tr>
                                    <td style="color:#64748b; font-size:12px;">{{ $paginator->firstItem() + $i }}</td>
                                    <td class="oc-name">{{ $row['member_name'] }}</td>
                                    <td><span class="oc-code">{{ $row['member_code'] }}</span></td>
                                    <td><span class="oc-group">{{ $row['group_name'] }}</span></td>
                                    <td>
                                        @if($this->month)
                                            {{ $ethiopianMonths[$this->month] ?? $row['month_name'] }}
                                        @else
                                            <div style="display:flex; flex-wrap:wrap; gap:4px; max-width:280px;">
                                                @foreach(explode(', ', $row['month_name']) as $mn)
                                                    <span class="oc-month">{{ trim($mn) }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="right" style="color:#64748b;">Birr {{ number_format($row['expected'], 2) }}</td>
                                    <td class="right oc-paid">Birr {{ number_format($row['paid'], 2) }}</td>
                                    <td class="right">
                                        <span class="oc-out">Birr {{ number_format($row['outstanding'], 2) }}</span>
                                        <span class="oc-mini"><span style="width: {{ $outPct }}%"></span></span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($paginator->hasPages())
                    <div class="oc-pager">{{ $paginator->onEachSide(1)->links() }}</div>
                @endif
            @endif
        </div>
    @endif
</div>
</x-filament-panels::page>
