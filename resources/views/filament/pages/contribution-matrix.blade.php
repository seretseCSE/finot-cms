<x-filament-panels::page>
    <div style="width:100%;max-width:100%">

        {{-- ── Filters Card ─────────────────────────────────────────────── --}}
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:16px">

            {{-- Header --}}
            <div style="padding:12px 20px;border-bottom:1px solid #f3f4f6;background:#f9fafb;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:10px">
                    <svg width="18" height="18" style="width:18px;height:18px;flex-shrink:0;color:#2563eb" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h18M3 6h18M3 14h18M3 18h18"/>
                    </svg>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:#111827;line-height:1.3">Contribution Matrix</div>
                        <div style="font-size:11px;color:#6b7280;line-height:1.3">Manage monthly member contributions efficiently</div>
                    </div>
                </div>
                <button wire:click="refreshData"
                        style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:12px;font-weight:500;color:#374151;background:#fff;border:1px solid #d1d5db;border-radius:8px;cursor:pointer">
                    <svg width="13" height="13" style="width:13px;height:13px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh Data
                </button>
            </div>

            {{-- Filters row --}}
            <div style="padding:16px 20px;display:flex;gap:12px;flex-wrap:wrap">

                <div style="flex:1;min-width:160px">
                    <label style="display:block;font-size:11px;font-weight:500;color:#4b5563;margin-bottom:4px">Academic Year</label>
                    <select wire:model.live="academicYear"
                            style="width:100%;font-size:12px;border:1px solid #d1d5db;border-radius:8px;padding:6px 10px;background:#fff;color:#111827">
                        <option value="">Select Academic Year</option>
                        @foreach($this->getFilterOptions()['academic_years'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="flex:1;min-width:140px">
                    <label style="display:block;font-size:11px;font-weight:500;color:#4b5563;margin-bottom:4px">Department</label>
                    <select wire:model.live="department"
                            style="width:100%;font-size:12px;border:1px solid #d1d5db;border-radius:8px;padding:6px 10px;background:#fff;color:#111827">
                        <option value="">All Departments</option>
                        @foreach($this->getFilterOptions()['departments'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="flex:1;min-width:120px">
                    <label style="display:block;font-size:11px;font-weight:500;color:#4b5563;margin-bottom:4px">Member Type</label>
                    <select wire:model.live="type"
                            style="width:100%;font-size:12px;border:1px solid #d1d5db;border-radius:8px;padding:6px 10px;background:#fff;color:#111827">
                        <option value="">All Types</option>
                        @foreach($this->getFilterOptions()['types'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="flex:1;min-width:120px">
                    <label style="display:block;font-size:11px;font-weight:500;color:#4b5563;margin-bottom:4px">Status</label>
                    <select wire:model.live="status"
                            style="width:100%;font-size:12px;border:1px solid #d1d5db;border-radius:8px;padding:6px 10px;background:#fff;color:#111827">
                        <option value="">All Statuses</option>
                        @foreach($this->getFilterOptions()['statuses'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

            </div>
        </div>

        {{-- ── Matrix Table ──────────────────────────────────────────────── --}}
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
            <div style="overflow-x:auto;-webkit-overflow-scrolling:touch">
                <table style="width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed;min-width:900px">

                    {{-- Head --}}
                    <thead>
                        <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                            <th style="width:180px;padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;position:sticky;left:0;background:#f9fafb;z-index:2">
                                Member Name
                            </th>
                            @foreach(range(1, 12) as $m)
                                <th style="width:58px;padding:10px 4px;text-align:center;font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.03em">
                                    {{ substr($this->months[$m], 0, 3) }}
                                </th>
                            @endforeach
                            <th style="width:80px;padding:10px 10px;text-align:right;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em">
                                Total
                            </th>
                        </tr>
                    </thead>

                    {{-- Body --}}
                    <tbody>
                        @forelse($this->members as $index => $member)
                            @php
                                $totalYearly = 0;
                                foreach(range(1, 12) as $m) {
                                    if ($this->grid[$member->id][$m] ?? false) {
                                        $totalYearly += $this->getMemberGroupAmount($member, $m);
                                    }
                                }
                                $rowBg = $index % 2 === 0 ? '#ffffff' : '#fafafa';
                            @endphp

                            <tr style="background:{{ $rowBg }};border-bottom:1px solid #f3f4f6"
                                x-data="{}"
                                onmouseenter="this.style.background='#eff6ff'"
                                onmouseleave="this.style.background='{{ $rowBg }}'">

                                {{-- Member name — sticky --}}
                                <td style="padding:8px 14px;font-size:12px;font-weight:500;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;position:sticky;left:0;background:inherit;z-index:1;border-right:1px solid #f3f4f6">
                                    {{ $member->first_name }} {{ $member->father_name }}
                                </td>

                                {{-- Month checkboxes --}}
                                @foreach(range(1, 12) as $m)
                                    <td style="padding:8px 4px;text-align:center">
                                        <input
                                            type="checkbox"
                                            wire:change="toggle({{ $member->id }}, {{ $m }})"
                                            {{ ($this->grid[$member->id][$m] ?? false) ? 'checked' : '' }}
                                            style="width:15px;height:15px;cursor:pointer;accent-color:#2563eb"
                                        >
                                    </td>
                                @endforeach

                                {{-- Total --}}
                                <td style="padding:8px 10px;text-align:right;font-size:12px;font-weight:600;color:{{ $totalYearly > 0 ? '#15803d' : '#9ca3af' }}">
                                    {{ number_format($totalYearly, 2) }}
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="14" style="padding:48px;text-align:center;color:#9ca3af;font-size:13px">
                                    No members found. Adjust filters to see contributions.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>

    </div>

    {{-- ── Autosave scroll-lock script ─────────────────────────────────── --}}
    <script>
        // Prevent Livewire full re-renders from scrolling the page
        document.addEventListener('livewire:navigating', () => {
            window.__cmScrollY = window.scrollY;
        });

        document.addEventListener('livewire:navigated', () => {
            if (window.__cmScrollY !== undefined) {
                window.scrollTo(0, window.__cmScrollY);
            }
        });

        // Lock scroll position on every Livewire update (catches wire:click updates)
        document.addEventListener('livewire:request', () => {
            window.__cmScrollY = window.scrollY;
        });

        document.addEventListener('livewire:commit', () => {
            if (window.__cmScrollY !== undefined) {
                requestAnimationFrame(() => window.scrollTo(0, window.__cmScrollY));
            }
        });
    </script>
</x-filament-panels::page>

