@extends('layouts.public')

@section('title', $event->name . ' - ' . __('Event Details'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Event Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:140px 24px 80px;background:var(--dark-950);overflow:hidden;">
    {{-- Parallax background image --}}
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/page-title-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90) 0%,rgba(26,68,247,.2) 50%,var(--overlay-95) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Event Details') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;color:var(--text-hero);">
            {{ $event->name }}
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ $event->date_time->format('F j, Y \a\t g:i A') }} • {{ $event->location }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  EVENT DETAILS
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:80px 24px;background:var(--dark-900);position:relative;">
    <div style="max-width:900px;margin:0 auto;position:relative;z-index:1;">
        <div style="display:grid;grid-template-columns:1fr;gap:60px;">
            
            {{-- Event Image --}}
            @if($event->featured_image)
                <div class="sr" style="text-align:center;">
                    <img src="{{ $event->featured_image_url }}" alt="{{ $event->name }}" 
                         style="width:100%;max-height:400px;object-fit:cover;border-radius:var(--r-lg);box-shadow:0 20px 40px rgba(0,0,0,.3);">
                </div>
            @endif

            {{-- Event Information --}}
            <div class="sr">
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:40px;margin-bottom:60px;">
                    
                    {{-- Date & Time --}}
                    <div style="text-align:center;">
                        <div style="width:64px;height:64px;border-radius:16px;background:rgba(26,68,247,.1);border:1px solid rgba(26,68,247,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                            <svg width="32" height="32" fill="none" stroke="var(--blue-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 style="font-size:1.1rem;color:var(--text-display);margin-bottom:8px;">{{ __('Date & Time') }}</h3>
                        <p style="color:var(--text-60);line-height:1.6;">
                            {{ $event->date_time->format('l, F j, Y') }}<br>
                            {{ $event->date_time->format('g:i A') }}
                        </p>
                    </div>

                    {{-- Location --}}
                    <div style="text-align:center;">
                        <div style="width:64px;height:64px;border-radius:16px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                            <svg width="32" height="32" fill="none" stroke="var(--purple-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <h3 style="font-size:1.1rem;color:var(--text-display);margin-bottom:8px;">{{ __('Location') }}</h3>
                        <p style="color:var(--text-60);line-height:1.6;">
                            {{ $event->location }}
                        </p>
                    </div>

                    {{-- Status --}}
                    <div style="text-align:center;">
                        <div style="width:64px;height:64px;border-radius:16px;background:rgba(251,146,60,.1);border:1px solid rgba(251,146,60,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                            <svg width="32" height="32" fill="none" stroke="var(--gold-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3 style="font-size:1.1rem;color:var(--text-display);margin-bottom:8px;">{{ __('Status') }}</h3>
                        <p style="color:var(--text-60);line-height:1.6;">
                            {{ __($event->status) }}
                        </p>
                    </div>
                </div>

                {{-- Description --}}
                @if($event->description)
                    <div style="margin-bottom:60px;">
                        <h2 class="display" style="font-size:1.8rem;margin-bottom:24px;color:var(--text-display);">{{ __('About This Event') }}</h2>
                        <div style="color:var(--text-60);line-height:1.8;font-size:1.05rem;">
                            {!! $event->description !!}
                        </div>
                    </div>
                @endif

                {{-- Registration Info --}}
                @if($event->registration_required)
                    <div style="margin-bottom:60px;">
                        <h2 class="display" style="font-size:1.8rem;margin-bottom:24px;color:var(--text-display);">{{ __('Registration Information') }}</h2>
                        <div style="background:rgba(26,68,247,.05);border:1px solid rgba(26,68,247,.2);border-radius:var(--r-lg);padding:32px;">
                            @if($event->max_capacity)
                                <p style="color:var(--text-60);margin-bottom:16px;">
                                    {{ __('Maximum Capacity') }}: {{ $event->max_capacity }} {{ __('people') }}
                                </p>
                            @endif
                            @if($event->registration_deadline)
                                <p style="color:var(--text-60);margin-bottom:24px;">
                                    {{ __('Registration Deadline') }}: {{ $event->registration_deadline->format('F j, Y') }}
                                </p>
                            @endif
                            <div style="text-align:center;">
                                <a href="{{ route('contact') }}" class="btn btn-primary" style="padding:12px 32px;font-size:1rem;">
                                    {{ __('Register Now') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Action Buttons --}}
                <div style="text-align:center;">
                    <a href="{{ route('news', ['tab' => 'events']) }}" class="btn btn-ghost" style="margin-right:16px;">
                        ← {{ __('Back to Events') }}
                    </a>
                    @if($event->registration_required)
                        <a href="{{ route('contact') }}" class="btn btn-primary">
                            {{ __('Register') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  RELATED EVENTS
     ═══════════════════════════════════════════════════════ --}}
@if($relatedEvents->isNotEmpty())
<section style="padding:80px 24px;background:var(--dark-950);position:relative;">
    <div style="max-width:1200px;margin:0 auto;position:relative;z-index:1;">
        <h2 class="display sr" style="font-size:2.2rem;margin-bottom:40px;text-align:center;color:var(--text-display);">
            {{ __('Related Events') }}
        </h2>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:32px;">
            @foreach($relatedEvents as $relatedEvent)
                <div class="card sr" style="padding:0;overflow:hidden;">
                    @if($relatedEvent->featured_image)
                        <div style="height:200px;background:linear-gradient(135deg,var(--dark-800),var(--blue-primary));position:relative;overflow:hidden;">
                            <img src="{{ $relatedEvent->featured_image_url }}" alt="{{ $relatedEvent->name }}" 
                                 style="width:100%;height:100%;object-fit:cover;opacity:.8;">
                            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:var(--overlay-60);">
                                <div style="text-align:center;color:#fff;">
                                    <div style="font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;color:var(--gold);font-weight:700;">{{ $relatedEvent->date_time->format('M') }}</div>
                                    <div style="font-size:2rem;font-weight:800;line-height:1;">{{ $relatedEvent->date_time->format('d') }}</div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div style="height:200px;background:linear-gradient(135deg,var(--dark-800),var(--blue-primary));display:flex;align-items:center;justify-content:center;">
                            <div style="text-align:center;color:#fff;">
                                <div style="font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;color:var(--gold);font-weight:700;">{{ $relatedEvent->date_time->format('M') }}</div>
                                <div style="font-size:2rem;font-weight:800;line-height:1;">{{ $relatedEvent->date_time->format('d') }}</div>
                            </div>
                        </div>
                    @endif
                    
                    <div style="padding:24px;">
                        <h3 style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:600;color:var(--text-display);margin-bottom:12px;">
                            {{ $relatedEvent->name }}
                        </h3>
                        <p style="color:var(--text-60);margin-bottom:16px;line-height:1.6;">
                            {{ $relatedEvent->date_time->format('F j, Y \a\t g:i A') }}
                        </p>
                        <p style="color:var(--text-60);margin-bottom:20px;line-height:1.6;">
                            {{ Str::limit(strip_tags($relatedEvent->description), 100) }}
                        </p>
                        <a href="{{ route('events.show', $relatedEvent) }}" class="btn btn-primary" style="width:100%;text-align:center;">
                            {{ __('View Details') }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection
