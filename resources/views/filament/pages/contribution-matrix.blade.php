<x-filament-panels::page>
    <style>
        :root {
            --cm-surface: #fff;
            --cm-surface-2: #f8fafc;
            --cm-row-alt: #f8fafc;
            --cm-hover: #eff6ff;
            --cm-border: #e2e8f0;
            --cm-text: #0f172a;
            --cm-muted: #64748b;
            --cm-primary: #1A44F7;
            --cm-success: #15803d;
            --cm-success-bg: #dcfce7;
            --cm-success-border: #16a34a;
        }
        .dark {
            --cm-surface: #1f2937;
            --cm-surface-2: #111827;
            --cm-row-alt: #1a2332;
            --cm-hover: #1e3a5f;
            --cm-border: #374151;
            --cm-text: #f3f4f6;
            --cm-muted: #9ca3af;
            --cm-primary: #60a5fa;
            --cm-success: #4ade80;
            --cm-success-bg: #14532d;
            --cm-success-border: #22c55e;
        }

        .cm-wrap { display: flex; flex-direction: column; gap: 16px; }

        .cm-filters {
            background: var(--cm-surface);
            border: 1px solid var(--cm-border);
            border-radius: 16px;
            overflow: hidden;
        }
        .cm-filters-head {
            padding: 12px 18px;
            border-bottom: 1px solid var(--cm-border);
            background: var(--cm-surface-2);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .cm-filters-title { font-size: 13px; font-weight: 700; color: var(--cm-text); }
        .cm-filters-sub { font-size: 12px; color: var(--cm-muted); margin-top: 2px; }
        .cm-filters-row {
            padding: 16px 18px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .cm-field { flex: 1; min-width: 160px; }
        .cm-field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--cm-muted);
            margin-bottom: 6px;
        }
        .cm-field .cm-hint { font-size: 11px; color: var(--cm-muted); margin-top: 5px; }
        .cm-select {
            width: 100%;
            height: 40px;
            font-size: 13px;
            border: 1px solid var(--cm-border);
            border-radius: 10px;
            padding: 0 12px;
            background: var(--cm-surface);
            color: var(--cm-text);
        }
        .cm-select:focus { outline: none; border-color: var(--cm-primary); box-shadow: 0 0 0 3px rgba(26, 68, 247, 0.15); }
        .cm-select:disabled { opacity: 0.6; cursor: not-allowed; background: var(--cm-surface-2); }
        .cm-ghost {
            height: 40px;
            padding: 0 14px;
            font-size: 13px;
            font-weight: 600;
            color: var(--cm-text);
            background: var(--cm-surface);
            border: 1px solid var(--cm-border);
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .cm-panel {
            background: var(--cm-surface);
            border: 1px solid var(--cm-border);
            border-radius: 16px;
            overflow: hidden;
        }
        .cm-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        .cm-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            min-width: 1100px;
        }
        .cm-table th, .cm-table td {
            border-bottom: 1px solid var(--cm-border);
        }
        .cm-table thead th {
            background: var(--cm-surface-2);
            font-size: 11px;
            font-weight: 700;
            color: var(--cm-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 10px 8px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 3;
        }
        .cm-table thead th.cm-sticky {
            text-align: left;
            padding-left: 16px;
            left: 0;
            z-index: 4;
            min-width: 240px;
            width: 240px;
        }
        .cm-month-head { min-width: 72px; }
        .cm-month-name { display: block; color: var(--cm-text); font-size: 12px; text-transform: none; letter-spacing: 0; }
        .cm-col-actions { display: flex; justify-content: center; gap: 4px; margin-top: 6px; }
        .cm-col-btn {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            border: 1px solid var(--cm-border);
            background: var(--cm-surface);
            color: var(--cm-muted);
            cursor: pointer;
        }
        .cm-col-btn:hover { border-color: var(--cm-primary); color: var(--cm-primary); }

        .cm-name-cell {
            position: sticky;
            left: 0;
            z-index: 1;
            background: inherit;
            border-right: 1px solid var(--cm-border);
            padding: 10px 14px;
            min-width: 240px;
        }
        .cm-name { font-weight: 600; color: var(--cm-text); white-space: nowrap; }
        .cm-group { font-size: 11px; color: var(--cm-muted); margin-top: 2px; }
        .cm-row-actions { display: flex; gap: 6px; margin-top: 6px; }
        .cm-row-btn {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 6px;
            border: 1px solid var(--cm-border);
            background: transparent;
            color: var(--cm-primary);
            cursor: pointer;
        }
        .cm-row-btn.clear { color: var(--cm-muted); }

        .cm-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            margin: 6px auto;
            border-radius: 12px;
            border: 2px solid var(--cm-border);
            background: var(--cm-surface);
            cursor: pointer;
        }
        .cm-cell input {
            width: 20px;
            height: 20px;
            accent-color: var(--cm-success-border);
            cursor: pointer;
        }
        .cm-cell:hover { border-color: var(--cm-primary); background: var(--cm-hover); }
        .cm-cell:has(input:checked) {
            background: var(--cm-success-bg);
            border-color: var(--cm-success-border);
        }
        .cm-cell.is-locked {
            cursor: not-allowed;
            opacity: 0.35;
            pointer-events: none;
            background: var(--cm-surface-2);
        }
        .cm-cell.is-locked span { font-size: 14px; color: var(--cm-muted); }

        .cm-total {
            text-align: right;
            padding: 10px 16px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .cm-savebar {
            position: sticky;
            bottom: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 18px;
            background: var(--cm-surface);
            border-top: 1px solid var(--cm-border);
            box-shadow: 0 -10px 24px rgba(15, 23, 42, 0.08);
        }
        .cm-savebar p { margin: 0; font-size: 13px; color: var(--cm-muted); }
        .cm-save {
            height: 42px;
            padding: 0 20px;
            border: 0;
            border-radius: 10px;
            background: #15803d;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }
        .cm-save:hover { background: #166534; }
        .cm-save:disabled { opacity: 0.65; cursor: wait; }

        .cm-empty { padding: 48px 16px; text-align: center; color: var(--cm-muted); font-size: 14px; }

        .cm-pager {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 18px;
            border-top: 1px solid var(--cm-border);
            background: var(--cm-surface-2);
        }
        .cm-pager p { margin: 0; font-size: 13px; color: var(--cm-muted); }
        .cm-pager__btns { display: flex; align-items: center; gap: 8px; }
        .cm-pager button { height: 36px; }
        .cm-pager button:disabled { opacity: 0.45; cursor: not-allowed; }
        .cm-pager__page { font-size: 13px; font-weight: 600; color: var(--cm-text); min-width: 7rem; text-align: center; }
    </style>

    @php
        $filterOptions = $this->getFilterOptions();
        $groups = $this->getGroupsForFilter();
    @endphp

    <div class="cm-wrap">
        <div class="cm-filters">
            <div class="cm-filters-head">
                <div>
                    <div class="cm-filters-title">Contribution matrix</div>
                    <div class="cm-filters-sub">Tick paid months, then save. Cells stay selected until you click Save.</div>
                </div>
                <button type="button" class="cm-ghost" wire:click="refreshData" title="Reload this page from the database. Unsaved ticks will be discarded.">Reload from database</button>
            </div>

            <div class="cm-filters-row">
                <div class="cm-field">
                    <label for="cm-year">Academic year</label>
                    <select id="cm-year" class="cm-select" wire:model.live="academicYear">
                        <option value="">Select year</option>
                        @foreach($filterOptions['academic_years'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cm-field">
                    <label for="cm-dept">Department</label>
                    <select id="cm-dept" class="cm-select" wire:model.live="department">
                        <option value="">All departments</option>
                        @foreach($filterOptions['departments'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cm-field">
                    <label for="cm-type">Member type</label>
                    <select id="cm-type" class="cm-select" wire:model.live="type">
                        <option value="">All types</option>
                        @foreach($filterOptions['types'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cm-field" wire:key="cm-groups-{{ $this->type ?? 'all' }}">
                    <label for="cm-group">Member group</label>
                    @if($this->type && empty($groups))
                        <select id="cm-group" class="cm-select" disabled>
                            <option>No groups for {{ $this->type }}</option>
                        </select>
                        <div class="cm-hint">Create a group with this type first.</div>
                    @else
                        <select id="cm-group" class="cm-select" wire:model.live="group">
                            <option value="">
                                {{ $this->type ? 'All '.$this->type.' groups' : 'All groups' }}
                            </option>
                            @foreach($groups as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <div class="cm-hint">
                            {{ $this->type
                                ? 'Only groups created under '.$this->type.' are listed.'
                                : 'Choose a member type to list only that type’s groups.' }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="cm-panel">
            <div class="cm-scroll">
                <table class="cm-table">
                    <thead>
                        <tr>
                            <th class="cm-sticky">Member</th>
                            @foreach(range(1, 12) as $m)
                                <th class="cm-month-head">
                                    <span class="cm-month-name">{{ substr($this->months[$m], 0, 3) }}</span>
                                    <div class="cm-col-actions">
                                        <button type="button" class="cm-col-btn" wire:click="markAllPaid({{ $m }})" title="Mark this month paid for everyone with an amount">All</button>
                                        <button type="button" class="cm-col-btn" wire:click="markAllUnpaid({{ $m }})" title="Clear this month">None</button>
                                    </div>
                                </th>
                            @endforeach
                            <th style="text-align:right;padding-right:16px">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->members as $index => $member)
                            @php
                                $totalYearly = 0;
                                foreach (range(1, 12) as $m) {
                                    if (filter_var($this->grid[$member->id][$m] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                                        $totalYearly += $this->getMemberGroupAmount($member, $m);
                                    }
                                }
                                $rowBg = $index % 2 === 0 ? 'var(--cm-surface)' : 'var(--cm-row-alt)';
                                $groupName = $member->currentGroupAssignment?->group?->name ?? 'No group';
                            @endphp
                            <tr style="background: {{ $rowBg }}"
                                onmouseenter="this.style.background='var(--cm-hover)'"
                                onmouseleave="this.style.background='{{ $rowBg }}'">
                                <td class="cm-name-cell">
                                    <div class="cm-name">{{ $member->first_name }} {{ $member->father_name }}</div>
                                    <div class="cm-group">{{ $groupName }}</div>
                                    <div class="cm-row-actions">
                                        <button type="button" class="cm-row-btn" wire:click="selectAllMonths({{ $member->id }})">Fill year</button>
                                        <button type="button" class="cm-row-btn clear" wire:click="clearAllMonths({{ $member->id }})">Clear</button>
                                    </div>
                                </td>
                                @foreach(range(1, 12) as $m)
                                    @php $amount = $this->getMemberGroupAmount($member, $m); @endphp
                                    <td>
                                        @if($amount > 0)
                                            <label class="cm-cell" title="{{ $this->months[$m] }} · {{ number_format($amount, 2) }} Birr">
                                                <input
                                                    type="checkbox"
                                                    wire:model="grid.{{ $member->id }}.{{ $m }}"
                                                >
                                            </label>
                                        @else
                                            <span class="cm-cell is-locked" title="No contribution amount set for this group and month">
                                                <span>—</span>
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="cm-total" style="color: {{ $totalYearly > 0 ? 'var(--cm-success)' : 'var(--cm-muted)' }}">
                                    {{ number_format($totalYearly, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="cm-empty">
                                    No members found. Pick a member type and group, or clear the filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->membersTotal > $this->perPage)
                <div class="cm-pager">
                    @php
                        $from = (($this->page - 1) * $this->perPage) + 1;
                        $to = min($this->membersTotal, $this->page * $this->perPage);
                    @endphp
                    <p>Showing {{ $from }}–{{ $to }} of {{ $this->membersTotal }} members</p>
                    <div class="cm-pager__btns">
                        <button type="button" class="cm-ghost" wire:click="previousPage" @disabled($this->page <= 1)>Previous</button>
                        <span class="cm-pager__page">Page {{ $this->page }} of {{ $this->lastPage() }}</span>
                        <button type="button" class="cm-ghost" wire:click="nextPage" @disabled($this->page >= $this->lastPage())>Next</button>
                    </div>
                </div>
            @endif

            @if($this->members->isNotEmpty())
                <div class="cm-savebar">
                    <p>Ticks stay selected when you change pages. They are not saved until you click the green button.</p>
                    <button
                        type="button"
                        class="cm-save"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                    >
                        <span wire:loading.remove wire:target="save">Save contributions</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
