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
        .at-filter-field { width: 280px; }

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
        .at-bulk-btn.late    { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
        .at-bulk-btn.perm    { background: #fef9c3; color: #a16207; border-color: #fef08a; }

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
        .at-status-btn.active-late    { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }
        .at-status-btn.active-perm    { background: #fef9c3; border-color: #fde047; color: #a16207; }
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
    </style>

    <div class="at-wrap">

        {{-- ── Filters ── --}}
        <div class="at-card">
            <div class="at-filter-row">
                <div class="at-filter-field">
                    <label class="at-label">Session</label>
                    <div class="at-select-wrap">
                        <select wire:model.live="sessionId" class="at-select">
                            <option value="">— Select a session —</option>
                            @foreach($sessions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($sessionId && !empty($teachers))
                    <button type="button" wire:click="saveAttendance" class="at-btn-save">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save attendance
                    </button>
                @endif
            </div>
        </div>

        @if($sessionId && !empty($teachers))

            {{-- ── Summary ── --}}
            <div class="at-card">
                <div class="at-summary">
                    <span class="at-summary-text">{!! $this->attendanceSummary !!}</span>
                    <span class="at-summary-count">{{ count($teachers) }} teachers</span>
                </div>
            </div>

            {{-- ── Bulk actions ── --}}
            <div class="at-card">
                <div class="at-bulk-row">
                    <span class="at-bulk-label">Bulk mark</span>

                    {{--
                        FIX: Same bulk-mark bug as the student page. Changed from
                        `$set('bulkStatus', ...)` to `applyBulkStatus('...')`.
                        Add a public `applyBulkStatus(string $status)` method in your
                        Livewire component that does:
                            foreach ($this->selectedTeachers as $id) {
                                $this->attendance[$id] = $status;
                            }
                    --}}
                    <button type="button" wire:click="applyBulkStatus('Present')" class="at-bulk-btn present">Present</button>
                    <button type="button" wire:click="applyBulkStatus('Absent')"  class="at-bulk-btn absent">Absent</button>
                    <button type="button" wire:click="applyBulkStatus('Late')"    class="at-bulk-btn late">Late</button>
                    <button type="button" wire:click="applyBulkStatus('Permission')" class="at-bulk-btn perm">Permission</button>

                    @if(count($selectedTeachers) > 0)
                        <span class="at-sel-info">{{ count($selectedTeachers) }} selected</span>
                        <button type="button" wire:click="$set('selectedTeachers', [])" class="at-clear-btn">
                            Clear
                        </button>
                    @endif
                </div>
            </div>

            {{-- ── Teacher table ── --}}
            <div class="at-table-wrap">
                <table class="at-table">
                    <thead>
                        <tr>
                            <th style="width:44px; text-align:center;">
                                <input type="checkbox" wire:click="toggleSelectAll"
                                    @if(count($selectedTeachers) === count($teachers)) checked @endif>
                            </th>
                            <th>Teacher name</th>
                            <th style="width:260px; text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($teachers as $teacherId => $teacher)
                            @php $currentStatus = $attendance[$teacherId] ?? null; @endphp
                            <tr class="{{ in_array($teacherId, $selectedTeachers) ? 'at-selected' : '' }}">
                                <td style="text-align:center;">
                                    <input type="checkbox" wire:model.live="selectedTeachers" value="{{ $teacherId }}">
                                </td>
                                <td>
                                    <span class="at-name">{{ $teacher['name'] }}</span>
                                </td>
                                <td>
                                    <div class="at-status-group">
                                        <button type="button"
                                            wire:click="$set('attendance.{{ $teacherId }}', 'Present')"
                                            class="at-status-btn {{ $currentStatus === 'Present' ? 'active-present' : '' }}">
                                            <span class="dot" style="background:#22c55e;"></span> P
                                        </button>
                                        <button type="button"
                                            wire:click="$set('attendance.{{ $teacherId }}', 'Absent')"
                                            class="at-status-btn {{ $currentStatus === 'Absent' ? 'active-absent' : '' }}">
                                            <span class="dot" style="background:#ef4444;"></span> A
                                        </button>
                                        <button type="button"
                                            wire:click="$set('attendance.{{ $teacherId }}', 'Late')"
                                            class="at-status-btn {{ $currentStatus === 'Late' ? 'active-late' : '' }}">
                                            <span class="dot" style="background:#3b82f6;"></span> L
                                        </button>
                                        <button type="button"
                                            wire:click="$set('attendance.{{ $teacherId }}', 'Permission')"
                                            class="at-status-btn {{ $currentStatus === 'Permission' ? 'active-perm' : '' }}">
                                            <span class="dot" style="background:#eab308;"></span> X
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- ── Bottom save ── --}}
            <div class="at-bottom-save">
                <button type="button" wire:click="saveAttendance" class="at-btn-save">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save teacher attendance
                </button>
            </div>

        @elseif(empty($sessions))
            <div class="at-alert warn">
                <p style="font-weight:500;">No open sessions found for today.</p>
                <p>Create an attendance session first from the "Create Session" page.</p>
            </div>

        @elseif(!$sessionId)
            <div class="at-alert neutral">
                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                </svg>
                <p>Select a session to view teacher attendance</p>
                <p>Choose an open session from the filter above to begin marking attendance.</p>
            </div>
        @endif

    </div>
</x-filament-panels::page>
