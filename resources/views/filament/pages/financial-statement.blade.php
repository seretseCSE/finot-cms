<x-filament-panels::page>
    <div style="max-width:100%;width:100%">

        {{-- ── Form Card ──────────────────────────────────────────────────── --}}
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:16px">

            {{-- Card Header --}}
            <div style="padding:12px 20px;border-bottom:1px solid #f3f4f6;background:#f9fafb;display:flex;align-items:center;gap:10px">
                <svg width="18" height="18" style="width:18px;height:18px;flex-shrink:0;color:#2563eb" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#111827;line-height:1.3">Financial Statement Generation</div>
                    <div style="font-size:11px;color:#6b7280;line-height:1.3">Generate PDF reports for contributions and donations</div>
                </div>
            </div>

            {{-- Card Body --}}
            <div style="padding:20px">

                {{-- Filters row --}}
                <div style="display:flex;gap:12px;flex-wrap:wrap">

                    {{-- Period Type --}}
                    <div style="flex:1;min-width:140px">
                        <label style="display:block;font-size:11px;font-weight:500;color:#4b5563;margin-bottom:4px">Period Type</label>
                        <select wire:model.live="periodType"
                                style="width:100%;font-size:13px;border:1px solid #d1d5db;border-radius:8px;padding:7px 10px;background:#fff;color:#111827">
                            <option value="" disabled selected>Select period type</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>

                    {{-- Year --}}
                    <div style="flex:1;min-width:100px">
                        <label style="display:block;font-size:11px;font-weight:500;color:#4b5563;margin-bottom:4px">Year</label>
                        <select wire:model.live="selectedYear"
                                style="width:100%;font-size:13px;border:1px solid #d1d5db;border-radius:8px;padding:7px 10px;background:#fff;color:#111827">
                            <option value="" disabled selected>Select year</option>
                            @foreach(\App\Models\AcademicYear::query()->orderByDesc('start_date')->get() as $academicYear)
                                <option value="{{ $academicYear->start_date->year }}">{{ $academicYear->name }} ({{ $academicYear->start_date->year }})</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Month / Quarter / Static --}}
                    <div style="flex:1;min-width:140px">
                        @if($periodType === 'monthly')
                            <label style="display:block;font-size:11px;font-weight:500;color:#4b5563;margin-bottom:4px">Month</label>
                            <select wire:model.live="selectedMonth"
                                    style="width:100%;font-size:13px;border:1px solid #d1d5db;border-radius:8px;padding:7px 10px;background:#fff;color:#111827">
                                <option value="" disabled selected>Select month</option>
                                @foreach(\App\Helpers\EthiopianDateHelper::getMonthsForContribution() as $key => $month)
                                    <option value="{{ $key }}">{{ $month }}</option>
                                @endforeach
                            </select>

                        @elseif($periodType === 'quarterly')
                            <label style="display:block;font-size:11px;font-weight:500;color:#4b5563;margin-bottom:4px">Quarter</label>
                            <select wire:model.live="selectedQuarter"
                                    style="width:100%;font-size:13px;border:1px solid #d1d5db;border-radius:8px;padding:7px 10px;background:#fff;color:#111827">
                                <option value="" disabled selected>Select quarter</option>
                                <option value="1">Q1 (Jan – Mar)</option>
                                <option value="2">Q2 (Apr – Jun)</option>
                                <option value="3">Q3 (Jul – Sep)</option>
                                <option value="4">Q4 (Oct – Dec)</option>
                            </select>

                        @else
                            <label style="display:block;font-size:11px;font-weight:500;color:#4b5563;margin-bottom:4px">Period</label>
                            <div style="width:100%;font-size:13px;border:1px solid #e5e7eb;border-radius:8px;padding:7px 10px;background:#f9fafb;color:#9ca3af;box-sizing:border-box">
                                {{ $periodType === 'annual' ? 'Full year' : '—' }}
                            </div>
                        @endif
                    </div>

                </div>

                {{-- Action Buttons --}}
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;gap:8px"
                     x-data="{ loading: false }"
                     @generating.window="loading = true"
                     @generated.window="loading = false">

                    <button type="button"
                            wire:click="resetForm"
                            style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;font-size:13px;font-weight:500;color:#374151;background:#fff;border:1px solid #d1d5db;border-radius:8px;cursor:pointer">
                        <svg width="14" height="14" style="width:14px;height:14px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Reset
                    </button>

                    {{-- Generate button — Alpine controls the loading state, not wire:loading --}}
                    <button type="button"
                            wire:click="generateStatement"
                            x-bind:disabled="loading"
                            x-on:click="loading = true"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-75"
                            wire:target="generateStatement"
                            style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;font-size:13px;font-weight:500;color:#fff;background:#2563eb;border:none;border-radius:8px;cursor:pointer;min-width:160px;justify-content:center">

                        {{-- Idle state --}}
                        <span x-show="!loading" style="display:inline-flex;align-items:center;gap:6px">
                            <svg width="14" height="14" style="width:14px;height:14px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Generate Statement
                        </span>

                        {{-- Loading state --}}
                        <span x-show="loading" style="display:inline-flex;align-items:center;gap:6px">
                            <svg width="14" height="14" style="width:14px;height:14px;flex-shrink:0" class="animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor"
                                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            Generating…
                        </span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Success Banner ────────────────────────────────────────────── --}}
        @if(session()->has('message'))
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 16px;margin-bottom:12px;font-size:13px"
                 x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                <div style="display:flex;align-items:center;gap:8px;color:#166534">
                    <svg width="14" height="14" style="width:14px;height:14px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ session('message') }}
                </div>
                <button @click="show = false" style="background:none;border:none;cursor:pointer;color:#16a34a;padding:0;display:flex">
                    <svg width="14" height="14" style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- ── Error Banner ──────────────────────────────────────────────── --}}
        @if($errors->has('generation_error'))
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 16px;margin-bottom:12px;font-size:13px"
                 x-data="{ show: true }" x-show="show">
                <div style="display:flex;align-items:flex-start;gap:8px;color:#991b1b">
                    <svg width="14" height="14" style="width:14px;height:14px;flex-shrink:0;margin-top:1px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <div>
                        <div style="font-weight:600">Generation failed</div>
                        <div style="color:#dc2626;margin-top:2px">{{ $errors->first('generation_error') }}</div>
                    </div>
                </div>
                <button @click="show = false" style="background:none;border:none;cursor:pointer;color:#f87171;padding:0;display:flex;flex-shrink:0">
                    <svg width="14" height="14" style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- ── Info Note ─────────────────────────────────────────────────── --}}
        <div style="display:flex;align-items:flex-start;gap:8px;font-size:11px;color:#6b7280;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 16px">
            <svg width="14" height="14" style="width:14px;height:14px;flex-shrink:0;margin-top:1px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Reports include contributions, donations, and outstanding dues, exported as an A4 PDF.
        </div>

    </div>

    {{-- ── PDF Download Script ──────────────────────────────────────────── --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('download-pdf', (payload) => {
                // Reset the Alpine loading state on the button
                document.dispatchEvent(new CustomEvent('generated'));

                const { content, filename } = payload[0];
                const bytes = atob(content);
                const buf   = new Uint8Array(bytes.length);
                for (let i = 0; i < bytes.length; i++) buf[i] = bytes.charCodeAt(i);
                const blob = new Blob([buf], { type: 'application/pdf' });
                const url  = URL.createObjectURL(blob);
                const a    = Object.assign(document.createElement('a'), { href: url, download: filename });
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            });
        });
    </script>
</x-filament-panels::page>