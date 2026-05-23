{{-- ═══════════════════════════════════════════════════════
     HERO — Finote Tsidik Sunday School
═══════════════════════════════════════════════════════ --}}
<section id="hero-section" style="position:relative;min-height:100vh;display:flex;align-items:flex-end;overflow:hidden;background:var(--bg-950);">

    {{-- Full-width background image --}}
    <div style="position:absolute;inset:0;background:url('{{ asset('images/hero-bg.jpg') }}') center/cover no-repeat;filter:brightness(.8) saturate(1.05);"></div>

    {{-- Gradient overlay: strong at bottom for text, light at top --}}
    <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(5,10,28,.95) 0%,rgba(5,10,28,.5) 50%,rgba(5,10,28,.1) 100%);"></div>

    {{-- Content pinned to bottom --}}
    <div class="hero-fade-in" style="position:relative;z-index:2;width:100%;max-width:1280px;margin:0 auto;padding:0 40px 72px;">

        {{-- Eyebrow --}}
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
            <div style="width:28px;height:1px;background:var(--gold);"></div>
            <span style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);font-weight:500;">Sunday school &middot; ሰንበት ትምህርት ቤት</span>
        </div>

        {{-- Headline: school name --}}
        <h1 style="font-family:'Playfair Display',serif;font-weight:700;font-size:clamp(2.4rem,6vw,4.5rem);line-height:1.08;color:#fff;letter-spacing:-.02em;margin-bottom:8px;">
            {{ __('Finote Tsidik') }}
        </h1>

        {{-- Amharic full name --}}
        <p class="am" style="font-size:clamp(1.1rem,2.5vw,1.6rem);color:rgba(255,255,255,.6);margin-bottom:18px;font-weight:400;letter-spacing:.02em;">
            {{ __('ፍኖተ ጽድቅ ሰንበት ትምህርት ቤት') }}
        </p>

        {{-- Subtext --}}
        <p style="font-size:clamp(.9rem,1.4vw,1.05rem);color:rgba(255,255,255,.45);max-width:520px;margin:0 0 32px;line-height:1.8;font-weight:400;">
            {{ __('Walking the path of righteousness together — nurturing children in faith, scripture, and community since the very beginning.') }}
        </p>

        {{-- CTAs --}}
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:48px;">

            <a href="{{ route('about') }}" class="btn btn-gold" style="padding:13px 28px;font-size:.9rem;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m-8-8h16"/></svg>
                {{ __('Enroll Your Child') }}
            </a>

            <a href="{{ route('about') }}" class="btn btn-ghost" style="padding:12px 28px;font-size:.9rem;border-color:rgba(255,255,255,.15);color:rgba(255,255,255,.7);">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ __('Watch Intro Video') }}
            </a>
        </div>

        {{-- Stats row --}}
        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;padding-top:24px;border-top:1px solid rgba(255,255,255,.08);">

            <div style="display:flex;flex-direction:column;gap:3px;">
                <span style="font-size:1.25rem;font-weight:500;color:#fff;letter-spacing:-.02em;">200+</span>
                <span style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.35);">{{ __('Children Enrolled') }}</span>
            </div>

            <div style="width:1px;height:28px;background:rgba(255,255,255,.08);"></div>

            <div style="display:flex;flex-direction:column;gap:3px;">
                <span style="font-size:1.25rem;font-weight:500;color:#fff;letter-spacing:-.02em;">12</span>
                <span style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.35);">{{ __('Classes') }}</span>
            </div>

            <div style="width:1px;height:28px;background:rgba(255,255,255,.08);"></div>

            <div style="display:flex;flex-direction:column;gap:3px;">
                <span style="font-size:1.25rem;font-weight:500;color:#fff;letter-spacing:-.02em;">20 {{ __('yrs') }}</span>
                <span style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.35);">{{ __('Serving Families') }}</span>
            </div>

            {{-- Scroll indicator --}}
            <div class="hide-mobile" style="display:flex;align-items:center;gap:6px;margin-left:auto;color:rgba(255,255,255,.3);">
                <span style="font-size:10px;letter-spacing:.15em;text-transform:uppercase;">{{ __('Scroll') }}</span>
                <div style="width:1px;height:28px;background:linear-gradient(to bottom,var(--gold),transparent);animation:heroPulse 2s ease-in-out infinite;"></div>
            </div>

        </div>
    </div>

</section>

@push('styles')
<style>
    .hero-fade-in {
        animation: heroFadeIn 1.2s cubic-bezier(.22,1,.36,1) forwards;
    }
    @keyframes heroFadeIn {
        0%   { opacity: 0; transform: translateY(24px); }
        100% { opacity: 1; transform: translateY(0); }
    }
    @keyframes heroPulse {
        0%,100% { opacity:.3; }
        50%      { opacity:.9; }
    }
    @media(max-width:768px) {
        .hero-fade-in { padding-left:20px !important; padding-right:20px !important; padding-bottom:48px !important; }
    }
</style>
@endpush