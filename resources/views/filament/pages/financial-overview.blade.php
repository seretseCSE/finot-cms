<x-filament-panels::page>
    @push('styles')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap');

        .fin-root {
            font-family: 'DM Sans', sans-serif;
            --clr-income:   #10b981;
            --clr-expense:  #f43f5e;
            --clr-profit:   #6366f1;
            --clr-avail:    #f59e0b;
            --clr-bg:       #f8fafc;
            --clr-surface:  #ffffff;
            --clr-border:   #e2e8f0;
            --clr-text:     #0f172a;
            --clr-muted:    #64748b;
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0/.08), 0 1px 2px -1px rgb(0 0 0/.06);
            --shadow-md: 0 4px 20px -2px rgb(0 0 0/.10), 0 2px 8px -3px rgb(0 0 0/.08);
            --shadow-lg: 0 12px 40px -4px rgb(0 0 0/.14), 0 4px 16px -6px rgb(0 0 0/.10);
        }

        .dark .fin-root {
            --clr-bg:      #0b0f1a;
            --clr-surface: #111827;
            --clr-border:  #1e293b;
            --clr-text:    #f1f5f9;
            --clr-muted:   #94a3b8;
        }

        /* ── Filter bar ── */
        .fin-filters {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: 20px;
            padding: 24px 28px;
            box-shadow: var(--shadow-sm);
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .fin-filters label {
            display: block;
            font-family: 'Syne', sans-serif;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--clr-muted);
            margin-bottom: 8px;
        }
        .fin-filters select {
            border: 1.5px solid var(--clr-border);
            border-radius: 12px;
            background: var(--clr-bg);
            color: var(--clr-text);
            padding: 10px 36px 10px 14px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z' clip-rule='evenodd'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 18px;
            min-width: 200px;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }
        .fin-filters select:focus {
            border-color: var(--clr-profit);
            box-shadow: 0 0 0 3px rgb(99 102 241 / .15);
        }

        /* ── KPI Cards ── */
        .fin-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        @media (max-width: 1100px) { .fin-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px)  { .fin-kpi-grid { grid-template-columns: 1fr; } }

        .fin-kpi {
            position: relative;
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: 20px;
            padding: 28px 24px 24px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: transform .25s, box-shadow .25s;
        }
        .fin-kpi:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }

        .fin-kpi__stripe {
            position: absolute;
            inset: 0;
            border-radius: 20px;
            opacity: .07;
        }
        .fin-kpi__orb {
            position: absolute;
            right: -20px; top: -20px;
            width: 100px; height: 100px;
            border-radius: 50%;
            filter: blur(30px);
            opacity: .25;
        }
        .fin-kpi__label {
            font-family: 'Syne', sans-serif;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: var(--clr-muted);
            margin-bottom: 12px;
        }
        .fin-kpi__value {
            font-family: 'Syne', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: var(--clr-text);
            line-height: 1;
            margin-bottom: 4px;
        }
        .fin-kpi__currency {
            font-size: 13px;
            font-weight: 500;
            color: var(--clr-muted);
            margin-left: 4px;
        }
        .fin-kpi__icon {
            position: absolute;
            top: 24px; right: 24px;
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }
        .fin-kpi__icon svg { width: 22px; height: 22px; }

        /* card accent colours */
        .kpi--income  .fin-kpi__stripe { background: var(--clr-income); }
        .kpi--income  .fin-kpi__orb    { background: var(--clr-income); }
        .kpi--income  .fin-kpi__icon   { background: rgb(16 185 129/.12); color: var(--clr-income); }

        .kpi--expense .fin-kpi__stripe { background: var(--clr-expense); }
        .kpi--expense .fin-kpi__orb    { background: var(--clr-expense); }
        .kpi--expense .fin-kpi__icon   { background: rgb(244 63 94/.12); color: var(--clr-expense); }

        .kpi--profit  .fin-kpi__stripe { background: var(--clr-profit); }
        .kpi--profit  .fin-kpi__orb    { background: var(--clr-profit); }
        .kpi--profit  .fin-kpi__icon   { background: rgb(99 102 241/.12); color: var(--clr-profit); }
        .kpi--profit  .fin-kpi__value  { color: var(--clr-profit); }

        .kpi--avail   .fin-kpi__stripe { background: var(--clr-avail); }
        .kpi--avail   .fin-kpi__orb    { background: var(--clr-avail); }
        .kpi--avail   .fin-kpi__icon   { background: rgb(245 158 11/.12); color: var(--clr-avail); }

        /* ── Two-panel mid section ── */
        .fin-mid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 860px) { .fin-mid { grid-template-columns: 1fr; } }

        .fin-card {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }
        .fin-card__heading {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 20px;
        }
        .fin-card__heading-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .fin-card__heading-icon svg { width: 18px; height: 18px; }
        .fin-card__title {
            font-family: 'Syne', sans-serif;
            font-size: 15px; font-weight: 700;
            color: var(--clr-text);
        }

        .fin-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px;
            border-radius: 12px;
            background: var(--clr-bg);
            margin-bottom: 8px;
            transition: background .2s;
        }
        .fin-row:hover { background: color-mix(in srgb, var(--clr-bg), #000 4%); }
        .fin-row__dot {
            width: 8px; height: 8px; border-radius: 50%;
            margin-right: 10px; flex-shrink: 0;
        }
        .fin-row__label {
            font-size: 13.5px; font-weight: 500;
            color: var(--clr-text);
            display: flex; align-items: center;
        }
        .fin-row__value {
            font-family: 'Syne', sans-serif;
            font-size: 14px; font-weight: 700;
            color: var(--clr-text);
        }
        .fin-total-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px;
            border-radius: 12px;
            margin-top: 12px;
            border: 1.5px solid var(--clr-border);
        }
        .fin-total-row__label {
            font-family: 'Syne', sans-serif;
            font-size: 11px; font-weight: 700;
            letter-spacing: .08em; text-transform: uppercase;
            color: var(--clr-muted);
        }
        .fin-total-row__value {
            font-family: 'Syne', sans-serif;
            font-size: 18px; font-weight: 800;
        }

        .fin-bank-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px;
            border-radius: 12px;
            background: var(--clr-bg);
            margin-bottom: 8px;
            gap: 12px;
            transition: background .2s;
        }
        .fin-bank-item:hover { background: color-mix(in srgb, var(--clr-bg), #000 4%); }
        .fin-bank-avatar {
            width: 34px; height: 34px; border-radius: 10px;
            background: linear-gradient(135deg, #14b8a6, #0ea5e9);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            color: #fff;
        }
        .fin-bank-avatar svg { width: 16px; height: 16px; }
        .fin-bank-name { font-size: 13px; font-weight: 500; color: var(--clr-text); }
        .fin-bank-balance { font-family: 'Syne', sans-serif; font-size: 13.5px; font-weight: 700; color: var(--clr-text); white-space: nowrap; }

        /* ── Bottom section ── */
        .fin-bottom {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 1000px) { .fin-bottom { grid-template-columns: 1fr; } }

        /* ── Table ── */
        .fin-table-wrap { overflow: hidden; border-radius: 14px; border: 1px solid var(--clr-border); }
        .fin-table { width: 100%; border-collapse: collapse; }
        .fin-table thead { background: var(--clr-bg); }
        .fin-table thead th {
            padding: 14px 18px;
            font-family: 'Syne', sans-serif;
            font-size: 10.5px; font-weight: 700;
            letter-spacing: .08em; text-transform: uppercase;
            color: var(--clr-muted);
            text-align: left;
        }
        .fin-table tbody tr {
            border-top: 1px solid var(--clr-border);
            transition: background .15s;
        }
        .fin-table tbody tr:hover { background: var(--clr-bg); }
        .fin-table td { padding: 14px 18px; }

        .tx-avatar {
            width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .tx-avatar svg { width: 18px; height: 18px; }
        .tx-title { font-size: 13.5px; font-weight: 600; color: var(--clr-text); }
        .tx-id { font-size: 11px; font-family: monospace; color: var(--clr-muted); margin-top: 2px; }

        .badge {
            display: inline-flex; align-items: center;
            padding: 4px 10px; border-radius: 8px;
            font-size: 11px; font-weight: 700;
            letter-spacing: .04em; text-transform: uppercase;
        }
        .badge--income  { background: rgb(16 185 129/.12); color: #059669; }
        .badge--expense { background: rgb(244 63 94/.12);  color: #e11d48; }

        .tx-amount-income  { font-family:'Syne',sans-serif; font-size:14px; font-weight:700; color:var(--clr-income); }
        .tx-amount-expense { font-family:'Syne',sans-serif; font-size:14px; font-weight:700; color:var(--clr-text); }
        .tx-date { font-size: 12.5px; color: var(--clr-muted); }

        /* ── Top lists ── */
        .fin-rank-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--clr-border);
            margin-bottom: 8px;
            gap: 10px;
            transition: box-shadow .2s, border-color .2s;
            position: relative;
            overflow: hidden;
        }
        .fin-rank-item:hover { box-shadow: var(--shadow-md); }
        .fin-rank-item__bar {
            position: absolute; left: 0; top: 0; bottom: 0;
            width: 3px;
        }
        .fin-rank-badge {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif;
            font-size: 11px; font-weight: 800;
            flex-shrink: 0;
        }
        .fin-rank-title { font-size: 13px; font-weight: 600; color: var(--clr-text); }
        .fin-rank-cat   { font-size: 11px; color: var(--clr-muted); margin-top: 1px; }
        .fin-rank-amount {
            font-family: 'Syne', sans-serif;
            font-size: 13px; font-weight: 800;
            white-space: nowrap;
            padding: 4px 10px; border-radius: 8px;
        }

        /* ── Animations ── */
        @keyframes fadeUp {
            from { opacity:0; transform: translateY(18px); }
            to   { opacity:1; transform: translateY(0); }
        }
        .fin-root > * { animation: fadeUp .5s both; }
        .fin-root > *:nth-child(1) { animation-delay: .05s; }
        .fin-root > *:nth-child(2) { animation-delay: .15s; }
        .fin-root > *:nth-child(3) { animation-delay: .25s; }
        .fin-root > *:nth-child(4) { animation-delay: .35s; }

        .fin-empty {
            text-align: center; padding: 40px 20px;
            color: var(--clr-muted);
            font-size: 13.5px; font-weight: 500;
            border: 1.5px dashed var(--clr-border);
            border-radius: 14px;
        }
    </style>
    @endpush

    @php $financialData = $this->getFinancialData(); $isProfit = $financialData['net_profit'] >= 0; @endphp

    <div class="fin-root" style="display:flex;flex-direction:column;gap:24px;">

        {{-- ══ FILTERS ══ --}}
        <div class="fin-filters">
            <div>
                <label>Time Period</label>
                <select wire:model.live="selectedPeriod">
                    <option value="today">Today</option>
                    <option value="current_week">This Week</option>
                    <option value="current_month">This Month</option>
                    <option value="current_quarter">This Quarter</option>
                    <option value="current_year">This Year</option>
                </select>
            </div>
            <div>
                <label>Bank Account</label>
                <select wire:model.live="selectedBank">
                    <option value="all">All Banks</option>
                    @foreach($this->getBankAccounts() as $account)
                        <option value="{{ $account['id'] }}">{{ $account['full_name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- ══ KPI CARDS ══ --}}
        <div class="fin-kpi-grid">

            {{-- Income --}}
            <div class="fin-kpi kpi--income">
                <div class="fin-kpi__stripe"></div>
                <div class="fin-kpi__orb"></div>
                <div class="fin-kpi__label">Total Income</div>
                <div class="fin-kpi__value">
                    {{ number_format($financialData['total_income'], 2) }}
                    <span class="fin-kpi__currency">ETB</span>
                </div>
                <div class="fin-kpi__icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
                </div>
            </div>

            {{-- Expenses --}}
            <div class="fin-kpi kpi--expense">
                <div class="fin-kpi__stripe"></div>
                <div class="fin-kpi__orb"></div>
                <div class="fin-kpi__label">Total Expenses</div>
                <div class="fin-kpi__value">
                    {{ number_format($financialData['total_expenses'], 2) }}
                    <span class="fin-kpi__currency">ETB</span>
                </div>
                <div class="fin-kpi__icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.306-4.307a11.95 11.95 0 015.814 5.519l2.74 1.22m0 0l-5.94 2.28m5.94-2.28l-2.28-5.941"/></svg>
                </div>
            </div>

            {{-- Net Profit --}}
            <div class="fin-kpi kpi--profit">
                <div class="fin-kpi__stripe"></div>
                <div class="fin-kpi__orb"></div>
                <div class="fin-kpi__label">Net Profit</div>
                <div class="fin-kpi__value">
                    {{ number_format($financialData['net_profit'], 2) }}
                    <span class="fin-kpi__currency" style="color:var(--clr-profit);opacity:.7">ETB</span>
                </div>
                <div class="fin-kpi__icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971z"/></svg>
                </div>
            </div>

            {{-- Total Available --}}
            <div class="fin-kpi kpi--avail">
                <div class="fin-kpi__stripe"></div>
                <div class="fin-kpi__orb"></div>
                <div class="fin-kpi__label">Total Available</div>
                <div class="fin-kpi__value">
                    {{ number_format($financialData['total_available'], 2) }}
                    <span class="fin-kpi__currency">ETB</span>
                </div>
                <div class="fin-kpi__icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75"/></svg>
                </div>
            </div>
        </div>

        {{-- ══ MID: Additional Funds + Bank Balances ══ --}}
        <div class="fin-mid">

            {{-- Additional Funds --}}
            <div class="fin-card">
                <div class="fin-card__heading">
                    <div class="fin-card__heading-icon" style="background:rgb(99 102 241/.12);color:#6366f1;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                    </div>
                    <span class="fin-card__title">Additional Funds</span>
                </div>

                <div class="fin-row">
                    <span class="fin-row__label">
                        <span class="fin-row__dot" style="background:#6366f1;"></span>
                        Member Contributions
                    </span>
                    <span class="fin-row__value">{{ number_format($financialData['contributions'], 2) }} ETB</span>
                </div>
                <div class="fin-row">
                    <span class="fin-row__label">
                        <span class="fin-row__dot" style="background:#ec4899;"></span>
                        Donations
                    </span>
                    <span class="fin-row__value">{{ number_format($financialData['donations'], 2) }} ETB</span>
                </div>
                <div class="fin-total-row" style="background:linear-gradient(135deg,rgb(99 102 241/.08),rgb(236 72 153/.06));">
                    <span class="fin-total-row__label">Total</span>
                    <span class="fin-total-row__value" style="background:linear-gradient(90deg,#6366f1,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
                        {{ number_format($financialData['contributions'] + $financialData['donations'], 2) }} ETB
                    </span>
                </div>
            </div>

            {{-- Bank Balances --}}
            <div class="fin-card">
                <div class="fin-card__heading">
                    <div class="fin-card__heading-icon" style="background:rgb(20 184 166/.12);color:#14b8a6;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/></svg>
                    </div>
                    <span class="fin-card__title">Bank Balances</span>
                </div>

                @forelse($this->getBankAccounts() as $account)
                    <div class="fin-bank-item">
                        <div class="fin-bank-avatar">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="fin-bank-name">{{ $account['full_name'] }}</div>
                        </div>
                        <div class="fin-bank-balance">{{ $account['formatted_balance'] }}</div>
                    </div>
                @empty
                    <div class="fin-empty">No active bank accounts found.</div>
                @endforelse

                <div class="fin-total-row" style="background:linear-gradient(135deg,rgb(20 184 166/.1),rgb(14 165 233/.08));">
                    <span class="fin-total-row__label">Total Balance</span>
                    <span class="fin-total-row__value" style="color:#14b8a6;">
                        {{ number_format($financialData['bank_balances'], 2) }} ETB
                    </span>
                </div>
            </div>
        </div>

        {{-- ══ BOTTOM: Transactions + Rankings ══ --}}
        <div class="fin-bottom">

            {{-- Recent Transactions --}}
            <div class="fin-card" style="padding-bottom:8px;">
                <div class="fin-card__heading">
                    <div class="fin-card__heading-icon" style="background:rgb(59 130 246/.12);color:#3b82f6;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                    </div>
                    <span class="fin-card__title">Recent Transactions</span>
                </div>

                <div class="fin-table-wrap">
                    <table class="fin-table">
                        <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($this->getRecentTransactions() as $transaction)
                                @php $isIncome = $transaction['type']->value === 'income'; @endphp
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:12px;">
                                            <div class="tx-avatar" style="{{ $isIncome ? 'background:rgb(16 185 129/.12);color:#10b981' : 'background:rgb(244 63 94/.12);color:#f43f5e' }}">
                                                @if($isIncome)
                                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5l-15 15m0 0h11.25m-11.25 0V8.25"/></svg>
                                                @else
                                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
                                                @endif
                                            </div>
                                            <div>
                                                <div class="tx-title">{{ $transaction['title'] }}</div>
                                                <div class="tx-id">{{ $transaction['transaction_id'] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $isIncome ? 'badge--income' : 'badge--expense' }}">
                                            {{ $transaction['type']->getLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="{{ $isIncome ? 'tx-amount-income' : 'tx-amount-expense' }}">
                                            {{ $transaction['amount'] }}
                                        </span>
                                    </td>
                                    <td><span class="tx-date">{{ $transaction['date'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="fin-empty" style="border:none;padding:32px 20px;">
                                            No recent transactions found.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Top Income & Expenses --}}
            <div style="display:flex;flex-direction:column;gap:20px;">

                {{-- Top Income --}}
                <div class="fin-card">
                    <div class="fin-card__heading">
                        <div class="fin-card__heading-icon" style="background:rgb(16 185 129/.12);color:#10b981;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0"/></svg>
                        </div>
                        <span class="fin-card__title">Top Income</span>
                    </div>

                    @forelse($this->getTopIncome() as $index => $income)
                        <div class="fin-rank-item">
                            <div class="fin-rank-item__bar" style="background:var(--clr-income);"></div>
                            <div class="fin-rank-badge" style="background:rgb(16 185 129/.12);color:#10b981;">
                                #{{ $index + 1 }}
                            </div>
                            <div style="flex:1;min-width:0;padding-left:4px;">
                                <div class="fin-rank-title">{{ $income['title'] }}</div>
                                <div class="fin-rank-cat">{{ $income['category'] }}</div>
                            </div>
                            <div class="fin-rank-amount" style="background:rgb(16 185 129/.1);color:#10b981;">{{ $income['amount'] }}</div>
                        </div>
                    @empty
                        <div class="fin-empty">No income entries found.</div>
                    @endforelse
                </div>

                {{-- Top Expenses --}}
                <div class="fin-card">
                    <div class="fin-card__heading">
                        <div class="fin-card__heading-icon" style="background:rgb(244 63 94/.12);color:#f43f5e;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z"/></svg>
                        </div>
                        <span class="fin-card__title">Top Expenses</span>
                    </div>

                    @forelse($this->getTopExpenses() as $index => $expense)
                        <div class="fin-rank-item">
                            <div class="fin-rank-item__bar" style="background:var(--clr-expense);"></div>
                            <div class="fin-rank-badge" style="background:rgb(244 63 94/.12);color:#f43f5e;">
                                #{{ $index + 1 }}
                            </div>
                            <div style="flex:1;min-width:0;padding-left:4px;">
                                <div class="fin-rank-title">{{ $expense['title'] }}</div>
                                <div class="fin-rank-cat">{{ $expense['category'] }}</div>
                            </div>
                            <div class="fin-rank-amount" style="background:rgb(244 63 94/.1);color:#f43f5e;">{{ $expense['amount'] }}</div>
                        </div>
                    @empty
                        <div class="fin-empty">No expense entries found.</div>
                    @endforelse
                </div>

            </div>
        </div>
    </div>
</x-filament-panels::page>
