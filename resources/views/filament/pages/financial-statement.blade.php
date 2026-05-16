<x-filament-panels::page>
    @push('styles')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');

        .fs-btn--primary { overflow: hidden; contain: layout; }

        html { overflow-anchor: none; }

        .fs-root {
            font-family: 'DM Sans', sans-serif;
            --clr-primary:  #2563eb;
            --clr-success:  #10b981;
            --clr-danger:   #f43f5e;
            --clr-warning:  #f59e0b;
            --clr-purple:   #8b5cf6;
            --clr-bg:       #f8fafc;
            --clr-surface:  #ffffff;
            --clr-border:   #e2e8f0;
            --clr-text:     #0f172a;
            --clr-muted:    #64748b;
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0/.08), 0 1px 2px -1px rgb(0 0 0/.06);
            --shadow-md: 0 4px 20px -2px rgb(0 0 0/.10), 0 2px 8px -3px rgb(0 0 0/.08);
            --shadow-lg: 0 12px 40px -4px rgb(0 0 0/.14), 0 4px 16px -6px rgb(0 0 0/.10);
        }
        .dark .fs-root {
            --clr-bg:      #0b0f1a;
            --clr-surface: #111827;
            --clr-border:  #1e293b;
            --clr-text:    #f1f5f9;
            --clr-muted:   #94a3b8;
        }

        /* ── Card shell ── */
        .fs-card {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .fs-card__header {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 24px;
            border-bottom: 1px solid var(--clr-border);
            background: var(--clr-bg);
        }
        .fs-card__header-icon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            background: rgb(37 99 235/.12); color: var(--clr-primary);
            flex-shrink: 0;
        }
        .fs-card__header-icon svg { width: 18px; height: 18px; }
        .fs-card__title {
            font-family: 'Syne', sans-serif;
            font-size: 14px; font-weight: 700;
            color: var(--clr-text); line-height: 1.2;
        }
        .fs-card__subtitle {
            font-size: 12px; color: var(--clr-muted); margin-top: 1px;
        }
        .fs-card__body { padding: 24px; }

        /* ── Filters ── */
        .fs-filters {
            display: flex; gap: 16px; flex-wrap: wrap;
        }
        .fs-field { flex: 1; min-width: 150px; }
        .fs-label {
            display: block;
            font-family: 'Syne', sans-serif;
            font-size: 10.5px; font-weight: 700;
            letter-spacing: .08em; text-transform: uppercase;
            color: var(--clr-muted); margin-bottom: 7px;
        }
        .fs-select {
            width: 100%;
            font-family: 'DM Sans', sans-serif;
            font-size: 13.5px; font-weight: 500;
            color: var(--clr-text);
            background: var(--clr-bg);
            border: 1.5px solid var(--clr-border);
            border-radius: 12px;
            padding: 10px 36px 10px 14px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z' clip-rule='evenodd'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 18px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            cursor: pointer;
            box-sizing: border-box;
        }
        .fs-select:focus {
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 3px rgb(37 99 235/.15);
        }
        .fs-static {
            width: 100%; box-sizing: border-box;
            font-size: 13.5px; color: var(--clr-muted);
            background: var(--clr-bg);
            border: 1.5px solid var(--clr-border);
            border-radius: 12px;
            padding: 10px 14px;
        }

        /* ── Divider ── */
        .fs-divider {
            border: none; border-top: 1px solid var(--clr-border);
            margin: 20px 0;
        }

        /* ── Action row ── */
        .fs-actions {
            display: flex; align-items: center; justify-content: flex-end; gap: 10px;
        }

        .fs-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 18px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13.5px; font-weight: 600;
            border-radius: 12px; cursor: pointer;
            transition: all .2s; outline: none; border: none;
            white-space: nowrap;
        }
        .fs-btn svg { width: 15px; height: 15px; flex-shrink: 0; }

        .fs-btn--ghost {
            background: var(--clr-bg);
            color: var(--clr-muted);
            border: 1.5px solid var(--clr-border);
        }
        .fs-btn--ghost:hover { background: var(--clr-border); color: var(--clr-text); }

        .fs-btn--primary {
            background: var(--clr-primary);
            color: #fff;
            box-shadow: 0 4px 14px rgb(37 99 235/.35);
            min-width: 175px; justify-content: center;
        }
        .fs-btn--primary:hover { background: #1d4ed8; box-shadow: 0 6px 18px rgb(37 99 235/.45); transform: translateY(-1px); }
        .fs-btn--primary:disabled { opacity: .65; cursor: not-allowed; transform: none; }

        /* ── Banners ── */
        .fs-banner {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 12px; border-radius: 14px; padding: 14px 18px;
            font-size: 13.5px; margin-bottom: 16px;
            animation: fadeUp .35s both;
        }
        .fs-banner__inner { display: flex; align-items: flex-start; gap: 10px; }
        .fs-banner__icon { flex-shrink: 0; margin-top: 1px; }
        .fs-banner__icon svg { width: 16px; height: 16px; }
        .fs-banner__close {
            background: none; border: none; cursor: pointer;
            padding: 0; display: flex; flex-shrink: 0; opacity: .6;
            transition: opacity .15s;
        }
        .fs-banner__close:hover { opacity: 1; }
        .fs-banner__close svg { width: 14px; height: 14px; }

        .fs-banner--success {
            background: rgb(16 185 129/.08);
            border: 1px solid rgb(16 185 129/.3);
            color: #065f46;
        }
        .fs-banner--error {
            background: rgb(244 63 94/.08);
            border: 1px solid rgb(244 63 94/.3);
            color: #9f1239;
        }
        .fs-banner--error .fs-banner__title { font-weight: 700; margin-bottom: 2px; }
        .fs-banner--error .fs-banner__detail { font-size: 12.5px; opacity: .85; }

        /* ── Info note ── */
        .fs-note {
            display: flex; align-items: flex-start; gap: 10px;
            background: var(--clr-bg);
            border: 1px solid var(--clr-border);
            border-radius: 12px; padding: 12px 16px;
            font-size: 12.5px; color: var(--clr-muted);
        }
        .fs-note svg { width: 15px; height: 15px; flex-shrink: 0; margin-top: 1px; color: var(--clr-primary); }

        /* ── Spin ── */
        @keyframes spin { to { transform: rotate(360deg); } }
        .fs-spin { animation: spin .8s linear infinite; }

        /* ── Page animation ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fs-root > * { animation: fadeUp .45s both; }
        .fs-root > *:nth-child(1) { animation-delay: .05s; }
        .fs-root > *:nth-child(2) { animation-delay: .12s; }
        .fs-root > *:nth-child(3) { animation-delay: .19s; }
        .fs-root > *:nth-child(4) { animation-delay: .26s; }

        /* ── Period badge ── */
        .fs-period-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 999px;
            background: rgb(37 99 235/.1); color: var(--clr-primary);
            font-family: 'Syne', sans-serif;
            font-size: 11px; font-weight: 700;
            letter-spacing: .05em; text-transform: uppercase;
            border: 1px solid rgb(37 99 235/.2);
        }

        /* ── Spin dot for loading ── */
        .fs-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: currentColor; display: inline-block;
            animation: blink 1.2s ease-in-out infinite;
        }
        .fs-dot:nth-child(2) { animation-delay: .2s; }
        .fs-dot:nth-child(3) { animation-delay: .4s; }
        @keyframes blink { 0%,80%,100% { opacity:.2; } 40% { opacity:1; } }
    </style>
    @endpush

    <div class="fs-root" style="max-width:100%;width:100%">

        {{-- ══ Success Banner ══ --}}
        @if(session()->has('message'))
            <div class="fs-banner fs-banner--success"
                 x-data="{ show: true }" x-show="show"
                 x-init="setTimeout(() => show = false, 5000)">
                <div class="fs-banner__inner">
                    <span class="fs-banner__icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <span>{{ session('message') }}</span>
                </div>
                <button class="fs-banner__close" @click="show = false">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- ══ Error Banner ══ --}}
        @if($errors->has('generation_error'))
            <div class="fs-banner fs-banner--error"
                 x-data="{ show: true }" x-show="show">
                <div class="fs-banner__inner">
                    <span class="fs-banner__icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </span>
                    <div>
                        <div class="fs-banner__title">Generation failed</div>
                        <div class="fs-banner__detail">{{ $errors->first('generation_error') }}</div>
                    </div>
                </div>
                <button class="fs-banner__close" @click="show = false">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- ══ Main Form Card ══ --}}
        <div class="fs-card">
            <div class="fs-card__header">
                <div class="fs-card__header-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="fs-card__title">Financial Statement Generation</div>
                    <div class="fs-card__subtitle">Generate PDF reports for contributions and donations</div>
                </div>
            </div>

            <div class="fs-card__body">

                {{-- ── Filter Row ── --}}
                <div class="fs-filters">

                    {{-- Period Type --}}
                    <div class="fs-field">
                        <label class="fs-label">Period Type</label>
                        <select wire:model.live="periodType" class="fs-select">
                            <option value="" disabled>Select period type</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>

                    {{-- Year --}}
                    <div class="fs-field" style="min-width:130px;max-width:200px;">
                        <label class="fs-label">Year</label>
                        <select wire:model.live="selectedYear" class="fs-select">
                            <option value="" disabled>Select year</option>
                            @foreach(\App\Models\AcademicYear::query()->orderByDesc('start_date')->get() as $academicYear)
                                <option value="{{ $academicYear->start_date->year }}">
                                    {{ $academicYear->name }} ({{ $academicYear->start_date->year }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Dynamic: Month / Quarter / Static --}}
                    <div class="fs-field">
                        @if($periodType === 'monthly')
                            <label class="fs-label">Month</label>
                            <select wire:model.live="selectedMonth" class="fs-select">
                                <option value="" disabled>Select month</option>
                                @foreach(\App\Helpers\EthiopianDateHelper::getMonthsForContribution() as $key => $month)
                                    <option value="{{ $key }}">{{ $month }}</option>
                                @endforeach
                            </select>

                        @elseif($periodType === 'quarterly')
                            <label class="fs-label">Quarter</label>
                            <select wire:model.live="selectedQuarter" class="fs-select">
                                <option value="" disabled>Select quarter</option>
                                <option value="1">Q1 — Jan to Mar</option>
                                <option value="2">Q2 — Apr to Jun</option>
                                <option value="3">Q3 — Jul to Sep</option>
                                <option value="4">Q4 — Oct to Dec</option>
                            </select>

                        @else
                            <label class="fs-label">Period</label>
                            <div class="fs-static">
                                {{ $periodType === 'annual' ? 'Full year' : '—' }}
                            </div>
                        @endif
                    </div>

                </div>

                {{-- ── Period badge (shows what period is selected) ── --}}
                @if($periodType)
                    <div style="margin-top:16px;">
                        <span class="fs-period-badge">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                            </svg>
                            @if($periodType === 'monthly') Monthly Report
                            @elseif($periodType === 'quarterly') Quarterly Report
                            @elseif($periodType === 'annual') Annual Report
                            @else Select a period
                            @endif
                        </span>
                    </div>
                @endif

                <hr class="fs-divider">

                {{-- ── Actions ── --}}
                <div class="fs-actions">

                    <button type="button"
                            wire:click="resetForm"
                            class="fs-btn fs-btn--ghost">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                        Reset
                    </button>

                <button type="button"
                        wire:click="generateStatement"
                        wire:loading.attr="disabled"
                        wire:target="generateStatement"
                        class="fs-btn fs-btn--primary"
                        style="min-height:42px; min-width:200px; overflow:hidden; contain:layout;"
                        x-data="{ loading: false }"
                        x-on:click="loading = true"
                        wire:loading.remove.class="loading-active"
                        @finish.window="loading = false">

                    <span x-show="!loading" style="display:inline-flex; align-items:center; gap:7px;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        Generate Statement
                    </span>

                    <span x-show="loading" x-cloak style="display:inline-flex; align-items:center; gap:8px;">
                        <svg class="fs-spin" fill="none" viewBox="0 0 24 24" style="width:15px;height:15px;">
                            <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path style="opacity:.8" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        Generating…
                    </span>
                </button>

                </div>
            </div>
        </div>

        {{-- ══ Info Note ══ --}}
        <div class="fs-note">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
            </svg>
            <span>
                Reports include member contributions, donations, and outstanding dues — exported as a single A4 PDF.
                The PDF will download automatically once generation is complete.
            </span>
        </div>

    </div>

    {{-- ══ PDF Download Script ══ --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('download-pdf', (payload) => {
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
