<section id="stats" class="snap-section" style="background:#050505;" data-counter-section>
    <div style="position:absolute;inset:0;background:url('{{ asset('images/stats-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(1.1);"></div>
    <div style="position:absolute;inset:0;background:rgba(5,5,5,0.65);"></div>
    <div class="bg-grid" style="position:absolute;inset:0;opacity:0.2;"></div>

    <div style="position:relative;z-index:2;width:100%;max-width:1100px;padding:0 32px;">
        <div class="ss-reveal" style="text-align:center;margin-bottom:60px;">
            <span class="ss-eyebrow" style="justify-content:center;">Impact</span>
            <h2 class="ss-section-title" data-delay="100">By the Numbers</h2>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:24px;">
            @foreach($stats as $stat)
            <div class="ss-reveal" data-delay="{{ $loop->index * 100 }}" style="text-align:center;padding:32px 16px;border-radius:12px;background:rgba(255,255,255,0.015);border:1px solid rgba(255,255,255,0.04);">
                <div class="stat-number">
                    <span data-count="{{ $stat['count'] }}" data-suffix="{{ $stat['suffix'] }}">0</span>{{ $stat['suffix'] }}
                </div>
                <p style="font-size:0.8rem;color:rgba(255,255,255,0.4);letter-spacing:0.08em;text-transform:uppercase;margin-top:8px;">{{ $stat['label'] }}</p>
            </div>
            @endforeach
        </div>
    </div>

    <span class="ss-label">04 / 07</span>
</section>
