<x-filament-panels::page>

<style>
:root {
    --rpt-card-bg:        #ffffff;
    --rpt-card-border:    #e5e7eb;
    --rpt-body-bg:        #f9fafb;
    --rpt-row-border:     #f3f4f6;
    --rpt-text-primary:   #111827;
    --rpt-text-secondary: #4b5563;
    --rpt-text-muted:     #6b7280;
    --rpt-bar-track:      #e5e7eb;
    --rpt-icon-muted:     #6b7280;
}

.dark {
    --rpt-card-bg:        #1f2937;
    --rpt-card-border:    #374151;
    --rpt-body-bg:        #111827;
    --rpt-row-border:     #1f2937;
    --rpt-text-primary:   #f9fafb;
    --rpt-text-secondary: #d1d5db;
    --rpt-text-muted:     #9ca3af;
    --rpt-bar-track:      #374151;
    --rpt-icon-muted:     #9ca3af;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: .5; }
}
</style>

<div style="display:flex;flex-direction:column;gap:1.25rem;padding-bottom:2rem;">

    {{-- ── Filters ── --}}
    <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1.5rem;">
        <h3 style="font-size:15px;font-weight:600;color:var(--rpt-text-primary);margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
            <x-filament::icon icon="heroicon-o-funnel" style="width:18px;height:18px;color:var(--rpt-icon-muted);" />
            Report Filters
        </h3>
        {{ $this->form }}
        <div style="margin-top:1rem;display:flex;justify-content:flex-end;">
            <x-filament::button color="gray" wire:click="resetFilters">Reset Filters</x-filament::button>
        </div>
    </div>

    @if($isLoading)
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
            @for($i=1;$i<=4;$i++)
            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;animation:pulse 1.5s infinite;">
                <div style="width:36px;height:36px;border-radius:8px;background:var(--rpt-card-border);margin-bottom:10px;"></div>
                <div style="height:10px;background:var(--rpt-card-border);border-radius:4px;width:70%;margin-bottom:8px;"></div>
                <div style="height:20px;background:var(--rpt-card-border);border-radius:4px;width:45%;"></div>
            </div>
            @endfor
        </div>

        <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1.5rem;animation:pulse 1.5s infinite;">
            @for($i=1;$i<=5;$i++)
            <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--rpt-row-border);">
                <div style="width:36px;height:36px;border-radius:50%;background:var(--rpt-card-border);flex-shrink:0;"></div>
                <div style="flex:1;">
                    <div style="height:10px;background:var(--rpt-card-border);border-radius:4px;width:55%;margin-bottom:6px;"></div>
                    <div style="height:10px;background:var(--rpt-card-border);border-radius:4px;width:35%;"></div>
                </div>
                <div style="height:10px;background:var(--rpt-card-border);border-radius:4px;width:30px;"></div>
                <div style="height:10px;background:var(--rpt-card-border);border-radius:4px;width:30px;"></div>
                <div style="height:8px;background:var(--rpt-card-border);border-radius:99px;width:80px;"></div>
                <div style="height:20px;background:var(--rpt-card-border);border-radius:99px;width:60px;"></div>
            </div>
            @endfor
        </div>

    @elseif($reportData === null && !$isLoading)
        <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:2.5rem;text-align:center;">
            <x-filament::icon icon="heroicon-o-chart-bar" style="width:48px;height:48px;color:var(--rpt-text-muted);margin-bottom:1rem;" />
            <h3 style="font-size:16px;font-weight:600;color:var(--rpt-text-primary);margin-bottom:6px;">No Report Generated</h3>
            <p style="font-size:13px;color:var(--rpt-text-muted);">Select an Academic Year to view attendance data.</p>
        </div>

    @elseif($reportData)

        {{-- ── Summary Cards ── --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">

            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#1e3a5f;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-calendar-days" style="width:18px;height:18px;color:#60a5fa;" />
                </div>
                <p style="font-size:12px;color:var(--rpt-text-secondary);margin-bottom:3px;">Total Sessions</p>
                <p style="font-size:22px;font-weight:700;color:var(--rpt-text-primary);line-height:1;">{{ $reportData['total_sessions'] }}</p>
            </div>

            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#312e81;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-document-text" style="width:18px;height:18px;color:#818cf8;" />
                </div>
                <p style="font-size:12px;color:var(--rpt-text-secondary);margin-bottom:3px;">Total Records</p>
                <p style="font-size:22px;font-weight:700;color:var(--rpt-text-primary);line-height:1;">{{ $reportData['total_entries'] }}</p>
            </div>

            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#14532d;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-check-circle" style="width:18px;height:18px;color:#4ade80;" />
                </div>
                <p style="font-size:12px;color:var(--rpt-text-secondary);margin-bottom:3px;">Present</p>
                <p style="font-size:22px;font-weight:700;color:var(--rpt-text-primary);line-height:1;">{{ $reportData['present'] }}</p>
            </div>

            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#7f1d1d;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-x-circle" style="width:18px;height:18px;color:#f87171;" />
                </div>
                <p style="font-size:12px;color:var(--rpt-text-secondary);margin-bottom:3px;">Absent</p>
                <p style="font-size:22px;font-weight:700;color:var(--rpt-text-primary);line-height:1;">{{ $reportData['absent'] }}</p>
            </div>

        </div>

        {{-- ── Export Options ── --}}
        <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:14px;font-weight:600;color:var(--rpt-text-primary);display:flex;align-items:center;gap:8px;">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" style="width:16px;height:16px;color:var(--rpt-icon-muted);" />
                Export Options
            </h3>
            <div style="display:flex;gap:8px;">
                <x-filament::button color="gray" size="sm" wire:click="exportToExcel">
                    Export Excel
                </x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="exportToPdf">
                    Export PDF
                </x-filament::button>
            </div>
        </div>

        {{-- ── Teacher Attendance Table ── --}}
        <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1.5rem;">
            <h3 style="font-size:15px;font-weight:600;color:var(--rpt-text-primary);margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                <x-filament::icon icon="heroicon-o-academic-cap" style="width:18px;height:18px;color:var(--rpt-icon-muted);" />
                Attendance by Teacher
            </h3>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--rpt-body-bg);">
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Teacher</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Subject</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Sessions</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Present</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Absent</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData['rows'] as $entry)
                            @php
                                $r = $entry['rate'];
                                if($r >= 90)      { $barColor='#16a34a'; $badgeBg='#14532d'; $badgeColor='#86efac'; $badgeText='Excellent'; }
                                elseif($r >= 75)  { $barColor='#2563eb'; $badgeBg='#1e3a5f'; $badgeColor='#93c5fd'; $badgeText='Good'; }
                                elseif($r >= 60)  { $barColor='#d97706'; $badgeBg='#713f12'; $badgeColor='#fde68a'; $badgeText='Fair'; }
                                else              { $barColor='#dc2626'; $badgeBg='#7f1d1d'; $badgeColor='#fca5a5'; $badgeText='Poor'; }
                            @endphp
                            <tr style="border-bottom:1px solid var(--rpt-row-border);">
                                <td style="padding:11px 14px;font-weight:600;color:var(--rpt-text-primary);">{{ $entry['teacher_name'] }}</td>
                                <td style="padding:11px 14px;color:var(--rpt-text-secondary);">{{ $entry['subject'] }}</td>
                                <td style="padding:11px 14px;color:var(--rpt-text-secondary);">{{ $entry['total_sessions'] }}</td>
                                <td style="padding:11px 14px;color:var(--rpt-text-secondary);">{{ $entry['present'] }}</td>
                                <td style="padding:11px 14px;color:var(--rpt-text-secondary);">{{ $entry['absent'] }}</td>
                                <td style="padding:11px 14px;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:13px;font-weight:500;color:var(--rpt-text-primary);min-width:36px;">{{ $r }}%</span>
                                        <div style="width:64px;height:5px;background:var(--rpt-bar-track);border-radius:99px;overflow:hidden;">
                                            <div style="height:100%;border-radius:99px;background:{{ $barColor }};width:{{ $r }}%;"></div>
                                        </div>
                                        <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $badgeBg }};color:{{ $badgeColor }};">
                                            {{ $badgeText }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding:20px 14px;text-align:center;color:var(--rpt-text-muted);font-size:13px;">
                                    No teacher attendance records found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @endif
</div>

</x-filament-panels::page>
