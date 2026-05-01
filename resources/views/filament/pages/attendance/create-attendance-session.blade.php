<x-filament-panels::page>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap');

        .sc-wrap { font-family: 'DM Sans', ui-sans-serif, sans-serif; display: flex; flex-direction: column; gap: 1rem; }

        .sc-step {
            background: #ffffff;
            border: 0.5px solid #e5e7eb;
            border-radius: 14px;
            padding: 1.5rem;
            transition: border-color 0.2s;
        }
        .dark .sc-step { background: #1f2937; border-color: #374151; }

        .sc-step-header { display: flex; align-items: center; gap: 12px; margin-bottom: 1.25rem; }
        .sc-step-header .sc-actions { margin-left: auto; display: flex; gap: 8px; }

        .sc-num {
            width: 28px; height: 28px; border-radius: 50%;
            background: #111827; color: #fff;
            font-size: 12px; font-weight: 500;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; letter-spacing: 0.02em;
        }
        .dark .sc-num { background: #374151; }

        .sc-title { font-size: 15px; font-weight: 500; color: #111827; letter-spacing: -0.01em; }
        .dark .sc-title { color: #f9fafb; }
        .sc-sub { font-size: 12px; color: #9ca3af; margin-top: 1px; }

        .sc-label {
            display: block; font-size: 11px; font-weight: 500;
            color: #6b7280; letter-spacing: 0.06em;
            text-transform: uppercase; margin-bottom: 6px;
        }
        .dark .sc-label { color: #9ca3af; }

        .sc-select-wrap { position: relative; }
        .sc-select-wrap::after {
            content: ''; position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            border-left: 4px solid transparent; border-right: 4px solid transparent;
            border-top: 5px solid #9ca3af; pointer-events: none;
        }
        .sc-select {
            width: 100%; padding: 10px 14px;
            border: 0.5px solid #d1d5db; border-radius: 8px;
            font-family: inherit; font-size: 14px; color: #111827;
            background: #f9fafb; outline: none;
            appearance: none; -webkit-appearance: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .dark .sc-select { background: #111827; border-color: #374151; color: #f9fafb; }
        .sc-select:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

        .sc-date {
            width: 100%; padding: 10px 14px;
            border: 0.5px solid #d1d5db; border-radius: 8px;
            font-family: inherit; font-size: 14px; color: #111827;
            background: #f9fafb; outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .dark .sc-date { background: #111827; border-color: #374151; color: #f9fafb; }
        .sc-date:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

        .sc-table-wrap { border: 0.5px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
        .dark .sc-table-wrap { border-color: #374151; }

        .sc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .sc-table th {
            text-align: left; font-size: 11px; font-weight: 500;
            letter-spacing: 0.06em; text-transform: uppercase;
            color: #9ca3af; padding: 9px 14px;
            border-bottom: 0.5px solid #e5e7eb; background: #f9fafb;
        }
        .dark .sc-table th { border-color: #374151; background: #111827; }
        .sc-table td { padding: 11px 14px; border-bottom: 0.5px solid #f3f4f6; color: #111827; vertical-align: middle; }
        .dark .sc-table td { border-color: #1f2937; color: #f9fafb; }
        .sc-table tr:last-child td { border-bottom: none; }
        .sc-table tr:hover td { background: #f9fafb; }
        .dark .sc-table tr:hover td { background: #111827; }

        .sc-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; display: inline-block; margin-right: 8px; flex-shrink: 0; }
        .sc-teacher-cell { display: flex; align-items: center; cursor: pointer; }

        .sc-empty {
            text-align: center; padding: 2rem 1rem;
            font-size: 13px; color: #9ca3af;
            background: #f9fafb; border-radius: 10px;
            border: 0.5px solid #e5e7eb;
        }
        .dark .sc-empty { background: #111827; border-color: #374151; }

        .sc-teacher-check { width: 16px; height: 16px; accent-color: #6366f1; cursor: pointer; margin-right: 10px; flex-shrink: 0; }

        .sc-repeater-item {
            border: 0.5px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            background: #fafafa;
        }
        .dark .sc-repeater-item { background: #1a1f2e; border-color: #374151; }

        .sc-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px;
            background: #111827; color: #fff;
            border: none; border-radius: 8px;
            font-family: inherit; font-size: 13px; font-weight: 500;
            cursor: pointer; white-space: nowrap;
            transition: opacity 0.15s, transform 0.1s;
            letter-spacing: 0.01em;
        }
        .sc-btn:hover { opacity: 0.85; }
        .sc-btn:active { transform: scale(0.98); }
        .sc-btn:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }
        .sc-btn svg { width: 14px; height: 14px; flex-shrink: 0; }

        .sc-btn-outline {
            background: transparent; color: #111827;
            border: 0.5px solid #d1d5db;
        }
        .dark .sc-btn-outline { color: #f9fafb; border-color: #374151; }
        .sc-btn-outline:hover { background: #f3f4f6; }
        .dark .sc-btn-outline:hover { background: #1f2937; }

        .sc-btn-danger {
            background: #fef2f2; color: #dc2626;
            border: 0.5px solid #fecaca;
        }
        .dark .sc-btn-danger { background: #7f1d1d20; border-color: #dc262640; }
        .sc-btn-danger:hover { background: #fee2e2; }
        .dark .sc-btn-danger:hover { background: #7f1d1d30; }

        .sc-add-row { display: flex; justify-content: center; padding: 0.5rem 0 0 0; }

        .sc-alert {
            border-radius: 12px; padding: 1.5rem; text-align: center;
            border: 0.5px solid;
        }
        .sc-alert.warn { background: #fffbeb; border-color: #fde68a; }
        .sc-alert.warn p { color: #92400e; font-size: 13px; }
        .sc-alert.warn p + p { color: #b45309; font-size: 12px; margin-top: 4px; }

        .sc-summary-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding-top: 0.5rem; }
        .sc-summary-count {
            font-size: 11px; font-weight: 500; color: #6b7280;
            background: #f3f4f6; border: 0.5px solid #e5e7eb;
            border-radius: 20px; padding: 3px 10px;
        }
        .dark .sc-summary-count { background: #1f2937; border-color: #374151; color: #9ca3af; }

        .sc-section-divider { border-top: 0.5px solid #e5e7eb; margin: 1.5rem 0; }
        .dark .sc-section-divider { border-color: #374151; }

        .sc-badge {
            display: inline-flex; align-items: center;
            padding: 2px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 600; letter-spacing: 0.03em;
        }
        .sc-badge.open { background: #dcfce7; color: #15803d; }
        .sc-badge.completed { background: #dbeafe; color: #1d4ed8; }
        .sc-badge.locked { background: #fee2e2; color: #b91c1c; }
    </style>

    <div class="sc-wrap">

        {{-- ═══ Create Section ═══ --}}
        <div class="sc-step">
            <div class="sc-step-header">
                <div class="sc-num">+</div>
                <div class="sc-title">Create new attendance session</div>
                <div class="sc-sub">One session per day, covering multiple classes</div>
            </div>

            {{-- Date picker --}}
            <div style="max-width:280px; margin-bottom:1.25rem;">
                <label class="sc-label" for="sc-session-date">Session date</label>
                <input id="sc-session-date"
                    type="date"
                    wire:model.live="sessionDate"
                    class="sc-date">
            </div>

            @if($todaySessionExists)
                <div class="sc-alert warn" style="margin-bottom:1rem;">
                    <p style="font-weight:500;">A session already exists for {{ $sessionDate }}</p>
                    <p>Only one session is allowed per day. Choose a different date or delete the existing session below.</p>
                </div>
            @endif

            @if(!$todaySessionExists)
                {{-- Class entries --}}
                @foreach($classEntries as $index => $entry)
                <div class="sc-repeater-item" wire:key="class-entry-{{ $index }}" style="margin-bottom:0.75rem;">
                    <div class="sc-step-header" style="margin-bottom:0.75rem;">
                        <div class="sc-num" style="font-size:11px;">{{ $index + 1 }}</div>
                        <div class="sc-sub">
                            @if($entry['classId'])
                                {{ $this->classes[$entry['classId']] ?? 'Selected class' }}
                            @else
                                Select a class
                            @endif
                        </div>
                        @if(count($classEntries) > 1)
                        <div class="sc-actions">
                            <button type="button"
                                wire:click="removeClassEntry({{ $index }})"
                                class="sc-btn sc-btn-danger"
                                style="padding:4px 10px; font-size:11px;">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="width:11px;height:11px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Remove
                            </button>
                        </div>
                        @endif
                    </div>

                    <div class="sc-select-wrap" style="max-width:300px;">
                        <label class="sc-label" for="sc-class-{{ $index }}">Class</label>
                        <select id="sc-class-{{ $index }}"
                            class="sc-select"
                            wire:change="onClassSelected({{ $index }}, $event.target.value)">
                            <option value="">— Select a class —</option>
                            @foreach($this->classes as $id => $name)
                                <option value="{{ $id }}" {{ $entry['classId'] == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Teacher assignments --}}
                    @if($entry['classId'] && !empty($entry['assignments']))
                    <div style="margin-top:1rem;">
                        <div class="sc-label">Teachers to include</div>
                        <div class="sc-table-wrap">
                            <table class="sc-table">
                                <thead>
                                    <tr>
                                        <th style="width:32px;"></th>
                                        <th>Teacher</th>
                                        <th>Subject</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($entry['assignments'] as $ass)
                                        <tr>
                                            <td>
                                                <input type="checkbox"
                                                    class="sc-teacher-check"
                                                    value="{{ $ass['id'] }}"
                                                    wire:model="classEntries.{{ $index }}.selectedAssignments"
                                                    id="ta-{{ $index }}-{{ $ass['id'] }}">
                                            </td>
                                            <td>
                                                <label for="ta-{{ $index }}-{{ $ass['id'] }}" class="sc-teacher-cell">
                                                    <span class="sc-dot"></span>
                                                    {{ $ass['teacher_name'] }}
                                                </label>
                                            </td>
                                            <td style="color: #6b7280;">{{ $ass['subject_name'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @elseif($entry['classId'] && empty($entry['assignments']))
                    <div class="sc-empty" style="margin-top:1rem;">
                        No active teacher assignments found for this class in the current academic year.
                    </div>
                    @endif
                </div>
                @endforeach

                {{-- Add another class --}}
                <div class="sc-add-row" style="margin-top:0.5rem;">
                    <button type="button" wire:click="addClassEntry" class="sc-btn sc-btn-outline">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add another class
                    </button>
                </div>

                {{-- Create button --}}
                <div class="sc-summary-row" style="margin-top:1.25rem;">
                    <span class="sc-summary-count">
                        @php $clsCount = count(array_filter($classEntries, fn($e) => !empty($e['classId']))); @endphp
                        {{ $clsCount }} class(es) selected
                    </span>
                    <button type="button"
                        wire:click="createSession"
                        class="sc-btn"
                        @if($clsCount === 0) disabled @endif>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Create session
                    </button>
                </div>
            @endif
        </div>

        {{-- ═══ Sessions List ═══ --}}
        <div class="sc-section-divider"></div>

        <div class="sc-step">
            <div class="sc-step-header" style="margin-bottom:0.75rem;">
                <div class="sc-num" style="background:#4f46e5;">&check;</div>
                <div>
                    <div class="sc-title">All attendance sessions</div>
                    <div class="sc-sub">Recent sessions with class and teacher counts</div>
                </div>
            </div>

            @if(empty($allSessions))
                <div class="sc-empty">No sessions created yet.</div>
            @else
                <div class="sc-table-wrap">
                    <table class="sc-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Classes</th>
                                <th>Teachers</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allSessions as $s)
                                <tr>
                                    <td style="font-weight:500;">{{ $s['date'] }}</td>
                                    <td>{{ $s['class_count'] }}</td>
                                    <td>{{ $s['teacher_count'] }}</td>
                                    <td>
                                        <span class="sc-badge {{ strtolower($s['status']) }}">{{ $s['status'] }}</span>
                                    </td>
                                    <td style="color:#6b7280; font-size:12px;">{{ $s['created_at'] }}</td>
                                    <td style="text-align:right;">
                                        <div style="display:flex; gap:6px; justify-content:flex-end;">
                                            <a href="{{ route('filament.admin.resources.attendance-sessions.view', $s['id']) }}"
                                                class="sc-btn sc-btn-outline"
                                                style="padding:4px 10px; font-size:11px;"
                                                target="_blank">
                                                View
                                            </a>
                                            @if(Auth::user()?->hasRole(['admin', 'superadmin']))
                                                <button type="button"
                                                    wire:click="deleteSession({{ $s['id'] }})"
                                                    wire:confirm="Are you sure you want to delete this session?"
                                                    class="sc-btn sc-btn-danger"
                                                    style="padding:4px 10px; font-size:11px;">
                                                    Delete
                                                </button>
                                            @endif
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
