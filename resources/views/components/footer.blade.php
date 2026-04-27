{{-- ═══════════════════════════════════════
     FOOTER — Finot-Tsidik Sunday School
     Netlify brand colors: #1A44F7 + #F3BA15
═══════════════════════════════════════ --}}
<footer style="background:var(--bg-950);border-top:1px solid var(--border-subtle);position:relative;overflow:hidden;">

    {{-- Decorative background elements --}}
    <div style="position:absolute;top:-100px;right:-100px;width:400px;height:400px;border-radius:50%;background:var(--blue-primary);filter:blur(160px);opacity:.04;pointer-events:none;"></div>
    <div style="position:absolute;bottom:-80px;left:-80px;width:300px;height:300px;border-radius:50%;background:var(--gold);filter:blur(130px);opacity:.03;pointer-events:none;"></div>

    {{-- Ethiopian cross watermark --}}
    <svg style="position:absolute;right:5%;top:10%;width:180px;opacity:.02;pointer-events:none;" viewBox="0 0 100 100" fill="none">
        <rect x="43" y="6" width="14" height="88" rx="2" fill="#F3BA15"/>
        <rect x="6" y="43" width="88" height="14" rx="2" fill="#F3BA15"/>
        <rect x="35" y="35" width="30" height="30" rx="3" fill="var(--bg-950)"/>
        <rect x="39" y="39" width="22" height="22" rx="2" fill="#F3BA15" opacity=".35"/>
    </svg>

    {{-- Main footer content --}}
    <div style="max-width:1280px;margin:0 auto;padding:64px 24px 32px;">

        {{-- 4-column grid --}}
        <div style="display:grid;grid-template-columns:1.4fr 1fr 1fr 1.2fr;gap:48px;margin-bottom:48px;">

            {{-- Column 1: Brand --}}
            <div>
                <a href="/" style="display:flex;align-items:center;gap:12px;text-decoration:none;margin-bottom:20px;">
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" style="height:44px;width:auto;">
                    <div>
                        <div class="am" style="font-size:1.1rem;font-weight:700;color:var(--text-display);line-height:1.2;">ፍኖተ ጽድቅ</div>
                        <div style="font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;color:var(--gold);opacity:.8;">ሰንበት ትምህርት ቤት</div>
                    </div>
                </a>
                <p style="font-size:.85rem;color:var(--text-40);line-height:1.7;margin-bottom:24px;">
                    {{ __('Faith, service, and fellowship — building a stronger community through the light of the Gospel since 1984 E.C.') }}
                </p>

                {{-- Social links --}}
                <div style="display:flex;gap:10px;">
                    @foreach([
                        ['icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.243 7.757l-1.414 1.414L12 10.914l-2.828 2.257-1.414-1.414L10.586 9.343 7.757 6.514 9.171 5.1 12 7.929l2.828-2.829 1.414 1.414L13.414 9.343l2.829 2.829z', 'href' => 'https://t.me/Finote1619', 'color' => '#0088CC'],
                        ['icon' => 'M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z', 'href' => 'https://web.facebook.com/FinoteTsidkeSundaySchool', 'color' => '#1877F2'],
                        ['icon' => 'M21.543 6.498C22 8.28 22 12 12 12s0 3.72-.457 5.502c-.254.985-.997 1.76-1.938 2.022C17.896 20 12 20 12 20s-5.893 0-7.605-.476c-.945-.266-1.687-1.04-1.938-2.022C2 15.72 2 12 2 12s0-3.72.457-5.502c.254-.985.997-1.76 1.938-2.022C6.107 4 12 4 12 4s5.896 0 7.605.476c.945.266 1.687 1.04 1.938 2.022zM10 15.5l6-3.5-6-3.5v7z', 'href' => 'https://youtube.com/@finote1619?si=HDw0RDGj0I1kSAKI', 'color' => '#FF0000'],
                        ['icon' => 'M12.525.02c1.31-.02 2.61.01 3.91.03 0 0 .34 2.16.73 3.39.65 2.03 1.82 2.77 3.15 3.36.73.32 1.48.53 2.25.64v3.73c-1.27-.13-2.49-.52-3.54-1.12-.56-.32-1.09-.69-1.57-1.1 0 2.39.01 4.78 0 7.17-.03 1.54-.5 3.07-1.37 4.32-1.4 2.01-3.75 3.26-6.19 3.3-1.55.03-3.1-.46-4.36-1.33-2.1-1.44-3.44-3.91-3.46-6.47-.01-.54.03-1.08.12-1.61.4-2.34 1.87-4.41 3.92-5.57 1.23-.69 2.64-1.06 4.06-1.07.14 0 .28 0 .42.01v3.84c-.15-.02-.3-.04-.45-.04-1.34-.01-2.62.72-3.27 1.87-.3.53-.43 1.14-.41 1.74.06 1.49 1.12 2.81 2.55 3.18.56.14 1.15.16 1.72.06.89-.16 1.67-.66 2.19-1.37.27-.36.44-.79.52-1.23.1-.56.09-1.13.09-1.7V.02h3.28z', 'href' => 'https://www.tiktok.com/@finote1619_?_t=8oiZzAgbsXu&_r=1', 'color' => '#000000'],
                        ['icon' => 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1112.324 0 6.162 6.162 0 01-12.324 0zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.405a1.44 1.44 0 112.881.001 1.44 1.44 0 01-2.881-.001z', 'href' => 'https://www.instagram.com/finote16_19?utm_source=qr&igsh=MW90eHZvOGlnZndwOA%3D', 'color' => '#E4405F'],
                    ] as $social)
                        <a href="{{ $social['href'] }}" style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:var(--glass);border:1px solid var(--border-subtle);text-decoration:none;transition:background .2s,border-color .2s,transform .2s;" onmouseover="this.style.background='var(--glass-hover)';this.style.borderColor='var(--blue-glow)';this.style.transform='translateY(-2px)'" onmouseout="this.style.background='var(--glass)';this.style.borderColor='var(--border-subtle)';this.style.transform='none'">
                            <svg width="16" height="16" fill="{{ $social['color'] }}" viewBox="0 0 24 24" style="opacity:.7;"><path d="{{ $social['icon'] }}"/></svg>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Column 2: Quick Links --}}
            <div>
                <h4 style="font-family:'Inter',sans-serif;font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:20px;font-weight:600;">
                    {{ __('Quick Links') }}
                </h4>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    @foreach([
                        ['href' => '/',               'label' => __('Home')],
                        ['href' => route('about'),    'label' => __('About Us')],
                                                ['href' => route('events'),   'label' => __('Events')],
                        ['href' => route('contact'),  'label' => __('Contact')],
                    ] as $link)
                        <a href="{{ $link['href'] }}" style="font-size:.85rem;color:var(--text-40);text-decoration:none;transition:color .2s,padding-left .2s;" onmouseover="this.style.color='var(--text-display)';this.style.paddingLeft='4px'" onmouseout="this.style.color='var(--text-40)';this.style.paddingLeft='0'">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Column 3: Resources --}}
            <div>
                <h4 style="font-family:'Inter',sans-serif;font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:20px;font-weight:600;">
                    {{ __('Resources') }}
                </h4>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    @foreach([
                        ['href' => route('media'),         'label' => __('Media')],
                        ['href' => route('songs.index'),   'label' => __('Songs')],
                        ['href' => route('library'),       'label' => __('Library')],
                        ['href' => route('blog.index'),    'label' => __('Blog')],
                        ['href' => route('tours.index'),   'label' => __('Tours')],
                        ['href' => route('fundraising.index'), 'label' => __('Fundraising')],
                    ] as $link)
                        <a href="{{ $link['href'] }}" style="font-size:.85rem;color:var(--text-40);text-decoration:none;transition:color .2s,padding-left .2s;" onmouseover="this.style.color='var(--text-display)';this.style.paddingLeft='4px'" onmouseout="this.style.color='var(--text-40)';this.style.paddingLeft='0'">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Column 4: Contact --}}
            <div>
                <h4 style="font-family:'Inter',sans-serif;font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:20px;font-weight:600;">
                    {{ __('Contact Us') }}
                </h4>
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div style="display:flex;align-items:flex-start;gap:12px;">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(26,68,247,.1);border:1px solid rgba(26,68,247,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="14" height="14" fill="none" stroke="var(--blue-primary)" viewBox="0 0 24 24" style="opacity:.8;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <div style="font-size:.85rem;color:var(--text-display);">{{ __('Addis Ababa, Ayertena') }}</div>
                            <div class="am" style="font-size:.78rem;color:var(--text-40);margin-top:2px;">አዲስ አበባ፣ አየርጤና</div>
                        </div>
                    </div>

                    <div style="display:flex;align-items:flex-start;gap:12px;">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(26,68,247,.1);border:1px solid rgba(26,68,247,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="14" height="14" fill="none" stroke="var(--blue-primary)" viewBox="0 0 24 24" style="opacity:.8;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <div style="font-size:.85rem;color:var(--text-display);">info@finotetsidik.org</div>
                        </div>
                    </div>

                    <div style="display:flex;align-items:flex-start;gap:12px;">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(26,68,247,.1);border:1px solid rgba(26,68,247,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="14" height="14" fill="none" stroke="var(--blue-primary)" viewBox="0 0 24 24" style="opacity:.8;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <div style="font-size:.82rem;color:var(--text-60);line-height:1.6;">
                                {{ __('Sunday: 2:00 - 5:00 PM') }}<br>
                                {{ __('Saturday: 3:00 - 5:30 PM') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Bottom bar --}}
        <div style="border-top:1px solid var(--border-subtle);padding-top:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <p style="font-size:.78rem;color:var(--text-40);margin:0;">
                &copy; {{ date('Y') }} <strong style="color:var(--text-60);">{{ config('app.name') }}</strong> &mdash; {{ __('All Rights Reserved') }}
            </p>
            <p style="font-size:.72rem;color:rgba(255,255,255,.25);margin:0;">
                {{ __('Designed by') }} <span style="color:var(--gold);opacity:.6;">AudioVisual</span>
            </p>
        </div>

    </div>
</footer>

@push('styles')
<style>
    @media(max-width:768px) {
        footer > div > div:first-child {
            grid-template-columns: 1fr !important;
            gap: 36px !important;
        }
    }
    @media(min-width:769px) and (max-width:1024px) {
        footer > div > div:first-child {
            grid-template-columns: 1fr 1fr !important;
        }
    }
</style>
@endpush
