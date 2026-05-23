@extends('layouts.public')

@section('title', __('Event Calendar'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Events Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    {{-- Parallax background image --}}
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/page-title-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90) 0%,rgba(26,68,247,.2) 50%,var(--overlay-95) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Community Calendar') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;color:var(--text-hero);">
            {{ __('Our') }}
            <span style="color:var(--gold);">{{ __('Events') }}</span>
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ __('Join us in worship, learning, and fellowship. Stay updated with our upcoming activities and special celebrations.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  CALENDAR CONTROLS
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:60px 24px 20px;background:var(--dark-900);position:relative;">
    <div style="max-width:1200px;margin:0 auto;position:relative;z-index:1;">
        <div class="card sr" style="padding:24px 32px;display:flex;align-items:center;justify-content:space-between;margin-bottom:40px;">
            <div style="display:flex;align-items:center;gap:24px;">
                <a href="{{ route('events', ['month' => $startOfMonth->copy()->subMonth()->month, 'year' => $startOfMonth->copy()->subMonth()->year]) }}" class="btn btn-ghost" style="padding:8px 12px;border-radius:10px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h2 class="display" style="font-size:1.6rem;min-width:200px;text-align:center;">{{ $startOfMonth->format('F Y') }}</h2>
                <a href="{{ route('events', ['month' => $startOfMonth->copy()->addMonth()->month, 'year' => $startOfMonth->copy()->addMonth()->year]) }}" class="btn btn-ghost" style="padding:8px 12px;border-radius:10px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            <a href="{{ route('events', ['month' => now()->month, 'year' => now()->year]) }}" class="btn btn-primary" style="padding:10px 24px;">
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
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  UPCOMING EVENTS LIST
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:0 24px 100px;background:var(--dark-900);">
    <div style="max-width:900px;margin:0 auto;">
        <h3 class="display sr" style="font-size:2.2rem;margin-bottom:40px;text-align:center;">{{ __('Event Details') }}</h3>

        @if($upcomingEvents->isEmpty())
            <div class="sr" style="max-width:480px;margin:0 auto;">
                <x-empty-state-card type="events" />
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
                                    {{ Str::limit(strip_tags($event->description), 200) }}
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

@endsection
