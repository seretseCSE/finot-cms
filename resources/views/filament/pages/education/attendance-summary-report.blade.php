<x-filament-panels::page>
<div style="display:flex;flex-direction:column;gap:1.25rem;padding-bottom:2rem;">

    {{-- ── Filters ── --}}
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1.5rem;">
        <h3 style="font-size:15px;font-weight:600;color:#111827;margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
            <x-filament::icon icon="heroicon-o-funnel" style="width:18px;height:18px;color:#6b7280;" />
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
            <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1rem;animation:pulse 1.5s infinite;">
                <div style="width:36px;height:36px;border-radius:8px;background:#e5e7eb;margin-bottom:10px;"></div>
                <div style="height:10px;background:#e5e7eb;border-radius:4px;width:70%;margin-bottom:8px;"></div>
                <div style="height:20px;background:#e5e7eb;border-radius:4px;width:45%;"></div>
            </div>
            @endfor
        </div>

        <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1.5rem;animation:pulse 1.5s infinite;">
            <div style="height:16px;background:#e5e7eb;border-radius:4px;width:180px;margin-bottom:1.25rem;"></div>
            @for($i=1;$i<=5;$i++)
            <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f3f4f6;">
                <div style="width:36px;height:36px;border-radius:50%;background:#e5e7eb;flex-shrink:0;"></div>
                <div style="flex:1;">
                    <div style="height:10px;background:#e5e7eb;border-radius:4px;width:55%;margin-bottom:6px;"></div>
                    <div style="height:10px;background:#e5e7eb;border-radius:4px;width:35%;"></div>
                </div>
                <div style="height:10px;background:#e5e7eb;border-radius:4px;width:30px;"></div>
                <div style="height:10px;background:#e5e7eb;border-radius:4px;width:30px;"></div>
                <div style="height:8px;background:#e5e7eb;border-radius:99px;width:80px;"></div>
                <div style="height:20px;background:#e5e7eb;border-radius:99px;width:60px;"></div>
            </div>
            @endfor
        </div>

    @elseif($reportData)

        {{-- ── Summary Metric Cards ── --}}
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;">

            <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-calendar-days" style="width:18px;height:18px;color:#2563eb;" />
                </div>
                <p style="font-size:12px;color:#6b7280;margin-bottom:3px;">Total Sessions</p>
                <p style="font-size:22px;font-weight:700;color:#111827;line-height:1;">{{ $reportData['summary']['total_sessions'] }}</p>
            </div>

            <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-users" style="width:18px;height:18px;color:#4f46e5;" />
                </div>
                <p style="font-size:12px;color:#6b7280;margin-bottom:3px;">Total Students</p>
                <p style="font-size:22px;font-weight:700;color:#111827;line-height:1;">{{ $reportData['summary']['total_students'] }}</p>
            </div>

            <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-chart-pie" style="width:18px;height:18px;color:#16a34a;" />
                </div>
                <p style="font-size:12px;color:#6b7280;margin-bottom:3px;">Present Rate</p>
                <p style="font-size:22px;font-weight:700;color:#111827;line-height:1;">{{ $reportData['summary']['present_rate'] }}%</p>
            </div>

            <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-check-circle" style="width:18px;height:18px;color:#16a34a;" />
                </div>
                <p style="font-size:12px;color:#6b7280;margin-bottom:3px;">Present</p>
                <p style="font-size:22px;font-weight:700;color:#111827;line-height:1;">{{ $reportData['summary']['present'] }}</p>
            </div>

            <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                    <x-filament::icon icon="heroicon-o-x-circle" style="width:18px;height:18px;color:#dc2626;" />
                </div>
                <p style="font-size:12px;color:#6b7280;margin-bottom:3px;">Absent</p>
                <p style="font-size:22px;font-weight:700;color:#111827;line-height:1;">{{ $reportData['summary']['absent'] }}</p>
            </div>

        </div>

        {{-- ── Export Options ── --}}
        <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:14px;font-weight:600;color:#111827;display:flex;align-items:center;gap:8px;">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" style="width:16px;height:16px;color:#6b7280;" />
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
        <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1.5rem;">
            <h3 style="font-size:15px;font-weight:600;color:#111827;margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                <x-filament::icon icon="heroicon-o-users" style="width:18px;height:18px;color:#6b7280;" />
                Attendance by Student
            </h3>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#f9fafb;">
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #f3f4f6;">Student</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #f3f4f6;">Sessions</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #f3f4f6;">Present</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #f3f4f6;">Rate</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #f3f4f6;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $avatarPalette = [
                                ['bg'=>'#dbeafe','color'=>'#1d4ed8'],
                                ['bg'=>'#e0e7ff','color'=>'#4338ca'],
                                ['bg'=>'#dcfce7','color'=>'#15803d'],
                                ['bg'=>'#fef9c3','color'=>'#a16207'],
                                ['bg'=>'#fee2e2','color'=>'#b91c1c'],
                                ['bg'=>'#ccfbf1','color'=>'#0f766e'],
                            ];
                        @endphp
                        @foreach($reportData['by_student'] as $index => $student)
                            @php
                                $palette = $avatarPalette[$index % count($avatarPalette)];
                                $words = explode(' ', $student['student']->full_name);
                                $initials = strtoupper(substr($words[0],0,1) . (isset($words[1]) ? substr($words[1],0,1) : ''));
                                $rate = $student['rate'];
                                if($rate >= 90) { $barColor='#16a34a'; $badgeBg='#dcfce7'; $badgeColor='#166534'; $badgeText='Excellent'; }
                                elseif($rate >= 75) { $barColor='#2563eb'; $badgeBg='#dbeafe'; $badgeColor='#1e40af'; $badgeText='Good'; }
                                elseif($rate >= 60) { $barColor='#d97706'; $badgeBg='#fef9c3'; $badgeColor='#92400e'; $badgeText='Fair'; }
                                else { $barColor='#dc2626'; $badgeBg='#fee2e2'; $badgeColor='#991b1b'; $badgeText='Poor'; }
                            @endphp
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:11px 14px;">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:34px;height:34px;border-radius:50%;background:{{ $palette['bg'] }};color:{{ $palette['color'] }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0;">
                                            {{ $initials }}
                                        </div>
                                        <div>
                                            <div style="font-weight:600;color:#111827;font-size:13px;">{{ $student['student']->full_name }}</div>
                                            <div style="color:#9ca3af;font-size:12px;">{{ $student['student']->phone }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:11px 14px;color:#374151;">{{ $student['total_sessions'] }}</td>
                                <td style="padding:11px 14px;color:#374151;">{{ $student['present'] }}</td>
                                <td style="padding:11px 14px;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:13px;font-weight:500;color:#111827;min-width:36px;">{{ $rate }}%</span>
                                        <div style="width:64px;height:5px;background:#e5e7eb;border-radius:99px;overflow:hidden;">
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
        <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:1.5rem;">
            <h3 style="font-size:15px;font-weight:600;color:#111827;margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                <x-filament::icon icon="heroicon-o-arrow-trending-up" style="width:18px;height:18px;color:#6b7280;" />
                Attendance Trend by Date
            </h3>
            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach($reportData['by_date'] as $date)
                    @php
                        $r = $date['rate'];
                        $barCol = $r >= 90 ? '#16a34a' : ($r >= 75 ? '#2563eb' : ($r >= 60 ? '#d97706' : '#dc2626'));
                    @endphp
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f9fafb;border-radius:8px;gap:12px;">
                        <div style="display:flex;align-items:center;gap:16px;">
                            <span style="font-size:13px;font-weight:600;color:#111827;white-space:nowrap;">
                                {{ \Carbon\Carbon::parse($date['date'])->format('M d, Y') }}
                            </span>
                            <span style="font-size:12px;color:#9ca3af;">{{ $date['present'] }}/{{ $date['total'] }} present</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                            <span style="font-size:13px;font-weight:600;color:#111827;min-width:36px;text-align:right;">{{ $r }}%</span>
                            <div style="width:80px;height:5px;background:#e5e7eb;border-radius:99px;overflow:hidden;">
                                <div style="height:100%;border-radius:99px;background:{{ $barCol }};width:{{ $r }}%;"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    @endif
</div>

<style>
@keyframes pulse {
    0%,100% { opacity:1; }
    50% { opacity:.5; }
}
</style>
</x-filament-panels::page>
