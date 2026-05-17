<x-filament-panels::page>

<style>
/* ── Design tokens (same system as aid report) ── */
:root {
    --tr-bg:           #f8f7f4;
    --tr-surface:      #ffffff;
    --tr-surface2:     #f3f2ef;
    --tr-border:       #e8e6e1;
    --tr-text:         #1a1917;
    --tr-text-2:       #6b6760;
    --tr-text-3:       #9c9890;
    --tr-accent:       #2d6a4f;
    --tr-accent-light: #e8f5ee;
    --tr-blue:         #1d4ed8;
    --tr-blue-light:   #eff6ff;
    --tr-amber:        #b45309;
    --tr-amber-light:  #fffbeb;
    --tr-red:          #dc2626;
    --tr-red-light:    #fef2f2;
    --tr-purple:       #7c3aed;
    --tr-purple-light: #f5f3ff;
    --tr-input-bg:     #ffffff;
    --tr-shadow:       0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --tr-shadow-md:    0 4px 12px rgba(0,0,0,.08);
}
.dark {
    --tr-bg:           #0f0f0e;
    --tr-surface:      #1a1917;
    --tr-surface2:     #232220;
    --tr-border:       #2e2c29;
    --tr-text:         #f0ede8;
    --tr-text-2:       #a09c95;
    --tr-text-3:       #6b6760;
    --tr-accent:       #4ade80;
    --tr-accent-light: #0d2117;
    --tr-blue:         #60a5fa;
    --tr-blue-light:   #0d1829;
    --tr-amber:        #fbbf24;
    --tr-amber-light:  #1c1508;
    --tr-red:          #f87171;
    --tr-red-light:    #2a0d0d;
    --tr-purple:       #a78bfa;
    --tr-purple-light: #150d2b;
    --tr-input-bg:     #232220;
    --tr-shadow:       0 1px 3px rgba(0,0,0,.3);
    --tr-shadow-md:    0 4px 16px rgba(0,0,0,.4);
}

.tr-page { display:flex;flex-direction:column;gap:1.5rem;padding-bottom:2.5rem; }

/* ── Filters ── */
.tr-filters {
    background:var(--tr-surface);
    border:1px solid var(--tr-border);
    border-radius:14px;
    padding:1.25rem 1.5rem;
    box-shadow:var(--tr-shadow);
}
.tr-filters-label {
    font-size:11px;font-weight:700;letter-spacing:.08em;
    text-transform:uppercase;color:var(--tr-text-3);
    margin-bottom:.875rem;display:flex;align-items:center;gap:6px;
}
.tr-filters-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:12px;max-width:560px; }
.tr-filter-group { display:flex;flex-direction:column;gap:5px; }
.tr-filter-group label { font-size:11px;font-weight:600;color:var(--tr-text-2); }
.tr-input {
    width:100%;padding:8px 11px;
    background:var(--tr-input-bg);
    border:1px solid var(--tr-border);
    border-radius:8px;
    font-size:13px;color:var(--tr-text);
    transition:border-color .15s,box-shadow .15s;
    outline:none;
    box-shadow:var(--tr-shadow);
}
.tr-input:focus { border-color:var(--tr-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--tr-accent) 20%,transparent); }

/* ── Stat cards ── */
.tr-stats-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:12px; }
.tr-card {
    background:var(--tr-surface);
    border:1px solid var(--tr-border);
    border-radius:14px;
    padding:1.125rem 1.25rem;
    box-shadow:var(--tr-shadow);
    position:relative;overflow:hidden;
    transition:box-shadow .2s,transform .2s;
}
.tr-card:hover { box-shadow:var(--tr-shadow-md);transform:translateY(-1px); }
.tr-card-accent { position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0; }
.tr-card-icon {
    width:36px;height:36px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    margin-bottom:.75rem;font-size:16px;
}
.tr-card-label { font-size:11px;font-weight:600;color:var(--tr-text-2);margin-bottom:4px;letter-spacing:.02em; }
.tr-card-value { font-size:24px;font-weight:800;line-height:1;color:var(--tr-text);letter-spacing:-.02em; }
.tr-card-sub   { font-size:11px;color:var(--tr-text-3);margin-top:4px; }

/* ── Table panel ── */
.tr-panel {
    background:var(--tr-surface);
    border:1px solid var(--tr-border);
    border-radius:14px;
    overflow:hidden;
    box-shadow:var(--tr-shadow);
}
.tr-panel-header {
    padding:.875rem 1.25rem;
    border-bottom:1px solid var(--tr-border);
    display:flex;align-items:center;gap:8px;
    background:var(--tr-surface2);
}
.tr-panel-title { font-size:13px;font-weight:700;color:var(--tr-text);letter-spacing:.01em; }

/* ── Table ── */
.tr-table { width:100%;border-collapse:collapse;font-size:13px; }
.tr-table thead tr { background:var(--tr-surface2); }
.tr-table th {
    padding:10px 14px;text-align:left;
    font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
    color:var(--tr-text-3);border-bottom:1px solid var(--tr-border);
    white-space:nowrap;
}
.tr-table th.right { text-align:right; }
.tr-table td {
    padding:11px 14px;
    border-bottom:1px solid var(--tr-border);
    color:var(--tr-text);font-size:13px;
}
.tr-table td.muted  { color:var(--tr-text-2); }
.tr-table td.right  { text-align:right; }
.tr-table tbody tr:last-child td { border-bottom:none; }
.tr-table tbody tr { transition:background .12s; }
.tr-table tbody tr:hover { background:var(--tr-surface2); }

/* ── Place cell ── */
.tr-place { display:flex;align-items:center;gap:10px; }
.tr-place-pin {
    width:30px;height:30px;border-radius:8px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:14px;
    background:var(--tr-surface2);border:1px solid var(--tr-border);
}

/* ── Badges ── */
.tr-badge {
    display:inline-flex;align-items:center;gap:4px;
    padding:3px 9px;border-radius:99px;
    font-size:11px;font-weight:700;white-space:nowrap;
}
.tr-badge-dot { width:5px;height:5px;border-radius:50%; }

/* ── Confirmation bar ── */
.tr-conf-wrap { display:flex;align-items:center;gap:8px; }
.tr-conf-bar-track { width:52px;height:4px;background:var(--tr-border);border-radius:99px;overflow:hidden; }
.tr-conf-bar-fill  { height:100%;border-radius:99px;transition:width .3s; }

/* ── Empty state ── */
.tr-empty {
    padding:3rem 1.25rem;text-align:center;
    color:var(--tr-text-3);font-size:13px;
}
.tr-empty-icon { font-size:32px;margin-bottom:.5rem; }

/* ── Responsive ── */
@media(max-width:768px){
    .tr-filters-grid { grid-template-columns:1fr;max-width:100%; }
    .tr-stats-grid   { grid-template-columns:1fr; }
}
</style>

<div class="tr-page">

    {{-- ── Filters ── --}}
    <div class="tr-filters">
        <div class="tr-filters-label">
            <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L13 10.414V17a1 1 0 01-.553.894l-4-2A1 1 0 018 15v-4.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
            Report Filters
        </div>
        <div class="tr-filters-grid">
            <div class="tr-filter-group">
                <label>Status</label>
                <select wire:model.live="status" class="tr-input">
                    <option value="all">All Statuses</option>
                    <option value="Draft">Draft</option>
                    <option value="Published">Published</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            <div class="tr-filter-group">
                <label>Date Range</label>
                <select wire:model.live="date_range" class="tr-input">
                    <option value="all">All Time</option>
                    <option value="month">Last Month</option>
                    <option value="quarter">Last Quarter</option>
                    <option value="year">Last Year</option>
                </select>
            </div>
        </div>
    </div>

    @php
        $data = $this->getReportData();

        $statusMeta = [
            'Draft'       => ['bg'=>'var(--tr-surface2)',      'color'=>'var(--tr-text-2)',   'dot'=>'var(--tr-text-3)',  'icon'=>'✏️'],
            'Published'   => ['bg'=>'var(--tr-blue-light)',    'color'=>'var(--tr-blue)',     'dot'=>'var(--tr-blue)',    'icon'=>'📢'],
            'In Progress' => ['bg'=>'var(--tr-amber-light)',   'color'=>'var(--tr-amber)',    'dot'=>'var(--tr-amber)',   'icon'=>'⚙️'],
            'Completed'   => ['bg'=>'var(--tr-accent-light)',  'color'=>'var(--tr-accent)',   'dot'=>'var(--tr-accent)',  'icon'=>'✅'],
            'Cancelled'   => ['bg'=>'var(--tr-red-light)',     'color'=>'var(--tr-red)',      'dot'=>'var(--tr-red)',     'icon'=>'🚫'],
        ];

        $confirmedRate = ($data['totalPassengers'] ?? 0) > 0
            ? round(($data['totalConfirmed'] / $data['totalPassengers']) * 100)
            : 0;
    @endphp

    {{-- ── Summary Cards ── --}}
    <div class="tr-stats-grid">

        <div class="tr-card">
            <div class="tr-card-accent" style="background:var(--tr-text-2);"></div>
            <div class="tr-card-icon" style="background:var(--tr-surface2);">🗺️</div>
            <div class="tr-card-label">Total Tours</div>
            <div class="tr-card-value">{{ number_format($data['totalTours'] ?? 0) }}</div>
        </div>

        <div class="tr-card">
            <div class="tr-card-accent" style="background:var(--tr-blue);"></div>
            <div class="tr-card-icon" style="background:var(--tr-blue-light);">👤</div>
            <div class="tr-card-label">Total Passengers</div>
            <div class="tr-card-value" style="color:var(--tr-blue);">{{ number_format($data['totalPassengers'] ?? 0) }}</div>
        </div>

        <div class="tr-card">
            <div class="tr-card-accent" style="background:var(--tr-accent);"></div>
            <div class="tr-card-icon" style="background:var(--tr-accent-light);">✅</div>
            <div class="tr-card-label">Confirmed Passengers</div>
            <div class="tr-card-value" style="color:var(--tr-accent);">{{ number_format($data['totalConfirmed'] ?? 0) }}</div>
            <div class="tr-card-sub">{{ $confirmedRate }}% confirmation rate</div>
        </div>

    </div>

    {{-- ── Tours Table ── --}}
    <div class="tr-panel">
        <div class="tr-panel-header">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="var(--tr-text-2)"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
            <span class="tr-panel-title">Tours</span>
            <span style="margin-left:auto;font-size:11px;color:var(--tr-text-3);">
                {{ count($data['tours'] ?? []) }} {{ Str::plural('tour', count($data['tours'] ?? [])) }}
            </span>
        </div>

        @if(empty($data['tours']))
            <div class="tr-empty">
                <div class="tr-empty-icon">🗺️</div>
                No tours found for the selected filters.
            </div>
        @else
            <div style="overflow-x:auto;">
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
                                $meta      = $statusMeta[$tour->status] ?? $statusMeta['Draft'];
                                $paxTotal  = $tour->passengers->count();
                                $paxConf   = $tour->passengers->where('status', 'Confirmed')->count();
                                $confPct   = $paxTotal > 0 ? round(($paxConf / $paxTotal) * 100) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="tr-place">
                                        <div class="tr-place-pin">{{ $meta['icon'] }}</div>
                                        <span style="font-weight:600;color:var(--tr-text);">{{ $tour->place }}</span>
                                    </div>
                                </td>
                                <td class="muted">
                                    {{ $tour->tour_date?->format('M d, Y') ?? '—' }}
                                </td>
                                <td>
                                    <span class="tr-badge" style="background:{{ $meta['bg'] }};color:{{ $meta['color'] }};">
                                        <span class="tr-badge-dot" style="background:{{ $meta['dot'] }};"></span>
                                        {{ $tour->status }}
                                    </span>
                                </td>
                                <td class="right" style="font-weight:600;">{{ $paxTotal }}</td>
                                <td class="right">
                                    <div class="tr-conf-wrap" style="justify-content:flex-end;">
                                        <span style="font-weight:600;color:var(--tr-accent);min-width:20px;text-align:right;">{{ $paxConf }}</span>
                                        <div class="tr-conf-bar-track">
                                            <div class="tr-conf-bar-fill" style="width:{{ $confPct }}%;background:var(--tr-accent);"></div>
                                        </div>
                                        <span style="font-size:11px;color:var(--tr-text-3);min-width:28px;">{{ $confPct }}%</span>
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
