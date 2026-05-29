<section id="events" class="snap-section" style="background:#050505;flex-direction:column;padding:0;">
    <div style="position:absolute;inset:0;background:url('{{ asset('images/hero-bg.jpg') }}') center/cover no-repeat;filter:brightness(.2) saturate(1.05);"></div>
    <div style="position:absolute;inset:0;background:rgba(5,5,5,0.75);"></div>
    <div style="position:absolute;top:0;left:0;right:0;z-index:2;padding:32px 32px 0;">
        <div class="ss-reveal" data-delay="0">
            <span class="ss-eyebrow">Events</span>
        </div>
        <h2 class="ss-section-title ss-reveal" data-delay="100">Upcoming Events</h2>
    </div>

    <div class="h-track" id="events-track">
        @forelse($events as $event)
        <div class="h-panel" style="flex-direction:column;">
            <div class="event-card" style="text-align:center;">
                <div style="display:inline-flex;align-items:center;gap:8px;margin-bottom:16px;">
                    <span style="font-size:0.65rem;letter-spacing:0.15em;text-transform:uppercase;color:rgba(255,255,255,0.3);">
                        {{ $event->date_time?->format('M d, Y') }}
                    </span>
                    <span style="width:4px;height:4px;border-radius:50%;background:rgba(26,68,247,0.5);"></span>
                    <span style="font-size:0.65rem;letter-spacing:0.1em;color:rgba(255,255,255,0.3);">
                        {{ $event->formatted_time ?? $event->date_time?->format('g:i A') }}
                    </span>
                </div>
                <h3 style="font-size:clamp(1.1rem,2vw,1.5rem);font-weight:700;margin-bottom:8px;">{{ $event->name }}</h3>
                @if($event->location)
                <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:12px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span style="font-size:0.8rem;color:rgba(255,255,255,0.4);">{{ $event->location }}</span>
                </div>
                @endif
                <p style="font-size:0.85rem;color:rgba(255,255,255,0.5);line-height:1.7;margin-bottom:20px;max-width:400px;margin-left:auto;margin-right:auto;">
                    {{ Str::limit($event->description, 120) }}
                </p>
                <a href="{{ route('events.show', $event) }}" class="ss-btn ss-btn-ghost" style="font-size:0.8rem;padding:10px 24px;">
                    Event Details
                </a>
            </div>
        </div>
        @empty
        <div class="h-panel">
            <div style="text-align:center;">
                <p style="font-size:1.1rem;color:rgba(255,255,255,0.4);">No upcoming events at this time.</p>
                <p style="font-size:0.85rem;color:rgba(255,255,255,0.25);margin-top:8px;">Check back soon for new programs and gatherings.</p>
            </div>
        </div>
        @endforelse
    </div>

    <div class="h-dots">
        @foreach($events as $i => $event)
        <div class="h-dot {{ $i === 0 ? 'active' : '' }}"></div>
        @endforeach
    </div>

    <span class="ss-label">06 / 07</span>
</section>
