{{-- ═══════════════════════════════════════════════════════
     5.  PROGRAMS — 4 educational divisions with real counts
═══════════════════════════════════════════════════════ --}}
<section id="programs" style="padding:100px 24px;background:linear-gradient(180deg,var(--dark-900) 0%,var(--dark-950) 100%);position:relative;">
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:1;">
        <div style="text-align:center;margin-bottom:56px;">
            <div class="sec-label sr" style="justify-content:center;">{{ __('Education') }}</div>
            <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);margin-bottom:12px;">{{ __('Our Programs') }}</h2>
            <p class="sr" style="color:var(--parchment-60);max-width:500px;margin:0 auto;">{{ __('Spiritual education designed for every stage of life') }}</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;">
            @foreach([
                ['am' => 'ህጻናት', 'en' => __('Children\'s Program'), 'desc' => __('Grades 1-8: Foundation of faith through interactive lessons and activities'), 'count' => \App\Models\Member::where('member_type', 'Kids')->count(), 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'color' => '#4ade80'],
                ['am' => 'አዳጊ',   'en' => __('Youth Program'),      'desc' => __('Grades 9-12: Character development and deeper spiritual understanding'), 'count' => \App\Models\Member::where('member_type', 'Youth')->count(), 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'color' => '#60a5fa'],
                ['am' => 'ወጣት',  'en' => __('Young Adults'),       'desc' => __('18+: Advanced theology, leadership training, and community service'), 'count' => \App\Models\Member::where('member_type', 'Adult')->count(), 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'color' => 'var(--gold)'],
                ['am' => 'የርቀት', 'en' => __('Distance Learning'), 'desc' => __('Online spiritual education for those who cannot attend in person'), 'count' => max(0, \App\Models\Member::count() - \App\Models\Member::where('member_type', 'Kids')->count() - \App\Models\Member::where('member_type', 'Youth')->count() - \App\Models\Member::where('member_type', 'Adult')->count()), 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => '#c084fc'],
            ] as $prog)
            <div class="card sr" style="padding:32px 28px;position:relative;overflow:hidden;" data-delay="{{ $loop->index * 100 }}">
                {{-- Accent line --}}
                <div style="position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,{{ $prog['color'] }},transparent);"></div>

                <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(26,68,247,.08);border:1px solid rgba(26,68,247,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="22" height="22" fill="none" stroke="{{ $prog['color'] }}" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $prog['icon'] }}"/></svg>
                    </div>
                    <div>
                        <h3 class="am" style="font-size:1.1rem;font-weight:700;color:var(--text-display);">{{ $prog['am'] }}</h3>
                        <div style="font-size:.78rem;color:var(--parchment-60);">{{ $prog['en'] }}</div>
                    </div>
                </div>

                <p style="font-size:.84rem;color:var(--parchment-40);line-height:1.7;margin-bottom:20px;">{{ $prog['desc'] }}</p>

                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:{{ $prog['color'] }};"></div>
                        <span style="font-size:.78rem;color:var(--parchment-60);">
                            <strong style="color:{{ $prog['color'] }};">{{ $prog['count'] }}</strong> {{ __('students') }}
                        </span>
                    </div>
                    <a href="{{ route('library') }}" style="font-size:.78rem;color:var(--blue-500);text-decoration:none;font-weight:500;">{{ __('Learn more') }} →</a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>