{{-- ═══════════════════════════════════════════════════════
     6.  SERVICES — Key offerings from Netlify
═══════════════════════════════════════════════════════ --}}
<section id="services" style="padding:100px 24px;background:var(--dark-950);">
    <div style="max-width:1280px;margin:0 auto;">
        <div style="text-align:center;margin-bottom:56px;">
            <div class="sec-label sr" style="justify-content:center;">{{ __('What We Do') }}</div>
            <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);margin-bottom:12px;">
                <span class="am">አገልግሎቶች</span> — {{ __('Our Services') }}
            </h2>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;">
            @foreach([
                ['am' => 'መንፈሳዊ ኮርሶች', 'en' => __('Spiritual Courses'), 'desc' => __('Teaching the traditions and practices of the Ethiopian Orthodox faith'), 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
                ['am' => 'ስነ ምግባር', 'en' => __('Character Development'), 'desc' => __('Raising youth with strong moral values to serve church and country'), 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
                ['am' => 'የአብነት ትምህርት', 'en' => __('Religious Education'), 'desc' => __('Traditional church education producing deacons and religious scholars'), 'icon' => 'M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9'],
            ] as $svc)
            <div class="card sr" style="padding:32px;text-align:center;" data-delay="{{ $loop->index * 100 }}">
                <div style="width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,rgba(26,68,247,.15),rgba(26,68,247,.05));border:1px solid rgba(26,68,247,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                    <svg width="26" height="26" fill="none" stroke="var(--blue-500)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $svc['icon'] }}"/></svg>
                </div>
                <h3 class="am" style="font-size:1.05rem;font-weight:600;color:var(--text-display);margin-bottom:6px;">{{ $svc['am'] }}</h3>
                <div style="font-size:.82rem;color:var(--blue-400);font-weight:500;margin-bottom:12px;">{{ $svc['en'] }}</div>
                <p style="font-size:.84rem;color:var(--parchment-40);line-height:1.7;">{{ $svc['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>