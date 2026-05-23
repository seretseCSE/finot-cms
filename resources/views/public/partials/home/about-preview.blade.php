{{-- ═══════════════════════════════════════════════════════
     3.  ABOUT PREVIEW — Two-column with image
═══════════════════════════════════════════════════════ --}}
<section id="story" style="padding:100px 40px;background:var(--bg-950);position:relative;overflow:hidden;">

    <div style="max-width:1280px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:72px;align-items:center;">

        {{-- Text --}}
        <div class="sr-l">

            {{-- Eyebrow --}}
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                <div style="width:24px;height:1px;background:var(--gold);"></div>
                <span style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);font-weight:500;">{{ __('About Us') }}</span>
            </div>

            <h2 style="font-family:'Playfair Display',serif;font-weight:700;font-size:clamp(1.8rem,3vw,2.8rem);line-height:1.1;color:#fff;letter-spacing:-.02em;margin-bottom:20px;">
                {{ __('Our Story') }}<br>
                <em style="color:var(--gold);font-style:italic;">{{ __('Since 1984 E.C.') }}</em>
            </h2>

            <p class="am" style="font-size:.92rem;color:rgba(255,255,255,.55);line-height:1.85;margin-bottom:14px;">
                ሰንበት ት/ቤታችን ፍኖተ ጽድቅ ሰንበት ትምህርትቤት በአየርጤና አካባቢ በነበሩ ትጉህ እና መንፈሳዊ ወጣቶች በ1984 ዓ.ም ተመሠረተች። በእነዚህ ቀላል የማይባሉ ዓመታት እጅግ በርካታ ውጤታማ የአገልግሎት ሥራዎች ተሠርተዋል፡፡
            </p>

            <p style="font-size:.88rem;color:rgba(255,255,255,.35);line-height:1.75;margin-bottom:32px;">
                {{ __('From children to adults, our Sunday school provides spiritual education, fellowship, and community service opportunities for all ages.') }}
            </p>

            <a href="{{ route('about') }}" class="btn btn-gold btn-mobile-full" style="padding:13px 28px;font-size:.9rem;display:inline-flex;align-items:center;gap:8px;">
                {{ __('Learn More') }}
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>

        {{-- Image --}}
        <div class="sr-r" style="position:relative;">

            <div style="border-radius:16px;overflow:hidden;border:1px solid rgba(255,255,255,.07);">
                <img src="{{ asset('images/features-bg.jpg') }}" alt="{{ __('Finote Tsidik Sunday School') }}" style="width:100%;height:clamp(260px,40vh,420px);object-fit:cover;display:block;filter:brightness(.9) saturate(1.05);">
            </div>

            {{-- Floating years badge --}}
            <div style="position:absolute;bottom:-16px;left:-16px;background:var(--gold);color:var(--bg-950);padding:14px 22px;border-radius:12px;text-align:center;z-index:2;">
                <div style="font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;line-height:1;">{{ date('Y') - 1992 + 8 }}+</div>
                <div style="font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-top:2px;">{{ __('Years') }}</div>
            </div>

            {{-- Subtle corner accent --}}
            <div style="position:absolute;top:-12px;right:-12px;width:48px;height:48px;border-top:2px solid var(--gold);border-right:2px solid var(--gold);border-radius:0 12px 0 0;opacity:.4;"></div>

        </div>

    </div>
</section>

@push('styles')
<style>
    @media(max-width:768px) {
        #story > div { gap: 48px !important; }
        #story .sr-r { margin-top: 16px; }
    }
</style>
@endpush