@extends('layouts.public')

@section('title', __('News & Events'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/page-title-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90) 0%,rgba(26,68,247,.2) 50%,var(--overlay-95) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;margin-bottom:20px;">
            <span class="am">መግለጫዎች እና ዝግጅቶች</span>
        </div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;">
            {{ __('News & Events') }}
        </h1>
        <p class="sr" style="font-size:1.1rem;color:var(--text-60);max-width:600px;margin:0 auto;line-height:1.7;">
            {{ __('Stay informed with the latest announcements, news, and upcoming events from our community.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  TAB BUTTONS
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:40px 24px 0;background:var(--dark-900);">
    <div style="max-width:1280px;margin:0 auto;">
        <div class="sr" style="display:flex;gap:4px;margin-bottom:0;">
            <button class="news-tab active" data-tab="announcements" style="
                padding:12px 28px;border-radius:10px 10px 0 0;font-family:'Inter',sans-serif;font-size:.9rem;font-weight:600;
                background:var(--bg-900);border:1px solid var(--border-subtle);border-bottom:none;
                color:var(--text-display);cursor:pointer;transition:all .2s;position:relative;
            ">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;display:inline;vertical-align:-2px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                {{ __('Announcements') }}
            </button>
            <button class="news-tab" data-tab="events" style="
                padding:12px 28px;border-radius:10px 10px 0 0;font-family:'Inter',sans-serif;font-size:.9rem;font-weight:600;
                background:transparent;border:1px solid transparent;border-bottom:none;
                color:var(--text-40);cursor:pointer;transition:all .2s;
            ">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;display:inline;vertical-align:-2px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ __('Events') }}
            </button>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  ANNOUNCEMENTS TAB CONTENT
     ═══════════════════════════════════════════════════════ --}}
<section id="tab-announcements" class="news-tab-content" style="padding:60px 24px 80px;background:var(--dark-900);position:relative;display:{{ $activeTab === 'announcements' ? 'block' : 'none' }};">
    <div style="max-width:1280px;margin:0 auto;">
        @if($announcements->count() > 0)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:32px;">
                @foreach($announcements as $announcement)
                    <div class="card sr" style="padding:0;overflow:hidden;display:flex;flex-direction:column;border-radius:var(--r-lg);">
                        @if($announcement->is_urgent)
                            <div style="background:#ef4444;color:#fff;padding:6px 16px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;display:flex;align-items:center;gap:8px;">
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                {{ __('Urgent Announcement') }}
                            </div>
                        @endif

                        @if($announcement->image)
                            <div style="width:100%;height:200px;overflow:hidden;background:var(--dark-800);">
                                <img src="{{ $announcement->image_url }}" alt="{{ $announcement->title }}" style="width:100%;height:100%;object-fit:cover;">
                            </div>
                        @endif

                        <div style="padding:32px;">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                                <div style="width:40px;height:40px;border-radius:10px;background:rgba(26,68,247,.1);border:1px solid rgba(26,68,247,.2);display:flex;align-items:center;justify-content:center;color:var(--blue-400);">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                                </div>
                                <div style="font-size:.8rem;color:var(--parchment-40);font-weight:500;">
                                    {{ $announcement->published_at ? $announcement->published_at->format('M d, Y') : $announcement->start_date->format('M d, Y') }}
                                </div>
                            </div>

                            <h3 class="am" style="font-size:1.4rem;font-weight:700;color:var(--text-display);margin-bottom:12px;line-height:1.3;">
                                {{ app()->getLocale() === 'am' ? ($announcement->title_am ?? $announcement->title) : $announcement->title }}
                            </h3>

                            <div class="am" style="font-size:.95rem;color:var(--text-60);line-height:1.7;margin-bottom:24px;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;">
                                {!! strip_tags(app()->getLocale() === 'am' ? ($announcement->content_am ?? $announcement->content) : $announcement->content) !!}
                            </div>

                            <div style="margin-top:auto;display:flex;align-items:center;justify-content:space-between;padding-top:20px;border-top:1px solid var(--border-subtle);">
                                <a href="{{ route('announcements.show', $announcement->id) }}" class="btn btn-ghost" style="padding:8px 16px;font-size:.85rem;">
                                    {{ __('Read More') }}
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:60px;">
                {{ $announcements->links() }}
            </div>
        @else
            <div class="card sr" style="padding:80px 24px;text-align:center;">
                <div style="font-size:3rem;margin-bottom:20px;">📢</div>
                <h2 class="display" style="font-size:1.8rem;margin-bottom:12px;">{{ __('No Announcements') }}</h2>
                <p style="color:var(--text-60);max-width:500px;margin:0 auto;">{{ __('There are no active announcements at this time. Please check back later.') }}</p>
                <a href="{{ url('/') }}" class="btn btn-primary" style="margin-top:32px;">{{ __('Back to Home') }}</a>
            </div>
        @endif
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     4.  EVENTS TAB CONTENT
     ═══════════════════════════════════════════════════════ --}}
<section id="tab-events" class="news-tab-content" style="padding:60px 24px 80px;background:var(--dark-900);position:relative;display:{{ $activeTab === 'events' ? 'block' : 'none' }};">
    <div style="max-width:1200px;margin:0 auto;">

        {{-- Calendar Controls --}}
        <div class="card sr" style="padding:24px 32px;display:flex;align-items:center;justify-content:space-between;margin-bottom:40px;">
            <div style="display:flex;align-items:center;gap:24px;">
                <a href="{{ route('news', ['month' => $startOfMonth->copy()->subMonth()->month, 'year' => $startOfMonth->copy()->subMonth()->year, 'tab' => 'events']) }}" class="btn btn-ghost" style="padding:8px 12px;border-radius:10px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h2 class="display" style="font-size:1.6rem;min-width:200px;text-align:center;">{{ $startOfMonth->format('F Y') }}</h2>
                <a href="{{ route('news', ['month' => $startOfMonth->copy()->addMonth()->month, 'year' => $startOfMonth->copy()->addMonth()->year, 'tab' => 'events']) }}" class="btn btn-ghost" style="padding:8px 12px;border-radius:10px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            <a href="{{ route('news', ['month' => now()->month, 'year' => now()->year, 'tab' => 'events']) }}" class="btn btn-primary" style="padding:10px 24px;">
                {{ __('Today') }}
            </a>
        </div>

        {{-- Calendar Grid --}}
        <div class="sr" style="margin-bottom:80px;">
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:var(--r-lg);overflow:hidden;">
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                    <div style="background:rgba(255,255,255,.03);padding:14px;text-align:center;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.15em;color:var(--gold);">{{ __($day) }}</div>
                @endforeach

                @php
                    $firstDayOfMonth = $startOfMonth->copy()->startOfMonth();
                    $daysInMonth = $startOfMonth->daysInMonth;
                    $startDayOfWeek = $firstDayOfMonth->dayOfWeek;
                    $totalCells = ceil(($startDayOfWeek + $daysInMonth) / 7) * 7;
                @endphp

                @for ($cell = 0; $cell < $totalCells; $cell++)
                    @php
                        $dayNumber = $cell - $startDayOfWeek + 1;
                        $isCurrentMonth = $dayNumber > 0 && $dayNumber <= $daysInMonth;
                        $currentDateString = $isCurrentMonth ? $firstDayOfMonth->copy()->addDays($dayNumber - 1)->format('Y-m-d') : null;
                        $dayEvents = $isCurrentMonth && isset($calendarEvents[$currentDateString]) ? $calendarEvents[$currentDateString] : collect();
                        $isToday = $isCurrentMonth && $currentDateString === now()->format('Y-m-d');
                    @endphp
                    <div style="min-height:120px;padding:12px;background:{{ $isCurrentMonth ? 'rgba(255,255,255,.01)' : 'rgba(0,0,0,.15)' }};border:0.5px solid rgba(255,255,255,.03);position:relative;">
                        @if($isCurrentMonth)
                            <div style="font-size:.9rem;font-weight:{{ $isToday ? '800' : '500' }};color:{{ $isToday ? 'var(--gold)' : 'var(--parchment-40)' }};margin-bottom:8px;{{ $isToday ? 'display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:rgba(243,186,21,.15);' : '' }}">
                                {{ $dayNumber }}
                            </div>
                            @if($dayEvents->isNotEmpty())
                                <div style="display:flex;flex-direction:column;gap:4px;">
                                    @foreach($dayEvents->take(2) as $event)
                                        <div style="font-size:.68rem;padding:4px 8px;border-radius:6px;background:rgba(26,68,247,.15);border:1px solid rgba(26,68,247,.2);color:var(--blue-400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $event->name }}">
                                            {{ $event->name }}
                                        </div>
                                    @endforeach
                                    @if($dayEvents->count() > 2)
                                        <div style="font-size:.62rem;color:var(--parchment-40);padding-left:4px;">+{{ $dayEvents->count() - 2 }} more</div>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                @endfor
            </div>
        </div>

        {{-- Upcoming Events List --}}
        <h3 class="display sr" style="font-size:2.2rem;margin-bottom:40px;text-align:center;">{{ __('Upcoming Events') }}</h3>

        @if($upcomingEvents->isEmpty())
            <div class="card sr" style="padding:60px;text-align:center;">
                <div style="font-size:3rem;margin-bottom:20px;">📅</div>
                <h4 class="display" style="font-size:1.4rem;margin-bottom:12px;">{{ __('No Upcoming Events') }}</h4>
                <p style="color:var(--text-60);">{{ __('Check back later for new events.') }}</p>
            </div>
        @else
            <div style="display:grid;gap:24px;">
                @foreach($upcomingEvents as $event)
                    <div class="card sr" style="padding:0;overflow:hidden;display:flex;flex-wrap:wrap;">
                        <div style="width:{{ $event->featured_image ? '260px' : '100px' }};min-height:180px;background:linear-gradient(135deg,var(--dark-800),var(--blue-primary));position:relative;overflow:hidden;">
                            @if($event->featured_image)
                                <img src="{{ $event->featured_image_url }}" alt="{{ $event->name }}" style="width:100%;height:100%;object-fit:cover;opacity:.8;">
                            @endif
                            <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--overlay-40);">
                                <div style="font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;color:var(--gold);font-weight:700;">{{ $event->date_time->format('M') }}</div>
                                <div style="font-size:2.5rem;font-weight:800;color:#fff;line-height:1;">{{ $event->date_time->format('d') }}</div>
                            </div>
                        </div>
                        <div style="flex:1;min-width:300px;padding:32px;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
                                <div>
                                    <h4 style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:600;color:var(--text-display);margin-bottom:8px;">{{ $event->name }}</h4>
                                    <div style="display:flex;flex-wrap:wrap;gap:16px;font-size:.85rem;color:var(--text-40);">
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <svg width="14" height="14" fill="none" stroke="var(--gold)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ $event->date_time->format('g:i A') }}
                                        </div>
                                        @if($event->location)
                                            <div style="display:flex;align-items:center;gap:6px;">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                                {{ $event->location }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @if($event->registration_required)
                                    <span style="font-size:.65rem;padding:4px 10px;border-radius:99px;background:rgba(243,186,21,.1);border:1px solid rgba(243,186,21,.25);color:var(--gold);text-transform:uppercase;letter-spacing:.05em;">{{ __('Registration Required') }}</span>
                                @endif
                            </div>

                            @if($event->description)
                                <div style="color:var(--text-60);font-size:.9rem;line-height:1.7;margin-bottom:24px;">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($event->description), 200) }}
                                </div>
                            @endif

                            <div style="display:flex;gap:12px;">
                                <a href="{{ route('events.show', $event) }}" class="btn btn-primary" style="padding:8px 20px;font-size:.8rem;">{{ __('More Info') }}</a>
                                @if($event->registration_required)
                                    <a href="{{ route('contact') }}" class="btn btn-ghost" style="padding:8px 20px;font-size:.8rem;">{{ __('Register') }}</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

@push('scripts')
<script>
(function() {
    var tabs = document.querySelectorAll('.news-tab');
    var contents = document.querySelectorAll('.news-tab-content');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var target = this.getAttribute('data-tab');

            tabs.forEach(function(t) {
                t.classList.remove('active');
                t.style.background = 'transparent';
                t.style.border = '1px solid transparent';
                t.style.color = 'var(--text-40)';
            });

            this.classList.add('active');
            this.style.background = 'var(--bg-900)';
            this.style.border = '1px solid var(--border-subtle)';
            this.style.borderBottom = 'none';
            this.style.color = 'var(--text-display)';

            contents.forEach(function(c) {
                c.style.display = c.id === 'tab-' + target ? 'block' : 'none';
            });
        });
    });

    var activeTab = '{{ $activeTab }}';
    if (activeTab === 'events') {
        var eventsTab = document.querySelector('[data-tab="events"]');
        if (eventsTab) eventsTab.click();
    }
})();
</script>
@endpush

@endsection
