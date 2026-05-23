{{-- ═══════════════════════════════════════════════════════
     9.  CTA BANNER — Call to action with background
═══════════════════════════════════════════════════════ --}}
<section id="cta" style="position:relative;padding:100px 24px;overflow:hidden;">
    <div style="position:absolute;inset:0;background:url('{{ asset('images/cta-bg.jpg') }}') center/cover no-repeat;filter:brightness(.2);"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(26,68,247,.3),var(--overlay-85));"></div>

    <div style="max-width:800px;margin:0 auto;text-align:center;position:relative;z-index:1;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Get Involved') }}</div>
        <h2 class="display sr" style="font-size:clamp(2rem,4vw,3.2rem);margin-bottom:16px;">
            <span class="am">ይመዝገቡ</span> — {{ __('Register Today') }}
        </h2>
        <p class="sr" style="color:rgba(255,255,255,.7);font-size:1.05rem;line-height:1.75;margin-bottom:36px;">
            {{ __('Join our Sunday school community. Learn, grow, and serve together with faith-filled brothers and sisters.') }}
        </p>
        <div class="sr" style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;">
            <a href="{{ route('contact') }}" class="btn btn-gold">
                {{ __('Contact Us') }}
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
            <a href="{{ route('library') }}" class="btn btn-ghost">{{ __('View Programs') }}</a>
        </div>
    </div>
</section>