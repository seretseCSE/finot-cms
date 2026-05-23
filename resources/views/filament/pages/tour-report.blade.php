<x-filament-panels::page>

<style>
/* ── Design tokens ── */
:root {
    --tr-bg:           #f5f4f1;
    --tr-surface:      #ffffff;
    --tr-surface2:     #f9f8f6;
    --tr-border:       rgba(0,0,0,.08);
    --tr-border-md:    rgba(0,0,0,.13);
    --tr-text:         #141412;
    --tr-text-2:       #72706b;
    --tr-text-3:       #b0ada7;
    --tr-accent:       #1a6641;
    --tr-accent-dim:   #dcefe5;
    --tr-blue:         #1848a8;
    --tr-blue-dim:     #dde8f9;
    --tr-amber:        #8f4e08;
    --tr-amber-dim:    #fdefd8;
    --tr-red:          #b91c1c;
    --tr-red-dim:      #fde8e8;
    --tr-purple:       #5b21b6;
    --tr-purple-dim:   #ede9fb;
}

.dark {
    --tr-bg:           #111110;
    --tr-surface:      #1c1b19;
    --tr-surface2:     #232220;
    --tr-border:       rgba(255,255,255,.07);
    --tr-border-md:    rgba(255,255,255,.13);
    --tr-text:         #edebe6;
    --tr-text-2:       #908e88;
    --tr-text-3:       #5a5854;
    --tr-accent:       #4ade80;
    --tr-accent-dim:   #0c2118;
    --tr-blue:         #6ba4f5;
    --tr-blue-dim:     #0c1a34;
    --tr-amber:        #fbbf24;
    --tr-amber-dim:    #1c1306;
    --tr-red:          #f87171;
    --tr-red-dim:      #2a0c0c;
    --tr-purple:       #a78bfa;
    --tr-purple-dim:   #160e2e;
}

/* ── Page layout ── */
.tr-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding-bottom: 3rem;
}

/* ── Filter bar ── */
.tr-filters {
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: var(--tr-surface);
    border: 0.5px solid var(--tr-border-md);
    border-radius: 10px;
}

.tr-filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 160px;
}

.tr-filter-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--tr-text-3);
}

.tr-input {
    appearance: none;
    -webkit-appearance: none;
    width: 100%;
    padding: 7px 10px;
    background: var(--tr-surface2);
    border: 0.5px solid var(--tr-border-md);
    border-radius: 7px;
    font-size: 13px;
    color: var(--tr-text);
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    cursor: pointer;
}

.tr-input:focus {
    border-color: var(--tr-accent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--tr-accent) 15%, transparent);
}

/* ── Stat cards ── */
.tr-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.tr-card {
    background: var(--tr-surface);
    border: 0.5px solid var(--tr-border-md);
    border-radius: 10px;
    padding: 1.125rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 2px;
    transition: border-color .2s;
}

.tr-card:hover {
    border-color: var(--tr-border-md);
}

.tr-card-eyebrow {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--tr-text-3);
    margin-bottom: 6px;
}

.tr-card-value {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -.03em;
    line-height: 1;
    color: var(--tr-text);
}

.tr-card-sub {
    font-size: 12px;
    color: var(--tr-text-3);
    margin-top: 5px;
}

/* ── Table panel ── */
.tr-panel {
    background: var(--tr-surface);
    border: 0.5px solid var(--tr-border-md);
    border-radius: 10px;
    overflow: hidden;
}

.tr-panel-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: .75rem 1.25rem;
    border-bottom: 0.5px solid var(--tr-border);
    background: var(--tr-surface2);
}

.tr-panel-title {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--tr-text-2);
}

.tr-panel-count {
    margin-left: auto;
    font-size: 11px;
    color: var(--tr-text-3);
    font-variant-numeric: tabular-nums;
}

/* ── Table ── */
.tr-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.tr-table thead th {
    padding: 9px 14px;
    text-align: left;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--tr-text-3);
    background: var(--tr-surface2);
    border-bottom: 0.5px solid var(--tr-border-md);
    white-space: nowrap;
}

.tr-table thead th.right { text-align: right; }

.tr-table tbody td {
    padding: 11px 14px;
    border-bottom: 0.5px solid var(--tr-border);
    color: var(--tr-text);
    vertical-align: middle;
}

.tr-table tbody td.muted { color: var(--tr-text-2); font-size: 12px; }
.tr-table tbody td.right { text-align: right; }
.tr-table tbody tr:last-child td { border-bottom: none; }

.tr-table tbody tr {
    transition: background .1s;
}

.tr-table tbody tr:hover {
    background: var(--tr-surface2);
}

/* ── Place cell ── */
.tr-place {
    display: flex;
    align-items: center;
    gap: 10px;
}

.tr-place-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: var(--tr-surface2);
    border: 0.5px solid var(--tr-border-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
}

.tr-place-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--tr-text);
}

/* ── Status badge ── */
.tr-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .02em;
    white-space: nowrap;
}

.tr-badge-pip {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Confirmation bar ── */
.tr-conf {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: flex-end;
}

.tr-conf-num {
    font-size: 13px;
    font-weight: 600;
    color: var(--tr-accent);
    min-width: 20px;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.tr-conf-track {
    width: 48px;
    height: 3px;
    background: var(--tr-border-md);
    border-radius: 99px;
    overflow: hidden;
    flex-shrink: 0;
}

.tr-conf-fill {
    height: 100%;
    border-radius: 99px;
    background: var(--tr-accent);
}

.tr-conf-pct {
    font-size: 11px;
    color: var(--tr-text-3);
    min-width: 30px;
    font-variant-numeric: tabular-nums;
}

/* ── Empty state ── */
.tr-empty {
    padding: 3.5rem 1.25rem;
    text-align: center;
    color: var(--tr-text-3);
    font-size: 13px;
}

.tr-empty-icon {
    font-size: 28px;
    margin-bottom: .625rem;
    display: block;
    opacity: .6;
}

/* ── Divider between filter icon and label ── */
.tr-filter-icon {
    color: var(--tr-text-3);
    flex-shrink: 0;
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .tr-filters       { flex-direction: column; align-items: stretch; }
    .tr-filter-group  { min-width: unset; }
    .tr-stats-grid    { grid-template-columns: 1fr; }
}
</style>

<div class="tr-page">

    {{-- ── Filters ── --}}
    <div class="tr-filters">
        <div class="tr-filter-group">
            <span class="tr-filter-label">Status</span>
            <select wire:model.live="status" class="tr-input">
                <option value="all">All statuses</option>
                <option value="Draft">Draft</option>
                <option value="Published">Published</option>
                <option value="In Progress">In progress</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>
        <div class="tr-filter-group">
            <span class="tr-filter-label">Date range</span>
            <select wire:model.live="date_range" class="tr-input">
                <option value="all">All time</option>
                <option value="month">Last month</option>
                <option value="quarter">Last quarter</option>
                <option value="year">Last year</option>
            </select>
        </div>
    </div>

    @php
        $data = $this->getReportData();

        $statusMeta = [
            'Draft'       => ['bg' => 'var(--tr-surface2)',  'color' => 'var(--tr-text-2)',  'pip' => 'var(--tr-text-3)',  'icon' => 'events'],
            'Published'   => ['bg' => 'var(--tr-blue-dim)',  'color' => 'var(--tr-blue)',    'pip' => 'var(--tr-blue)',    'icon' => 'faith'],
            'In Progress' => ['bg' => 'var(--tr-amber-dim)', 'color' => 'var(--tr-amber)',   'pip' => 'var(--tr-amber)',   'icon' => 'community'],
            'Completed'   => ['bg' => 'var(--tr-accent-dim)','color' => 'var(--tr-accent)',  'pip' => 'var(--tr-accent)',  'icon' => 'leadership'],
            'Cancelled'   => ['bg' => 'var(--tr-red-dim)',   'color' => 'var(--tr-red)',     'pip' => 'var(--tr-red)',     'icon' => 'giving'],
        ];

        $confirmedRate = ($data['totalPassengers'] ?? 0) > 0
            ? round(($data['totalConfirmed'] / $data['totalPassengers']) * 100)
            : 0;
    @endphp

    {{-- ── Summary cards ── --}}
    <div class="tr-stats-grid">

        <div class="tr-card">
            <div class="tr-card-eyebrow">Total tours</div>
            <div class="tr-card-value">{{ number_format($data['totalTours'] ?? 0) }}</div>
        </div>

        <div class="tr-card">
            <div class="tr-card-eyebrow">Passengers</div>
            <div class="tr-card-value" style="color: var(--tr-blue);">
                {{ number_format($data['totalPassengers'] ?? 0) }}
            </div>
        </div>

        <div class="tr-card">
            <div class="tr-card-eyebrow">Confirmed</div>
            <div class="tr-card-value" style="color: var(--tr-accent);">
                {{ number_format($data['totalConfirmed'] ?? 0) }}
            </div>
            <div class="tr-card-sub">{{ $confirmedRate }}% confirmation rate</div>
        </div>

    </div>

    {{-- ── Tours table ── --}}
    <div class="tr-panel">
        <div class="tr-panel-header">
            <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor" style="color: var(--tr-text-3);" aria-hidden="true">
                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
            </svg>
            <span class="tr-panel-title">Tours</span>
            <span class="tr-panel-count">
                {{ count($data['tours'] ?? []) }} {{ Str::plural('result', count($data['tours'] ?? [])) }}
            </span>
        </div>

        @if(empty($data['tours']))
            <div class="tr-empty">
                <span class="tr-empty-icon"><x-tour-icon name="events" size="48" class="" aria-hidden="true" /></span>
                No tours match the selected filters.
            </div>
        @else
            <div style="overflow-x: auto;">
                <table class="tr-table">
                    <thead>
                        <tr>
                            <th>Place</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="right">Passengers</th>
                            <th class="right">Confirmed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['tours'] as $tour)
                            @php
                                $meta     = $statusMeta[$tour->status] ?? $statusMeta['Draft'];
                                $paxTotal = $tour->passengers->count();
                                $paxConf  = $tour->passengers->where('status', 'Confirmed')->count();
                                $confPct  = $paxTotal > 0 ? round(($paxConf / $paxTotal) * 100) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="tr-place">
                                        <div class="tr-place-icon"><x-tour-icon :name="$meta['icon']" size="18" class="" aria-hidden="true" /></div>
                                        <span class="tr-place-name">{{ $tour->place }}</span>
                                    </div>
                                </td>
                                <td class="muted">
                                    {{ $tour->tour_date?->format('M d, Y') ?? '—' }}
                                </td>
                                <td>
                                    <span class="tr-badge" style="background: {{ $meta['bg'] }}; color: {{ $meta['color'] }};">
                                        <span class="tr-badge-pip" style="background: {{ $meta['pip'] }};"></span>
                                        {{ $tour->status }}
                                    </span>
                                </td>
                                <td class="right" style="font-weight: 600; font-variant-numeric: tabular-nums;">
                                    {{ $paxTotal }}
                                </td>
                                <td class="right">
                                    <div class="tr-conf">
                                        <span class="tr-conf-num">{{ $paxConf }}</span>
                                        <div class="tr-conf-track">
                                            <div class="tr-conf-fill" style="width: {{ $confPct }}%;"></div>
                                        </div>
                                        <span class="tr-conf-pct">{{ $confPct }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
</x-filament-panels::page>
