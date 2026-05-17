<x-filament-panels::page>

<style>
/* ── Design tokens ── */
:root {
    --aid-bg:           #f8f7f4;
    --aid-surface:      #ffffff;
    --aid-surface2:     #f3f2ef;
    --aid-border:       #e8e6e1;
    --aid-text:         #1a1917;
    --aid-text-2:       #6b6760;
    --aid-text-3:       #9c9890;
    --aid-accent:       #2d6a4f;
    --aid-accent-light: #e8f5ee;
    --aid-blue:         #1d4ed8;
    --aid-blue-light:   #eff6ff;
    --aid-purple:       #7c3aed;
    --aid-purple-light: #f5f3ff;
    --aid-amber:        #b45309;
    --aid-amber-light:  #fffbeb;
    --aid-red:          #dc2626;
    --aid-input-bg:     #ffffff;
    --aid-shadow:       0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --aid-shadow-md:    0 4px 12px rgba(0,0,0,.08);
}
.dark {
    --aid-bg:           #0f0f0e;
    --aid-surface:      #1a1917;
    --aid-surface2:     #232220;
    --aid-border:       #2e2c29;
    --aid-text:         #f0ede8;
    --aid-text-2:       #a09c95;
    --aid-text-3:       #6b6760;
    --aid-accent:       #4ade80;
    --aid-accent-light: #0d2117;
    --aid-blue:         #60a5fa;
    --aid-blue-light:   #0d1829;
    --aid-purple:       #a78bfa;
    --aid-purple-light: #150d2b;
    --aid-amber:        #fbbf24;
    --aid-amber-light:  #1c1508;
    --aid-red:          #f87171;
    --aid-input-bg:     #232220;
    --aid-shadow:       0 1px 3px rgba(0,0,0,.3);
    --aid-shadow-md:    0 4px 16px rgba(0,0,0,.4);
}

/* ── Base layout ── */
.aid-page { display:flex;flex-direction:column;gap:1.5rem;padding-bottom:2.5rem; }

/* ── Filter bar ── */
.aid-filters {
    background:var(--aid-surface);
    border:1px solid var(--aid-border);
    border-radius:14px;
    padding:1.25rem 1.5rem;
    box-shadow:var(--aid-shadow);
}
.aid-filters-label {
    font-size:11px;font-weight:700;letter-spacing:.08em;
    text-transform:uppercase;color:var(--aid-text-3);
    margin-bottom:.875rem;display:flex;align-items:center;gap:6px;
}
.aid-filters-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:12px; }
.aid-filter-group { display:flex;flex-direction:column;gap:5px; }
.aid-filter-group label { font-size:11px;font-weight:600;color:var(--aid-text-2); }
.aid-input {
    width:100%;padding:8px 11px;
    background:var(--aid-input-bg);
    border:1px solid var(--aid-border);
    border-radius:8px;
    font-size:13px;color:var(--aid-text);
    transition:border-color .15s,box-shadow .15s;
    outline:none;
    box-shadow:var(--aid-shadow);
}
.aid-input:focus { border-color:var(--aid-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--aid-accent) 20%,transparent); }

/* ── Stat cards ── */
.aid-stats-grid { display:grid;gap:12px; }
.aid-stats-grid.cols-4 { grid-template-columns:repeat(4,1fr); }
.aid-stats-grid.cols-5 { grid-template-columns:repeat(5,1fr); }

.aid-card {
    background:var(--aid-surface);
    border:1px solid var(--aid-border);
    border-radius:14px;
    padding:1.125rem 1.25rem;
    box-shadow:var(--aid-shadow);
    position:relative;overflow:hidden;
    transition:box-shadow .2s,transform .2s;
}
.aid-card:hover { box-shadow:var(--aid-shadow-md);transform:translateY(-1px); }
.aid-card-accent { position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0; }
.aid-card-icon {
    width:36px;height:36px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    margin-bottom:.75rem;font-size:16px;
}
.aid-card-label { font-size:11px;font-weight:600;color:var(--aid-text-2);margin-bottom:4px;letter-spacing:.02em; }
.aid-card-value { font-size:24px;font-weight:800;line-height:1;color:var(--aid-text);letter-spacing:-.02em; }
.aid-card-sub   { font-size:11px;color:var(--aid-text-3);margin-top:4px; }

/* ── Section panels ── */
.aid-panel {
    background:var(--aid-surface);
    border:1px solid var(--aid-border);
    border-radius:14px;
    overflow:hidden;
    box-shadow:var(--aid-shadow);
}
.aid-panel-header {
    padding:.875rem 1.25rem;
    border-bottom:1px solid var(--aid-border);
    display:flex;align-items:center;gap:8px;
    background:var(--aid-surface2);
}
.aid-panel-title { font-size:13px;font-weight:700;color:var(--aid-text);letter-spacing:.01em; }
.aid-panel-body  { padding:1.125rem 1.25rem;display:flex;flex-direction:column;gap:8px; }

/* ── Row items ── */
.aid-row {
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 14px;
    background:var(--aid-surface2);
    border-radius:10px;
    border:1px solid var(--aid-border);
    transition:background .15s;
}
.aid-row:hover { background:color-mix(in srgb,var(--aid-accent) 6%,var(--aid-surface2)); }
.aid-row-label { font-size:13px;font-weight:600;color:var(--aid-text); }
.aid-row-right  { display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end; }
.aid-badge {
    display:inline-flex;align-items:center;padding:2px 9px;
    border-radius:99px;font-size:11px;font-weight:700;
    white-space:nowrap;
}

/* ── Progress bar ── */
.aid-progress-wrap { width:80px;height:4px;background:var(--aid-border);border-radius:99px;overflow:hidden; }
.aid-progress-bar  { height:100%;border-radius:99px;transition:width .4s ease; }

/* ── Responsive ── */
@media(max-width:768px){
    .aid-filters-grid { grid-template-columns:1fr; }
    .aid-stats-grid.cols-4,
    .aid-stats-grid.cols-5 { grid-template-columns:repeat(2,1fr); }
}
</style>

<div class="aid-page">

    {{-- ── Filters ── --}}
    <div class="aid-filters">
        <div class="aid-filters-label">
            <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L13 10.414V17a1 1 0 01-.553.894l-4-2A1 1 0 018 15v-4.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
            Report Filters
        </div>
        <div class="aid-filters-grid">
            <div class="aid-filter-group">
                <label>From Date</label>
                <input type="date" wire:model.live="date_from" class="aid-input" />
            </div>
            <div class="aid-filter-group">
                <label>To Date</label>
                <input type="date" wire:model.live="date_to" class="aid-input" />
            </div>
            <div class="aid-filter-group">
                <label>Aid Type</label>
                <select wire:model.live="aid_type" class="aid-input">
                    <option value="">All Aid Types</option>
                    @foreach(\App\Models\AidDistribution::distinct()->pluck('aid_type') as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @php
        $beneficiaryData  = $this->getBeneficiaryData();
        $distributionData = $this->getDistributionData();

        $totalDist = $distributionData['total_distributions'] ?? 0;
        $maxCount  = collect($distributionData['by_type'] ?? [])->max('count') ?: 1;
        $maxMonthly= collect($distributionData['monthly_trend'] ?? [])->max('count') ?: 1;
    @endphp

    {{-- ── Beneficiary Stats ── --}}
    <div class="aid-stats-grid cols-4">

        <div class="aid-card">
            <div class="aid-card-accent" style="background:var(--aid-text-2);"></div>
            <div class="aid-card-icon" style="background:var(--aid-surface2);">👥</div>
            <div class="aid-card-label">Total Beneficiaries</div>
            <div class="aid-card-value">{{ number_format($beneficiaryData['total'] ?? 0) }}</div>
        </div>

        <div class="aid-card">
            <div class="aid-card-accent" style="background:var(--aid-accent);"></div>
            <div class="aid-card-icon" style="background:var(--aid-accent-light);">✅</div>
            <div class="aid-card-label">Active</div>
            <div class="aid-card-value" style="color:var(--aid-accent);">{{ number_format($beneficiaryData['active'] ?? 0) }}</div>
            @php $activeRate = ($beneficiaryData['total'] ?? 0) > 0 ? round(($beneficiaryData['active'] / $beneficiaryData['total']) * 100) : 0; @endphp
            <div class="aid-card-sub">{{ $activeRate }}% of total</div>
        </div>

        <div class="aid-card">
            <div class="aid-card-accent" style="background:var(--aid-text-3);"></div>
            <div class="aid-card-icon" style="background:var(--aid-surface2);">⏸</div>
            <div class="aid-card-label">Inactive</div>
            <div class="aid-card-value" style="color:var(--aid-text-2);">{{ number_format($beneficiaryData['inactive'] ?? 0) }}</div>
        </div>

        <div class="aid-card">
            <div class="aid-card-accent" style="background:var(--aid-blue);"></div>
            <div class="aid-card-icon" style="background:var(--aid-blue-light);">🏁</div>
            <div class="aid-card-label">Completed</div>
            <div class="aid-card-value" style="color:var(--aid-blue);">{{ number_format($beneficiaryData['completed'] ?? 0) }}</div>
        </div>

    </div>

    {{-- ── Distribution Stats ── --}}
    <div class="aid-stats-grid cols-5">

        <div class="aid-card">
            <div class="aid-card-accent" style="background:var(--aid-text-2);"></div>
            <div class="aid-card-icon" style="background:var(--aid-surface2);">📦</div>
            <div class="aid-card-label">Total Distributions</div>
            <div class="aid-card-value">{{ number_format($distributionData['total_distributions'] ?? 0) }}</div>
        </div>

        <div class="aid-card">
            <div class="aid-card-accent" style="background:var(--aid-accent);"></div>
            <div class="aid-card-icon" style="background:var(--aid-accent-light);">💵</div>
            <div class="aid-card-label">Monetary</div>
            <div class="aid-card-value" style="color:var(--aid-accent);">{{ number_format($distributionData['monetary_distributions'] ?? 0) }}</div>
        </div>

        <div class="aid-card">
            <div class="aid-card-accent" style="background:var(--aid-purple);"></div>
            <div class="aid-card-icon" style="background:var(--aid-purple-light);">🎁</div>
            <div class="aid-card-label">Non-Monetary</div>
            <div class="aid-card-value" style="color:var(--aid-purple);">{{ number_format($distributionData['non_monetary_distributions'] ?? 0) }}</div>
        </div>

        <div class="aid-card">
            <div class="aid-card-accent" style="background:var(--aid-blue);"></div>
            <div class="aid-card-icon" style="background:var(--aid-blue-light);">💰</div>
            <div class="aid-card-label">Total Amount</div>
            <div class="aid-card-value" style="font-size:18px;color:var(--aid-blue);">{{ number_format($distributionData['total_amount'] ?? 0, 0) }}</div>
            <div class="aid-card-sub">ETB</div>
        </div>

        <div class="aid-card">
            <div class="aid-card-accent" style="background:var(--aid-amber);"></div>
            <div class="aid-card-icon" style="background:var(--aid-amber-light);">📊</div>
            <div class="aid-card-label">Avg per Distribution</div>
            <div class="aid-card-value" style="font-size:18px;color:var(--aid-amber);">{{ number_format($distributionData['average_amount'] ?? 0, 0) }}</div>
            <div class="aid-card-sub">ETB</div>
        </div>

    </div>

    {{-- ── By Aid Type ── --}}
    @if(!empty($distributionData['by_type']))
    <div class="aid-panel">
        <div class="aid-panel-header">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="var(--aid-text-2)"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
            <span class="aid-panel-title">Distributions by Aid Type</span>
            <span style="margin-left:auto;font-size:11px;color:var(--aid-text-3);">{{ count($distributionData['by_type']) }} types</span>
        </div>
        <div class="aid-panel-body">
            @foreach($distributionData['by_type'] as $type => $info)
                @php $pct = $maxCount > 0 ? round(($info['count'] / $maxCount) * 100) : 0; @endphp
                <div class="aid-row">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                        <div class="aid-progress-wrap" style="flex-shrink:0;">
                            <div class="aid-progress-bar" style="width:{{ $pct }}%;background:var(--aid-blue);"></div>
                        </div>
                        <span class="aid-row-label">{{ $type }}</span>
                    </div>
                    <div class="aid-row-right">
                        <span class="aid-badge" style="background:var(--aid-blue-light);color:var(--aid-blue);">
                            {{ number_format($info['count']) }} records
                        </span>
                        @if(($info['total'] ?? 0) > 0)
                        <span class="aid-badge" style="background:var(--aid-accent-light);color:var(--aid-accent);">
                            ETB {{ number_format($info['total'], 0) }}
                        </span>
                        @endif
                        @if(($info['non_monetary_count'] ?? 0) > 0)
                        <span class="aid-badge" style="background:var(--aid-purple-light);color:var(--aid-purple);">
                            {{ $info['non_monetary_count'] }} non-monetary
                        </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Monthly Trend ── --}}
    @if(!empty($distributionData['monthly_trend']))
    <div class="aid-panel">
        <div class="aid-panel-header">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="var(--aid-text-2)"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11 4a1 1 0 10-2 0v4a1 1 0 102 0V7zm-3 1a1 1 0 10-2 0v3a1 1 0 102 0V8zM8 9a1 1 0 00-2 0v2a1 1 0 102 0V9z" clip-rule="evenodd"/></svg>
            <span class="aid-panel-title">Monthly Trend</span>
            <span style="margin-left:auto;font-size:11px;color:var(--aid-text-3);">{{ count($distributionData['monthly_trend']) }} months</span>
        </div>
        <div class="aid-panel-body">
            @foreach($distributionData['monthly_trend'] as $month => $info)
                @php $pct = $maxMonthly > 0 ? round(($info['count'] / $maxMonthly) * 100) : 0; @endphp
                <div class="aid-row">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                        <div class="aid-progress-wrap" style="flex-shrink:0;">
                            <div class="aid-progress-bar" style="width:{{ $pct }}%;background:var(--aid-accent);"></div>
                        </div>
                        <span class="aid-row-label">{{ $month }}</span>
                    </div>
                    <div class="aid-row-right">
                        <span class="aid-badge" style="background:var(--aid-blue-light);color:var(--aid-blue);">
                            {{ number_format($info['count']) }} records
                        </span>
                        @if(($info['total'] ?? 0) > 0)
                        <span class="aid-badge" style="background:var(--aid-accent-light);color:var(--aid-accent);">
                            ETB {{ number_format($info['total'], 0) }}
                        </span>
                        @endif
                        @if(($info['non_monetary_count'] ?? 0) > 0)
                        <span class="aid-badge" style="background:var(--aid-purple-light);color:var(--aid-purple);">
                            {{ $info['non_monetary_count'] }} non-monetary
                        </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
</x-filament-panels::page>
