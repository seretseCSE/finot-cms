<x-filament-panels::page>

{{-- ── Dark-mode–aware CSS variables ── --}}
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
    /* status badge colours stay vivid in both modes */
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

/* skeleton shimmer adapts automatically via the variable */
.rpt-skeleton { background: var(--rpt-card-border) !important; }
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
        {{-- ── Skeleton ── --}}
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;">
            @for($i=1;$i<=5;$i++)
            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;animation:pulse 1.5s infinite;">
                <div class="rpt-skeleton" style="width:36px;height:36px;border-radius:8px;margin-bottom:10px;"></div>
                <div class="rpt-skeleton" style="height:10px;border-radius:4px;width:70%;margin-bottom:8px;"></div>
                <div class="rpt-skeleton" style="height:20px;border-radius:4px;width:45%;"></div>
            </div>
            @endfor
        </div>

        <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1.5rem;animation:pulse 1.5s infinite;">
            <div class="rpt-skeleton" style="height:16px;border-radius:4px;width:180px;margin-bottom:1.25rem;"></div>
            @for($i=1;$i<=5;$i++)
            <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--rpt-row-border);">
                <div class="rpt-skeleton" style="width:36px;height:36px;border-radius:50%;flex-shrink:0;"></div>
                <div style="flex:1;">
                    <div class="rpt-skeleton" style="height:10px;border-radius:4px;width:55%;margin-bottom:6px;"></div>
                    <div class="rpt-skeleton" style="height:10px;border-radius:4px;width:35%;"></div>
                </div>
                <div class="rpt-skeleton" style="height:10px;border-radius:4px;width:30px;"></div>
                <div class="rpt-skeleton" style="height:10px;border-radius:4px;width:30px;"></div>
                <div class="rpt-skeleton" style="height:8px;border-radius:99px;width:80px;"></div>
                <div class="rpt-skeleton" style="height:20px;border-radius:99px;width:60px;"></div>
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

        {{-- ── Summary Metric Cards ── --}}
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;">

            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#1e3a5f;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-calendar-days" style="width:18px;height:18px;color:#60a5fa;" />
                </div>
                <p style="font-size:12px;color:var(--rpt-text-secondary);margin-bottom:3px;">Total Sessions</p>
                <p style="font-size:22px;font-weight:700;color:var(--rpt-text-primary);line-height:1;">{{ $reportData['summary']['total_sessions'] }}</p>
            </div>

            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#312e81;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-users" style="width:18px;height:18px;color:#818cf8;" />
                </div>
                <p style="font-size:12px;color:var(--rpt-text-secondary);margin-bottom:3px;">Total Students</p>
                <p style="font-size:22px;font-weight:700;color:var(--rpt-text-primary);line-height:1;">{{ $reportData['summary']['total_students'] }}</p>
            </div>

            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#14532d;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-chart-pie" style="width:18px;height:18px;color:#4ade80;" />
                </div>
                <p style="font-size:12px;color:var(--rpt-text-secondary);margin-bottom:3px;">Present Rate</p>
                <p style="font-size:22px;font-weight:700;color:var(--rpt-text-primary);line-height:1;">{{ $reportData['summary']['present_rate'] }}%</p>
            </div>

            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#14532d;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-check-circle" style="width:18px;height:18px;color:#4ade80;" />
                </div>
                <p style="font-size:12px;color:var(--rpt-text-secondary);margin-bottom:3px;">Present</p>
                <p style="font-size:22px;font-weight:700;color:var(--rpt-text-primary);line-height:1;">{{ $reportData['summary']['present'] }}</p>
            </div>

            <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#7f1d1d;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-x-circle" style="width:18px;height:18px;color:#f87171;" />
                </div>
                <p style="font-size:12px;color:var(--rpt-text-secondary);margin-bottom:3px;">Absent</p>
                <p style="font-size:22px;font-weight:700;color:var(--rpt-text-primary);line-height:1;">{{ $reportData['summary']['absent'] }}</p>
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
                    <x-filament::icon icon="heroicon-o-document-arrow-down" style="width:15px;height:15px;margin-right:5px;" />
                    Export Excel
                </x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="exportToPdf">
                    <x-filament::icon icon="heroicon-o-document-arrow-down" style="width:15px;height:15px;margin-right:5px;" />
                    Export PDF
                </x-filament::button>
            </div>
        </div>

        {{-- ── Attendance by Student ── --}}
        <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1.5rem;">
            <h3 style="font-size:15px;font-weight:600;color:var(--rpt-text-primary);margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                <x-filament::icon icon="heroicon-o-users" style="width:18px;height:18px;color:var(--rpt-icon-muted);" />
                Attendance by Student
            </h3>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--rpt-body-bg);">
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Student</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Sessions</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Present</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Rate</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--rpt-text-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--rpt-card-border);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $avatarPalette = [
                                ['bg'=>'#1e3a5f','color'=>'#93c5fd'],
                                ['bg'=>'#312e81','color'=>'#a5b4fc'],
                                ['bg'=>'#14532d','color'=>'#86efac'],
                                ['bg'=>'#713f12','color'=>'#fde68a'],
                                ['bg'=>'#7f1d1d','color'=>'#fca5a5'],
                                ['bg'=>'#134e4a','color'=>'#5eead4'],
                            ];
                        @endphp
                        @foreach($reportData['by_student'] as $index => $student)
                            @php
                                $studentName = $student['student_name'] ?? ($student['student']?->full_name ?? 'N/A');
                                $palette = $avatarPalette[$index % count($avatarPalette)];
                                $words = explode(' ', $studentName);
                                $initials = strtoupper(substr($words[0],0,1) . (isset($words[1]) ? substr($words[1],0,1) : ''));
                                $rate = $student['rate'];
                                if($rate >= 90)      { $barColor='#16a34a'; $badgeBg='#14532d'; $badgeColor='#86efac'; $badgeText='Excellent'; }
                                elseif($rate >= 75)  { $barColor='#2563eb'; $badgeBg='#1e3a5f'; $badgeColor='#93c5fd'; $badgeText='Good'; }
                                elseif($rate >= 60)  { $barColor='#d97706'; $badgeBg='#713f12'; $badgeColor='#fde68a'; $badgeText='Fair'; }
                                else                 { $barColor='#dc2626'; $badgeBg='#7f1d1d'; $badgeColor='#fca5a5'; $badgeText='Poor'; }
                            @endphp
                            <tr style="border-bottom:1px solid var(--rpt-row-border);">
                                <td style="padding:11px 14px;">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:34px;height:34px;border-radius:50%;background:{{ $palette['bg'] }};color:{{ $palette['color'] }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0;">
                                            {{ $initials }}
                                        </div>
                                        <div>
                                            <div style="font-weight:600;color:var(--rpt-text-primary);font-size:13px;">{{ $studentName }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:11px 14px;color:var(--rpt-text-secondary);">{{ $student['total_sessions'] }}</td>
                                <td style="padding:11px 14px;color:var(--rpt-text-secondary);">{{ $student['present'] }}</td>
                                <td style="padding:11px 14px;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:13px;font-weight:500;color:var(--rpt-text-primary);min-width:36px;">{{ $rate }}%</span>
                                        <div style="width:64px;height:5px;background:var(--rpt-bar-track);border-radius:99px;overflow:hidden;">
                                            <div style="height:100%;border-radius:99px;background:{{ $barColor }};width:{{ $rate }}%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:11px 14px;">
                                    <span style="display:inline-flex;align-items:center;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $badgeBg }};color:{{ $badgeColor }};">
                                        {{ $badgeText }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Attendance Trend by Date ── --}}
        <div style="background:var(--rpt-card-bg);border-radius:12px;border:1px solid var(--rpt-card-border);padding:1.5rem;">
            <h3 style="font-size:15px;font-weight:600;color:var(--rpt-text-primary);margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                <x-filament::icon icon="heroicon-o-arrow-trending-up" style="width:18px;height:18px;color:var(--rpt-icon-muted);" />
                Attendance Trend by Date
            </h3>
            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach($reportData['by_date'] as $date)
                    @php
                        $r = $date['rate'];
                        $barCol = $r >= 90 ? '#16a34a' : ($r >= 75 ? '#2563eb' : ($r >= 60 ? '#d97706' : '#dc2626'));
                    @endphp
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--rpt-body-bg);border-radius:8px;gap:12px;">
                        <div style="display:flex;align-items:center;gap:16px;">
                            <span style="font-size:13px;font-weight:600;color:var(--rpt-text-primary);white-space:nowrap;">
                                {{ \Carbon\Carbon::parse($date['date'])->format('M d, Y') }}
                            </span>
                            <span style="font-size:12px;color:var(--rpt-text-muted);">{{ $date['present'] }}/{{ $date['total'] }} present</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                            <span style="font-size:13px;font-weight:600;color:var(--rpt-text-primary);min-width:36px;text-align:right;">{{ $r }}%</span>
                            <div style="width:80px;height:5px;background:var(--rpt-bar-track);border-radius:99px;overflow:hidden;">
                                <div style="height:100%;border-radius:99px;background:{{ $barCol }};width:{{ $r }}%;"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    @endif
</div>

</x-filament-panels::page>
