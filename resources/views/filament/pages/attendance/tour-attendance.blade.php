<x-filament-panels::page>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap');

        .at-wrap { font-family: 'DM Sans', ui-sans-serif, sans-serif; display: flex; flex-direction: column; gap: 1rem; }

        .at-card {
            background: #ffffff;
            border: 0.5px solid #e5e7eb;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            transition: border-color 0.2s;
        }
        .dark .at-card { background: #1f2937; border-color: #374151; }

        .at-label {
            display: block; font-size: 11px; font-weight: 500;
            color: #6b7280; letter-spacing: 0.06em;
            text-transform: uppercase; margin-bottom: 6px;
        }
        .dark .at-label { color: #9ca3af; }

        .at-select-wrap { position: relative; }
        .at-select-wrap::after {
            content: ''; position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            border-left: 4px solid transparent; border-right: 4px solid transparent;
            border-top: 5px solid #9ca3af; pointer-events: none;
        }
        .at-select {
            width: 100%; padding: 9px 32px 9px 12px;
            border: 0.5px solid #d1d5db; border-radius: 8px;
            font-family: inherit; font-size: 13px; color: #111827;
            background: #f9fafb; outline: none;
            appearance: none; -webkit-appearance: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .dark .at-select { background: #111827; border-color: #374151; color: #f9fafb; }
        .at-select:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

        .at-filter-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; }
        .at-filter-field { width: 400px; }

        .at-btn-save {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px;
            background: #111827; color: #fff;
            border: none; border-radius: 8px;
            font-family: inherit; font-size: 13px; font-weight: 500;
            cursor: pointer; white-space: nowrap; margin-left: auto;
            transition: opacity 0.15s, transform 0.1s;
        }
        .at-btn-save:hover { opacity: 0.85; }
        .at-btn-save:active { transform: scale(0.98); }
        .at-btn-save svg { width: 13px; height: 13px; }

        .at-summary { display: flex; align-items: center; justify-content: space-between; }
        .at-summary-text { font-size: 13px; color: #374151; }
        .dark .at-summary-text { color: #d1d5db; }
        .at-summary-count {
            font-size: 11px; font-weight: 500; color: #6b7280;
            background: #f3f4f6; border: 0.5px solid #e5e7eb;
            border-radius: 20px; padding: 3px 10px;
        }
        .dark .at-summary-count { background: #111827; border-color: #374151; color: #9ca3af; }

        .at-bulk-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .at-bulk-label { font-size: 11px; font-weight: 500; color: #6b7280; letter-spacing: 0.04em; text-transform: uppercase; }
        .dark .at-bulk-label { color: #9ca3af; }

        .at-bulk-btn {
            padding: 5px 12px; border-radius: 6px; border: 0.5px solid transparent;
            font-family: inherit; font-size: 12px; font-weight: 500; cursor: pointer;
            transition: opacity 0.15s, transform 0.1s;
        }
        .at-bulk-btn:active { transform: scale(0.97); }
        .at-bulk-btn:hover { opacity: 0.85; }
        .at-bulk-btn.present { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
        .at-bulk-btn.absent  { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }

        .at-sel-info { font-size: 12px; color: #6b7280; margin-left: 4px; }
        .at-clear-btn {
            font-size: 12px; color: #6b7280; background: none; border: none;
            cursor: pointer; text-decoration: underline; font-family: inherit;
        }
        .at-clear-btn:hover { color: #374151; }

        .at-table-wrap { border-radius: 14px; border: 0.5px solid #e5e7eb; overflow: hidden; }
        .dark .at-table-wrap { border-color: #374151; }

        .at-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .at-table thead tr { background: #f9fafb; }
        .dark .at-table thead tr { background: #111827; }
        .at-table th {
            padding: 10px 14px; text-align: left;
            font-size: 10px; font-weight: 600; letter-spacing: 0.07em;
            text-transform: uppercase; color: #9ca3af;
            border-bottom: 0.5px solid #e5e7eb;
        }
        .dark .at-table th { border-color: #374151; }
        .at-table td { padding: 10px 14px; border-bottom: 0.5px solid #f3f4f6; vertical-align: middle; }
        .dark .at-table td { border-color: #1f2937; }
        .at-table tbody tr:last-child td { border-bottom: none; }
        .at-table tbody tr:hover td { background: #f9fafb; }
        .dark .at-table tbody tr:hover td { background: #111827; }
        .at-table tbody tr.at-selected td { background: #eef2ff; }
        .dark .at-table tbody tr.at-selected td { background: #1e1b4b; }

        .at-name { font-size: 13px; font-weight: 500; color: #111827; }
        .dark .at-name { color: #f9fafb; }
        .at-code { font-size: 11px; color: #6b7280; }
        .dark .at-code { color: #9ca3af; }

        .at-status-group { display: flex; justify-content: center; gap: 6px; }

        .at-status-btn {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 6px; border: 0.5px solid #e5e7eb;
            font-family: inherit; font-size: 11px; font-weight: 600;
            cursor: pointer; transition: all 0.12s; background: #fff;
            color: #9ca3af; letter-spacing: 0.03em;
        }
        .dark .at-status-btn { background: #1f2937; border-color: #374151; color: #6b7280; }
        .at-status-btn .dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

        .at-status-btn.active-present { background: #dcfce7; border-color: #86efac; color: #15803d; }
        .at-status-btn.active-absent  { background: #fee2e2; border-color: #fca5a5; color: #b91c1c; }

        .at-status-btn:not([class*="active-"]):hover { border-color: #d1d5db; color: #374151; background: #f9fafb; }

        .at-alert { border-radius: 12px; padding: 1.5rem; text-align: center; border: 0.5px solid; }
        .at-alert.warn { background: #fffbeb; border-color: #fde68a; }
        .at-alert.warn p { color: #92400e; font-size: 13px; }
        .at-alert.warn p + p { color: #b45309; font-size: 12px; margin-top: 4px; }
        .at-alert.neutral { background: #f9fafb; border-color: #e5e7eb; padding: 3rem 1.5rem; }
        .at-alert.neutral p { color: #6b7280; font-size: 13px; font-weight: 500; margin-top: 8px; }
        .at-alert.neutral p + p { color: #9ca3af; font-size: 12px; margin-top: 4px; font-weight: 400; }
        .at-alert svg { color: #d1d5db; margin: 0 auto; display: block; }

        .at-bottom-save { display: flex; justify-content: flex-end; }

        .at-call-btn {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 6px;
            font-family: inherit; font-size: 12px; font-weight: 500;
            text-decoration: none; cursor: pointer;
            background: #eef2ff; color: #4338ca; border: 0.5px solid #c7d2fe;
            transition: opacity 0.15s;
        }
        .at-call-btn:hover { opacity: 0.8; }
        .dark .at-call-btn { background: #1e1b4b; color: #a5b4fc; border-color: #3730a3; }
        .at-call-btn svg { width: 14px; height: 14px; }
    </style>

    <div class="at-wrap">

        @if($tourId && $sessionId && !empty($passengers))

            {{-- ── Tour header ── --}}
            <div class="at-card">
                <div class="at-summary">
                    <span style="font-size:15px;font-weight:600;">{{ $tourPlace }}</span>
                    <span class="at-summary-count">{{ count($passengers) }} passengers</span>
                </div>
                <div style="margin-top:8px;">
                    <span class="at-summary-text">{!! $this->attendanceSummary !!}</span>
                </div>
                @if($isLocked)
                    <span style="font-size:12px;color:#9ca3af;margin-top:6px;display:inline-block;"> Locked — read only</span>
                @endif
            </div>

            {{-- ── Bulk actions ── --}}
            @if(!$isLocked)
            <div class="at-card">
                <div class="at-bulk-row">
                    <span class="at-bulk-label">Bulk mark</span>

                    <button type="button" wire:click="applyBulkStatus('Present')" class="at-bulk-btn present">Present</button>
                    <button type="button" wire:click="applyBulkStatus('Not Present')"  class="at-bulk-btn absent">Not Present</button>

                    @if(count($selectedPassengers) > 0)
                        <span class="at-sel-info">{{ count($selectedPassengers) }} selected</span>
                        <button type="button" wire:click="$set('selectedPassengers', [])" class="at-clear-btn">
                            Clear
                        </button>
                    @endif
                </div>
            </div>
            @endif

            {{-- ── Passenger table ── --}}
            <div class="at-table-wrap">
                <table class="at-table">
                    <thead>
                        <tr>
                            <th style="width:44px; text-align:center;">
                                <input type="checkbox" wire:click="toggleSelectAll"
                                    @if(count($selectedPassengers) === count($passengers)) checked @endif>
                            </th>
                            <th>Passenger name</th>
                            <th>Code</th>
                            <th>Phone</th>
                            <th>Pax</th>
                            <th style="width:200px; text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($passengers as $passengerId => $passenger)
                            @php $currentStatus = $attendance[$passengerId] ?? null; @endphp
                            <tr class="{{ in_array($passengerId, $selectedPassengers) ? 'at-selected' : '' }}">
                                <td style="text-align:center;">
                                    <input type="checkbox" wire:model.live="selectedPassengers" value="{{ $passengerId }}">
                                </td>
                                <td>
                                    <span class="at-name">{{ $passenger['full_name'] }}</span>
                                </td>
                                <td>
                                    <span class="at-code">{{ $passenger['passenger_code'] }}</span>
                                </td>
                                <td>
                                    @if($passenger['phone'])
                                        <a href="tel:{{ $passenger['phone'] }}" class="at-call-btn">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                                            </svg>
                                            Call
                                        </a>
                                    @else
                                        <span style="color:#9ca3af;font-size:12px;">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="at-code">{{ $passenger['passenger_count'] }}</span>
                                </td>
                                <td>
                                    @if($isLocked)
                                        <span style="font-size:12px;font-weight:600;padding:4px 12px;border-radius:6px;
                                            {{ $currentStatus === 'Present' ? 'background:#dcfce7;color:#15803d;' : '' }}
                                            {{ $currentStatus === 'Not Present' ? 'background:#fee2e2;color:#b91c1c;' : '' }}
                                            {{ !$currentStatus ? 'background:#f3f4f6;color:#9ca3af;' : '' }}">
                                            {{ $currentStatus ?? 'Unmarked' }}
                                        </span>
                                    @else
                                    <div class="at-status-group">
                                        <button type="button"
                                            wire:click="$set('attendance.{{ $passengerId }}', 'Present')"
                                            class="at-status-btn {{ $currentStatus === 'Present' ? 'active-present' : '' }}">
                                            <span class="dot" style="background:#22c55e;"></span> P
                                        </button>
                                        <button type="button"
                                            wire:click="$set('attendance.{{ $passengerId }}', 'Not Present')"
                                            class="at-status-btn {{ $currentStatus === 'Not Present' ? 'active-absent' : '' }}">
                                            <span class="dot" style="background:#ef4444;"></span> N
                                        </button>
                                    </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- ── Bottom save ── --}}
            @if(!$isLocked)
            <div class="at-bottom-save">
                <button type="button" wire:click="saveAttendance" class="at-btn-save">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save attendance
                </button>
            </div>
            @endif

        @elseif($tourId && !$sessionId)
            <div class="at-alert warn">
                <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" style="margin:0 auto;display:block;color:#d97706;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                <p style="font-weight:500;margin-top:10px;">No attendance session for this tour</p>
                <p>Mark the tour as "Completed" to auto-generate an attendance session, or use "Generate Attendance" batch action.</p>
            </div>

        @else
            <div class="at-alert neutral">
                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                </svg>
                <p>Select a tour to take attendance</p>
                <p>Go to the Tour Attendance list and click "Take Attendance" on a tour to begin marking.</p>
            </div>
        @endif

    </div>
</x-filament-panels::page>
