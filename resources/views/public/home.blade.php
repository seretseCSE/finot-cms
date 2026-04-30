@extends('layouts.public')

@section('title', config('app.name'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Full-viewport parallax with Netlify imagery
═══════════════════════════════════════════════════════ --}}
<section id="hero-section" style="position:relative;min-height:100vh;display:flex;align-items:center;overflow:hidden;background:var(--dark-950);">

    {{-- Parallax background image from Netlify --}}
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/hero-bg.jpg') }}') center/cover no-repeat;filter:brightness(.3) saturate(.8);will-change:transform;"></div>

    {{-- Blue + dark gradient overlay --}}
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-80) 0%,rgba(26,68,247,.18) 50%,var(--overlay-90) 100%);"></div>

    {{-- Tilet pattern --}}
    <div class="tilet" style="position:absolute;inset:0;opacity:.5;"></div>

    {{-- Floating Ethiopian cross decorators --}}
    <svg class="cross-deco" style="position:absolute;top:8%;right:8%;width:160px;" viewBox="0 0 100 100" fill="none">
        <rect x="43" y="6" width="14" height="88" rx="2" fill="#F3BA15"/>
        <rect x="6" y="43" width="88" height="14" rx="2" fill="#F3BA15"/>
        <rect x="35" y="35" width="30" height="30" rx="3" fill="var(--bg-950)"/><rect x="39" y="39" width="22" height="22" rx="2" fill="#F3BA15" opacity=".3"/>
    </svg>
    <svg class="cross-deco" style="position:absolute;bottom:12%;left:5%;width:90px;opacity:.04;" viewBox="0 0 100 100" fill="none">
        <rect x="43" y="6" width="14" height="88" rx="2" fill="#1A44F7"/>
        <rect x="6" y="43" width="88" height="14" rx="2" fill="#1A44F7"/>
    </svg>

    {{-- Ambient glows --}}
    <div style="position:absolute;top:15%;left:8%;width:500px;height:500px;border-radius:50%;background:var(--blue-primary);filter:blur(140px);opacity:.1;pointer-events:none;"></div>
    <div style="position:absolute;bottom:10%;right:12%;width:350px;height:350px;border-radius:50%;background:var(--gold);filter:blur(120px);opacity:.06;pointer-events:none;"></div>

    {{-- Content --}}
    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;padding:120px 24px 80px;width:100%;">

        {{-- Eyebrow --}}
        <div class="sec-label sr" style="margin-bottom:20px;">
            <span class="am">ፍኖተ ጽድቅ ሰንበት ትምህርት ቤት</span>
        </div>

        {{-- Headline --}}
        <h1 class="display sr" style="font-size:clamp(2.4rem,6vw,5rem);max-width:720px;margin-bottom:24px;color:var(--text-hero);">
            {{ __('A Place to') }}
            <em style="color:var(--gold);font-style:italic;"> {{ __('Belong') }}</em>
        </h1>

        <p class="sr" style="font-size:clamp(1rem,2vw,1.1rem);color:var(--text-60);max-width:540px;margin-bottom:36px;line-height:1.75;">
            {{ __('Faith, service, and fellowship — building a stronger community through the light of the Gospel since 1984 E.C.') }}
        </p>

        <div class="sr" style="display:flex;flex-wrap:wrap;gap:12px;">
            <a href="{{ route('about') }}" class="btn btn-primary btn-mobile-full">
                {{ __('About Us') }}
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
            <a href="{{ route('library') }}" class="btn btn-ghost btn-mobile-full">{{ __('Explore Resourses') }}</a>
        </div>

        {{-- Scroll indicator --}}
        <div class="hide-mobile" style="position:absolute;bottom:40px;left:50%;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;gap:6px;opacity:.4;">
            <span style="font-size:.65rem;letter-spacing:.18em;text-transform:uppercase;">{{ __('Scroll') }}</span>
            <div style="width:1px;height:36px;background:linear-gradient(to bottom,var(--gold),transparent);animation:pulse 2s infinite;"></div>
        </div>
    </div>
</section>

@push('styles')
<style>@keyframes pulse { 0%,100%{opacity:.4} 50%{opacity:1} }</style>
@endpush
{{-- ═══════════════════════════════════════════════════════
     1.5 ANNOUNCEMENTS — Horizontal cards if they exist
═══════════════════════════════════════════════════════ --}}
@if(count($announcements ?? []) > 0)
<section style="background:var(--dark-900);padding:60px 24px;position:relative;border-bottom:1px solid var(--border-subtle);">
    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:2;">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:32px;">
            <div>
                <div class="sec-label sr">{{ __('News') }}</div>
                <h2 class="display sr" style="font-size:clamp(1.5rem,3vw,2.2rem);">{{ __('Latest Announcements') }}</h2>
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                @if(auth()->check() && auth()->user()->hasRole(['admin', 'superadmin', 'internal_relations_head']))
                    <a href="{{ route('filament.admin.resources.announcements.create') }}" class="btn btn-primary sr" style="display:flex;align-items:center;gap:6px;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('New Announcement') }}
                    </a>
                @endif
                <a href="{{ route('news') }}" class="btn btn-ghost sr">{{ __('View All') }}</a>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">
            @foreach($announcements as $ann)
            <a href="{{ route('announcements.show', $ann->id) }}" class="card sr" style="padding:0;text-decoration:none;display:block;border-radius:16px;overflow:hidden;{{ $ann->is_urgent ? 'border-left:4px solid var(--red-primary);' : '' }}">
                {{-- Announcement Image --}}
                @if($ann->image)
                    <div style="width:100%;height:160px;overflow:hidden;background:var(--dark-800);">
                        <img src="{{ $ann->image_url }}" alt="{{ $ann->title }}" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                @endif
                
                <div style="padding:24px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <span style="font-size:.7rem;color:var(--parchment-40);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">{{ $ann->published_at ? $ann->published_at->format('M d, Y') : $ann->start_date->format('M d, Y') }}</span>
                    @if($ann->is_urgent)
                        <span style="background:rgba(239,68,68,.1);color:var(--red-primary);padding:2px 8px;border-radius:99px;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">{{ __('Urgent') }}</span>
                    @endif
                </div>
                <h3 class="am" style="font-size:.95rem;font-weight:700;color:var(--text-display);margin-bottom:8px;line-height:1.4;">
                    {{ Str::limit(app()->getLocale() === 'am' ? ($ann->title_am ?? $ann->title) : $ann->title, 60) }}
                </h3>
                <p class="am" style="font-size:.82rem;color:var(--text-40);line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:0;">
                    {!! strip_tags(app()->getLocale() === 'am' ? ($ann->content_am ?? $ann->content) : $ann->content) !!}
                </p>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ═══════════════════════════════════════════════════════
     2.  SERVICE INFO BAR — Schedule + Location + Quick Info
═══════════════════════════════════════════════════════ --}}
<section style="background:linear-gradient(90deg,var(--blue-primary),#2952FF);padding:0;position:relative;overflow:hidden;z-index:10;">
    <div style="max-width:1280px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:0;">
        @foreach([
            ['icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z', 'label' => __('Addis Ababa, Ayertena'), 'sub' => 'አዲስ አበባ፣ አየርጤና'],
            ['icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => __('Sunday 2:00 - 5:00 PM'), 'sub' => __('Saturday 3:00 - 5:30 PM')],
            ['icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', 'label' => '+251 911 123 456', 'sub' => __('Call Us')],
        ] as $info)
        <div style="padding:24px;display:flex;align-items:center;gap:16px;border-right:1px solid rgba(255,255,255,.12);border-bottom:1px solid rgba(255,255,255,.05);">
            <svg width="22" height="22" fill="none" stroke="rgba(255,255,255,.8)" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $info['icon'] }}"/></svg>
            <div>
                <div style="font-size:.9rem;color:#fff;font-weight:600;">{{ $info['label'] }}</div>
                <div class="am" style="font-size:.75rem;color:rgba(255,255,255,.7);">{{ $info['sub'] }}</div>
            </div>
        </div>
        @endforeach
    </div>
</section>


{{-- ═══════════════════════════════════════════════════════
     3.  ABOUT PREVIEW — Two-column with Netlify image
═══════════════════════════════════════════════════════ --}}
<section style="padding:100px 24px;background:var(--dark-950);position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;" class="tilet" style="opacity:.3;"></div>

    <div style="max-width:1280px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:64px;align-items:center;position:relative;z-index:1;">

        {{-- Text --}}
        <div class="sr-l">
            <div class="sec-label">{{ __('About Us') }}</div>
            <h2 class="display" style="font-size:clamp(1.8rem,3vw,2.8rem);margin-bottom:20px;">
                {{ __('Our Story') }}
                <span style="color:var(--gold);">{{ __('Since 1984 E.C.') }}</span>
            </h2>
            <p class="am" style="font-size:.92rem;color:var(--text-60);line-height:1.8;margin-bottom:16px;">
                ሰንበት ት/ቤታችን ፍኖተ ጽድቅ ሰንበት ትምህርትቤት በአየርጤና አካባቢ በነበሩ ትጉህ እና መንፈሳዊ ወጣቶች በ1984 ዓ.ም ተመሠረተች። በእነዚህ ቀላል የማይባሉ ዓመታት እጅግ በርካታ ውጤታማ የአገልግሎት ሥራዎች ተሠርተዋል፡፡
            </p>
            <p style="font-size:.88rem;color:var(--text-40);line-height:1.7;margin-bottom:28px;">
                {{ __('From children to adults, our Sunday school provides spiritual education, fellowship, and community service opportunities for all ages.') }}
            </p>
            <a href="{{ route('about') }}" class="btn btn-primary btn-mobile-full">
                {{ __('Learn More') }}
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>

        {{-- Image --}}
        <div class="sr-r" style="position:relative;">
            <div style="border-radius:var(--r-lg);overflow:hidden;border:1px solid var(--border-subtle);box-shadow:0 20px 60px rgba(0,0,0,.4);">
                <img src="{{ asset('images/features-bg.jpg') }}" alt="{{ __('Finot-Tsidik Sunday School') }}" style="width:100%;height:clamp(250px, 40vh, 400px);object-fit:cover;display:block;">
            </div>
            {{-- Floating badge --}}
            <div style="position:absolute;bottom:-15px;left:-15px;background:var(--gold);color:var(--bg-950);padding:12px 20px;border-radius:var(--r);box-shadow:0 8px 30px rgba(243,186,21,.3);text-align:center;z-index:2;">
                <div style="font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;line-height:1;">{{ date('Y') - 1992 + 8 }}+</div>
                <div style="font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;">{{ __('Years') }}</div>
            </div>
        </div>

    </div>
</section>

@push('styles')
<style>
    @media(max-width:768px) {
        section > div[style*="grid-template-columns:1fr 1fr"] {
            grid-template-columns: 1fr !important;
            gap: 36px !important;
        }
    }
</style>
@endpush


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
                <div style="font-size:1.8rem;margin-bottom:8px;">👥</div>
                <div style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);font-weight:700;color:#4ade80;line-height:1;">{{ App\Models\Member::count() }}</div>
                <div class="am" style="font-size:.95rem;color:var(--gold);margin-top:8px;font-weight:600;">አባልች</div>
                <div style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--parchment-40);margin-top:4px;">{{ __('Total Members') }}</div>
            </div>
            <div class="card sr" style="padding:28px 20px;text-align:center;border-color:rgba(255,255,255,.06);">
                <div style="font-size:1.8rem;margin-bottom:8px;">📚</div>
                <div style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);font-weight:700;color:#60a5fa;line-height:1;">{{ $totalLibraryResources ?? 0 }}</div>
                <div class="am" style="font-size:.95rem;color:var(--gold);margin-top:8px;font-weight:600;">መጻጻች</div>
                <div style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--parchment-40);margin-top:4px;">{{ __('Library Resources') }}</div>
            </div>
            <div class="card sr" style="padding:28px 20px;text-align:center;border-color:rgba(255,255,255,.06);">
                <div style="font-size:1.8rem;margin-bottom:8px;">👨‍👩‍👧‍👦</div>
                <div style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);font-weight:700;color:var(--gold);line-height:1;">{{ App\Models\MemberGroup::count() }}</div>
                <div class="am" style="font-size:.95rem;color:var(--gold);margin-top:8px;font-weight:600;">ቡማርነች</div>
                <div style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--parchment-40);margin-top:4px;">{{ __('Member Groups') }}</div>
            </div>
            <div class="card sr" style="padding:28px 20px;text-align:center;border-color:rgba(255,255,255,.06);">
                <div style="font-size:1.8rem;margin-bottom:8px;">👨‍👩‍👧‍👦</div>
                <div style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3rem);font-weight:700;color:var(--text-display);line-height:1;">{{ App\Models\ParentModel::count() }}</div>
                <div class="am" style="font-size:.95rem;color:var(--gold);margin-top:8px;font-weight:600;">ወላላች</div>
                <div style="font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--parchment-40);margin-top:4px;">{{ __('Parents') }}</div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     4.5 OUR LEADERSHIP — 10 Admins
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:100px 24px;background:var(--dark-900);position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;" class="tilet" style="opacity:.2;"></div>
    <div style="position:absolute;top:20%;right:10%;width:400px;height:400px;border-radius:50%;background:var(--blue-primary);filter:blur(140px);opacity:.05;pointer-events:none;"></div>

    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:1;">
        <div style="text-align:center;margin-bottom:60px;">
            <div class="sec-label sr" style="justify-content:center;">{{ __('Leadership') }}</div>
            <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);margin-bottom:12px;">
                <span class="am">የሰንበት ትምህርት ቤቱ አመራሮች</span> — {{ __('Our Leadership') }}
            </h2>
            <p class="sr" style="color:var(--parchment-60);max-width:600px;margin:0 auto;">{{ __('Dedicated servants leading our Sunday school with faith and integrity.') }}</p>
        </div>

        {{-- Top 3: President, VP, Secretary --}}
        <div style="display:flex;justify-content:center;gap:32px;flex-wrap:wrap;margin-bottom:64px;">
            @foreach([
                ['name' => 'Melake Hayil Kesis Solomon Mulugeta', 'am' => 'መልአከ ኃይል ቀሲስ ሰሎሞን ሙሉጌታ', 'title' => __('President'), 'title_am' => 'ሰብሳቢ', 'icon' => '✝️', 'color' => '#1A44F7'],
                ['name' => 'Deacon Yosef Tefera', 'am' => 'ዲያቆን ዮሴፍ ተፈራ', 'title' => __('Vice President'), 'title_am' => 'ምክትል ሰብሳቢ', 'icon' => '📖', 'color' => '#F3BA15'],
                ['name' => 'Sister Hiwot Abera', 'am' => 'እህት ሕይወት አበራ', 'title' => __('General Secretary'), 'title_am' => 'ዋና ጸሐፊ', 'icon' => '✍️', 'color' => '#10B981'],
            ] as $leader)
            <div class="sr" style="width:280px;text-align:center;">
                {{-- Circular Image Wrap --}}
                <div style="width:clamp(140px, 20vw, 180px);height:clamp(140px, 20vw, 180px);border-radius:50%;margin:0 auto 24px;position:relative;padding:8px;background:var(--glass);border:2px dashed var(--gold-border);">
                    <div style="width:100%;height:100%;border-radius:50%;background:linear-gradient(135deg, {{ $leader['color'] }}22, {{ $leader['color'] }}44);display:flex;align-items:center;justify-content:center;font-size:clamp(2rem, 5vw, 3.5rem);border:1px solid {{ $leader['color'] }}33;box-shadow:0 10px 30px rgba(0,0,0,0.1);">
                        {{ $leader['icon'] }}
                    </div>
                    {{-- Small decorative badge --}}
                    <div style="position:absolute;bottom:5px;right:5px;width:38px;height:38px;border-radius:50%;background:var(--gold);color:var(--bg-950);display:flex;align-items:center;justify-content:center;font-size:1rem;box-shadow:0 4px 15px rgba(243,186,21,0.4);border:3px solid var(--bg-950);">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                    </div>
                </div>
                {{-- Titles & Name --}}
                <div style="font-size:.7rem;color:var(--gold);font-weight:700;text-transform:uppercase;letter-spacing:.12em;margin-bottom:8px;">{{ $leader['title'] }}</div>
                <h3 class="am" style="font-size:1.15rem;font-weight:700;color:var(--text-display);margin-bottom:4px;line-height:1.3;">{{ $leader['am'] }}</h3>
                <div style="font-size:.85rem;color:var(--text-60);">{{ $leader['name'] }}</div>
            </div>
            @endforeach
        </div>

        {{-- Department Heads --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:32px;justify-content:center;">
            @foreach($departments as $department)
            <div class="sr" style="text-align:center;">
                {{-- Circular Image Wrap --}}
                <div style="width:clamp(100px, 15vw, 120px);height:clamp(100px, 15vw, 120px);border-radius:50%;margin:0 auto 20px;position:relative;padding:6px;background:var(--glass);border:2px dashed var(--gold-border);">
                    <div style="width:100%;height:100%;border-radius:50%;background:linear-gradient(135deg, #1A44F722, #1A44F744);display:flex;align-items:center;justify-content:center;font-size:clamp(1.5rem, 4vw, 2.5rem);border:1px solid #1A44F733;box-shadow:0 8px 25px rgba(0,0,0,0.1);">
                        🏢
                    </div>
                    {{-- Small decorative badge --}}
                    <div style="position:absolute;bottom:4px;right:4px;width:32px;height:32px;border-radius:50%;background:var(--gold);color:var(--bg-950);display:flex;align-items:center;justify-content:center;font-size:.8rem;box-shadow:0 3px 12px rgba(243,186,21,0.4);border:2px solid var(--bg-950);">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                    </div>
                </div>
                {{-- Title/Role --}}
                <div style="font-size:.7rem;color:var(--gold);font-weight:700;text-transform:uppercase;letter-spacing:.12em;margin-bottom:8px;">{{ __('Department Head') }}</div>
                {{-- Department Name in Amharic --}}
                <h3 class="am" style="font-size:1rem;font-weight:700;color:var(--text-display);margin-bottom:4px;line-height:1.3;">{{ $department->name_am ?? $department->name_en }}</h3>
                {{-- Department Head Name in English --}}
                <div style="font-size:.8rem;color:var(--text-60);">{{ $department->headUserName }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════════════════════
     5.  PROGRAMS — 4 educational divisions with real counts
═══════════════════════════════════════════════════════ --}}
<section style="padding:100px 24px;background:linear-gradient(180deg,var(--dark-900) 0%,var(--dark-950) 100%);position:relative;">
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:1;">
        <div style="text-align:center;margin-bottom:56px;">
            <div class="sec-label sr" style="justify-content:center;">{{ __('Education') }}</div>
            <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);margin-bottom:12px;">{{ __('Our Programs') }}</h2>
            <p class="sr" style="color:var(--parchment-60);max-width:500px;margin:0 auto;">{{ __('Spiritual education designed for every stage of life') }}</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;">
            @foreach([
                ['am' => 'ህጻናት', 'en' => __('Children\'s Program'), 'desc' => __('Grades 1-8: Foundation of faith through interactive lessons and activities'), 'count' => \App\Models\Member::where('member_type', 'Kids')->count(), 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'color' => '#4ade80'],
                ['am' => 'አዳጊ',   'en' => __('Youth Program'),      'desc' => __('Grades 9-12: Character development and deeper spiritual understanding'), 'count' => \App\Models\Member::where('member_type', 'Youth')->count(), 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'color' => '#60a5fa'],
                ['am' => 'ወጣት',  'en' => __('Young Adults'),       'desc' => __('18+: Advanced theology, leadership training, and community service'), 'count' => \App\Models\Member::where('member_type', 'Adult')->count(), 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'color' => 'var(--gold)'],
                ['am' => 'የርቀት', 'en' => __('Distance Learning'), 'desc' => __('Online spiritual education for those who cannot attend in person'), 'count' => max(0, \App\Models\Member::count() - \App\Models\Member::where('member_type', 'Kids')->count() - \App\Models\Member::where('member_type', 'Youth')->count() - \App\Models\Member::where('member_type', 'Adult')->count()), 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => '#c084fc'],
            ] as $prog)
            <div class="card sr" style="padding:32px 28px;position:relative;overflow:hidden;" data-delay="{{ $loop->index * 100 }}">
                {{-- Accent line --}}
                <div style="position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,{{ $prog['color'] }},transparent);"></div>

                <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(26,68,247,.08);border:1px solid rgba(26,68,247,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="22" height="22" fill="none" stroke="{{ $prog['color'] }}" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $prog['icon'] }}"/></svg>
                    </div>
                    <div>
                        <h3 class="am" style="font-size:1.1rem;font-weight:700;color:var(--text-display);">{{ $prog['am'] }}</h3>
                        <div style="font-size:.78rem;color:var(--parchment-60);">{{ $prog['en'] }}</div>
                    </div>
                </div>

                <p style="font-size:.84rem;color:var(--parchment-40);line-height:1.7;margin-bottom:20px;">{{ $prog['desc'] }}</p>

                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:{{ $prog['color'] }};"></div>
                        <span style="font-size:.78rem;color:var(--parchment-60);">
                            <strong style="color:{{ $prog['color'] }};">{{ $prog['count'] }}</strong> {{ __('students') }}
                        </span>
                    </div>
                    <a href="{{ route('library') }}" style="font-size:.78rem;color:var(--blue-500);text-decoration:none;font-weight:500;">{{ __('Learn more') }} →</a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════════════════════
     6.  SERVICES — Key offerings from Netlify
═══════════════════════════════════════════════════════ --}}
<section style="padding:100px 24px;background:var(--dark-950);">
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


{{-- ═══════════════════════════════════════════════════════
     7.  UPCOMING EVENTS — Real data from database
═══════════════════════════════════════════════════════ --}}
<section style="padding:100px 24px;background:linear-gradient(180deg,var(--dark-900),var(--dark-950));position:relative;">
    <div style="max-width:1280px;margin:0 auto;">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:48px;">
            <div>
                <div class="sec-label sr">{{ __('Calendar') }}</div>
                <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);">{{ __('Upcoming Events') }}</h2>
            </div>
            <a href="{{ route('news', ['tab' => 'events']) }}" class="btn btn-ghost sr">{{ __('View All Events') }}</a>
        </div>

        @if($upcomingEvents && $upcomingEvents->count() > 0)
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
            @foreach($upcomingEvents as $event)
            <div class="card sr" style="padding:24px;display:flex;gap:16px;align-items:flex-start;" data-delay="{{ $loop->index * 80 }}">
                {{-- Date badge --}}
                <div style="width:56px;min-width:56px;text-align:center;padding:10px 8px;border-radius:12px;background:linear-gradient(135deg,rgba(26,68,247,.15),rgba(26,68,247,.05));border:1px solid rgba(26,68,247,.2);">
                    <div style="font-size:.6rem;letter-spacing:.08em;text-transform:uppercase;color:var(--blue-400);font-weight:600;">{{ $event->date_time->format('M') }}</div>
                    <div style="font-size:1.5rem;font-weight:800;color:var(--text-display);line-height:1.2;">{{ $event->date_time->format('d') }}</div>
                </div>
                {{-- Event info --}}
                <div style="flex:1;min-width:0;">
                    <h3 style="font-size:.95rem;font-weight:600;color:var(--text-display);margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $event->name }}</h3>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <div style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--parchment-60);">
                            <svg width="12" height="12" fill="none" stroke="var(--gold)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $event->date_time->format('h:i A') }}
                        </div>
                        @if($event->location)
                        <div style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--parchment-40);">
                            <svg width="12" height="12" fill="none" stroke="var(--parchment-40)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            {{ Str::limit($event->location, 30) }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="card sr" style="padding:48px;text-align:center;">
            <div style="font-size:2rem;margin-bottom:12px;">📅</div>
            <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--text-display);margin-bottom:8px;">{{ __('No Upcoming Events') }}</h3>
            <p style="color:var(--parchment-60);font-size:.85rem;">{{ __('Check back later for new events.') }}</p>
        </div>
        @endif
    </div>
</section>




{{-- ═══════════════════════════════════════════════════════
     9.  CTA BANNER — Call to action with background
═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:100px 24px;overflow:hidden;">
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


{{-- ═══════════════════════════════════════════════════════
     10.  FUNDRAISING — Skeleton → API loaded
═══════════════════════════════════════════════════════ --}}
<section style="padding:80px 24px;background:var(--dark-950);position:relative;overflow:hidden;">
    <div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:600px;height:300px;border-radius:50%;background:var(--gold);filter:blur(130px);opacity:.03;pointer-events:none;"></div>

    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:1;">
        <div style="text-align:center;margin-bottom:48px;">
            <div class="sec-label sr" style="justify-content:center;">{{ __('Support Our Mission') }}</div>
            <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);margin-bottom:12px;">{{ __('Fundraising Progress') }}</h2>
        </div>

        <div id="fundraising-progress" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;">
            @for($i = 0; $i < 3; $i++)
            <div class="card" style="padding:24px;">
                <div class="skel" style="height:100px;margin-bottom:16px;"></div>
                <div class="skel" style="height:18px;width:70%;margin-bottom:10px;"></div>
                <div class="skel" style="height:6px;margin-bottom:8px;"></div>
                <div style="display:flex;justify-content:space-between;"><div class="skel" style="height:12px;width:40%;"></div><div class="skel" style="height:12px;width:20%;"></div></div>
            </div>
            @endfor
        </div>

        <div style="text-align:center;margin-top:36px;" class="sr">
            <a href="{{ route('fundraising.index') }}" class="btn btn-primary">{{ __('View All Campaigns') }}</a>
        </div>
    </div>
</section>

@push('scripts')
<script>
async function loadFundraising(){
    try {
        const res = await fetch('{{ route('fundraising.api') }}');
        const data = await res.json();
        const container = document.getElementById('fundraising-progress');

        if(!data.campaigns.length){
            container.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:48px 24px;">
                <div style="font-size:2rem;margin-bottom:12px;">💰</div>
                <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--text-display);margin-bottom:8px;">{{ __('No Active Campaigns') }}</h3>
                <p style="color:var(--parchment-60);font-size:.85rem;">{{ __('Check back later for new fundraising campaigns.') }}</p>
            </div>`;
            return;
        }

        container.innerHTML = data.campaigns.slice(0,3).map(c => `
            <div class="card" style="overflow:hidden;padding:0;">
                <div style="height:80px;background:linear-gradient(135deg,rgba(26,68,247,.6),var(--overlay-80));display:flex;align-items:flex-end;padding:14px;position:relative;">
                    <span style="position:relative;font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;padding:4px 10px;border-radius:99px;background:rgba(243,186,21,.15);border:1px solid rgba(243,186,21,.25);color:var(--gold);">${c.status}</span>
                </div>
                <div style="padding:20px;">
                    <h3 style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:600;color:var(--text-display);margin-bottom:12px;">${c.campaign_name}</h3>
                    <div style="margin-bottom:6px;display:flex;justify-content:space-between;font-size:.8rem;">
                        <span style="color:var(--text-display);font-weight:600;">ETB ${Number(c.total_raised).toLocaleString()}</span>
                        <span style="color:var(--gold);">${c.progress_percentage}%</span>
                    </div>
                    <div class="prog-track"><div class="prog-fill" id="pf-${c.id}" style="width:0%;"></div></div>
                    <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--parchment-40);margin-top:6px;">
                        <span>Goal: ETB ${Number(c.target_amount).toLocaleString()}</span>
                        ${c.days_remaining!==null ? `<span>${c.days_remaining} days left</span>` : ''}
                    </div>
                </div>
            </div>`).join('');

        requestAnimationFrame(()=>{
            data.campaigns.slice(0,3).forEach(c=>{
                const el = document.getElementById('pf-'+c.id);
                if(el) setTimeout(()=>{ el.style.width = Math.min(100,c.progress_percentage)+'%'; }, 300);
            });
        });
    } catch(e) {
        document.getElementById('fundraising-progress').innerHTML = `
            <div style="grid-column:1/-1;text-align:center;padding:32px;color:var(--parchment-60);font-size:.85rem;">
                {{ __('Unable to load fundraising data.') }}
            </div>`;
    }
}
document.addEventListener('DOMContentLoaded', loadFundraising);
</script>
@endpush


{{-- ═══════════════════════════════════════════════════════
     11.  RECENT BLOG POSTS — From database
═══════════════════════════════════════════════════════ --}}
@if($recentPosts && $recentPosts->count() > 0)
<section style="padding:80px 24px;background:linear-gradient(180deg,var(--dark-950),var(--dark-900));">
    <div style="max-width:1280px;margin:0 auto;">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:48px;">
            <div>
                <div class="sec-label sr">{{ __('News') }}</div>
                <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);">{{ __('Latest Posts') }}</h2>
            </div>
            <a href="{{ route('blog.index') }}" class="btn btn-ghost sr">{{ __('View All Posts') }}</a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;">
            @foreach($recentPosts as $post)
            <a href="{{ route('blog.show', $post->slug) }}" class="card sr" style="overflow:hidden;text-decoration:none;display:block;" data-delay="{{ $loop->index * 80 }}">
                @if($post->featured_image)
                <div style="height:200px;overflow:hidden;">
                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" style="width:100%;height:100%;object-fit:cover;transition:transform .5s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                </div>
                @else
                <div style="height:160px;background:linear-gradient(135deg,var(--dark-700),var(--blue-primary));display:flex;align-items:center;justify-content:center;">
                    <svg width="40" height="40" fill="none" stroke="rgba(255,255,255,.3)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                </div>
                @endif
                <div style="padding:20px;">
                    @if($post->tags)
                    <span style="display:inline-block;font-size:.7rem;padding:3px 10px;border-radius:99px;background:rgba(26,68,247,.12);border:1px solid rgba(26,68,247,.25);color:var(--blue-400);margin-bottom:10px;">{{ Str::limit($post->tags, 20) }}</span>
                    @endif
                    <h3 style="font-size:1rem;font-weight:600;color:var(--text-display);margin-bottom:8px;line-height:1.4;">{{ $post->title }}</h3>
                    <div style="display:flex;align-items:center;gap:8px;font-size:.75rem;color:var(--parchment-40);">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $post->published_at ? $post->published_at->diffForHumans() : ($post->publish_date ? $post->publish_date->format('M d, Y') : '') }}
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif


{{-- ═══════════════════════════════════════════════════════
     12.  LIBRARY RESOURCES — Featured downloads
═══════════════════════════════════════════════════════ --}}
<section style="padding:80px 24px;background:var(--dark-950);">
    <div style="max-width:1280px;margin:0 auto;">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:44px;">
            <div>
                <div class="sec-label sr">{{ __('Knowledge') }}</div>
                <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);">{{ __('Library Resources') }}</h2>
            </div>
            <a href="{{ route('library') }}" class="btn btn-ghost sr">{{ __('View All Resources') }}</a>
        </div>

        @if($featuredLibraryResources && $featuredLibraryResources->count() > 0)
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;">
            @foreach($featuredLibraryResources as $resource)
            <div class="card sr" style="overflow:hidden;" data-delay="{{ $loop->index * 70 }}">
                <div style="height:100px;background:linear-gradient(135deg,var(--dark-700),var(--blue-primary));display:flex;align-items:center;justify-content:center;position:relative;">
                    <svg style="width:40px;color:var(--gold);opacity:.6;" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/>
                    </svg>
                    <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(5,10,28,.5),transparent);"></div>
                </div>
                <div style="padding:16px;">
                    <h3 style="font-size:.88rem;font-weight:600;color:var(--text-display);margin-bottom:6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $resource->title }}</h3>
                    @if($resource->category)
                    <span style="font-size:.68rem;padding:2px 8px;border-radius:99px;background:rgba(26,68,247,.15);border:1px solid rgba(26,68,247,.25);color:var(--blue-400);display:inline-block;margin-bottom:8px;">{{ $resource->category->name }}</span>
                    @endif
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.72rem;color:var(--parchment-40);">{{ $resource->formatted_file_size }}</span>
                        <a href="{{ route('library.download', $resource) }}" style="display:flex;align-items:center;gap:5px;font-size:.78rem;font-weight:600;color:var(--gold);text-decoration:none;">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            {{ __('Download') }}
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($totalLibraryResources > 0)
        <div style="text-align:center;margin-top:24px;">
            <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 16px;border-radius:99px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);color:#86efac;font-size:.78rem;">
                <svg width="13" height="13" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ __(':count resources available', ['count' => $totalLibraryResources]) }}
            </span>
        </div>
        @endif
        @else
        <div class="card sr" style="padding:48px;text-align:center;">
            <div style="font-size:2rem;margin-bottom:12px;">📚</div>
            <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--text-display);margin-bottom:8px;">{{ __('No Featured Resources') }}</h3>
            <p style="color:var(--parchment-60);margin-bottom:20px;font-size:.85rem;">{{ __('Check the library for all available resources.') }}</p>
            <a href="{{ route('library') }}" class="btn btn-primary">{{ __('Browse Library') }}</a>
        </div>
        @endif
    </div>
</section>


{{-- ═══════════════════════════════════════════════════════
     13.  FAQ + CTA SPLIT
═══════════════════════════════════════════════════════ --}}
<section style="padding:80px 24px;background:linear-gradient(180deg,var(--dark-900),var(--dark-950));">
    <div class="faq-cta-grid" style="max-width:1280px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:start;">

        {{-- FAQ --}}
        <div class="sr-l">
            <div class="sec-label">{{ __('Questions') }}</div>
            <h2 class="display" style="font-size:clamp(1.8rem,3vw,2.6rem);margin-bottom:32px;">{{ __('FAQs') }}</h2>

            @if($faqs->count() > 0)
                @foreach($faqs as $faq)
            <div class="faq-item">
                <button class="faq-btn">
                    <span>{{ app()->getLocale() === 'am' ? ($faq->question_am ?? $faq->question) : $faq->question }}</span>
                    <div class="faq-icon">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/></svg>
                    </div>
                </button>
                <div class="faq-body"><p>{!! app()->getLocale() === 'am' ? ($faq->answer_am ?? $faq->answer) : $faq->answer !!}</p></div>
            </div>
                @endforeach
            @else
                {{-- Fallback FAQs if none exist in database --}}
                @foreach([
                    ['q' => __('Where are you located?'), 'a' => __('We are located in Addis Ababa, Ayertena area. See the Contact page for exact directions.')],
                    ['q' => __('How can I volunteer?'), 'a' => __('Send us a message via Contact and we will respond with available opportunities.')],
                    ['q' => __('Who can join the programs?'), 'a' => __('Our programs are open to all ages — from children to adults. Visit Programs for details.')],
                    ['q' => __('How can I become a member?'), 'a' => __('Contact our Internal Relations department or visit us during service hours for membership information.')],
                ] as $faq)
            <div class="faq-item">
                <button class="faq-btn">
                    <span>{{ $faq['q'] }}</span>
                    <div class="faq-icon">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/></svg>
                    </div>
                </button>
                <div class="faq-body"><p>{{ $faq['a'] }}</p></div>
            </div>
                @endforeach
            @endif
        </div>

        {{-- CTA --}}
        <div class="sr-r">
            <div style="background:linear-gradient(135deg,var(--dark-800),var(--dark-700));border:1px solid rgba(26,68,247,.15);border-radius:var(--r-lg);padding:44px;position:relative;overflow:hidden;">
                <div style="position:absolute;bottom:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:var(--blue-primary);filter:blur(70px);opacity:.15;pointer-events:none;"></div>
                <svg style="position:absolute;top:-20px;right:-20px;width:120px;opacity:.04;pointer-events:none;" viewBox="0 0 100 100" fill="none">
                    <rect x="43" y="6" width="14" height="88" rx="2" fill="#F3BA15"/>
                    <rect x="6" y="43" width="88" height="14" rx="2" fill="#F3BA15"/>
                </svg>

                <div class="sec-label">{{ __('Stay Connected') }}</div>
                <h3 class="display" style="font-size:clamp(1.5rem,2.5vw,2.2rem);margin-bottom:14px;">{{ __('Join Our Community') }}</h3>
                <p style="color:var(--parchment-60);font-size:.9rem;margin-bottom:28px;line-height:1.7;">{{ __('Get updates about events, programs, and announcements. Be part of something meaningful.') }}</p>

                <div style="display:flex;flex-direction:column;gap:10px;">
                    <a href="{{ route('contact') }}" class="btn btn-primary" style="justify-content:center;">{{ __('Contact Us') }}</a>
                    <a href="{{ route('library') }}" class="btn btn-ghost" style="justify-content:center;">{{ __('Explore Programs') }}</a>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:28px;padding-top:24px;border-top:1px solid rgba(255,255,255,.06);">
                    @foreach([
                        ['am' => 'ህጻናት', 'en' => __('Children'), 'count' => \App\Models\Member::where('member_type', 'Kids')->count()],
                        ['am' => 'አዳጊ',   'en' => __('Youth'),    'count' => \App\Models\Member::where('member_type', 'Youth')->count()],
                        ['am' => 'ወጣት',  'en' => __('Adults'),   'count' => \App\Models\Member::where('member_type', 'Adult')->count()],
                        ['am' => 'ጠቅላ',  'en' => __('Total'),    'count' => \App\Models\Member::count()],
                    ] as $prog)
                    <div style="padding:10px 12px;border-radius:8px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);text-align:center;">
                        <div class="am" style="font-size:.85rem;color:var(--gold);font-weight:600;">{{ $prog['am'] }}</div>
                        <div style="font-size:.72rem;color:var(--parchment-40);margin-top:2px;">{{ $prog['en'] }}: {{ $prog['count'] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</section>

@push('styles')
<style>
    @media(max-width:768px) {
        .faq-cta-grid { grid-template-columns: 1fr !important; gap: 36px !important; }
    }
</style>
@endpush

@endsection
