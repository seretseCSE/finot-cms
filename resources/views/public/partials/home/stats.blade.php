{{-- ═══════════════════════════════════════════════════════
     4.  STATS — Real member counts from database
═══════════════════════════════════════════════════════ --}}
<section id="stats-section" style="position:relative;padding:80px 24px;overflow:hidden;">
    {{-- Background image from Netlify --}}
    <div style="position:absolute;inset:0;background:url('{{ asset('images/stats-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25);"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-80),rgba(26,68,247,.2),var(--overlay-90));"></div>

    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:1;">
        <div style="text-align:center;margin-bottom:48px;">
            <div class="sec-label sr" style="justify-content:center;">{{ __('Our Community') }}</div>
            <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.6rem);">
                <span class="am">ተማሪዎቻችን</span> — {{ __('Our Students') }}
            </h2>
            <p class="sr" style="color:var(--parchment-60);margin-top:8px;">{{ __('Currently enrolled and active members') }}</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:24px;text-align:center;">
            <div class="card sr" style="padding:28px 20px;text-align:center;border-color:rgba(255,255,255,.06);">
                <x-tour-icon name="community" class="tour-icon-stat" aria-hidden="true" />
                <div style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);font-weight:700;color:#4ade80;line-height:1;">{{ App\Models\Member::count() }}</div>
                <div class="am" style="font-size:.95rem;color:var(--gold);margin-top:8px;font-weight:600;">አባልች</div>
                <div style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--parchment-40);margin-top:4px;">{{ __('Total Members') }}</div>
            </div>
            <div class="card sr" style="padding:28px 20px;text-align:center;border-color:rgba(255,255,255,.06);">
                <x-tour-icon name="education" class="tour-icon-stat" style="color:#60a5fa" aria-hidden="true" />
                <div style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);font-weight:700;color:#60a5fa;line-height:1;">{{ $totalLibraryResources ?? 0 }}</div>
                <div class="am" style="font-size:.95rem;color:var(--gold);margin-top:8px;font-weight:600;">መጻጻች</div>
                <div style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--parchment-40);margin-top:4px;">{{ __('Library Resources') }}</div>
            </div>
            <div class="card sr" style="padding:28px 20px;text-align:center;border-color:rgba(255,255,255,.06);">
                <x-tour-icon name="community" class="tour-icon-stat" aria-hidden="true" />
                <div style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);font-weight:700;color:var(--gold);line-height:1;">{{ App\Models\MemberGroup::count() }}</div>
                <div class="am" style="font-size:.95rem;color:var(--gold);margin-top:8px;font-weight:600;">ቡማርነች</div>
                <div style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--parchment-40);margin-top:4px;">{{ __('Member Groups') }}</div>
            </div>
            <div class="card sr" style="padding:28px 20px;text-align:center;border-color:rgba(255,255,255,.06);">
                <x-tour-icon name="community" class="tour-icon-stat" aria-hidden="true" />
                <div style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);font-weight:700;color:var(--text-display);line-height:1;">{{ App\Models\ParentModel::count() }}</div>
                <div class="am" style="font-size:.95rem;color:var(--gold);margin-top:8px;font-weight:600;">ወላላች</div>
                <div style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--parchment-40);margin-top:4px;">{{ __('Parents') }}</div>
            </div>
        </div>
    </div>
</section>