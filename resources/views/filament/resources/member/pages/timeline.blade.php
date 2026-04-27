<x-filament-panels::page>
    <style>
        /* ── Animations ── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Header card ── */
        .tl-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #4f46e5 100%);
            border-radius: 16px;
            padding: 32px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px -8px rgba(79,70,229,.35);
        }
        .tl-header::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: rgba(255,255,255,.08);
        }
        .tl-header::after {
            content: '';
            position: absolute;
            bottom: -30px; left: -30px;
            width: 120px; height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
        }
        .tl-header-inner {
            position: relative; z-index: 1;
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px;
        }
        .tl-avatar {
            width: 72px; height: 72px; border-radius: 16px;
            background: rgba(255,255,255,.18); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; font-weight: 800; color: #fff;
            border: 2px solid rgba(255,255,255,.25); flex-shrink: 0;
        }
        .tl-name { font-size: 26px; font-weight: 800; letter-spacing: -.3px; margin: 0; }
        .tl-meta { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px; align-items: center; }
        .tl-meta span { font-size: 13px; color: rgba(255,255,255,.8); display: flex; align-items: center; gap: 4px; }
        .tl-badge {
            background: rgba(255,255,255,.14); backdrop-filter: blur(4px);
            padding: 2px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .tl-since-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1.2px; color: rgba(255,255,255,.6); margin-bottom: 2px; }
        .tl-since-val   { font-size: 20px; font-weight: 700; }
        .tl-status-pill  {
            margin-top: 6px; display: inline-block;
            padding: 3px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            background: rgba(52,211,153,.2); color: #a7f3d0; border: 1px solid rgba(52,211,153,.3);
        }
        .tl-profile-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .tl-profile-tag {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
            background: rgba(255,255,255,.12); color: rgba(255,255,255,.9);
            border: 1px solid rgba(255,255,255,.15);
        }
        .tl-profile-tag svg { width: 14px; height: 14px; }

        /* ── Filter bar ── */
        .tl-filters {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
            padding: 16px 20px; border-radius: 14px;
            background: #fff; border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .tl-filter-label { font-size: 13px; font-weight: 700; color: #374151; white-space: nowrap; }
        .tl-filter-select {
            padding: 7px 32px 7px 12px; border-radius: 10px;
            border: 1px solid #d1d5db; background: #f9fafb;
            font-size: 13px; color: #374151; font-weight: 500;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center;
            transition: border-color .2s, box-shadow .2s;
            cursor: pointer; min-width: 140px;
        }
        .tl-filter-select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }

        /* ── Stat cards ── */
        .tl-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
        .tl-stat {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
            padding: 18px; position: relative; overflow: hidden;
            transition: transform .25s ease, box-shadow .25s ease;
        }
        .tl-stat:hover { transform: translateY(-3px); box-shadow: 0 10px 24px -6px rgba(0,0,0,.1); }
        .tl-stat-bar { position: absolute; top: 0; left: 0; right: 0; height: 4px; border-radius: 14px 14px 0 0; }
        .tl-stat-inner { display: flex; align-items: center; gap: 14px; }
        .tl-stat-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; transition: transform .25s ease;
        }
        .tl-stat:hover .tl-stat-icon { transform: scale(1.1); }
        .tl-stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: #6b7280; font-weight: 600; }
        .tl-stat-value { font-size: 22px; font-weight: 800; color: #111827; margin-top: 2px; }
        .tl-stat-value small { font-size: 12px; font-weight: 500; color: #9ca3af; }

        .tl-stat.green  .tl-stat-bar  { background: linear-gradient(90deg, #34d399, #10b981); }
        .tl-stat.green  .tl-stat-icon { background: #ecfdf5; }
        .tl-stat.green  .tl-stat-icon svg { color: #059669; }
        .tl-stat.violet .tl-stat-bar  { background: linear-gradient(90deg, #a78bfa, #8b5cf6); }
        .tl-stat.violet .tl-stat-icon { background: #f5f3ff; }
        .tl-stat.violet .tl-stat-icon svg { color: #7c3aed; }
        .tl-stat.sky    .tl-stat-bar  { background: linear-gradient(90deg, #38bdf8, #0ea5e9); }
        .tl-stat.sky    .tl-stat-icon { background: #f0f9ff; }
        .tl-stat.sky    .tl-stat-icon svg { color: #0284c7; }
        .tl-stat.amber  .tl-stat-bar  { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
        .tl-stat.amber  .tl-stat-icon { background: #fffbeb; }
        .tl-stat.amber  .tl-stat-icon svg { color: #d97706; }
        .tl-stat.rose   .tl-stat-bar  { background: linear-gradient(90deg, #fb7185, #e11d48); }
        .tl-stat.rose   .tl-stat-icon { background: #fff1f2; }
        .tl-stat.rose   .tl-stat-icon svg { color: #be123c; }
        .tl-stat.indigo .tl-stat-bar  { background: linear-gradient(90deg, #818cf8, #6366f1); }
        .tl-stat.indigo .tl-stat-icon { background: #eef2ff; }
        .tl-stat.indigo .tl-stat-icon svg { color: #4338ca; }

        /* ── Timeline section ── */
        .tl-section {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 16px;
            padding: 28px 32px; box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .tl-section-head { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .tl-section-icon {
            width: 42px; height: 42px; border-radius: 12px; background: #eef2ff;
            display: flex; align-items: center; justify-content: center;
        }
        .tl-section-icon svg { color: #4f46e5; }
        .tl-section-title { font-size: 18px; font-weight: 700; color: #111827; margin: 0; }
        .tl-section-sub { font-size: 13px; color: #6b7280; margin: 2px 0 0; }

        /* ── Empty state ── */
        .tl-empty { text-align: center; padding: 56px 20px; }
        .tl-empty-icon {
            width: 72px; height: 72px; border-radius: 50%; background: #f3f4f6;
            display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px;
        }
        .tl-empty-icon svg { color: #d1d5db; }
        .tl-empty p { color: #6b7280; font-size: 14px; margin: 0; }
        .tl-empty p + p { color: #9ca3af; font-size: 12px; margin-top: 4px; }

        /* ── Timeline rail ── */
        .tl-rail { position: relative; }
        .tl-rail::before {
            content: ''; position: absolute; top: 0; bottom: 0; left: 15px; width: 2px;
            background: linear-gradient(180deg, #818cf8 0%, #c4b5fd 40%, #e5e7eb 100%); border-radius: 2px;
        }

        /* ── Event card ── */
        .tl-event {
            position: relative; display: flex; gap: 20px; padding-left: 4px;
            animation: fadeInUp .45s ease-out both;
        }
        .tl-event + .tl-event { margin-top: 18px; }
        .tl-dot {
            position: relative; z-index: 2; flex-shrink: 0;
            width: 24px; height: 24px; border-radius: 50%; margin-top: 18px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 0 4px #fff;
        }
        .tl-dot-inner { width: 8px; height: 8px; border-radius: 50%; background: #fff; }
        .tl-card {
            flex: 1; min-width: 0; background: #fff; border: 1px solid #e5e7eb;
            border-radius: 14px; padding: 16px 20px; border-left: 4px solid;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .tl-card:hover { transform: translateX(4px); box-shadow: 0 6px 20px -4px rgba(0,0,0,.08); }
        .tl-card-top { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px; }
        .tl-type-tag {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .tl-type-tag svg { width: 12px; height: 12px; }
        .tl-event-title { font-size: 15px; font-weight: 700; color: #1f2937; margin: 6px 0 2px; }
        .tl-event-desc  { font-size: 13px; color: #4b5563; margin: 0; line-height: 1.5; }
        .tl-status-badge { padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .tl-card-footer {
            display: flex; flex-wrap: wrap; align-items: center; gap: 14px;
            margin-top: 10px; padding-top: 10px; border-top: 1px solid #f3f4f6;
            font-size: 12px; color: #6b7280;
        }
        .tl-card-footer span { display: flex; align-items: center; gap: 4px; }
        .tl-card-footer svg { width: 14px; height: 14px; }
        .tl-ago { margin-left: auto; font-size: 11px; color: #9ca3af; }

        /* ── Color tokens ── */
        .clr-success .tl-dot          { background: #10b981; }
        .clr-success .tl-card         { border-left-color: #34d399; }
        .clr-success .tl-type-tag     { background: #ecfdf5; color: #065f46; }
        .clr-success .tl-status-badge { background: #ecfdf5; color: #065f46; }

        .clr-danger .tl-dot           { background: #ef4444; }
        .clr-danger .tl-card          { border-left-color: #f87171; }
        .clr-danger .tl-type-tag      { background: #fef2f2; color: #991b1b; }
        .clr-danger .tl-status-badge  { background: #fef2f2; color: #991b1b; }

        .clr-warning .tl-dot          { background: #f59e0b; }
        .clr-warning .tl-card         { border-left-color: #fbbf24; }
        .clr-warning .tl-type-tag     { background: #fffbeb; color: #92400e; }
        .clr-warning .tl-status-badge { background: #fffbeb; color: #92400e; }

        .clr-info .tl-dot             { background: #0ea5e9; }
        .clr-info .tl-card            { border-left-color: #38bdf8; }
        .clr-info .tl-type-tag        { background: #f0f9ff; color: #075985; }
        .clr-info .tl-status-badge    { background: #f0f9ff; color: #075985; }

        .clr-primary .tl-dot          { background: #6366f1; }
        .clr-primary .tl-card         { border-left-color: #818cf8; }
        .clr-primary .tl-type-tag     { background: #eef2ff; color: #3730a3; }
        .clr-primary .tl-status-badge { background: #eef2ff; color: #3730a3; }

        .clr-secondary .tl-dot, .clr-gray .tl-dot          { background: #6b7280; }
        .clr-secondary .tl-card, .clr-gray .tl-card         { border-left-color: #9ca3af; }
        .clr-secondary .tl-type-tag, .clr-gray .tl-type-tag { background: #f3f4f6; color: #374151; }
        .clr-secondary .tl-status-badge, .clr-gray .tl-status-badge { background: #f3f4f6; color: #374151; }

        /* ── Dark mode ── */
        .dark .tl-stat, .dark .tl-section, .dark .tl-card, .dark .tl-filters { background: #1f2937; border-color: #374151; }
        .dark .tl-stat-label { color: #9ca3af; }
        .dark .tl-stat-value { color: #f9fafb; }
        .dark .tl-section-title, .dark .tl-event-title { color: #f9fafb; }
        .dark .tl-section-sub, .dark .tl-event-desc { color: #9ca3af; }
        .dark .tl-card-footer { border-top-color: #374151; color: #9ca3af; }
        .dark .tl-empty-icon { background: #374151; }
        .dark .tl-empty-icon svg { color: #4b5563; }
        .dark .tl-dot { box-shadow: 0 0 0 4px #1f2937; }
        .dark .tl-filter-select { background: #374151; border-color: #4b5563; color: #e5e7eb; }
        .dark .tl-filter-label { color: #d1d5db; }
        .dark .tl-stat.green  .tl-stat-icon { background: rgba(16,185,129,.12); }
        .dark .tl-stat.violet .tl-stat-icon { background: rgba(139,92,246,.12); }
        .dark .tl-stat.sky    .tl-stat-icon { background: rgba(14,165,233,.12); }
        .dark .tl-stat.amber  .tl-stat-icon { background: rgba(245,158,11,.12); }
        .dark .tl-stat.rose   .tl-stat-icon { background: rgba(225,29,72,.12); }
        .dark .tl-stat.indigo .tl-stat-icon { background: rgba(99,102,241,.12); }
        .dark .tl-section-icon { background: rgba(79,70,229,.15); }

        @media (max-width: 640px) {
            .tl-header { padding: 22px; }
            .tl-name   { font-size: 20px; }
            .tl-section { padding: 20px 18px; }
            .tl-card    { padding: 14px 16px; }
            .tl-header-inner { flex-direction: column; align-items: flex-start; }
            .tl-filters { flex-direction: column; }
        }
    </style>

    @php
        $profile = $this->getMemberProfile();
        $stats   = $this->getStatistics();
        $events  = $this->getTimelineEvents();

        $typeLabels = [
            'attendance'   => ['icon' => 'heroicon-o-clipboard-document-check', 'label' => 'Attendance'],
            'contribution' => ['icon' => 'heroicon-o-banknotes',               'label' => 'Contribution'],
            'enrollment'   => ['icon' => 'heroicon-o-academic-cap',            'label' => 'Enrollment'],
            'education'    => ['icon' => 'heroicon-o-book-open',               'label' => 'Education'],
            'guardian'     => ['icon' => 'heroicon-o-users',                   'label' => 'Guardian'],
            'group'        => ['icon' => 'heroicon-o-user-group',              'label' => 'Group'],
            'tour'         => ['icon' => 'heroicon-o-map-pin',                 'label' => 'Tour'],
        ];
    @endphp

    <div style="display:flex;flex-direction:column;gap:24px;">

        {{-- ───────────── MEMBER HEADER ───────────── --}}
        <div class="tl-header">
            <div class="tl-header-inner">
                <div style="display:flex;align-items:center;gap:18px;">
                    <div class="tl-avatar">
                        {{ strtoupper(substr($record->full_name, 0, 1)) }}{{ strtoupper(substr(explode(' ', $record->full_name)[1] ?? '', 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="tl-name">{{ $record->full_name }}</h2>
                        <div class="tl-meta">
                            <span>
                                <x-filament::icon icon="heroicon-m-phone" style="width:14px;height:14px;" />
                                {{ $record->phone }}
                            </span>
                            @if($record->member_code)
                                <span class="tl-badge">ID: {{ $record->member_code }}</span>
                            @endif
                        </div>
                        {{-- Profile tags --}}
                        <div class="tl-profile-tags">
                            @if($profile['member_type'] !== 'N/A')
                                <span class="tl-profile-tag">
                                    <x-filament::icon icon="heroicon-m-tag" />
                                    {{ $profile['member_type'] }}
                                </span>
                            @endif
                            @if($profile['department'] !== 'N/A')
                                <span class="tl-profile-tag">
                                    <x-filament::icon icon="heroicon-m-building-office" />
                                    {{ $profile['department'] }}
                                </span>
                            @endif
                            @if($profile['current_group'] !== 'N/A')
                                <span class="tl-profile-tag">
                                    <x-filament::icon icon="heroicon-m-user-group" />
                                    {{ $profile['current_group'] }}
                                </span>
                            @endif
                            @if($profile['current_class'] !== 'N/A')
                                <span class="tl-profile-tag">
                                    <x-filament::icon icon="heroicon-m-academic-cap" />
                                    {{ $profile['current_class'] }}
                                    @if($profile['current_academic_year'])
                                        <small style="opacity:.7;">({{ $profile['current_academic_year'] }})</small>
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <p class="tl-since-label">Member Since</p>
                    <p class="tl-since-val">{{ $record->member_since?->toFormattedDateString() ?? 'N/A' }}</p>
                    @if($record->member_status)
                        <span class="tl-status-pill">{{ $record->member_status }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- ───────────── QUICK STATS ───────────── --}}
        <div class="tl-stats">
            <div class="tl-stat green">
                <div class="tl-stat-bar"></div>
                <div class="tl-stat-inner">
                    <div class="tl-stat-icon">
                        <x-filament::icon icon="heroicon-o-check-circle" style="width:22px;height:22px;" />
                    </div>
                    <div>
                        <p class="tl-stat-label">Attendance</p>
                        <p class="tl-stat-value">
                            {{ $stats['attendance_present'] }}
                            <small>/ {{ $stats['attendance_total'] }}</small>
                        </p>
                    </div>
                </div>
            </div>

            <div class="tl-stat violet">
                <div class="tl-stat-bar"></div>
                <div class="tl-stat-inner">
                    <div class="tl-stat-icon">
                        <x-filament::icon icon="heroicon-o-banknotes" style="width:22px;height:22px;" />
                    </div>
                    <div>
                        <p class="tl-stat-label">Contributions</p>
                        <p class="tl-stat-value">
                            {{ number_format($stats['contributions_sum'], 2) }}
                            <small>ETB</small>
                        </p>
                    </div>
                </div>
            </div>

            <div class="tl-stat sky">
                <div class="tl-stat-bar"></div>
                <div class="tl-stat-inner">
                    <div class="tl-stat-icon">
                        <x-filament::icon icon="heroicon-o-academic-cap" style="width:22px;height:22px;" />
                    </div>
                    <div>
                        <p class="tl-stat-label">Enrollments</p>
                        <p class="tl-stat-value">{{ $stats['enrollments_count'] }}</p>
                    </div>
                </div>
            </div>

            <div class="tl-stat amber">
                <div class="tl-stat-bar"></div>
                <div class="tl-stat-inner">
                    <div class="tl-stat-icon">
                        <x-filament::icon icon="heroicon-o-user-group" style="width:22px;height:22px;" />
                    </div>
                    <div>
                        <p class="tl-stat-label">Groups</p>
                        <p class="tl-stat-value">{{ $stats['groups_count'] }}</p>
                    </div>
                </div>
            </div>

            <div class="tl-stat rose">
                <div class="tl-stat-bar"></div>
                <div class="tl-stat-inner">
                    <div class="tl-stat-icon">
                        <x-filament::icon icon="heroicon-o-map-pin" style="width:22px;height:22px;" />
                    </div>
                    <div>
                        <p class="tl-stat-label">Tours</p>
                        <p class="tl-stat-value">{{ $stats['tours_count'] }}</p>
                    </div>
                </div>
            </div>

            <div class="tl-stat indigo">
                <div class="tl-stat-bar"></div>
                <div class="tl-stat-inner">
                    <div class="tl-stat-icon">
                        <x-filament::icon icon="heroicon-o-book-open" style="width:22px;height:22px;" />
                    </div>
                    <div>
                        <p class="tl-stat-label">Education</p>
                        <p class="tl-stat-value" style="font-size:15px;">{{ $stats['education_level'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ───────────── FILTER BAR ───────────── --}}
        <div class="tl-filters">
            <span class="tl-filter-label">
                <x-filament::icon icon="heroicon-m-funnel" style="width:16px;height:16px;display:inline;vertical-align:middle;margin-right:4px;" />
                Filters:
            </span>

            <select wire:model.live="academicYearFilter" class="tl-filter-select">
                <option value="all">All Academic Years</option>
                @foreach($this->getAcademicYears() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            <select wire:model.live="dateRangeFilter" class="tl-filter-select">
                @foreach($this->getDateRangeOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select wire:model.live="eventTypeFilter" class="tl-filter-select">
                @foreach($this->getEventTypeOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- ───────────── ACTIVITY TIMELINE ───────────── --}}
        <div class="tl-section">
            <div class="tl-section-head">
                <div class="tl-section-icon">
                    <x-filament::icon icon="heroicon-o-clock" style="width:20px;height:20px;" />
                </div>
                <div>
                    <h3 class="tl-section-title">Activity Timeline</h3>
                    <p class="tl-section-sub">
                        {{ count($events) }} event{{ count($events) !== 1 ? 's' : '' }} found
                        @if($this->dateRangeFilter !== 'all')
                            · Filtered by {{ $this->getDateRangeOptions()[$this->dateRangeFilter] ?? '' }}
                        @endif
                        @if($this->academicYearFilter !== 'all')
                            · Academic Year: {{ $this->getAcademicYears()[$this->academicYearFilter] ?? '' }}
                        @endif
                    </p>
                </div>
            </div>

            @if(empty($events))
                <div class="tl-empty">
                    <div class="tl-empty-icon">
                        <x-filament::icon icon="heroicon-o-calendar" style="width:36px;height:36px;" />
                    </div>
                    <p>No activity recorded yet</p>
                    <p>Events will appear here as they happen. Try adjusting the filters above.</p>
                </div>
            @else
                <div class="tl-rail">
                    @foreach($events as $i => $event)
                        @php
                            $color = $this->getEventColor($event['type'], $event['status']);
                            $meta  = $typeLabels[$event['type']] ?? ['icon' => 'heroicon-o-information-circle', 'label' => ucfirst($event['type'])];
                        @endphp

                        <div class="tl-event clr-{{ $color }}" style="animation-delay: {{ min($i * 0.05, 1) }}s;">
                            <div class="tl-dot">
                                <div class="tl-dot-inner"></div>
                            </div>
                            <div class="tl-card">
                                <div class="tl-card-top">
                                    <div style="min-width:0;flex:1;">
                                        <span class="tl-type-tag">
                                            <x-filament::icon :icon="$meta['icon']" style="width:12px;height:12px;" />
                                            {{ $meta['label'] }}
                                        </span>
                                        <h4 class="tl-event-title">{{ $event['title'] }}</h4>
                                        <p class="tl-event-desc">{{ $event['description'] }}</p>
                                    </div>
                                    <span class="tl-status-badge">{{ $event['status'] }}</span>
                                </div>
                                <div class="tl-card-footer">
                                    <span>
                                        <x-filament::icon icon="heroicon-m-calendar-days" />
                                        {{ \Carbon\Carbon::parse($event['date'])->format('M d, Y') }}
                                    </span>
                                    @if(!empty($event['time']))
                                        <span>
                                            <x-filament::icon icon="heroicon-m-clock" />
                                            {{ $event['time'] }}
                                        </span>
                                    @endif
                                    <span class="tl-ago">
                                        {{ \Carbon\Carbon::parse($event['date'])->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>