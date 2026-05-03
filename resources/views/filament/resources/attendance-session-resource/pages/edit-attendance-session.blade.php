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

        /* ── New bulk-select layout ── */
        .sc-two-col { display: grid; grid-template-columns: 260px 1fr; gap: 1.5rem; }
        @media (max-width: 768px) { .sc-two-col { grid-template-columns: 1fr; } }

        .sc-class-list { display: flex; flex-direction: column; gap: 4px; }
        .sc-class-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 9px 10px; border-radius: 8px; border: 0.5px solid transparent;
            cursor: pointer; transition: background 0.12s, border-color 0.12s;
        }
        .sc-class-row:hover { background: #f3f4f6; }
        .dark .sc-class-row:hover { background: #1f2937; }
        .sc-class-row.active { background: #eef2ff; border-color: #c7d2fe; }
        .dark .sc-class-row.active { background: #1e1b4b; border-color: #4338ca; }

        .sc-class-check { width: 16px; height: 16px; accent-color: #6366f1; cursor: pointer; margin-right: 10px; flex-shrink: 0; }
        .sc-class-info { display: flex; align-items: center; flex: 1; }
        .sc-class-name { font-size: 13px; font-weight: 500; color: #111827; }
        .dark .sc-class-name { color: #f9fafb; }
        .sc-class-count {
            font-size: 11px; font-weight: 500; color: #6b7280;
            background: #f3f4f6; border-radius: 20px; padding: 2px 8px;
            flex-shrink: 0;
        }
        .dark .sc-class-count { background: #374151; color: #d1d5db; }
        .sc-class-row.active .sc-class-count { background: #c7d2fe; color: #4338ca; }
        .dark .sc-class-row.active .sc-class-count { background: #4338ca; color: #e0e7ff; }

        .sc-group-header {
            background: #f9fafb; border-bottom: 0.5px solid #e5e7eb;
            font-size: 11px; font-weight: 600; color: #374151;
            padding: 9px 14px; text-transform: uppercase; letter-spacing: 0.05em;
            display: flex; align-items: center; gap: 8px;
        }
        .dark .sc-group-header { background: #111827; border-color: #374151; color: #d1d5db; }

        .sc-tag-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 1rem; }
        .sc-tag {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 500; color: #4338ca;
            background: #eef2ff; border: 0.5px solid #c7d2fe;
        }
        .dark .sc-tag { background: #1e1b4b; border-color: #4338ca; color: #a5b4fc; }
        .sc-tag button {
            background: none; border: none; cursor: pointer; color: inherit;
            font-size: 16px; line-height: 1; padding: 0; display: flex; align-items: center;
        }
        .sc-tag button:hover { opacity: 0.7; }

        .sc-sticky-summary {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            padding: 1rem 1.5rem; margin-top: 1.25rem;
            background: #ffffff; border: 0.5px solid #e5e7eb;
            border-radius: 14px;
        }
        .dark .sc-sticky-summary { background: #1f2937; border-color: #374151; }

        .sc-empty-teachers {
            text-align: center; padding: 3rem 1.5rem;
            font-size: 13px; color: #9ca3af;
            background: #f9fafb; border-radius: 10px;
            border: 0.5px solid #e5e7eb;
        }
        .dark .sc-empty-teachers { background: #111827; border-color: #374151; }
    </style>

    <div class="sc-wrap">

        {{-- ═══ Edit Section ═══ --}}
        <div class="sc-step">
            <div class="sc-step-header">
                <div class="sc-num" style="background:#4f46e5;">✎</div>
                <div class="sc-title">Edit attendance session</div>
                <div class="sc-sub">Update session date, classes and teacher assignments</div>
            </div>

            {{-- Date picker --}}
            <div style="max-width:280px; margin-bottom:1.25rem;">
                <label class="sc-label" for="sc-session-date">Session date</label>
                <input id="sc-session-date"
                    type="date"
                    wire:model.live="sessionDate"
                    class="sc-date">
            </div>

            <div class="sc-two-col">

                {{-- Left: Class Selector --}}
                <div>
                    <div class="sc-step-header" style="margin-bottom:0.5rem;">
                        <div class="sc-title" style="font-size:13px;">Select Classes</div>
                        <div class="sc-actions">
                            <button type="button"
                                wire:click="$set('selectedClassIds', {{ json_encode($this->classes->keys()->toArray()) }})"
                                class="sc-btn sc-btn-outline"
                                style="padding:4px 10px;font-size:11px;">
                                All
                            </button>
                            <button type="button"
                                wire:click="$set('selectedClassIds', [])"
                                class="sc-btn sc-btn-outline"
                                style="padding:4px 10px;font-size:11px;">
                                Clear
                            </button>
                        </div>
                    </div>

                    <div class="sc-class-list">
                        @foreach($this->classes as $id => $name)
                            @php
                                $isSelected = in_array($id, $selectedClassIds);
                                $teacherCount = count($classAssignmentsMap[$id] ?? []);
                            @endphp
                            <label class="sc-class-row {{ $isSelected ? 'active' : '' }}">
                                <div class="sc-class-info">
                                    <input type="checkbox"
                                        wire:model.live="selectedClassIds"
                                        value="{{ $id }}"
                                        class="sc-class-check"
                                        @if($isSelected) checked @endif>
                                    <span class="sc-class-name">{{ $name }}</span>
                                </div>
                                @if($teacherCount > 0)
                                    <span class="sc-class-count">{{ $teacherCount }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Right: Teacher Assignments --}}
                <div>
                    <div class="sc-step-header" style="margin-bottom:0.5rem;">
                        <div class="sc-title" style="font-size:13px;">Select Teachers</div>
                        <div class="sc-actions">
                            <button type="button"
                                wire:click="selectAllAssignments"
                                class="sc-btn sc-btn-outline"
                                style="padding:4px 10px;font-size:11px;">
                                All
                            </button>
                            <button type="button"
                                wire:click="deselectAllAssignments"
                                class="sc-btn sc-btn-outline"
                                style="padding:4px 10px;font-size:11px;">
                                Clear
                            </button>
                        </div>
                    </div>

                    @if(!empty($availableAssignments))
                        <div class="sc-table-wrap">
                            <table class="sc-table">
                                <thead>
                                    <tr>
                                        <th style="width:32px;"></th>
                                        <th>Teacher</th>
                                        <th>Subject</th>
                                        <th style="width:90px;">Class</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $grouped = collect($availableAssignments)
                                            ->groupBy('class_id')
                                            ->sortBy(fn($items) => $items->first()['class_name']);
                                    @endphp
                                    @foreach($grouped as $classId => $assignments)
                                        @php
                                            $className = $assignments->first()['class_name'];
                                            $ids = $assignments->pluck('id')->toArray();
                                            $allSelected = count(array_intersect($ids, $selectedAssignmentIds)) === count($ids);
                                        @endphp
                                        <tr class="sc-group-header">
                                            <td>
                                                <input type="checkbox"
                                                    wire:click="toggleClassAssignments({{ $classId }})"
                                                    @if($allSelected) checked @endif>
                                            </td>
                                            <td colspan="3">{{ $className }} — Select all {{ count($ids) }} teachers</td>
                                        </tr>
                                        @foreach($assignments as $ass)
                                            <tr>
                                                <td>
                                                    <input type="checkbox"
                                                        class="sc-teacher-check"
                                                        wire:model="selectedAssignmentIds"
                                                        value="{{ $ass['id'] }}"
                                                        id="ta-{{ $ass['id'] }}">
                                                </td>
                                                <td>
                                                    <label for="ta-{{ $ass['id'] }}" class="sc-teacher-cell">
                                                        <span class="sc-dot"></span>
                                                        {{ $ass['teacher_name'] }}
                                                    </label>
                                                </td>
                                                <td style="color:#6b7280;">{{ $ass['subject_name'] }}</td>
                                                <td style="font-size:12px;color:#6b7280;">{{ $ass['class_name'] }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="sc-empty-teachers">
                            Select one or more classes to view teacher assignments.
                        </div>
                    @endif
                </div>

            </div>

            {{-- Tag pills --}}
            @if(!empty($selectedClassIds))
                <div class="sc-tag-row">
                    @foreach($selectedClassIds as $cid)
                        <span class="sc-tag">
                            {{ $this->selectedClasses[$cid] ?? 'Class' }}
                            <button type="button" wire:click="removeClassTag({{ $cid }})">&times;</button>
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Summary bar --}}
            @php
                $clsCount = count($selectedClassIds);
                $assCount = count($selectedAssignmentIds);
            @endphp
            <div class="sc-sticky-summary">
                <span class="sc-summary-count">
                    {{ $clsCount }} class(es) · {{ $assCount }} teacher(s) selected
                </span>
                <button type="button"
                    wire:click="updateSession"
                    class="sc-btn"
                    @if($clsCount === 0 || $assCount === 0) disabled @endif>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Update session
                </button>
            </div>

        </div>

    </div>
</x-filament-panels::page>
