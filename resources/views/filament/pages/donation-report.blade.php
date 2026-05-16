<x-filament-panels::page>

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
.dr-page { font-family: 'DM Sans', sans-serif; }

.dr-filter-bar {
    background: var(--color-background-secondary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-lg);
    padding: 1.25rem 1.5rem;
}
.dr-filter-label {
    font-size: 11px; font-weight: 500; letter-spacing: 0.06em;
    text-transform: uppercase; color: var(--color-text-secondary);
    display: flex; align-items: center; gap: 6px; margin-bottom: 1rem;
}
.dr-filter-actions { display: flex; gap: 8px; margin-top: 1rem; }
.dr-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: var(--border-radius-md);
    font-size: 13px; font-weight: 500; cursor: pointer;
    border: 0.5px solid var(--color-border-secondary);
    background: var(--color-background-primary);
    color: var(--color-text-primary); transition: all 0.15s;
}
.dr-btn:hover { background: var(--color-background-secondary); }
.dr-btn.primary { background: #185FA5; color: #fff; border-color: #185FA5; }
.dr-btn.primary:hover { background: #0C447C; border-color: #0C447C; }

.dr-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.dr-kpi {
    background: var(--color-background-primary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-lg);
    padding: 1.1rem 1.25rem;
}
.dr-kpi-label { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-secondary); margin-bottom: 6px; }
.dr-kpi-value { font-size: 22px; font-weight: 600; color: var(--color-text-primary); line-height: 1.1; }
.dr-kpi-sub { font-size: 11px; color: var(--color-text-secondary); margin-top: 4px; }

.dr-section {
    background: var(--color-background-primary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
}
.dr-section-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 0.5px solid var(--color-border-tertiary);
    background: var(--color-background-secondary);
}
.dr-section-title { font-size: 13px; font-weight: 500; color: var(--color-text-primary); display: flex; align-items: center; gap: 8px; }
.dr-section-title i { font-size: 15px; color: var(--color-text-secondary); }
.dr-count-pill {
    font-size: 11px; font-weight: 500; padding: 3px 10px;
    border-radius: 99px; background: var(--color-background-primary);
    border: 0.5px solid var(--color-border-secondary);
    color: var(--color-text-secondary);
}

.dr-table { width: 100%; border-collapse: collapse; }
.dr-table thead tr { border-bottom: 0.5px solid var(--color-border-tertiary); }
.dr-table th {
    padding: 10px 16px; text-align: left;
    font-size: 10px; font-weight: 500; letter-spacing: 0.07em;
    text-transform: uppercase; color: var(--color-text-secondary);
    white-space: nowrap;
}
.dr-table th.right { text-align: right; }
.dr-table tbody tr { border-bottom: 0.5px solid var(--color-border-tertiary); transition: background 0.12s; }
.dr-table tbody tr:last-child { border-bottom: none; }
.dr-table tbody tr:hover { background: var(--color-background-secondary); }
.dr-table td { padding: 11px 16px; font-size: 13px; color: var(--color-text-primary); }
.dr-table td.muted { color: var(--color-text-secondary); }
.dr-table td.right { text-align: right; font-weight: 600; }
.dr-table td.mono { font-variant-numeric: tabular-nums; }

.type-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 99px;
    font-size: 11px; font-weight: 500; white-space: nowrap;
}
.type-general   { background: #e6f1fb; color: #185FA5; }
.type-building  { background: #eaf3de; color: #3B6D11; }
.type-mission   { background: #eeedfe; color: #534AB7; }
.type-charity   { background: #faeeda; color: #633806; }
.type-other     { background: var(--color-background-secondary); color: var(--color-text-secondary); border: 0.5px solid var(--color-border-secondary); }

.dr-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; padding: 1.25rem 1.5rem; border-top: 0.5px solid var(--color-border-tertiary); }
.dr-summary-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 14px; border-radius: var(--border-radius-md);
    border: 0.5px solid var(--color-border-tertiary);
    background: var(--color-background-secondary);
}
.dr-summary-type { font-size: 13px; font-weight: 500; color: var(--color-text-primary); }
.dr-summary-amount { font-size: 13px; font-weight: 600; color: #1D9E75; }

.dr-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 1rem; gap: 10px; }
.dr-empty i { font-size: 32px; color: var(--color-text-secondary); }
.dr-empty p { font-size: 14px; color: var(--color-text-secondary); }
.dr-empty span { font-size: 12px; color: var(--color-text-secondary); opacity: 0.7; }

.progress-bar-wrap { height: 3px; background: var(--color-background-secondary); border-radius: 99px; margin-top: 8px; overflow: hidden; }
.progress-bar-fill { height: 100%; border-radius: 99px; }
</style>
@endpush

<div class="dr-page" style="display:flex; flex-direction:column; gap:1.25rem; padding-bottom:2rem;">

    {{-- Filters --}}
    <div class="dr-filter-bar">
        <div class="dr-filter-label">
            <i class="ti ti-adjustments-horizontal" aria-hidden="true"></i>
            Report Filters
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{ $this->form }}
        </div>
        <div class="dr-filter-actions">
            <button wire:click="applyFilters" class="dr-btn primary">
                <i class="ti ti-check" aria-hidden="true"></i> Apply Filters
            </button>
            <button wire:click="resetFilters" class="dr-btn">
                <i class="ti ti-refresh" aria-hidden="true"></i> Reset
            </button>
        </div>
    </div>

    @if(!empty($reportData['donations']) && count($reportData['donations']) > 0)

    {{-- KPI Row --}}
    <div class="dr-kpi-grid">
        <div class="dr-kpi">
            <div class="dr-kpi-label">Total Donated</div>
            <div class="dr-kpi-value">Birr {{ number_format($reportData['totalDonated'] ?? 0, 2) }}</div>
            <div class="dr-kpi-sub">All time in selected range</div>
        </div>
        <div class="dr-kpi">
            <div class="dr-kpi-label">Total This Year</div>
            <div class="dr-kpi-value">Birr {{ number_format($reportData['totalThisYear'] ?? 0, 2) }}</div>
            <div class="dr-kpi-sub">Current year contributions</div>
        </div>
        <div class="dr-kpi">
            <div class="dr-kpi-label">Donations</div>
            <div class="dr-kpi-value">{{ count($reportData['donations']) }}</div>
            <div class="dr-kpi-sub">Records found</div>
        </div>
        @if(count($reportData['donations']) > 0)
        <div class="dr-kpi">
            <div class="dr-kpi-label">Average Donation</div>
            <div class="dr-kpi-value">Birr {{ number_format(($reportData['totalDonated'] ?? 0) / count($reportData['donations']), 2) }}</div>
            <div class="dr-kpi-sub">Per donation</div>
        </div>
        @endif
    </div>

    @endif

    {{-- Table --}}
    <div class="dr-section">
        <div class="dr-section-header">
            <div class="dr-section-title">
                <i class="ti ti-heart-handshake" aria-hidden="true"></i>
                Donation Report
            </div>
            <span class="dr-count-pill">{{ count($reportData['donations']) }} records</span>
        </div>

        @if(empty($reportData['donations']) || count($reportData['donations']) === 0)
            <div class="dr-empty">
                <i class="ti ti-gift-off" aria-hidden="true"></i>
                <p>No donations found</p>
                <span>Try adjusting your filters or date range</span>
            </div>
        @else
            <div style="overflow-x:auto;">
                <table class="dr-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Donor Name</th>
                            <th class="right">Amount</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Recorded By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['donations'] as $i => $donation)
                            <tr>
                                <td class="muted" style="width:40px; font-size:11px;">{{ $i + 1 }}</td>
                                <td style="font-weight:500;">{{ $donation->formatted_donor_name }}</td>
                                <td class="right mono">Birr {{ number_format($donation->amount, 2) }}</td>
                                <td>
                                    @php
                                        $typeClass = match($donation->donation_type) {
                                            'General Fund'       => 'type-general',
                                            'Building Fund'      => 'type-building',
                                            'Missionary Support' => 'type-mission',
                                            'Charity/Aid'        => 'type-charity',
                                            default              => 'type-other',
                                        };
                                        $typeIcon = match($donation->donation_type) {
                                            'General Fund'       => 'ti-building-bank',
                                            'Building Fund'      => 'ti-building',
                                            'Missionary Support' => 'ti-globe',
                                            'Charity/Aid'        => 'ti-hand-heart',
                                            default              => 'ti-tag',
                                        };
                                    @endphp
                                    <span class="type-badge {{ $typeClass }}">
                                        <i class="ti {{ $typeIcon }}" style="font-size:11px;" aria-hidden="true"></i>
                                        {{ $donation->formatted_donation_type }}
                                    </span>
                                </td>
                                <td class="muted">{{ $donation->ethiopian_date }}</td>
                                <td class="muted">{{ $donation->recordedBy->name }}</td>
                                <td class="muted" style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $donation->notes }}">
                                    {{ $donation->notes ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Summary by Type --}}
            @if(!empty($reportData['totalByType']))
                @php $grandTotal = $reportData['totalDonated'] ?: 1; @endphp
                <div style="padding:1rem 1.5rem; border-top:0.5px solid var(--color-border-tertiary);">
                    <div style="font-size:11px; font-weight:500; text-transform:uppercase; letter-spacing:0.06em; color:var(--color-text-secondary); margin-bottom:12px;">
                        Summary by Type
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:8px;">
                        @foreach($reportData['totalByType'] as $typeData)
                            @php $pct = ($typeData['total'] / $grandTotal) * 100; @endphp
                            <div style="padding:10px 14px; border-radius:var(--border-radius-md); border:0.5px solid var(--color-border-tertiary); background:var(--color-background-secondary);">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:13px; font-weight:500; color:var(--color-text-primary);">{{ $typeData['type'] }}</span>
                                    <span style="font-size:13px; font-weight:600; color:#1D9E75;">Birr {{ number_format($typeData['total'], 2) }}</span>
                                </div>
                                <div class="progress-bar-wrap">
                                    <div class="progress-bar-fill" style="width:{{ $pct }}%; background:#1D9E75;"></div>
                                </div>
                                <div style="font-size:11px; color:var(--color-text-secondary); margin-top:4px;">{{ number_format($pct, 1) }}% of total</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>

</div>

</x-filament-panels::page>
