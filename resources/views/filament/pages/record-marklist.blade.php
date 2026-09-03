<x-filament-panels::page>
    @php
        $locked = $this->isLocked();
        $total = count($this->rows);
        $scored = collect($this->rows)->filter(fn ($row) => filled($row['score'] ?? null) || (filled($row['conduct'] ?? null) && filled($row['memorization'] ?? null) && filled($row['participation'] ?? null)))->count();
        $status = $this->marklistStatus;
        $statusLabel = match ($status) {
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'draft' => 'Draft',
            default => null,
        };
        $rubricLabels = [
            'excellent' => 'Excellent',
            'good' => 'Good',
            'needs_work' => 'Needs work',
        ];
    @endphp

    <style>
        .rm-wrap { display: flex; flex-direction: column; gap: 1rem; }

        .rm-card {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            padding: 1.15rem 1.25rem;
        }
        .dark .rm-card { background: #111827; border-color: rgba(255, 255, 255, 0.08); }

        .rm-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.4rem;
        }

        .rm-filter { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.85rem; align-items: end; }
        @media (max-width: 900px) { .rm-filter { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 560px) { .rm-filter { grid-template-columns: 1fr; } }

        .rm-select, .rm-input {
            width: 100%;
            padding: 0.55rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.7rem;
            font-size: 0.875rem;
            color: #0f172a;
            background: #f8fafc;
            outline: none;
        }
        .dark .rm-select, .dark .rm-input { background: #0b1220; border-color: #334155; color: #f8fafc; }
        .rm-select:focus, .rm-input:focus { border-color: #1A44F7; box-shadow: 0 0 0 3px rgba(26, 68, 247, 0.12); }
        .rm-select:disabled, .rm-input:disabled { opacity: 0.7; cursor: not-allowed; }

        .rm-select.is-excellent { color: #15803d; background: #f0fdf4; }
        .rm-select.is-good { color: #1d4ed8; background: #eff6ff; }
        .rm-select.is-needs_work { color: #b45309; background: #fffbeb; }
        .dark .rm-select.is-excellent { background: rgba(22, 163, 74, 0.12); color: #86efac; }
        .dark .rm-select.is-good { background: rgba(26, 68, 247, 0.16); color: #93b4ff; }
        .dark .rm-select.is-needs_work { background: rgba(245, 158, 11, 0.14); color: #fcd34d; }

        .rm-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; }
        .rm-toolbar__meta { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
        .rm-chip {
            display: inline-flex; align-items: center;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 650;
            background: #f1f5f9;
            color: #475569;
        }
        .dark .rm-chip { background: #1f2937; color: #cbd5e1; }
        .rm-chip.is-draft { background: #eff6ff; color: #1A44F7; }
        .rm-chip.is-submitted { background: #fffbeb; color: #b45309; }
        .rm-chip.is-approved { background: #ecfdf5; color: #15803d; }

        .rm-table-wrap { border-radius: 1rem; border: 1px solid rgba(15, 23, 42, 0.08); overflow: hidden; }
        .dark .rm-table-wrap { border-color: rgba(255, 255, 255, 0.08); }
        .rm-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .rm-table thead tr { background: #f8fafc; }
        .dark .rm-table thead tr { background: #0b1220; }
        .rm-table th {
            padding: 0.7rem 0.9rem;
            text-align: left;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }
        .rm-table td { padding: 0.7rem 0.9rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .dark .rm-table th, .dark .rm-table td { border-color: #1f2937; }
        .rm-table tbody tr:last-child td { border-bottom: none; }
        .rm-table tbody tr:hover td { background: #f8fafc; }
        .dark .rm-table tbody tr:hover td { background: #0b1220; }

        .rm-name { font-weight: 600; color: #0f172a; }
        .dark .rm-name { color: #f8fafc; }
        .rm-code { display: block; margin-top: 0.1rem; font-size: 0.75rem; color: #94a3b8; font-family: ui-monospace, monospace; }

        .rm-empty {
            text-align: center;
            padding: 2.4rem 1rem;
            color: #64748b;
        }
        .rm-empty strong { display: block; margin-bottom: 0.35rem; color: #0f172a; font-size: 1rem; }
        .dark .rm-empty strong { color: #f8fafc; }

        .rm-actions { display: flex; flex-wrap: wrap; gap: 0.65rem; }
        .rm-error { margin-top: 0.35rem; font-size: 0.75rem; color: #dc2626; }
    </style>

    <div class="rm-wrap" data-tour="record-marklist">
        <div class="rm-card">
            <form wire:submit="loadRoster" class="rm-filter">
                <div>
                    <label class="rm-label" for="rm-class">Class</label>
                    <select id="rm-class" wire:model="classId" class="rm-select">
                        <option value="">Select class</option>
                        @foreach ($classes as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('classId') <p class="rm-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="rm-label" for="rm-term">Semester</label>
                    <select id="rm-term" wire:model="termId" class="rm-select">
                        <option value="">Select semester</option>
                        @foreach ($terms as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('termId') <p class="rm-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="rm-label" for="rm-subject">Subject</label>
                    <select id="rm-subject" wire:model="subjectId" class="rm-select">
                        <option value="">Select subject</option>
                        @foreach ($subjects as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('subjectId') <p class="rm-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-filament::button type="submit">
                        Load roster
                    </x-filament::button>
                </div>
            </form>
        </div>

        @if ($total === 0)
            <div class="rm-card rm-empty">
                <strong>No roster loaded</strong>
                Choose a class, semester, and subject, then load the roster to start scoring.
            </div>
        @else
            <div class="rm-card rm-toolbar">
                <div class="rm-toolbar__meta">
                    <span class="rm-chip">{{ $total }} {{ \Illuminate\Support\Str::plural('student', $total) }}</span>
                    <span class="rm-chip">{{ $scored }} fully scored</span>
                    @if ($statusLabel)
                        <span class="rm-chip is-{{ $status }}">{{ $statusLabel }}</span>
                    @endif
                    @if ($locked)
                        <span class="rm-chip">Locked — view only</span>
                    @endif
                </div>
                @unless ($locked)
                    <div class="rm-actions">
                        <x-filament::button wire:click="saveDraft" color="gray">Save draft</x-filament::button>
                        <x-filament::button wire:click="submit">Submit for approval</x-filament::button>
                    </div>
                @endunless
            </div>

            <div class="rm-table-wrap overflow-x-auto">
                <table class="rm-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Score</th>
                            <th>Max</th>
                            <th>Rank</th>
                            <th>Conduct</th>
                            <th>Memorization</th>
                            <th>Participation</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows as $index => $row)
                            <tr>
                                <td>
                                    <span class="rm-name">{{ $row['name'] ?: 'Student '.$row['member_id'] }}</span>
                                    @if (filled($row['code'] ?? null))
                                        <span class="rm-code">{{ $row['code'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        wire:model="rows.{{ $index }}.score"
                                        @disabled($locked)
                                        class="rm-input"
                                        placeholder="0"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        min="1"
                                        wire:model="rows.{{ $index }}.max_score"
                                        @disabled($locked)
                                        class="rm-input"
                                    >
                                </td>
                                <td>
                                    <span class="rm-chip">{{ $row['rank'] ?? '—' }}</span>
                                </td>
                                @foreach (['conduct', 'memorization', 'participation'] as $field)
                                    <td>
                                        <select
                                            wire:model.live="rows.{{ $index }}.{{ $field }}"
                                            @disabled($locked)
                                            @class(['rm-select', 'is-'.($row[$field] ?? '') => filled($row[$field] ?? null)])
                                        >
                                            <option value="">Not scored</option>
                                            @foreach ($rubric as $score)
                                                <option value="{{ $score->value }}">{{ $rubricLabels[$score->value] ?? str_replace('_', ' ', $score->value) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                                <td>
                                    <input
                                        type="text"
                                        wire:model="rows.{{ $index }}.remarks"
                                        @disabled($locked)
                                        class="rm-input"
                                        placeholder="Optional note"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
