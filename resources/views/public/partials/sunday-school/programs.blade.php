<section id="programs" class="snap-section" style="background:#050505;flex-direction:column;padding:0;">
    <div style="position:absolute;inset:0;background:url('{{ asset('images/hero-bg.jpg') }}') center/cover no-repeat;filter:brightness(.2) saturate(1.05);"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(90deg,rgba(5,5,5,0.8) 0%,rgba(5,5,5,0.4) 100%);"></div>
    <div style="position:absolute;top:0;left:0;right:0;z-index:2;padding:32px 32px 0;">
        <div class="ss-reveal" data-delay="0">
            <span class="ss-eyebrow">Programs</span>
        </div>
        <h2 class="ss-section-title ss-reveal" data-delay="100">Our Programs</h2>
    </div>

    <div class="h-track" id="programs-track">
        @foreach($programs as $program)
        <div class="h-panel" style="flex-direction:column;">
            <div class="program-card">
                <div class="program-icon">{{ $program['icon'] }}</div>
                <p class="am" style="font-size:0.8rem;letter-spacing:0.15em;text-transform:uppercase;color:#F3BA15;margin-bottom:4px;">{{ $program['name_am'] }}</p>
                <h3 style="font-size:clamp(1.2rem,2.5vw,1.75rem);font-weight:700;margin-bottom:8px;">{{ $program['name'] }}</h3>
                <p style="font-size:0.85rem;color:rgba(255,255,255,0.5);line-height:1.7;margin-bottom:20px;">{{ $program['description'] }}</p>
                <div style="display:flex;gap:24px;align-items:center;">
                    <span style="font-size:0.75rem;color:rgba(255,255,255,0.35);letter-spacing:0.05em;">{{ $program['grades'] }}</span>
                    <span style="display:flex;align-items:center;gap:4px;font-size:1.5rem;font-weight:700;color:rgba(26,68,247,0.8);">
                        {{ $program['count'] }}
                        <span style="font-size:0.7rem;font-weight:400;color:rgba(255,255,255,0.3);">students</span>
                    </span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="h-dots">
        @foreach($programs as $i => $program)
        <div class="h-dot {{ $i === 0 ? 'active' : '' }}"></div>
        @endforeach
    </div>

    <span class="ss-label">03 / 07</span>
</section>
