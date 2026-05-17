<x-filament-panels::page>

<style>
/* ── Design tokens ── */
:root {
    --br-surface:      #ffffff;
    --br-surface2:     #f3f2ef;
    --br-border:       #e8e6e1;
    --br-text:         #1a1917;
    --br-text-2:       #6b6760;
    --br-text-3:       #9c9890;
    --br-accent:       #2d6a4f;
    --br-accent-light: #e8f5ee;
    --br-blue:         #1d4ed8;
    --br-blue-light:   #eff6ff;
    --br-amber:        #b45309;
    --br-amber-light:  #fffbeb;
    --br-red:          #dc2626;
    --br-red-light:    #fef2f2;
    --br-purple:       #7c3aed;
    --br-purple-light: #f5f3ff;
    --br-input-bg:     #ffffff;
    --br-shadow:       0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --br-shadow-md:    0 4px 12px rgba(0,0,0,.08);
}
.dark {
    --br-surface:      #1a1917;
    --br-surface2:     #232220;
    --br-border:       #2e2c29;
    --br-text:         #f0ede8;
    --br-text-2:       #a09c95;
    --br-text-3:       #6b6760;
    --br-accent:       #4ade80;
    --br-accent-light: #0d2117;
    --br-blue:         #60a5fa;
    --br-blue-light:   #0d1829;
    --br-amber:        #fbbf24;
    --br-amber-light:  #1c1508;
    --br-red:          #f87171;
    --br-red-light:    #2a0d0d;
    --br-purple:       #a78bfa;
    --br-purple-light: #150d2b;
    --br-input-bg:     #232220;
    --br-shadow:       0 1px 3px rgba(0,0,0,.3);
    --br-shadow-md:    0 4px 16px rgba(0,0,0,.4);
}

.br-page { display:flex;flex-direction:column;gap:1.5rem;padding-bottom:2.5rem; }

/* ── Filters ── */
.br-filters {
    background:var(--br-surface);
    border:1px solid var(--br-border);
    border-radius:14px;
    padding:1.25rem 1.5rem;
    box-shadow:var(--br-shadow);
}
.br-filters-label {
    font-size:11px;font-weight:700;letter-spacing:.08em;
    text-transform:uppercase;color:var(--br-text-3);
    margin-bottom:.875rem;display:flex;align-items:center;gap:6px;
}
.br-filters-body { margin-bottom:1rem; }
.br-filters-actions { display:flex;gap:8px; }

/* ── Panels ── */
.br-panel {
    background:var(--br-surface);
    border:1px solid var(--br-border);
    border-radius:14px;
    overflow:hidden;
    box-shadow:var(--br-shadow);
}
.br-panel-header {
    padding:.875rem 1.25rem;
    border-bottom:1px solid var(--br-border);
    display:flex;align-items:center;gap:8px;
    background:var(--br-surface2);
}
.br-panel-title  { font-size:13px;font-weight:700;color:var(--br-text);letter-spacing:.01em; }
.br-panel-count  { margin-left:auto;font-size:11px;color:var(--br-text-3); }

/* ── Tables ── */
.br-table { width:100%;border-collapse:collapse;font-size:13px; }
.br-table thead tr { background:var(--br-surface2); }
.br-table th {
    padding:10px 14px;text-align:left;
    font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
    color:var(--br-text-3);border-bottom:1px solid var(--br-border);
    white-space:nowrap;
}
.br-table th.right { text-align:right; }
.br-table td {
    padding:11px 14px;
    border-bottom:1px solid var(--br-border);
    color:var(--br-text);font-size:13px;white-space:nowrap;
}
.br-table td.muted { color:var(--br-text-2); }
.br-table td.right { text-align:right; }
.br-table tbody tr:last-child td { border-bottom:none; }
.br-table tbody tr { transition:background .12s; }
.br-table tbody tr:hover { background:var(--br-surface2); }

/* ── Name cell ── */
.br-name-cell { display:flex;align-items:center;gap:10px; }
.br-avatar {
    width:30px;height:30px;border-radius:50%;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    font-size:11px;font-weight:700;
}
.br-code {
    font-size:11px;font-weight:700;
    letter-spacing:.04em;font-family:monospace;
    color:var(--br-text-3);
    background:var(--br-surface2);
    border:1px solid var(--br-border);
    padding:2px 7px;border-radius:5px;
}

/* ── Badges ── */
.br-badge {
    display:inline-flex;align-items:center;gap:4px;
    padding:2px 9px;border-radius:99px;
    font-size:11px;font-weight:700;white-space:nowrap;
}
.br-badge-dot { width:5px;height:5px;border-radius:50%;flex-shrink:0; }

/* ── Summary type cards ── */
.br-type-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:1.125rem 1.25rem; }
.br-type-card {
    background:var(--br-surface2);
    border:1px solid var(--br-border);
    border-radius:10px;
    padding:.875rem 1rem;
    transition:box-shadow .15s,transform .15s;
}
.br-type-card:hover { box-shadow:var(--br-shadow-md);transform:translateY(-1px); }
.br-type-name  { font-size:12px;font-weight:600;color:var(--br-text);margin-bottom:4px; }
.br-type-count { font-size:11px;color:var(--br-text-3);margin-bottom:6px; }
.br-type-amt   { font-size:16px;font-weight:800;color:var(--br-accent);letter-spacing:-.01em; }
.br-type-cur   { font-size:10px;color:var(--br-text-3);font-weight:500;margin-left:2px; }

/* ── Empty state ── */
.br-empty {
    padding:3rem 1.25rem;text-align:center;
    color:var(--br-text-3);font-size:13px;
}
.br-empty-icon { font-size:32px;margin-bottom:.5rem; }
.br-empty-title { font-size:14px;font-weight:600;color:var(--br-text-2);margin-bottom:4px; }

@media(max-width:768px){
    .br-type-grid { grid-template-columns:1fr 1fr; }
}
@media(max-width:480px){
    .br-type-grid { grid-template-columns:1fr; }
}
</style>

<div class="br-page">

    {{-- ── Filters ── --}}
    <div class="br-filters">
        <div class="br-filters-label">
            <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L13 10.414V17a1 1 0 01-.553.894l-4-2A1 1 0 018 15v-4.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
            Report Filters
        </div>
        <div class="br-filters-body">
            {{ $this->form }}
        </div>
        <div class="br-filters-actions">
            <x-filament::button wire:click="applyFilters">Apply Filters</x-filament::button>
            <x-filament::button wire:click="resetFilters" color="gray">Reset</x-filament::button>
        </div>
    </div>

    @php
        $avatarPalette = [
            ['bg'=>'#1e3a5f','color'=>'#93c5fd'],
            ['bg'=>'#312e81','color'=>'#a5b4fc'],
            ['bg'=>'#14532d','color'=>'#86efac'],
            ['bg'=>'#713f12','color'=>'#fde68a'],
            ['bg'=>'#7f1d1d','color'=>'#fca5a5'],
            ['bg'=>'#134e4a','color'=>'#5eead4'],
        ];

        $statusMeta = [
            'Active'    => ['bg'=>'var(--br-accent-light)',  'color'=>'var(--br-accent)', 'dot'=>'var(--br-accent)'],
            'Inactive'  => ['bg'=>'var(--br-amber-light)',   'color'=>'var(--br-amber)',  'dot'=>'var(--br-amber)'],
            'Completed' => ['bg'=>'var(--br-blue-light)',    'color'=>'var(--br-blue)',   'dot'=>'var(--br-blue)'],
        ];
    @endphp

    {{-- ── Beneficiaries Table ── --}}
    <div class="br-panel">
        <div class="br-panel-header">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="var(--br-text-2)"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
            <span class="br-panel-title">Beneficiaries</span>
            <span class="br-panel-count">{{ count($reportData['beneficiaries']) }} found</span>
        </div>

        @if(empty($reportData['beneficiaries']))
            <div class="br-empty">
                <div class="br-empty-icon">👥</div>
                <div class="br-empty-title">No Beneficiaries Found</div>
                No beneficiaries match the selected criteria.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table class="br-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Full Name</th>
                            <th>Type</th>
                            <th>Need Category</th>
                            <th class="right">Total Aid Received</th>
                            <th>Last Distribution</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['beneficiaries'] as $i => $beneficiary)
                            @php
                                $pal  = $avatarPalette[$i % count($avatarPalette)];
                                $words = explode(' ', $beneficiary->full_name);
                                $initials = strtoupper(substr($words[0],0,1) . (isset($words[1]) ? substr($words[1],0,1) : ''));
                                $sm = $statusMeta[$beneficiary->status] ?? ['bg'=>'var(--br-surface2)','color'=>'var(--br-text-2)','dot'=>'var(--br-text-3)'];
                            @endphp
                            <tr>
                                <td>
                                    <span class="br-code">{{ $beneficiary->beneficiary_code }}</span>
                                </td>
                                <td>
                                    <div class="br-name-cell">
                                        <div class="br-avatar" style="background:{{ $pal['bg'] }};color:{{ $pal['color'] }};">
                                            {{ $initials }}
                                        </div>
                                        <span style="font-weight:600;">{{ $beneficiary->full_name }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="br-badge" style="background:var(--br-blue-light);color:var(--br-blue);">
                                        {{ $beneficiary->type }}
                                    </span>
                                </td>
                                <td>
                                    <span class="br-badge" style="background:var(--br-amber-light);color:var(--br-amber);">
                                        {{ $beneficiary->need_category }}
                                    </span>
                                </td>
                                <td class="right" style="font-weight:700;color:var(--br-accent);">
                                    {{ number_format($beneficiary->total_aid_received, 2) }}
                                    <span style="font-size:10px;font-weight:500;color:var(--br-text-3);margin-left:2px;">Birr</span>
                                </td>
                                <td class="muted">
                                    {{ $beneficiary->last_distribution_date ?? '—' }}
                                </td>
                                <td>
                                    <span class="br-badge" style="background:{{ $sm['bg'] }};color:{{ $sm['color'] }};">
                                        <span class="br-badge-dot" style="background:{{ $sm['dot'] }};"></span>
                                        {{ $beneficiary->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Aid Distributions Table ── --}}
    <div class="br-panel">
        <div class="br-panel-header">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="var(--br-text-2)"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
            <span class="br-panel-title">Aid Distributions</span>
            <span class="br-panel-count">{{ count($reportData['aidDistributions']) }} found</span>
        </div>

        @if(empty($reportData['aidDistributions']))
            <div class="br-empty">
                <div class="br-empty-icon">🤲</div>
                <div class="br-empty-title">No Distributions Found</div>
                No aid distributions match the selected criteria.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table class="br-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Beneficiary</th>
                            <th>Aid Type</th>
                            <th class="right">Amount</th>
                            <th>Receipt No.</th>
                            <th>Distributed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['aidDistributions'] as $i => $dist)
                            @php $pal = $avatarPalette[$i % count($avatarPalette)]; @endphp
                            <tr>
                                <td class="muted">
                                    {{ $dist->distribution_date->format('M d, Y') }}
                                </td>
                                <td>
                                    @if($dist->beneficiary)
                                        @php
                                            $bw = explode(' ', $dist->beneficiary->full_name);
                                            $bi = strtoupper(substr($bw[0],0,1) . (isset($bw[1]) ? substr($bw[1],0,1) : ''));
                                        @endphp
                                        <div class="br-name-cell">
                                            <div class="br-avatar" style="background:{{ $pal['bg'] }};color:{{ $pal['color'] }};font-size:10px;">
                                                {{ $bi }}
                                            </div>
                                            <span style="font-weight:600;">{{ $dist->beneficiary->full_name }}</span>
                                        </div>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="br-badge" style="background:var(--br-amber-light);color:var(--br-amber);">
                                        {{ $dist->aid_type }}
                                    </span>
                                </td>
                                <td class="right" style="font-weight:700;color:var(--br-accent);">
                                    {{ number_format($dist->amount, 2) }}
                                    <span style="font-size:10px;font-weight:500;color:var(--br-text-3);margin-left:2px;">Birr</span>
                                </td>
                                <td>
                                    @if($dist->receipt_number)
                                        <span class="br-code">{{ $dist->receipt_number }}</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td class="muted">
                                    {{ $dist->distributedBy?->name ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Aid Summary by Type ── --}}
    @if(!empty($reportData['aidByType']))
    <div class="br-panel">
        <div class="br-panel-header">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="var(--br-text-2)"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
            <span class="br-panel-title">Aid Summary by Type</span>
            <span class="br-panel-count">{{ count($reportData['aidByType']) }} types</span>
        </div>
        <div class="br-type-grid">
            @php $maxType = collect($reportData['aidByType'])->max('total') ?: 1; @endphp
            @foreach($reportData['aidByType'] as $typeData)
                @php $pct = round(($typeData['total'] / $maxType) * 100); @endphp
                <div class="br-type-card">
                    <div class="br-type-name">{{ $typeData['type'] }}</div>
                    <div class="br-type-count">{{ number_format($typeData['count']) }} distributions</div>
                    <div style="height:3px;background:var(--br-border);border-radius:99px;overflow:hidden;margin-bottom:8px;">
                        <div style="height:100%;border-radius:99px;background:var(--br-accent);width:{{ $pct }}%;transition:width .4s;"></div>
                    </div>
                    <div>
                        <span class="br-type-amt">{{ number_format($typeData['total'], 2) }}</span>
                        <span class="br-type-cur">Birr</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
</x-filament-panels::page>
