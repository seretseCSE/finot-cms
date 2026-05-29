<section id="about" class="snap-section" style="background:#050505;">
    <div style="position:absolute;inset:0;background:url('{{ asset('images/features-bg.jpg') }}') center/cover no-repeat;filter:brightness(.3) saturate(1.05);"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(5,5,5,0.75) 0%,rgba(5,5,5,0.5) 100%);"></div>
    <div class="bg-grid-lg" style="position:absolute;inset:0;opacity:0.4;"></div>

    <div style="position:relative;z-index:2;width:100%;max-width:1200px;padding:0 32px;">
        <div class="ss-reveal" data-delay="0">
            <span class="ss-eyebrow">About Us</span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;">
            <div>
                <h2 class="ss-section-title ss-reveal" data-delay="100" style="margin-bottom:8px;">
                    Our Story
                </h2>
                <p class="am ss-reveal" data-delay="150" style="font-size:1.1rem;color:rgba(255,255,255,0.5);margin-bottom:24px;">
                    ከ1984 ዓ.ም. ጀምሮ
                </p>
                <p class="ss-reveal" data-delay="200" style="font-size:0.95rem;line-height:1.8;color:rgba(255,255,255,0.6);margin-bottom:16px;">
                    Founded in 1984 E.C. by a dedicated group of youth in the Ayertena area, Finote Tsidik Sunday School has grown from a small gathering into a vibrant spiritual community serving hundreds of families.
                </p>
                <p class="ss-reveal" data-delay="250" style="font-size:0.95rem;line-height:1.8;color:rgba(255,255,255,0.6);margin-bottom:32px;">
                    From children to adults, our Sunday school provides spiritual education, fellowship, and community service opportunities for all ages, rooted in the rich traditions of the Ethiopian Orthodox Tewahedo Church.
                </p>
                <div class="ss-reveal" data-delay="300" style="display:flex;gap:12px;flex-wrap:wrap;">
                    <a href="/about" class="ss-btn ss-btn-ghost" style="font-size:0.8rem;padding:10px 24px;">
                        Learn More
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>
            </div>

            <div class="ss-reveal-right" data-delay="300" style="position:relative;">
                <div style="position:relative;border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;">
                    <div style="text-align:center;padding:32px;">
                        <div style="font-size:clamp(3rem,6vw,5rem);font-weight:800;line-height:1;color:rgba(255,255,255,0.1);margin-bottom:8px;">1984</div>
                        <div style="font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase;color:rgba(255,255,255,0.25);">E.C. · ዓ.ም.</div>
                        <div style="margin-top:20px;width:40px;height:2px;background:rgba(26,68,247,0.3);margin-left:auto;margin-right:auto;"></div>
                        <div style="margin-top:16px;font-size:0.85rem;color:rgba(255,255,255,0.4);">Founded by faithful youth</div>
                    </div>
                </div>
                <div style="position:absolute;bottom:-12px;right:-12px;width:80px;height:80px;border-radius:12px;background:rgba(26,68,247,0.08);border:1px solid rgba(26,68,247,0.15);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.7rem;letter-spacing:0.1em;color:rgba(26,68,247,0.5);">
                    {{ (int)(now()->year - 1992) + 8 }}+ YRS
                </div>
            </div>
        </div>
    </div>

    <span class="ss-label">02 / 07</span>
</section>
