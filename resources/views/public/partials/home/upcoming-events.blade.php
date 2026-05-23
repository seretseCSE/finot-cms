{{-- ═══════════════════════════════════════════════════════
     7.  UPCOMING EVENTS — Real data from database
═══════════════════════════════════════════════════════ --}}
<section id="events" style="padding:100px 24px;background:linear-gradient(180deg,var(--dark-900),var(--dark-950));position:relative;">
    <div style="max-width:1280px;margin:0 auto;">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:48px;">
            <div>
                <div class="sec-label sr">{{ __('Calendar') }}</div>
                <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);">{{ __('Upcoming Events') }}</h2>
            </div>
            <a href="{{ route('news', ['tab' => 'events']) }}" class="btn btn-ghost sr">{{ __('View All Events') }}</a>
        </div>

        @if($upcomingEvents && $upcomingEvents->count() > 0)
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
            @foreach($upcomingEvents as $event)
            <div class="card sr" style="padding:24px;display:flex;gap:16px;align-items:flex-start;" data-delay="{{ $loop->index * 80 }}">
                {{-- Date badge --}}
                <div style="width:56px;min-width:56px;text-align:center;padding:10px 8px;border-radius:12px;background:linear-gradient(135deg,rgba(26,68,247,.15),rgba(26,68,247,.05));border:1px solid rgba(26,68,247,.2);">
                    <div style="font-size:.6rem;letter-spacing:.08em;text-transform:uppercase;color:var(--blue-400);font-weight:600;">{{ $event->date_time->format('M') }}</div>
                    <div style="font-size:1.5rem;font-weight:800;color:var(--text-display);line-height:1.2;">{{ $event->date_time->format('d') }}</div>
                </div>
                {{-- Event info --}}
                <div style="flex:1;min-width:0;">
                    <h3 style="font-size:.95rem;font-weight:600;color:var(--text-display);margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $event->name }}</h3>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <div style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--parchment-60);">
                            <svg width="12" height="12" fill="none" stroke="var(--gold)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $event->date_time->format('h:i A') }}
                        </div>
                        @if($event->location)
                        <div style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--parchment-40);">
                            <svg width="12" height="12" fill="none" stroke="var(--parchment-40)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            {{ Str::limit($event->location, 30) }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="sr" style="max-width:420px;margin:0 auto;">
            <x-empty-state-card type="events" />
        </div>
        @endif
    </div>
</section>