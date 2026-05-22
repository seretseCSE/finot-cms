{{-- ═══════════════════════════════════════════════════════
     FINOTE TSIDIK — World-Class Landing Page
     Colors: #1A44F7 (blue) · #F3BA15 (gold) · #050A1C (dark)
     Stack: Laravel Blade · Alpine.js · Chart.js · Pure CSS
═══════════════════════════════════════════════════════ --}}

@extends('layouts.public')

@section('title', 'ፍኖተ ጽድቅ — Finote Tsidik Sunday School')

@push('head')
<meta name="description" content="Finote Tsidik Sunday School — Faith, service, and fellowship since 1984 E.C. in Addis Ababa, Ayertena.">
<meta property="og:title" content="ፍኖተ ጽድቅ — Finote Tsidik Sunday School">
<meta property="og:description" content="Building a stronger community through the light of the Gospel since 1984 E.C.">
<meta property="og:image" content="{{ asset('images/logo.png') }}">
<meta property="og:type" content="website">
{{-- Alpine.js (load before body if not already in layout) --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@section('content')

{{-- Skip to main content (accessibility) --}}
<a href="#hero" class="skip-link">Skip to main content</a>

{{-- ══════════════════════════════════════════
     GLOBAL CSS VARIABLES & BASE STYLES
══════════════════════════════════════════ --}}
<style>
/* ── Root tokens ── */
:root {
  --blue-primary: #1A44F7;
  --blue-500: #2952FF;
  --blue-glow: rgba(26,68,247,.35);
  --gold: #F3BA15;
  --gold-light: #FFD84D;
  --gold-glow: rgba(243,186,21,.25);

  /* Dark mode (default) */
  --bg-base: #050A1C;
  --bg-900: #080D22;
  --bg-800: #0C1230;
  --bg-950: #030710;
  --surface: #0F1630;
  --surface-2: #141C38;
  --overlay-98: rgba(8,13,34,.98);
  --glass: rgba(255,255,255,.05);
  --glass-hover: rgba(255,255,255,.09);
  --border-subtle: rgba(255,255,255,.08);
  --border-mid: rgba(255,255,255,.14);
  --text-display: #F0F4FF;
  --text-60: rgba(240,244,255,.6);
  --text-40: rgba(240,244,255,.4);
  --text-25: rgba(240,244,255,.25);
  --shadow-card: 0 8px 40px rgba(0,0,0,.45);
  --shadow-glow: 0 0 40px rgba(26,68,247,.2);
}

[data-theme="light"] {
  --bg-base: #F5F7FF;
  --bg-900: #EEF1FF;
  --bg-800: #E4E9FF;
  --bg-950: #DDE3FF;
  --surface: #FFFFFF;
  --surface-2: #F0F3FF;
  --overlay-98: rgba(245,247,255,.98);
  --glass: rgba(26,68,247,.05);
  --glass-hover: rgba(26,68,247,.09);
  --border-subtle: rgba(26,68,247,.1);
  --border-mid: rgba(26,68,247,.18);
  --text-display: #0A0F2E;
  --text-60: rgba(10,15,46,.65);
  --text-40: rgba(10,15,46,.5);
  --text-25: rgba(10,15,46,.28);
  --shadow-card: 0 8px 40px rgba(26,68,247,.1);
  --shadow-glow: 0 0 40px rgba(26,68,247,.12);
}

/* ── Typography ── */
.font-display { font-family: 'Playfair Display', 'Noto Serif Ethiopic', Georgia, serif; }
.font-body    { font-family: 'DM Sans', system-ui, sans-serif; }
.font-amharic { font-family: 'Noto Serif Ethiopic', serif; }

/* ── Shared section styles ── */
.section-pad { padding: 96px 24px; }
.section-pad-sm { padding: 72px 24px; }
.container-lg { max-width: 1280px; margin: 0 auto; }
.container-md { max-width: 960px; margin: 0 auto; }

.section-label {
  display: inline-flex; align-items: center; gap: 8px;
  font-family: 'DM Sans', sans-serif;
  font-size: .72rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase;
  color: var(--gold); margin-bottom: 12px;
}
.section-label::before {
  content: ''; width: 24px; height: 2px;
  background: linear-gradient(90deg, var(--gold), transparent);
  border-radius: 2px;
}

.section-title {
  font-family: 'Playfair Display', 'Noto Serif Ethiopic', serif;
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 900; color: var(--text-display); line-height: 1.15;
  margin-bottom: 16px;
}

.section-sub {
  font-size: 1rem; color: var(--text-40); line-height: 1.7;
  max-width: 560px;
}

/* ── Cards ── */
.card {
  background: var(--surface);
  border: 1px solid var(--border-subtle);
  border-radius: 16px;
  transition: transform .3s cubic-bezier(.22,1,.36,1), box-shadow .3s, border-color .3s;
}
.card:hover {
  transform: translateY(-6px);
  box-shadow: var(--shadow-card);
  border-color: var(--border-mid);
}

/* ── Badges ── */
.badge {
  display: inline-flex; align-items: center;
  padding: 4px 12px; border-radius: 50px;
  font-size: .72rem; font-weight: 700; letter-spacing: .06em;
}
.badge-gold  { background: rgba(243,186,21,.15); color: var(--gold); border: 1px solid rgba(243,186,21,.25); }
.badge-blue  { background: rgba(26,68,247,.15);  color: #7FA3FF;    border: 1px solid rgba(26,68,247,.25); }
.badge-green { background: rgba(16,185,129,.15); color: #34D399;    border: 1px solid rgba(16,185,129,.25); }
.badge-red   { background: rgba(239,68,68,.15);  color: #F87171;    border: 1px solid rgba(239,68,68,.25); }

/* ── Buttons ── */
.btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 14px 28px; border-radius: 50px; cursor: pointer;
  font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 600;
  background: linear-gradient(135deg, var(--blue-primary), var(--blue-500));
  color: #fff; border: none; text-decoration: none;
  transition: transform .25s, box-shadow .25s;
  box-shadow: 0 4px 20px var(--blue-glow);
}
.btn-primary:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 8px 32px var(--blue-glow); }

.btn-gold {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 14px 28px; border-radius: 50px; cursor: pointer;
  font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 700;
  background: linear-gradient(135deg, var(--gold), var(--gold-light));
  color: #0A0F2E; border: none; text-decoration: none;
  transition: transform .25s, box-shadow .25s;
  box-shadow: 0 4px 20px var(--gold-glow);
}
.btn-gold:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 8px 32px var(--gold-glow); }

.btn-outline {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 13px 27px; border-radius: 50px; cursor: pointer;
  font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 600;
  background: transparent; color: var(--text-display);
  border: 1px solid var(--border-mid); text-decoration: none;
  transition: background .25s, border-color .25s, transform .25s;
}
.btn-outline:hover { background: var(--glass); border-color: var(--blue-primary); transform: translateY(-2px); }

/* ── Progress bar ── */
.progress-track {
  height: 8px; background: var(--border-subtle); border-radius: 50px; overflow: hidden;
}
.progress-fill {
  height: 100%; border-radius: 50px;
  background: linear-gradient(90deg, var(--blue-primary), var(--gold));
  transition: width 1.2s cubic-bezier(.22,1,.36,1);
}

/* ── Scroll reveal ── */
.reveal {
  opacity: 0; transform: translateY(28px);
  transition: opacity .7s cubic-bezier(.22,1,.36,1), transform .7s cubic-bezier(.22,1,.36,1);
}
.reveal.visible { opacity: 1; transform: translateY(0); }

/* ── Float animation ── */
@keyframes float {
  0%, 100% { transform: translateY(0); }
  50%       { transform: translateY(-8px); }
}
.float { animation: float 4s ease-in-out infinite; }

@keyframes pulse-ring {
  0%   { transform: scale(1); opacity: .6; }
  100% { transform: scale(1.5); opacity: 0; }
}

@keyframes draw-line {
  from { stroke-dashoffset: 1000; }
  to   { stroke-dashoffset: 0; }
}

@keyframes count-up { from { opacity: 0; } to { opacity: 1; } }

@media (prefers-reduced-motion: reduce) {
  .reveal { transition: none; opacity: 1; transform: none; }
  .float  { animation: none; }
}
</style>

{{-- ══════════════════════════════════════════
     §1 — HERO SECTION
══════════════════════════════════════════ --}}
<section id="hero"
  x-data="{
    mode: 'slideshow',
    slide: 0,
    slides: [
      '{{ asset('images/hero-bg.jpg') }}',
      '{{ asset('images/features-bg.jpg') }}',
      '{{ asset('images/hero-bg.jpg') }}',
      '{{ asset('images/features-bg.jpg') }}',
    ],
    timer: null,
    init() {
      this.startSlides();
    },
    startSlides() {
      clearInterval(this.timer);
      if(this.mode === 'slideshow') {
        this.timer = setInterval(() => { this.slide = (this.slide + 1) % this.slides.length; }, 5000);
      }
    },
    switchMode(m) { this.mode = m; this.startSlides(); }
  }"
  style="position:relative;height:100vh;min-height:640px;overflow:hidden;display:flex;align-items:center;">

  {{-- Slideshow background --}}
  <div x-cloak x-show="mode==='slideshow'" style="position:absolute;inset:0;z-index:0;">

  {{-- Video background --}}
  <div x-cloak x-show="mode==='video'" style="position:absolute;inset:0;z-index:0;">
    <video autoplay muted loop playsinline
      poster="{{ asset('images/hero-bg.jpg') }}"
      style="width:100%;height:100%;object-fit:cover;">
      <source src="{{ asset('videos/hero.webm') }}" type="video/webm">
      <source src="{{ asset('videos/hero.mp4') }}" type="video/mp4">
    </video>
  </div>

  {{-- Overlay --}}
  <div style="position:absolute;inset:0;z-index:1;
    background:linear-gradient(135deg,rgba(5,10,28,.92) 0%,rgba(10,20,60,.75) 60%,rgba(5,10,28,.55) 100%);"></div>

  {{-- Ethiopian cross pattern overlay --}}
  <svg style="position:absolute;inset:0;width:100%;height:100%;z-index:1;opacity:.03;pointer-events:none;" preserveAspectRatio="xMidYMid slice">
    <defs>
      <pattern id="cross-pattern" x="0" y="0" width="80" height="80" patternUnits="userSpaceOnUse">
        <rect x="34" y="8" width="12" height="64" rx="2" fill="#F3BA15"/>
        <rect x="8" y="34" width="64" height="12" rx="2" fill="#F3BA15"/>
      </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#cross-pattern)"/>
  </svg>

  {{-- Mode switcher --}}
  <div style="position:absolute;bottom:32px;left:50%;transform:translateX(-50%);z-index:10;display:flex;gap:8px;">
    <button @click="switchMode('slideshow')"
      :style="`padding:8px 18px;border-radius:50px;font-family:'DM Sans',sans-serif;font-size:.75rem;font-weight:600;cursor:pointer;
        transition:all .25s;border:1px solid rgba(255,255,255,.25);
        background:${mode==='slideshow'?'rgba(26,68,247,.7)':'rgba(255,255,255,.08)'};
        color:${mode==='slideshow'?'#fff':'rgba(255,255,255,.6)'};`">
      ⊞ Slideshow
    </button>
    <button @click="switchMode('video')"
      :style="`padding:8px 18px;border-radius:50px;font-family:'DM Sans',sans-serif;font-size:.75rem;font-weight:600;cursor:pointer;
        transition:all .25s;border:1px solid rgba(255,255,255,.25);
        background:${mode==='video'?'rgba(26,68,247,.7)':'rgba(255,255,255,.08)'};
        color:${mode==='video'?'#fff':'rgba(255,255,255,.6)'};`">
      ▶ Video
    </button>
  </div>

  {{-- Slide dots --}}
  <div x-show="mode==='slideshow'" style="position:absolute;bottom:80px;left:50%;transform:translateX(-50%);z-index:10;display:flex;gap:8px;">
    <template x-for="(s,i) in slides" :key="i">
      <button @click="slide=i;startSlides()"
        :style="`width:${slide===i?'28px':'8px'};height:8px;border-radius:50px;border:none;cursor:pointer;transition:all .3s;
          background:${slide===i?'var(--gold)':'rgba(255,255,255,.3)'};`">
      </button>
    </template>
  </div>

  {{-- Hero content --}}
  <div class="container-lg" style="position:relative;z-index:5;width:100%;padding:0 24px;">
    <div id="hero-grid" style="display:grid;grid-template-columns:1fr 420px;gap:48px;align-items:center;">

      {{-- Left: Text --}}
      <div>
        <div class="badge badge-gold reveal" style="margin-bottom:20px;animation-delay:.1s;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
          Founded 1984 E.C. · Ayertena, Addis Ababa
        </div>

        <h1 class="font-display reveal" style="font-size:clamp(2.4rem,5.5vw,4.2rem);font-weight:900;color:#fff;line-height:1.1;margin-bottom:16px;animation-delay:.2s;">
          <span class="font-amharic" style="display:block;font-size:clamp(1.8rem,4vw,3rem);color:var(--gold);margin-bottom:4px;">ፍኖተ ጽድቅ</span>
          Finote Tsidik<br>Sunday School
        </h1>

        <p class="reveal" style="font-family:'DM Sans',sans-serif;font-size:clamp(1rem,2vw,1.2rem);color:rgba(255,255,255,.75);max-width:520px;line-height:1.7;margin-bottom:12px;animation-delay:.35s;font-style:italic;">
          {{ __("Faith, discipleship, service, and fellowship — building the next generation through the light of the Gospel.") }}
        </p>

        <p class="reveal font-amharic" style="font-size:.95rem;color:rgba(243,186,21,.7);margin-bottom:36px;animation-delay:.45s;">
          ሰንበት ትምህርት ቤት · ጌታችን ኢየሱስ ክርስቶስ
        </p>

        <div class="reveal" style="display:flex;flex-wrap:wrap;gap:12px;animation-delay:.55s;">
          <a href="{{ route('about') }}" class="btn-gold">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            {{ __('Become a Member') }}
          </a>
          <a href="{{ route('fundraising.index') }}" class="btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            {{ __('Give / Donate') }}
          </a>
          <a href="{{ route('news') }}" class="btn-outline" style="color:#fff;border-color:rgba(255,255,255,.3);">
            {{ __('Sunday School') }}
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
          </a>
        </div>
      </div>

      {{-- Right: Stats card --}}
      <div id="hero-stats-card" class="reveal" style="animation-delay:.7s;">
        <div style="background:rgba(8,13,34,.75);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
          border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:32px;
          box-shadow:0 24px 64px rgba(0,0,0,.5),0 0 0 1px rgba(26,68,247,.15);">

          <div style="font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--gold);margin-bottom:24px;opacity:.8;">
            {{ __('Live Ministry Stats') }}
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
            @php
              $heroStats = $stats ?? [
                'active_members'   => \App\Models\Member::count(),
                'active_campaigns' => ($campaigns ?? collect())->count(),
                'events_this_week' => ($events ?? collect())->count(),
                'active_classes'   => ($classes ?? collect())->count(),
              ];
            @endphp
            @foreach([
              ['value' => $heroStats['active_members'],    'label' => __('Active Members'),    'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'color' => '#7FA3FF'],
              ['value' => '2016 E.C.',                     'label' => __('Academic Year'),     'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',         'color' => '#F3BA15', 'text' => true],
              ['value' => $heroStats['active_campaigns'],  'label' => __('Active Campaigns'),  'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'color' => '#34D399'],
              ['value' => $heroStats['events_this_week'],  'label' => __('Events This Week'),  'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z', 'color' => '#F87171'],
            ] as $stat)
              <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:18px;">
                <svg width="20" height="20" fill="none" stroke="{{ $stat['color'] }}" viewBox="0 0 24 24" style="margin-bottom:10px;opacity:.85;">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stat['icon'] }}"/>
                </svg>
                <div style="font-family:'DM Sans',sans-serif;font-size:{{ isset($stat['text']) ? '1rem' : '1.6rem' }};font-weight:700;color:#fff;line-height:1;">
                  {{ $stat['value'] }}{{ !isset($stat['text']) ? '+' : '' }}
                </div>
                <div style="font-size:.72rem;color:rgba(255,255,255,.45);margin-top:4px;font-family:'DM Sans',sans-serif;">{{ $stat['label'] }}</div>
              </div>
            @endforeach
          </div>

          <div style="border-top:1px solid rgba(255,255,255,.08);padding-top:16px;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:.78rem;color:rgba(255,255,255,.4);font-family:'DM Sans',sans-serif;">
              <span style="color:var(--gold);">●</span> {{ $heroStats['active_classes'] }} Active Classes Running
            </span>
            <a href="{{ route('about') }}" style="font-size:.78rem;color:var(--blue-primary);text-decoration:none;font-weight:600;font-family:'DM Sans',sans-serif;">Learn more →</a>
          </div>
        </div>
      </div>

    </div>
  </div>

  {{-- Scroll indicator --}}
  <div style="position:absolute;bottom:36px;right:36px;z-index:10;display:flex;flex-direction:column;align-items:center;gap:6px;">
    <div style="width:1px;height:48px;background:linear-gradient(to bottom,transparent,rgba(255,255,255,.3));"></div>
    <span style="font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.3);font-family:'DM Sans',sans-serif;writing-mode:vertical-rl;">Scroll</span>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §2 — FAITH JOURNEY SLIDER
══════════════════════════════════════════ --}}
<section id="faith-journey"
  x-data="{
    active: 0,
    slides: [
      { id: 0, title: 'Our Foundation', sub: 'A Place to Belong', year: '1984 E.C.', verse: '\"For where two or three gather in my name, I am there.\" — Matthew 18:20', story: 'Born in the heart of Ayertena by a small group of faithful youth who believed in the transformative power of Sunday education.', bg: '{{ asset('images/hero-bg.jpg') }}', gradient: 'rgba(5,10,28,.85),rgba(26,68,247,.4)' },
      { id: 1, title: 'Our Mission', sub: 'Rooted in Purpose', year: 'Vision', verse: '\"Go therefore and make disciples of all nations.\" — Matthew 28:19', story: 'To nurture spiritual growth, serve the community selflessly, and build a fellowship that reflects the love of Christ in every action.', bg: '{{ asset('images/features-bg.jpg') }}', gradient: 'rgba(10,5,28,.85),rgba(90,26,200,.4)' },
      { id: 2, title: 'Our Growth', sub: 'From Seeds to Harvest', year: '40+ Years', verse: '\"I planted, Apollos watered, but God gave the growth.\" — 1 Cor 3:6', story: 'From 12 founding students to hundreds of members — each year deepening faith, expanding reach, and multiplying impact across Addis Ababa.', bg: '{{ asset('images/hero-bg.jpg') }}', gradient: 'rgba(5,10,28,.85),rgba(26,68,100,.5)' },
      { id: 3, title: 'Our Ministries', sub: 'Serving in Diverse Ways', year: '7 Departments', verse: '\"Each of you should use whatever gift you have received to serve others.\" — 1 Peter 4:10', story: 'Seven vibrant departments — each a unique expression of faith — working in harmony to serve children, youth, families, and the wider community.', bg: '{{ asset('images/features-bg.jpg') }}', gradient: 'rgba(5,15,10,.85),rgba(5,100,50,.4)' },
      { id: 4, title: 'Join Our Story', sub: 'There Is a Place for You', year: 'Today', verse: '\"You are the light of the world. A city set on a hill cannot be hidden.\" — Matthew 5:14', story: 'Whether you are seeking spiritual growth, community, or a place to serve — our doors are open. Come as you are. Stay as family.', bg: '{{ asset('images/hero-bg.jpg') }}', gradient: 'rgba(5,10,28,.88),rgba(150,80,0,.35)' },
    ],
    goTo(i) { this.active = i; }
  }"
  style="position:relative;height:100vh;min-height:600px;overflow:hidden;">

  {{-- Slide backgrounds --}}
  <template x-for="(slide, i) in slides" :key="i">
    <div :style="`position:absolute;inset:0;transition:opacity 1s ease;opacity:${active===i?1:0};
      background:linear-gradient(${slide.gradient}),url(${slide.bg}) center/cover no-repeat;`"></div>
  </template>

  {{-- Decorative vertical line --}}
  <div style="position:absolute;left:50%;top:0;bottom:0;width:1px;background:linear-gradient(to bottom,transparent,rgba(243,186,21,.2),transparent);pointer-events:none;z-index:2;"></div>

  {{-- Content --}}
  <div style="position:absolute;inset:0;z-index:5;display:flex;align-items:center;justify-content:center;padding:24px;">
    <template x-for="(slide, i) in slides" :key="'c'+i">
        <div x-cloak x-show="active===i" x-transition:enter="transition ease-out duration-700"
        x-transition:enter-start="opacity-0 transform translate-y-8"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        style="text-align:center;max-width:760px;">

        <div class="badge badge-gold" style="margin-bottom:20px;" x-text="slide.year"></div>

        <h2 class="font-display" style="font-size:clamp(2.2rem,5vw,3.8rem);font-weight:900;color:#fff;margin-bottom:8px;" x-text="slide.title"></h2>
        <p style="font-size:1.2rem;color:var(--gold);font-family:'DM Sans',sans-serif;margin-bottom:24px;font-weight:500;" x-text="slide.sub"></p>

        <p style="font-size:1rem;color:rgba(255,255,255,.8);line-height:1.8;max-width:600px;margin:0 auto 24px;font-family:'DM Sans',sans-serif;" x-text="slide.story"></p>

        <p style="font-size:.9rem;color:rgba(255,255,255,.55);font-style:italic;font-family:'Playfair Display',serif;" x-text="slide.verse"></p>

        <div x-show="slide.id===4" style="margin-top:32px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
          <a href="{{ route('contact') }}" class="btn-gold">{{ __('Visit Us') }}</a>
          <a href="{{ route('about') }}" class="btn-outline" style="color:#fff;border-color:rgba(255,255,255,.3);">{{ __('Join as Member') }}</a>
        </div>
      </div>
    </template>
  </div>

  {{-- Right-side dot nav --}}
  <div style="position:absolute;right:32px;top:50%;transform:translateY(-50%);z-index:10;display:flex;flex-direction:column;gap:12px;">
    <template x-for="(slide, i) in slides" :key="'dot'+i">
      <button @click="goTo(i)"
        :title="slide.title"
        :style="`width:${active===i?'12px':'8px'};height:${active===i?'32px':'8px'};border-radius:50px;border:none;cursor:pointer;transition:all .3s;
          background:${active===i?'var(--gold)':'rgba(255,255,255,.3)'};`">
      </button>
    </template>
  </div>

  {{-- Top progress bar --}}
  <div style="position:absolute;top:0;left:0;right:0;height:3px;background:rgba(255,255,255,.1);z-index:10;">
    <div :style="`height:100%;background:linear-gradient(90deg,var(--blue-primary),var(--gold));transition:width .5s ease;width:${((active+1)/5)*100}%`"></div>
  </div>

  {{-- Prev / Next --}}
  <button @click="goTo(Math.max(0,active-1))"
    style="position:absolute;left:24px;top:50%;transform:translateY(-50%);z-index:10;
      width:48px;height:48px;border-radius:50%;border:1px solid rgba(255,255,255,.2);
      background:rgba(255,255,255,.08);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;
      transition:background .2s;" :disabled="active===0" :style="active===0?'opacity:.3':''">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
  </button>
  <button @click="goTo(Math.min(4,active+1))"
    style="position:absolute;right:72px;top:50%;transform:translateY(-50%);z-index:10;
      width:48px;height:48px;border-radius:50%;border:1px solid rgba(255,255,255,.2);
      background:rgba(255,255,255,.08);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;
      transition:background .2s;" :disabled="active===4" :style="active===4?'opacity:.3':''">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
  </button>

  {{-- Scroll bounce --}}
  <div style="position:absolute;bottom:28px;left:50%;transform:translateX(-50%);z-index:10;animation:float 2s ease-in-out infinite;">
    <svg width="28" height="28" fill="none" stroke="rgba(255,255,255,.4)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
  </div>
</section>


{{-- ══════════════════════════════════════════
     §1.5 — SERVICE INFO BAR
══════════════════════════════════════════ --}}
<section style="background:linear-gradient(90deg,var(--blue-primary),#2952FF);padding:0;position:relative;overflow:hidden;z-index:10;">
  <div style="max-width:1280px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:0;">
    @foreach([
      ['icon'=>'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z','label'=>__('Addis Ababa, Ayertena'),'sub'=>'አዲስ አበባ፣ አየርጤና'],
      ['icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z','label'=>__('Sunday 2:00 – 5:00 PM'),'sub'=>__('Saturday 3:00 – 5:30 PM')],
      ['icon'=>'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z','label'=>'+251 911 123 456','sub'=>__('Call Us')],
    ] as $info)
      <div style="padding:20px 24px;display:flex;align-items:center;gap:14px;border-right:1px solid rgba(255,255,255,.12);">
        <svg width="20" height="20" fill="none" stroke="rgba(255,255,255,.8)" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $info['icon'] }}"/>
        </svg>
        <div>
          <div style="font-family:'DM Sans',sans-serif;font-size:.9rem;color:#fff;font-weight:600;">{{ $info['label'] }}</div>
          <div class="font-amharic" style="font-size:.75rem;color:rgba(255,255,255,.7);">{{ $info['sub'] }}</div>
        </div>
      </div>
    @endforeach
  </div>
</section>

{{-- ══════════════════════════════════════════
     §3 — LIVE FEED
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-base);">
  <div class="container-lg">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:48px;flex-wrap:wrap;gap:16px;">
      <div>
        <div class="section-label">{{ __('Live Feed') }}</div>
        <h2 class="section-title">{{ __("What's Happening") }}</h2>
        <p class="section-sub">{{ __('Stay connected with the latest news, events, and content from our community.') }}</p>
      </div>
      <a href="{{ route('news') }}" style="font-size:.85rem;color:var(--blue-primary);text-decoration:none;font-weight:600;font-family:'DM Sans',sans-serif;display:flex;align-items:center;gap:4px;white-space:nowrap;">
        {{ __('View All') }} <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
      </a>
    </div>

    <div id="live-feed-grid">

      {{-- Announcements --}}
      <div>
        <div style="font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-40);margin-bottom:16px;">{{ __('Latest Announcements') }}</div>
        <div style="display:flex;flex-direction:column;gap:16px;">
          @php
            $announcements = $announcements ?? collect([
              (object)['title'=>'Annual General Meeting 2016 E.C.','excerpt'=>'All members are invited to attend the annual general meeting to review ministry progress and elect new leadership.','created_at'=>now(),'category'=>'General'],
              (object)['title'=>'New Sunday School Curriculum Launch','excerpt'=>'We are excited to announce the launch of our updated curriculum for children and youth programs starting next month.','created_at'=>now()->subDays(2),'category'=>'Education'],
              (object)['title'=>'Charity Drive — Winter Clothing','excerpt'=>'Our annual winter clothing drive begins this Saturday. Please bring gently used items to the church hall.','created_at'=>now()->subDays(5),'category'=>'Charity'],
            ]);
          @endphp
          @foreach($announcements->take(3) as $i => $ann)
            <a href="{{ isset($ann->id) ? route('announcements.show', $ann->id) : '#' }}" class="card reveal" style="padding:0;text-decoration:none;display:block;overflow:hidden;animation-delay:{{ $i * 0.1 }}s;{{ isset($ann->is_urgent) && $ann->is_urgent ? 'border-left:3px solid #F87171;' : '' }}">
              @if(isset($ann->image) && $ann->image)
                <div style="width:100%;height:120px;overflow:hidden;background:var(--surface-2);">
                  <img src="{{ $ann->image_url ?? asset('images/features-bg.jpg') }}" alt="{{ $ann->title }}" style="width:100%;height:100%;object-fit:cover;">
                </div>
              @endif
              <div style="padding:16px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px;">
                  <span class="badge badge-blue" style="font-size:.65rem;">{{ $ann->category ?? __('General') }}</span>
                  <div style="display:flex;align-items:center;gap:6px;">
                    @if(isset($ann->is_urgent) && $ann->is_urgent)
                      <span class="badge badge-red" style="font-size:.6rem;">{{ __('Urgent') }}</span>
                    @endif
                    <span style="font-size:.7rem;color:var(--text-25);font-family:'DM Sans',sans-serif;white-space:nowrap;">
                      {{ \Carbon\Carbon::parse($ann->published_at ?? $ann->created_at)->diffForHumans() }}
                    </span>
                  </div>
                </div>
                <h4 style="font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:700;color:var(--text-display);margin-bottom:8px;line-height:1.4;">
                  {{ app()->getLocale() === 'am' ? ($ann->title_am ?? $ann->title) : $ann->title }}
                </h4>
                <p style="font-size:.82rem;color:var(--text-40);line-height:1.6;margin:0;">
                  {{ Str::limit(strip_tags(app()->getLocale() === 'am' ? ($ann->content_am ?? $ann->content ?? $ann->excerpt ?? $ann->body ?? '') : ($ann->content ?? $ann->excerpt ?? $ann->body ?? '')), 100) }}
                </p>
              </div>
            </a>
          @endforeach
        </div>
      </div>

      {{-- Upcoming Events --}}
      <div>
        <div style="font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-40);margin-bottom:16px;">{{ __('Upcoming Events') }}</div>
        <div style="display:flex;flex-direction:column;gap:16px;">
          @php
            $upcomingEvents = $events ?? collect([
              (object)['title'=>'Youth Retreat 2016','start_date'=>now()->addDays(7),'location'=>'Debre Zeyit','registered'=>18,'capacity'=>30],
              (object)['title'=>'Christmas Celebration','start_date'=>now()->addDays(21),'location'=>'Church Hall','registered'=>85,'capacity'=>150],
            ]);
          @endphp
          @foreach($upcomingEvents->take(2) as $i => $event)
            @php
              $pct = isset($event->capacity) && $event->capacity > 0 ? round(($event->registered / $event->capacity)*100) : 0;
              $eventDate = $event->date_time ?? $event->start_date ?? now();
              $eventName = $event->name ?? $event->title ?? __('Event');
            @endphp
            <div class="card reveal" style="padding:20px;animation-delay:{{ $i * 0.15 }}s;">
              <div style="display:flex;gap:14px;align-items:flex-start;">
                <div style="background:linear-gradient(135deg,var(--blue-primary),var(--blue-500));border-radius:10px;padding:10px 14px;text-align:center;flex-shrink:0;">
                  <div style="font-size:1.4rem;font-weight:900;color:#fff;line-height:1;font-family:'DM Sans',sans-serif;">{{ \Carbon\Carbon::parse($eventDate)->format('d') }}</div>
                  <div style="font-size:.65rem;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;font-family:'DM Sans',sans-serif;">{{ \Carbon\Carbon::parse($eventDate)->format('M') }}</div>
                </div>
                <div style="flex:1;min-width:0;">
                  <h4 style="font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:700;color:var(--text-display);margin-bottom:6px;">{{ $eventName }}</h4>
                  <p style="font-size:.75rem;color:var(--text-40);margin-bottom:10px;font-family:'DM Sans',sans-serif;">📍 {{ $event->location ?? __('TBD') }}</p>
                  <div class="progress-track" style="margin-bottom:6px;">
                    <div class="progress-fill" style="width:{{ $pct }}%;"></div>
                  </div>
                  <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">
                    <span>{{ $event->registered ?? 0 }}/{{ $event->capacity ?? '∞' }}</span>
                    <span class="{{ $pct > 80 ? 'badge badge-red' : ($pct > 50 ? 'badge badge-gold' : 'badge badge-green') }}" style="font-size:.62rem;padding:2px 8px;">{{ $pct > 80 ? 'Filling Fast' : ($pct > 50 ? 'Available' : 'Open') }}</span>
                  </div>
                </div>
              </div>
              <a href="{{ route('news') }}" class="btn-primary" style="display:block;text-align:center;margin-top:14px;padding:10px;font-size:.8rem;">Register</a>
            </div>
          @endforeach
        </div>
      </div>

      {{-- Featured Media --}}
      <div>
        <div style="font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-40);margin-bottom:16px;">{{ __('Featured Media') }}</div>
        <div class="card reveal" style="overflow:hidden;">
          <div style="position:relative;aspect-ratio:16/9;background:linear-gradient(135deg,var(--bg-800),var(--surface-2));display:flex;align-items:center;justify-content:center;">
            <img src="{{ asset('images/features-bg.jpg') }}" alt="Featured media"
              style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;" loading="lazy"
              onerror="this.style.display='none'">
            <div style="position:absolute;inset:0;background:rgba(5,10,28,.3);display:flex;align-items:center;justify-content:center;">
              <div style="width:52px;height:52px;border-radius:50%;background:rgba(243,186,21,.9);display:flex;align-items:center;justify-content:center;">
                <svg width="20" height="20" fill="#0A0F2E" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
              </div>
            </div>
          </div>
          <div style="padding:20px;">
            <span class="badge badge-gold" style="margin-bottom:12px;">{{ __('New Video') }}</span>
            <h4 style="font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:700;color:var(--text-display);margin-bottom:8px;line-height:1.4;">{{ __('Weekly Sunday Teaching Series') }}</h4>
            <p style="font-size:.8rem;color:var(--text-40);line-height:1.6;margin-bottom:16px;">{{ __('This week\'s message from our Sunday School program — spiritual formation for all ages.') }}</p>
            <a href="{{ route('media') }}" class="btn-outline" style="font-size:.8rem;padding:10px 18px;">{{ __('Watch Now') }} →</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §4 — QUICK ACTIONS
══════════════════════════════════════════ --}}
<section class="section-pad-sm" style="background:var(--bg-900);">
  <div class="container-lg">
    <div style="text-align:center;margin-bottom:48px;">
      <div class="section-label" style="justify-content:center;">{{ __('Quick Actions') }}</div>
      <h2 class="section-title" style="margin:0 auto 12px;">{{ __('What Would You Like to Do?') }}</h2>
      <p class="section-sub" style="margin:0 auto;">{{ __('Everything you need, one click away.') }}</p>
    </div>

    <div id="actions-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">
      @foreach([
        ['icon'=>'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4','label'=>__('Download Resources'),'desc'=>__('Study materials, books & guides'),'href'=>route('library'),'color'=>'#7FA3FF','bg'=>'rgba(26,68,247,.12)'],
        ['icon'=>'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z M15 13a3 3 0 11-6 0 3 3 0 016 0z','label'=>__('View Gallery'),'desc'=>__('Photos & videos from events'),'href'=>route('media'),'color'=>'#F3BA15','bg'=>'rgba(243,186,21,.12)'],
        ['icon'=>'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3','label'=>__('Listen to Songs'),'desc'=>__('Worship music & hymns'),'href'=>route('songs.index'),'color'=>'#34D399','bg'=>'rgba(16,185,129,.12)'],
        ['icon'=>'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253','label'=>__('Read Blog'),'desc'=>__('Devotionals & teachings'),'href'=>route('blog.index'),'color'=>'#A78BFA','bg'=>'rgba(167,139,250,.12)'],
        ['icon'=>'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z','label'=>__('Donate'),'desc'=>__('Support our mission'),'href'=>route('fundraising.index'),'color'=>'#F87171','bg'=>'rgba(239,68,68,.12)'],
        ['icon'=>'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2','label'=>__('Tour Registration'),'desc'=>__('Pilgrimages & educational tours'),'href'=>route('tours.index'),'color'=>'#FBBF24','bg'=>'rgba(251,191,36,.12)'],
      ] as $i => $action)
        <a href="{{ $action['href'] }}" class="card reveal" style="padding:28px;text-decoration:none;animation-delay:{{ $i * 0.08 }}s;display:block;">
          <div style="width:52px;height:52px;border-radius:14px;background:{{ $action['bg'] }};display:flex;align-items:center;justify-content:center;margin-bottom:16px;transition:transform .3s;">
            <svg width="24" height="24" fill="none" stroke="{{ $action['color'] }}" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $action['icon'] }}"/>
            </svg>
          </div>
          <h4 style="font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:700;color:var(--text-display);margin-bottom:6px;">{{ $action['label'] }}</h4>
          <p style="font-size:.8rem;color:var(--text-40);margin:0;line-height:1.5;">{{ $action['desc'] }}</p>
        </a>
      @endforeach
    </div>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §5 — IMPACT DASHBOARD
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-base);">
  <div class="container-lg">
    <div style="id="impact-grid" style="display:grid;grid-template-columns:1fr 2fr;gap:64px;align-items:start;"">
      <div>
        <div class="section-label">{{ __('Impact') }}</div>
        <h2 class="section-title">{{ __('Real Numbers, Real Lives Changed') }}</h2>
        <p class="section-sub">{{ __('Transparency is core to who we are. Every number represents a life touched by faith.') }}</p>
        <div style="margin-top:32px;padding:24px;background:var(--surface);border:1px solid var(--border-subtle);border-radius:16px;">
          <div style="font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gold);margin-bottom:16px;">{{ __("This Year's Highlights") }}</div>
          @foreach([
            ['label'=>__('+12% Member Growth'),'color'=>'#34D399'],
            ['label'=>__('7 Active Departments'),'color'=>'var(--gold)'],
            ['label'=>__('3 New Campaigns Launched'),'color'=>'#7FA3FF'],
            ['label'=>__('40+ Years of Ministry'),'color'=>'#A78BFA'],
          ] as $h)
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
              <div style="width:8px;height:8px;border-radius:50%;background:{{ $h['color'] }};flex-shrink:0;"></div>
              <span style="font-size:.85rem;color:var(--text-60);font-family:'DM Sans',sans-serif;">{{ $h['label'] }}</span>
            </div>
          @endforeach
        </div>
      </div>

      <div>
        {{-- Stat counters --}}
        <div style="id="counter-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-bottom:28px;"
          x-data="{
            counters: [
              {end:{{ \App\Models\Member::count() }},label:'{{ __('Active Members') }}',suffix:'+',icon:'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',color:'#7FA3FF',trend:'+12% this year'},
              {end:{{ \App\Models\Member::where('member_type','Youth')->count() }},label:'{{ __('Youth Members') }}',suffix:'+',icon:'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',color:'#A78BFA',trend:'{{ \App\Models\Member::count() > 0 ? round(\App\Models\Member::where("member_type","Youth")->count() / \App\Models\Member::count() * 100) : 0 }}% of total'},
              {end:{{ \App\Models\Member::where('member_type','Kids')->count() }},label:'{{ __('Children Enrolled') }}',suffix:'',icon:'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z',color:'#F3BA15',trend:'{{ ($classes ?? collect())->count() }} active classes'},
              {end:{{ \App\Models\Member::where('member_type','Adult')->count() }},label:'{{ __('Young Adults') }}',suffix:'+',icon:'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',color:'#34D399',trend:'{{ __('Joined 2016 E.C.') }}'},
            ],
            vals: [0,0,0,0],
            started: false,
            start() {
              if(this.started) return; this.started = true;
              this.counters.forEach((c,i) => {
                let s=0, step=Math.ceil(c.end/60), t=setInterval(()=>{
                  s = Math.min(s+step, c.end);
                  this.vals[i] = s;
                  if(s>=c.end) clearInterval(t);
                }, 30);
              });
            }
          }"
          x-init="const _obs=new IntersectionObserver(e=>{if(e[0].isIntersecting){start();_obs.disconnect();}},{threshold:0.2});_obs.observe($el);">
          <template x-for="(c,i) in counters" :key="i">
            <div class="card" style="padding:24px;">
              <svg width="22" height="22" fill="none" :stroke="c.color" viewBox="0 0 24 24" style="margin-bottom:14px;opacity:.85;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="c.icon"/>
              </svg>
              <div style="font-family:'DM Sans',sans-serif;font-size:2rem;font-weight:800;color:var(--text-display);line-height:1;" x-text="vals[i]+c.suffix"></div>
              <div style="font-size:.82rem;color:var(--text-40);margin-top:6px;font-family:'DM Sans',sans-serif;" x-text="c.label"></div>
              <div style="margin-top:8px;display:inline-flex;align-items:center;gap:4px;font-size:.7rem;font-weight:600;font-family:'DM Sans',sans-serif;" :style="`color:${c.color}`">
                ↑ <span x-text="c.trend"></span>
              </div>
            </div>
          </template>
        </div>

        {{-- Charts --}}
        <div id="chart-row" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
          <div class="card" style="padding:24px;">
            <div style="font-family:'DM Sans',sans-serif;font-size:.8rem;font-weight:600;color:var(--text-60);margin-bottom:16px;">{{ __('Membership Growth') }}</div>
            <canvas id="membershipChart" height="140"></canvas>
          </div>
          <div class="card" style="padding:24px;">
            <div style="font-family:'DM Sans',sans-serif;font-size:.8rem;font-weight:600;color:var(--text-60);margin-bottom:16px;">{{ __('Ministry Activity') }}</div>
            <canvas id="ministryChart" height="140"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §6 — BLOG & RESOURCES TEASER
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-900);">
  <div class="container-lg">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:48px;flex-wrap:wrap;gap:16px;">
      <div>
        <div class="section-label">{{ __('Knowledge Hub') }}</div>
        <h2 class="section-title">{{ __('Blog & Resources') }}</h2>
        <p class="section-sub">{{ __('Teachings, devotionals, and learning materials for every stage of faith.') }}</p>
      </div>
      <a href="{{ route('blog.index') }}" style="font-size:.85rem;color:var(--blue-primary);text-decoration:none;font-weight:600;font-family:'DM Sans',sans-serif;display:flex;align-items:center;gap:4px;">
        {{ __('All Posts') }} <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
      </a>
    </div>

    {{-- Resource pills --}}
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:40px;">
      @foreach([
        ['label'=>'🎵 '.__('Songs'),'href'=>route('songs.index'),'color'=>'rgba(52,211,153,.15)','text'=>'#34D399'],
        ['label'=>'📚 '.__('Library'),'href'=>route('library'),'color'=>'rgba(167,139,250,.15)','text'=>'#A78BFA'],
        ['label'=>'🎥 '.__('Media'),'href'=>route('media'),'color'=>'rgba(243,186,21,.15)','text'=>'var(--gold)'],
        ['label'=>'✝ '.__('Teachings'),'href'=>route('blog.index'),'color'=>'rgba(26,68,247,.15)','text'=>'#7FA3FF'],
      ] as $r)
        <a href="{{ $r['href'] }}" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:50px;
          background:{{ $r['color'] }};color:{{ $r['text'] }};border:1px solid {{ $r['color'] }};
          font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:600;text-decoration:none;
          transition:transform .2s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
          {{ $r['label'] }}
        </a>
      @endforeach
    </div>

    {{-- Blog cards --}}
    <div style="id="blog-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;">
      @php
        $blogPosts = $blogPosts ?? collect([
          (object)['title'=>'The Power of Communal Prayer','excerpt'=>'Exploring how collective prayer has shaped our community across generations.','created_at'=>now()->subDays(3),'category'=>'Devotional','read_time'=>5],
          (object)['title'=>'Understanding Ethiopian Orthodox Fasting','excerpt'=>'A guide to the spiritual and physical significance of fasting in our tradition.','created_at'=>now()->subDays(8),'category'=>'Teaching','read_time'=>7],
          (object)['title'=>'Sunday School: Building Faith in Children','excerpt'=>'How structured religious education shapes lifelong spiritual habits.','created_at'=>now()->subDays(14),'category'=>'Education','read_time'=>4],
        ]);
      @endphp
      @foreach($blogPosts->take(3) as $i => $post)
        <div class="card reveal" style="overflow:hidden;animation-delay:{{ $i * 0.1 }}s;">
          <div style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--bg-800),var(--surface-2));
            background-image:url({{ asset('images/features-bg.jpg') }});background-size:cover;background-position:center;"></div>
          <div style="padding:24px;">
            <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center;">
              <span class="badge badge-blue" style="font-size:.65rem;">{{ $post->category ?? 'Blog' }}</span>
              <span style="font-size:.7rem;color:var(--text-25);font-family:'DM Sans',sans-serif;">{{ $post->read_time ?? 5 }} {{ __('min read') }}</span>
            </div>
            <h4 style="font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;color:var(--text-display);margin-bottom:10px;line-height:1.4;">{{ $post->title }}</h4>
            <p style="font-size:.82rem;color:var(--text-40);line-height:1.6;margin-bottom:16px;">{{ Str::limit($post->excerpt ?? '', 90) }}</p>
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <span style="font-size:.72rem;color:var(--text-25);font-family:'DM Sans',sans-serif;">{{ \Carbon\Carbon::parse($post->created_at)->format('M d, Y') }}</span>
              <a href="{{ route('blog.index') }}" style="font-size:.78rem;color:var(--blue-primary);text-decoration:none;font-weight:600;font-family:'DM Sans',sans-serif;">{{ __('Read') }} →</a>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §7 — GALLERY
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-base);"
  x-data="{
    activeFilter: 'all',
    filters: ['all','liturgical','community','events','architecture'],
    lightbox: null,
    items: [
      {src:'{{ asset('images/masonry-portfolio/masonry-portfolio-1.jpg') }}',cat:'liturgical',title:'Sunday Worship Service'},
      {src:'{{ asset('images/masonry-portfolio/masonry-portfolio-2.jpg') }}',cat:'community',title:'Community Fellowship'},
      {src:'{{ asset('images/masonry-portfolio/masonry-portfolio-3.jpg') }}',cat:'events',title:'Annual Celebration'},
      {src:'{{ asset('images/masonry-portfolio/masonry-portfolio-4.jpg') }}',cat:'architecture',title:'Our Church Building'},
      {src:'{{ asset('images/masonry-portfolio/masonry-portfolio-5.jpg') }}',cat:'liturgical',title:'Holy Communion'},
      {src:'{{ asset('images/masonry-portfolio/masonry-portfolio-6.jpg') }}',cat:'community',title:'Youth Group Meeting'},
      {src:'{{ asset('images/masonry-portfolio/masonry-portfolio-7.jpg') }}',cat:'events',title:'Christmas Program 2015 E.C.'},
      {src:'{{ asset('images/masonry-portfolio/masonry-portfolio-8.jpg') }}',cat:'architecture',title:'Church Interior'},
      {src:'{{ asset('images/masonry-portfolio/masonry-portfolio-9.jpg') }}',cat:'community',title:'Charity Drive'},
    ],
    get filtered() { return this.activeFilter==='all' ? this.items : this.items.filter(i=>i.cat===this.activeFilter); },
    openLightbox(i) { this.lightbox = i; document.body.style.overflow='hidden'; },
    closeLightbox() { this.lightbox = null; document.body.style.overflow=''; },
    prev() { if(this.lightbox > 0) this.lightbox--; },
    next() { if(this.lightbox < this.filtered.length-1) this.lightbox++; },
  }"
  @keydown.escape.window="closeLightbox()"
  @keydown.arrow-left.window="prev()"
  @keydown.arrow-right.window="next()">

  <div class="container-lg">
    <div style="text-align:center;margin-bottom:48px;">
      <div class="section-label" style="justify-content:center;">{{ __('Gallery') }}</div>
      <h2 class="section-title" style="margin:0 auto 12px;">{{ __('Moments of Faith') }}</h2>
      <p class="section-sub" style="margin:0 auto;">{{ __('Capturing memories from our community across 40+ years of ministry.') }}</p>
    </div>

    {{-- Filter tabs --}}
    <div style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-bottom:36px;">
      <template x-for="f in filters" :key="f">
        <button @click="activeFilter=f"
          :style="`padding:8px 20px;border-radius:50px;font-family:'DM Sans',sans-serif;font-size:.8rem;font-weight:600;cursor:pointer;
            border:1px solid;transition:all .25s;
            background:${activeFilter===f?'var(--blue-primary)':'var(--glass)'};
            border-color:${activeFilter===f?'var(--blue-primary)':'var(--border-subtle)'};
            color:${activeFilter===f?'#fff':'var(--text-40)'};`"
          x-text="f.charAt(0).toUpperCase()+f.slice(1)">
        </button>
      </template>
    </div>

    {{-- Masonry grid --}}
    <div id="gallery-masonry" style="columns:3;column-gap:16px;">
      <template x-for="(item, i) in filtered" :key="item.src+activeFilter">
        <div style="break-inside:avoid;margin-bottom:16px;cursor:pointer;position:relative;border-radius:12px;overflow:hidden;group"
          @click="openLightbox(i)"
          x-transition:enter="transition ease-out duration-400"
          x-transition:enter-start="opacity-0 scale-95"
          x-transition:enter-end="opacity-100 scale-100">
          <img :src="item.src" :alt="item.title" loading="lazy"
            style="width:100%;display:block;border-radius:12px;transition:transform .4s ease;"
            onerror="this.parentElement.style.display='none'"
            onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
          <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(5,10,28,.8) 0%,transparent 50%);
            border-radius:12px;opacity:0;transition:opacity .3s;"
            onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
            <div style="position:absolute;bottom:14px;left:14px;right:14px;">
              <p style="font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:600;color:#fff;margin:0;" x-text="item.title"></p>
            </div>
          </div>
        </div>
      </template>
    </div>

    <div style="text-align:center;margin-top:40px;">
      <a href="{{ route('media') }}" class="btn-outline">{{ __('View All Photos & Videos') }}</a>
    </div>
  </div>

  {{-- Lightbox --}}
  <div x-cloak x-show="lightbox!==null" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    style="position:fixed;inset:0;z-index:999;background:rgba(5,10,28,.97);display:flex;align-items:center;justify-content:center;padding:24px;">
    <button @click="closeLightbox()" style="position:absolute;top:20px;right:20px;width:44px;height:44px;border-radius:50%;background:var(--glass);border:1px solid var(--border-subtle);color:var(--text-display);cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:10;">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <button @click="prev()" :disabled="lightbox===0" style="position:absolute;left:20px;top:50%;transform:translateY(-50%);width:48px;height:48px;border-radius:50%;background:var(--glass);border:1px solid var(--border-subtle);color:var(--text-display);cursor:pointer;display:flex;align-items:center;justify-content:center;" :style="lightbox===0?'opacity:.3':''">
      <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <template x-if="lightbox!==null">
      <div style="max-width:900px;width:100%;text-align:center;">
        <img :src="filtered[lightbox].src" :alt="filtered[lightbox].title"
          style="max-height:75vh;max-width:100%;border-radius:12px;object-fit:contain;">
        <p style="font-family:'DM Sans',sans-serif;font-size:.95rem;color:var(--text-60);margin-top:16px;" x-text="filtered[lightbox].title"></p>
      </div>
    </template>
    <button @click="next()" :disabled="lightbox===filtered.length-1" style="position:absolute;right:20px;top:50%;transform:translateY(-50%);width:48px;height:48px;border-radius:50%;background:var(--glass);border:1px solid var(--border-subtle);color:var(--text-display);cursor:pointer;display:flex;align-items:center;justify-content:center;" :style="lightbox===filtered.length-1?'opacity:.3':''">
      <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §8 — FUNDRAISING CAMPAIGNS
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-900);">
  <div class="container-lg">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:48px;flex-wrap:wrap;gap:16px;">
      <div>
        <div class="section-label">{{ __('Fundraising') }}</div>
        <h2 class="section-title">{{ __('Support Our Mission') }}</h2>
        <p class="section-sub">{{ __('Your generosity builds God\'s kingdom and touches real lives in our community.') }}</p>
      </div>
      <a href="{{ route('fundraising.index') }}" style="font-size:.85rem;color:var(--blue-primary);text-decoration:none;font-weight:600;font-family:'DM Sans',sans-serif;display:flex;align-items:center;gap:4px;white-space:nowrap;">
        {{ __('All Campaigns') }} <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
      </a>
    </div>

    {{-- Ticker --}}
    <div style="background:linear-gradient(90deg,rgba(26,68,247,.1),rgba(243,186,21,.08),rgba(26,68,247,.1));border:1px solid var(--border-subtle);border-radius:12px;padding:14px 24px;margin-bottom:36px;display:flex;align-items:center;gap:12px;overflow:hidden;">
      <div style="width:8px;height:8px;border-radius:50%;background:var(--gold);flex-shrink:0;animation:pulse-ring 1.5s ease-out infinite;"></div>
      <p style="font-family:'DM Sans',sans-serif;font-size:.85rem;color:var(--text-60);margin:0;">
        <strong style="color:var(--gold);">{{ __('Live:') }}</strong> {{ __('24 members supported campaigns this week') }} &nbsp;·&nbsp; {{ __('ETB 18,500 raised in the last 48 hours') }} &nbsp;·&nbsp; {{ __('Building Fund is 68% complete') }}
      </p>
    </div>

    <div style="id="campaigns-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;">
      @php
        $campaigns = $campaigns ?? collect([
          (object)['title'=>'Church Building Expansion','description'=>'Help us expand our worship space to accommodate our growing congregation.','raised'=>68000,'goal'=>100000,'ends_at'=>now()->addDays(45),'category'=>'Building'],
          (object)['title'=>'Youth Ministry Equipment','description'=>'Purchase musical instruments and AV equipment for our youth worship program.','raised'=>12500,'goal'=>25000,'ends_at'=>now()->addDays(30),'category'=>'Ministry'],
          (object)['title'=>'Winter Charity Drive','description'=>'Provide warm clothing and food packages to families in need across Addis Ababa.','raised'=>8200,'goal'=>15000,'ends_at'=>now()->addDays(14),'category'=>'Charity'],
        ]);
      @endphp
      @foreach($campaigns->take(3) as $i => $camp)
        @php
          $pct = $camp->goal > 0 ? min(round(($camp->raised / $camp->goal) * 100), 100) : 0;
          $days = now()->diffInDays($camp->ends_at, false);
          $daysColor = $days < 3 ? '#F87171' : ($days < 7 ? '#FBBF24' : '#34D399');
        @endphp
        <div class="card reveal" style="overflow:hidden;animation-delay:{{ $i * 0.1 }}s;">
          <div style="height:160px;background:linear-gradient(135deg,var(--bg-800),var(--surface-2));
            background-image:url({{ asset('images/features-bg.jpg') }});background-size:cover;background-position:center;position:relative;">
            <div style="position:absolute;top:12px;right:12px;">
              <span class="badge badge-gold" style="font-size:.65rem;">{{ $camp->category ?? 'General' }}</span>
            </div>
          </div>
          <div style="padding:24px;">
            <h4 style="font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;color:var(--text-display);margin-bottom:8px;line-height:1.4;">{{ $camp->title }}</h4>
            <p style="font-size:.82rem;color:var(--text-40);line-height:1.6;margin-bottom:20px;">{{ Str::limit($camp->description, 80) }}</p>

            <div class="progress-track" style="margin-bottom:10px;">
              <div class="progress-fill" style="width:{{ $pct }}%;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
              <div>
                <span style="font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:700;color:var(--blue-primary);">ETB {{ number_format($camp->raised) }}</span>
                <span style="font-size:.75rem;color:var(--text-40);font-family:'DM Sans',sans-serif;"> / ETB {{ number_format($camp->goal) }}</span>
              </div>
              <span style="font-size:.75rem;font-weight:600;font-family:'DM Sans',sans-serif;color:{{ $daysColor }};">{{ __(':count days left', ['count' => max(0,$days)]) }}</span>
            </div>

            {{-- Donor avatars --}}
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
              <div style="display:flex;">
                @for($d = 0; $d < 4; $d++)
                  <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--blue-primary),var(--gold));margin-left:{{ $d > 0 ? '-8px' : '0' }};border:2px solid var(--surface);display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:.6rem;font-weight:700;color:#fff;">{{ chr(65+$d+$i*3) }}</span>
                  </div>
                @endfor
              </div>
              <span style="font-size:.75rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">{{ __('+ :count others donated', ['count' => 8+$i*5]) }}</span>
            </div>

              <a href="{{ route('fundraising.index') }}" class="btn-primary" style="display:block;text-align:center;padding:12px;">
                {{ __('Donate Now') }} ♥
            </a>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §9 — SUNDAY SCHOOL HUB
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-base);">
  <div class="container-lg">
    <div style="id="sunday-grid" style="display:grid;grid-template-columns:1fr 1.6fr;gap:64px;align-items:start;"">
      {{-- Left --}}
      <div>
        <div class="section-label">{{ __('Education') }}</div>
        <h2 class="section-title">{{ __('Sunday School Programs') }}</h2>
        <p class="section-sub">{{ __('Growing in faith at every age through structured, nurturing education.') }}</p>

        <div style="background:var(--surface);border:1px solid var(--border-subtle);border-radius:16px;padding:28px;margin:28px 0;">
          <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
            <div style="width:52px;height:52px;border-radius:14px;background:rgba(26,68,247,.12);display:flex;align-items:center;justify-content:center;">
              <svg width="24" height="24" fill="none" stroke="var(--blue-primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
              <div style="font-family:'DM Sans',sans-serif;font-size:2rem;font-weight:800;color:var(--text-display);">{{ \App\Models\Member::count() }}+</div>
              <div style="font-size:.82rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">{{ __('Students Enrolled — 2016 E.C.') }}</div>
            </div>
          </div>
          <span class="badge badge-green" style="margin-bottom:20px;">{{ __('Enrollment Open') }}</span>
          <a href="{{ route('about') }}" class="btn-primary" style="display:block;text-align:center;margin-top:8px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            {{ __('Enroll Now') }}
          </a>
        </div>

        {{-- Program categories --}}
        <div style="display:flex;flex-direction:column;gap:12px;">
          @php
            $progCats = [
              ['label'=>'ህጻናት — Children','ages'=>'Ages 6–12','icon'=>'🧒','count'=>\App\Models\Member::where('member_type','Kids')->count(),'color'=>'rgba(251,191,36,.12)','text'=>'#FBBF24'],
              ['label'=>'አዳጊ — Youth','ages'=>'Ages 13–18','icon'=>'🧑','count'=>\App\Models\Member::where('member_type','Youth')->count(),'color'=>'rgba(167,139,250,.12)','text'=>'#A78BFA'],
              ['label'=>'ወጣት — Young Adults','ages'=>'Ages 18+','icon'=>'👤','count'=>\App\Models\Member::where('member_type','Adult')->count(),'color'=>'rgba(26,68,247,.12)','text'=>'#7FA3FF'],
            ];
          @endphp
          @foreach($progCats as $cat)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:{{ $cat['color'] }};border:1px solid {{ $cat['color'] }};border-radius:12px;">
              <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:1.4rem;">{{ $cat['icon'] }}</span>
                <div>
                  <div style="font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:700;color:var(--text-display);">{{ $cat['label'] }}</div>
                  <div style="font-size:.75rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">{{ $cat['ages'] }}</div>
                </div>
              </div>
              <span style="font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:700;color:{{ $cat['text'] }};">{{ $cat['count'] }}</span>
            </div>
          @endforeach
        </div>
      </div>

      {{-- Right: Classes --}}
      <div>
        <div style="font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--text-40);margin-bottom:20px;">{{ __('Active Classes') }}</div>
        <div style="display:flex;flex-direction:column;gap:16px;">
          @php
            $classes = $classes ?? collect([
              (object)['name'=>"Children's Program — Grades 1–4",'teacher'=>'Sis. Hiwot Alemu','schedule'=>'Sunday 2:00–3:30 PM','students'=>28,'max'=>35,'age_group'=>'Children'],
              (object)['name'=>"Children's Program — Grades 5–8",'teacher'=>'Bro. Yonas Tadesse','schedule'=>'Sunday 2:00–3:30 PM','students'=>24,'max'=>30,'age_group'=>'Children'],
              (object)['name'=>'Youth Leadership Program','teacher'=>'Sis. Tigist Bekele','schedule'=>'Sunday 3:30–5:00 PM','students'=>32,'max'=>40,'age_group'=>'Youth'],
              (object)['name'=>'Young Adults Theology','teacher'=>'Bro. Dawit Haile','schedule'=>'Saturday 3:00–5:30 PM','students'=>22,'max'=>30,'age_group'=>'Adults'],
            ]);
          @endphp
          @foreach($classes->take(4) as $i => $class)
            @php $cpct = $class->max > 0 ? round(($class->students / $class->max)*100) : 0; @endphp
            <div class="card reveal" style="padding:20px;animation-delay:{{ $i * 0.1 }}s;">
              <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
                <h4 style="font-family:'DM Sans',sans-serif;font-size:.92rem;font-weight:700;color:var(--text-display);line-height:1.3;margin:0;">{{ $class->name }}</h4>
                <span class="badge badge-blue" style="font-size:.62rem;flex-shrink:0;">{{ $class->age_group }}</span>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:6px;">
                  <svg width="13" height="13" fill="none" stroke="var(--text-40)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  <span style="font-size:.78rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">{{ $class->teacher }}</span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                  <svg width="13" height="13" fill="none" stroke="var(--text-40)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <span style="font-size:.78rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">{{ $class->schedule }}</span>
                </div>
              </div>
              <div class="progress-track" style="height:6px;margin-bottom:6px;">
                <div class="progress-fill" style="width:{{ $cpct }}%;"></div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">
                <span>{{ $class->students }}/{{ $class->max }} {{ __('students') }}</span>
                <span>{{ $cpct }}% {{ __('full') }}</span>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §10 — SUNDAY SCHOOL LEADERS (3/4/3)
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-900);overflow:hidden;position:relative;">
  {{-- Decorative bg --}}
  <div style="position:absolute;top:-120px;left:50%;transform:translateX(-50%);width:600px;height:600px;border-radius:50%;background:var(--blue-primary);filter:blur(200px);opacity:.04;pointer-events:none;"></div>

  <div class="container-lg">
    <div style="text-align:center;margin-bottom:56px;">
      <div class="section-label" style="justify-content:center;">{{ __('Leadership') }}</div>
      <h2 class="section-title" style="margin:0 auto 12px;">{{ __('Sunday School Leaders') }}</h2>
      <p class="section-sub" style="margin:0 auto;">{{ __('Dedicated servants guiding our ministry with faith, wisdom, and love.') }}</p>
    </div>

    @php
      $leaders = [
        /* Row 1 — Administration (3) */
        ['name'=>'[Chairman Name]',      'role'=>'Chairman',       'dept'=>'Administration', 'row'=>1, 'initials'=>'CH'],
        ['name'=>'[Vice Chairman Name]', 'role'=>'Vice Chairman',  'dept'=>'Administration', 'row'=>1, 'initials'=>'VC'],
        ['name'=>'[Secretary Name]',     'role'=>'Secretary',      'dept'=>'Administration', 'row'=>1, 'initials'=>'SC'],
        /* Row 2 — Departments (4) */
        ['name'=>'[Dept Head 1]', 'role'=>'Department Head', 'dept'=>'Children\'s Education', 'row'=>2, 'initials'=>'D1'],
        ['name'=>'[Dept Head 2]', 'role'=>'Department Head', 'dept'=>'Youth Ministry',        'row'=>2, 'initials'=>'D2'],
        ['name'=>'[Dept Head 3]', 'role'=>'Department Head', 'dept'=>'Charity & Outreach',    'row'=>2, 'initials'=>'D3'],
        ['name'=>'[Dept Head 4]', 'role'=>'Department Head', 'dept'=>'Music & Worship',       'row'=>2, 'initials'=>'D4'],
        /* Row 3 — Departments (3) */
        ['name'=>'[Dept Head 5]', 'role'=>'Department Head', 'dept'=>'Prayer & Intercession', 'row'=>3, 'initials'=>'D5'],
        ['name'=>'[Dept Head 6]', 'role'=>'Department Head', 'dept'=>'Property & Finance',    'row'=>3, 'initials'=>'D6'],
        ['name'=>'[Dept Head 7]', 'role'=>'Department Head', 'dept'=>'Media & Publications',  'row'=>3, 'initials'=>'D7'],
      ];
      $rows = [1 => array_filter($leaders, fn($l) => $l['row']===1),
               2 => array_filter($leaders, fn($l) => $l['row']===2),
               3 => array_filter($leaders, fn($l) => $l['row']===3)];
    @endphp

    {{-- Row 1: 3 admins (larger cards) --}}
    <div style="id="leaders-row1" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:24px;">
      @foreach($rows[1] as $i => $leader)
        <div class="card reveal" style="padding:32px;text-align:center;animation-delay:{{ $i * 0.1 }}s;position:relative;overflow:hidden;">
          <div style="position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--blue-primary),var(--gold));"></div>

          {{-- Avatar --}}
          <div style="position:relative;display:inline-block;margin-bottom:20px;">
            <div style="width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,var(--blue-primary),var(--blue-500));display:flex;align-items:center;justify-content:center;margin:0 auto;border:3px solid var(--gold);">
              <img src="{{ asset('images/leaders/'.strtolower(str_replace(' ','-',$leader['initials'])).'.jpg') }}"
                alt="{{ $leader['name'] }}"
                style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
                onerror="this.style.display='none'">
              <span style="font-family:'DM Sans',sans-serif;font-size:1.4rem;font-weight:800;color:#fff;display:none;" id="init-{{$i}}-1">{{ $leader['initials'] }}</span>
            </div>
            {{-- Gold ring pulse --}}
            <div style="position:absolute;inset:-4px;border-radius:50%;border:2px solid rgba(243,186,21,.3);animation:pulse-ring 2.5s ease-out infinite;"></div>
          </div>

          <h3 style="font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;color:var(--text-display);margin-bottom:4px;">{{ $leader['name'] }}</h3>
          <div style="font-size:.82rem;font-weight:600;color:var(--gold);font-family:'DM Sans',sans-serif;margin-bottom:4px;">{{ $leader['role'] }}</div>
          <div style="font-size:.75rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">{{ $leader['dept'] }}</div>
        </div>
      @endforeach
    </div>

    {{-- Divider --}}
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
      <div style="flex:1;height:1px;background:var(--border-subtle);"></div>
      <span style="font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-25);">{{ __('Department Heads') }}</span>
      <div style="flex:1;height:1px;background:var(--border-subtle);"></div>
    </div>

    {{-- Row 2: 4 dept heads --}}
    <div style="id="leaders-row2" style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:20px;">
      @foreach($rows[2] as $i => $leader)
        <div class="card reveal" style="padding:24px;text-align:center;animation-delay:{{ ($i+3) * 0.08 }}s;">
          <div style="width:68px;height:68px;border-radius:50%;background:linear-gradient(135deg,var(--bg-800),var(--surface-2));border:2px solid var(--border-mid);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <img src="{{ asset('images/leaders/'.strtolower($leader['initials']).'.jpg') }}"
              alt="{{ $leader['name'] }}"
              style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
              onerror="this.style.display='none'">
            <span style="font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:800;color:var(--blue-primary);">{{ $leader['initials'] }}</span>
          </div>
          <h4 style="font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:700;color:var(--text-display);margin-bottom:4px;">{{ $leader['name'] }}</h4>
          <div style="font-size:.72rem;color:var(--text-40);font-family:'DM Sans',sans-serif;line-height:1.4;">{{ $leader['dept'] }}</div>
        </div>
      @endforeach
    </div>

    {{-- Row 3: 3 dept heads --}}
    <div style="id="leaders-row3" style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">
      @foreach($rows[3] as $i => $leader)
        <div class="card reveal" style="padding:24px;text-align:center;animation-delay:{{ ($i+7) * 0.08 }}s;">
          <div style="width:68px;height:68px;border-radius:50%;background:linear-gradient(135deg,var(--bg-800),var(--surface-2));border:2px solid var(--border-mid);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <img src="{{ asset('images/leaders/'.strtolower($leader['initials']).'.jpg') }}"
              alt="{{ $leader['name'] }}"
              style="width:100%;height:100%;border-radius:50%;object-fit:cover;"
              onerror="this.style.display='none'">
            <span style="font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:800;color:var(--blue-primary);">{{ $leader['initials'] }}</span>
          </div>
          <h4 style="font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:700;color:var(--text-display);margin-bottom:4px;">{{ $leader['name'] }}</h4>
          <div style="font-size:.72rem;color:var(--text-40);font-family:'DM Sans',sans-serif;line-height:1.4;">{{ $leader['dept'] }}</div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §11 — HISTORY TIMELINE
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-base);">
  <div class="container-lg">
    <div style="text-align:center;margin-bottom:64px;">
      <div class="section-label" style="justify-content:center;">{{ __('Heritage') }}</div>
      <h2 class="section-title" style="margin:0 auto 12px;">{{ __('Our Journey Through Time') }}</h2>
      <p class="section-sub" style="margin:0 auto;">{{ __('42+ years of faithful ministry — from humble beginnings to a thriving community.') }}</p>
    </div>

    {{-- Timeline --}}
    <div style="position:relative;">
      {{-- Central line --}}
      <div style="position:absolute;left:50%;top:0;bottom:0;width:2px;background:linear-gradient(to bottom,transparent,var(--blue-primary),var(--gold),var(--blue-primary),transparent);transform:translateX(-50%);z-index:0;"></div>

      @php
        $milestones = [
          ['year'=>'1984 E.C.','title'=>'Humble Beginnings','desc'=>'Started with 12 dedicated students in a small room in Ayertena, founded by a group of faithful youth who believed in the power of Sunday education.','side'=>'left','icon'=>'✝'],
          ['year'=>'1990 E.C.','title'=>'Growing Community','desc'=>'Expanded to three classrooms with over 80 students. The youth program launched, creating a new generation of faith leaders.','side'=>'right','icon'=>'🌱'],
          ['year'=>'2002 E.C.','title'=>'Structured Curriculum','desc'=>'Introduced a comprehensive Amharic and English curriculum with graded levels for children, youth, and young adults.','side'=>'left','icon'=>'📖'],
          ['year'=>'2012 E.C.','title'=>'Seven Departments','desc'=>'Formally established seven ministry departments, each with dedicated leadership, transforming from a class into a full ministry organization.','side'=>'right','icon'=>'🏛'],
          ['year'=>'2016 E.C.','title'=>'Thriving Ministry','desc'=>'Today — '.\App\Models\Member::count().' + students across '.($classes ?? collect())->count().' active classes, 7 vibrant departments, and a community that continues to grow and serve.','side'=>'left','icon'=>'⭐','highlight'=>true],
        ];
      @endphp

      @foreach($milestones as $i => $m)
        <div style="position:relative;z-index:1;display:grid;grid-template-columns:1fr 60px 1fr;gap:0;align-items:center;margin-bottom:48px;" class="reveal" style="animation-delay:{{ $i * 0.15 }}s;">

          {{-- Left content --}}
          @if($m['side']==='left')
            <div style="padding-right:32px;text-align:right;">
              <div class="card" style="padding:24px;display:inline-block;max-width:380px;text-align:left;{{ isset($m['highlight']) ? 'border-color:var(--gold);box-shadow:0 0 30px rgba(243,186,21,.15);' : '' }}">
                @if(isset($m['highlight']))<div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--gold),var(--blue-primary));border-radius:16px 16px 0 0;"></div>@endif
                <span class="badge badge-gold" style="margin-bottom:12px;">{{ $m['year'] }}</span>
                <h4 style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--text-display);margin-bottom:8px;">{{ $m['title'] }}</h4>
                <p style="font-size:.85rem;color:var(--text-40);line-height:1.7;margin:0;font-family:'DM Sans',sans-serif;">{{ $m['desc'] }}</p>
              </div>
            </div>
          @else
            <div></div>
          @endif

          {{-- Center marker --}}
          <div style="display:flex;justify-content:center;align-items:center;position:relative;">
            <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--blue-primary),var(--blue-500));border:3px solid {{ isset($m['highlight']) ? 'var(--gold)' : 'var(--bg-base)' }};display:flex;align-items:center;justify-content:center;font-size:1.1rem;z-index:2;box-shadow:0 0 20px var(--blue-glow);">
              {{ $m['icon'] }}
            </div>
          </div>

          {{-- Right content --}}
          @if($m['side']==='right')
            <div style="padding-left:32px;">
              <div class="card" style="padding:24px;max-width:380px;">
                <span class="badge badge-gold" style="margin-bottom:12px;">{{ $m['year'] }}</span>
                <h4 style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--text-display);margin-bottom:8px;">{{ $m['title'] }}</h4>
                <p style="font-size:.85rem;color:var(--text-40);line-height:1.7;margin:0;font-family:'DM Sans',sans-serif;">{{ $m['desc'] }}</p>
              </div>
            </div>
          @else
            <div></div>
          @endif
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §12 — EVENTS & TOURS TABS
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-900);"
  x-data="{ tab: 'events' }">
  <div class="container-lg">
    <div style="text-align:center;margin-bottom:48px;">
      <div class="section-label" style="justify-content:center;">{{ __('Activities') }}</div>
      <h2 class="section-title" style="margin:0 auto 24px;">{{ __('Upcoming Activities') }}</h2>

      {{-- Tabs --}}
      <div style="display:inline-flex;background:var(--surface);border:1px solid var(--border-subtle);border-radius:50px;padding:4px;">
        <button @click="tab='events'"
          :style="`padding:10px 28px;border-radius:50px;font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:600;cursor:pointer;border:none;transition:all .25s;
            background:${tab==='events'?'var(--blue-primary)':'transparent'};
            color:${tab==='events'?'#fff':'var(--text-40)'};`">
          ⚡ {{ __('Events') }}
        </button>
        <button @click="tab='tours'"
          :style="`padding:10px 28px;border-radius:50px;font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:600;cursor:pointer;border:none;transition:all .25s;
            background:${tab==='tours'?'var(--blue-primary)':'transparent'};
            color:${tab==='tours'?'#fff':'var(--text-40)'};`">
          🚌 {{ __('Tours') }}
        </button>
      </div>
    </div>

    {{-- Events tab --}}
    <div x-show="tab==='events'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
      <div style="display:flex;flex-direction:column;gap:16px;max-width:800px;margin:0 auto;">
        @php
          $events2 = $events ?? collect([
            (object)['title'=>'Youth Annual Retreat','start_date'=>now()->addDays(7),'location'=>'Debre Zeyit','registered'=>18,'capacity'=>30,'time'=>'8:00 AM'],
            (object)['title'=>'Christmas Celebration 2016 E.C.','start_date'=>now()->addDays(21),'location'=>'Church Main Hall','registered'=>85,'capacity'=>150,'time'=>'2:00 PM'],
            (object)['title'=>'New Year Prayer Service','start_date'=>now()->addDays(35),'location'=>'Church Sanctuary','registered'=>40,'capacity'=>200,'time'=>'6:00 AM'],
          ]);
        @endphp
        @foreach($events2->take(3) as $i => $ev)
          @php
            $ep = $ev->capacity > 0 ? round(($ev->registered / $ev->capacity)*100) : 0;
            $evDate = $ev->date_time ?? $ev->start_date ?? now();
            $evName = $ev->name ?? $ev->title ?? __('Event');
          @endphp
          <div class="card reveal" style="padding:24px;display:grid;grid-template-columns:80px 1fr auto;gap:20px;align-items:center;animation-delay:{{ $i * 0.1 }}s;">
            <div style="background:linear-gradient(135deg,var(--blue-primary),var(--blue-500));border-radius:12px;padding:14px;text-align:center;">
              <div style="font-size:1.6rem;font-weight:900;color:#fff;line-height:1;font-family:'DM Sans',sans-serif;">{{ \Carbon\Carbon::parse($evDate)->format('d') }}</div>
              <div style="font-size:.65rem;color:rgba(255,255,255,.7);text-transform:uppercase;font-family:'DM Sans',sans-serif;">{{ \Carbon\Carbon::parse($evDate)->format('M') }}</div>
            </div>
            <div>
              <h4 style="font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;color:var(--text-display);margin-bottom:6px;">{{ $evName }}</h4>
              <div style="display:flex;gap:16px;flex-wrap:wrap;">
                <span style="font-size:.78rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">📍 {{ $ev->location ?? __('TBD') }}</span>
                <span style="font-size:.78rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">🕐 {{ \Carbon\Carbon::parse($evDate)->format('h:i A') }}</span>
              </div>
              <div style="margin-top:10px;">
                <div class="progress-track" style="height:5px;margin-bottom:4px;">
                  <div class="progress-fill" style="width:{{ $ep }}%;"></div>
                </div>
                <span style="font-size:.72rem;color:var(--text-40);font-family:'DM Sans',sans-serif;">{{ $ev->registered ?? 0 }}/{{ $ev->capacity ?? '∞' }} registered</span>
              </div>
            </div>
            <a href="{{ route('news') }}" class="btn-primary" style="padding:10px 20px;font-size:.82rem;white-space:nowrap;">Register</a>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Tours tab --}}
    <div x-show="tab==='tours'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
      <div style="id="tours-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;">
        @php
          $tours = $tours ?? collect([
            (object)['title'=>'Lalibela Pilgrimage','departure_date'=>now()->addDays(45),'registered'=>22,'capacity'=>40,'cost'=>3500,'highlights'=>['Guided tour of rock churches','All meals included','Return transport']],
            (object)['title'=>'Axum Heritage Tour','departure_date'=>now()->addDays(90),'registered'=>15,'capacity'=>35,'cost'=>4200,'highlights'=>['Stelae & St. Mary of Zion','Expert guide','Hotel accommodation']],
            (object)['title'=>'Lake Tana Monasteries','departure_date'=>now()->addDays(120),'registered'=>8,'capacity'=>25,'cost'=>2800,'highlights'=>['Boat ride to islands','Ancient manuscripts','Breakfast included']],
          ]);
        @endphp
        @foreach($tours->take(3) as $i => $tour)
          @php $tp = $tour->capacity > 0 ? round(($tour->registered / $tour->capacity)*100) : 0; @endphp
          <div class="card reveal" style="overflow:hidden;animation-delay:{{ $i * 0.1 }}s;">
            <div style="height:180px;background:linear-gradient(135deg,var(--bg-800),var(--surface-2));
              background-image:url({{ asset('images/hero-bg.jpg') }});background-size:cover;background-position:center;position:relative;">
              <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(5,10,28,.7),transparent);"></div>
              <div style="position:absolute;bottom:12px;left:12px;">
                <span class="badge" style="background:rgba(243,186,21,.9);color:#0A0F2E;font-size:.68rem;">ETB {{ number_format($tour->cost) }}</span>
              </div>
            </div>
            <div style="padding:20px;">
              <h4 style="font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;color:var(--text-display);margin-bottom:8px;">{{ $tour->title }}</h4>
              <p style="font-size:.78rem;color:var(--text-40);font-family:'DM Sans',sans-serif;margin-bottom:12px;">📅 Departs {{ \Carbon\Carbon::parse($tour->departure_date)->format('M d, Y') }}</p>
              <ul style="list-style:none;padding:0;margin:0 0 16px;display:flex;flex-direction:column;gap:6px;">
                @foreach($tour->highlights ?? [] as $h)
                  <li style="font-size:.78rem;color:var(--text-40);font-family:'DM Sans',sans-serif;display:flex;align-items:center;gap:6px;">
                    <span style="color:var(--gold);font-size:.6rem;">✦</span> {{ $h }}
                  </li>
                @endforeach
              </ul>
              <div class="progress-track" style="margin-bottom:6px;">
                <div class="progress-fill" style="width:{{ $tp }}%;"></div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-40);font-family:'DM Sans',sans-serif;margin-bottom:14px;">
                <span>{{ $tour->registered }}/{{ $tour->capacity }} seats</span>
                <span>{{ 100-$tp }}% available</span>
              </div>
              <a href="{{ route('tours.index') }}" class="btn-outline" style="display:block;text-align:center;padding:10px;">Register for Tour</a>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>

{{-- ══════════════════════════════════════════
     §13 — COMMUNITY IMPACT
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-base);"
  x-data="{
    active: 0,
    testimonials: [
      { quote: 'This Sunday School changed my life. I came as a lost teenager and found faith, purpose, and family. The teachers poured into us with love and patience.', name: 'Marta Haile', role: 'Youth Member', initials: 'MH' },
      { quote: 'The charity programs here are unmatched. We received clothing and food during a very difficult season. The kindness we experienced was genuinely Christlike.', name: 'Abebe Tadesse', role: 'Community Beneficiary', initials: 'AT' },
      { quote: 'Teaching here for 8 years has been the greatest privilege of my life. Watching children grow in their faith is a reward beyond any words.', name: 'Sis. Tigist Bekele', role: 'Sunday School Teacher', initials: 'TB' },
    ],
    interval: null,
    init() { this.interval = setInterval(()=>{ this.active=(this.active+1)%this.testimonials.length; }, 5000); },
    destroy() { clearInterval(this.interval); }
  }">

  <div class="container-lg">
    <div style="text-align:center;margin-bottom:56px;">
      <div class="section-label" style="justify-content:center;">{{ __('Impact') }}</div>
      <h2 class="section-title" style="margin:0 auto 12px;">{{ __('Community Impact') }}</h2>
      <p class="section-sub" style="margin:0 auto;">{{ __('Serving those in need with love, compassion, and the spirit of Christ.') }}</p>
    </div>

    {{-- Stats --}}
    <div style="id="community-stats" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:64px;"
      x-data="{
        stats:[{end:1200,label:'Lives Touched',suffix:'+',icon:'❤'},{end:48,label:'Aid Distributions',suffix:'',icon:'📦'},{end:1250,label:'Volunteer Hours',suffix:'+',icon:'⏰'}],
        vals:[0,0,0],started:false,
        start(){ if(this.started)return;this.started=true;
          this.stats.forEach((s,i)=>{ let c=0,step=Math.ceil(s.end/60),t=setInterval(()=>{c=Math.min(c+step,s.end);this.vals[i]=c;if(c>=s.end)clearInterval(t);},30); }); }
      }" x-init="const _obs=new IntersectionObserver(e=>{if(e[0].isIntersecting){start();_obs.disconnect();}},{threshold:0.2});_obs.observe($el);">
      <template x-for="(s,i) in stats" :key="i">
        <div class="card reveal" style="padding:36px;text-align:center;">
          <div style="font-size:2.4rem;margin-bottom:12px;" x-text="s.icon"></div>
          <div style="font-family:'DM Sans',sans-serif;font-size:2.4rem;font-weight:800;color:var(--text-display);line-height:1;" x-text="vals[i]+s.suffix"></div>
          <div style="font-size:.85rem;color:var(--text-40);margin-top:8px;font-family:'DM Sans',sans-serif;" x-text="s.label"></div>
        </div>
      </template>
    </div>

    {{-- Testimonials --}}
    <div style="position:relative;background:linear-gradient(135deg,rgba(26,68,247,.08),rgba(243,186,21,.05));border:1px solid var(--border-subtle);border-radius:24px;padding:48px;text-align:center;overflow:hidden;">
      <div style="font-size:5rem;color:var(--gold);opacity:.15;font-family:'Playfair Display',serif;line-height:1;position:absolute;top:16px;left:32px;">"</div>

      <template x-for="(t,i) in testimonials" :key="i">
        <div x-cloak x-show="active===i" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
          <p style="font-family:'Playfair Display',serif;font-size:clamp(1rem,2vw,1.2rem);color:var(--text-60);font-style:italic;line-height:1.8;max-width:680px;margin:0 auto 32px;" x-text="'\"'+t.quote+'\"'"></p>
          <div style="display:flex;align-items:center;justify-content:center;gap:16px;">
            <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--blue-primary),var(--gold));border:3px solid var(--gold);display:flex;align-items:center;justify-content:center;">
              <span style="font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:800;color:#fff;" x-text="t.initials"></span>
            </div>
            <div style="text-align:left;">
              <div style="font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:700;color:var(--text-display);" x-text="t.name"></div>
              <div style="font-size:.78rem;color:var(--text-40);font-family:'DM Sans',sans-serif;" x-text="t.role"></div>
            </div>
          </div>
        </div>
      </template>

      {{-- Dots --}}
      <div style="display:flex;justify-content:center;gap:8px;margin-top:32px;">
        <template x-for="(t,i) in testimonials" :key="'td'+i">
          <button @click="active=i;clearInterval(interval)"
            :style="`width:${active===i?'28px':'8px'};height:8px;border-radius:50px;border:none;cursor:pointer;transition:all .3s;
              background:${active===i?'var(--gold)':'var(--border-mid)'};`">
          </button>
        </template>
      </div>
    </div>

    {{-- Volunteer CTA --}}
    <div style="margin-top:32px;background:linear-gradient(135deg,var(--blue-primary) 0%,var(--blue-500) 100%);border-radius:20px;padding:40px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:24px;">
      <div>
        <h3 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:#fff;margin-bottom:8px;">Want to Make a Difference?</h3>
        <p style="font-size:.9rem;color:rgba(255,255,255,.75);margin:0;font-family:'DM Sans',sans-serif;">Join our volunteer team and serve the community with love and purpose.</p>
      </div>
      <a href="{{ route('contact') }}" style="display:inline-flex;align-items:center;gap:8px;padding:14px 28px;border-radius:50px;background:#fff;color:var(--blue-primary);font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:700;text-decoration:none;transition:transform .2s,box-shadow .2s;box-shadow:0 4px 20px rgba(0,0,0,.2);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
        Become a Volunteer →
      </a>
    </div>
  </div>
</section>

{{-- ══════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════ --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

  // ── Scroll reveal ──────────────────────────────────────────────────
  const revealEls = document.querySelectorAll('.reveal');
  const ro = new IntersectionObserver((entries) => {
    entries.forEach(e => { if(e.isIntersecting) { e.target.classList.add('visible'); ro.unobserve(e.target); } });
  }, { threshold: 0.12 });
  revealEls.forEach(el => ro.observe(el));

  // ── Charts (wait for Chart.js to load) ────────────────────────────
  function initCharts() {
    if(typeof Chart === 'undefined') { setTimeout(initCharts, 200); return; }

    const isDark = () => document.documentElement.getAttribute('data-theme') !== 'light';
    const gridColor  = () => isDark() ? 'rgba(255,255,255,.06)' : 'rgba(10,15,46,.06)';
    const textColor  = () => isDark() ? 'rgba(240,244,255,.4)' : 'rgba(10,15,46,.5)';

    // Membership growth chart
    const mCtx = document.getElementById('membershipChart');
    if(mCtx) {
      new Chart(mCtx, {
        type: 'line',
        data: {
          labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
          datasets: [{
            label: 'Members',
            data: [{{ implode(',', $monthlyMembershipData ?? array_map(fn() => rand(280, 350), range(1, 12))) }}],
            borderColor: '#1A44F7',
            backgroundColor: 'rgba(26,68,247,.08)',
            borderWidth: 2.5,
            pointBackgroundColor: '#1A44F7',
            pointRadius: 3,
            fill: true,
            tension: 0.4,
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: true,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { color: gridColor() }, ticks: { color: textColor(), font: { size: 10, family: 'DM Sans' } } },
            y: { grid: { color: gridColor() }, ticks: { color: textColor(), font: { size: 10, family: 'DM Sans' } } },
          }
        }
      });
    }

    // Ministry activity chart
    const aCtx = document.getElementById('ministryChart');
    if(aCtx) {
      new Chart(aCtx, {
        type: 'bar',
        data: {
          labels: ['Events','Tours','Resources','Blog','Classes'],
          datasets: [{
            label: 'Count',
            data: [12, 3, 28, 15, 12],
            backgroundColor: ['rgba(26,68,247,.7)','rgba(243,186,21,.7)','rgba(52,211,153,.7)','rgba(167,139,250,.7)','rgba(248,113,113,.7)'],
            borderRadius: 6,
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: true,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false }, ticks: { color: textColor(), font: { size: 9, family: 'DM Sans' } } },
            y: { grid: { color: gridColor() }, ticks: { color: textColor(), font: { size: 9, family: 'DM Sans' } } },
          }
        }
      });
    }
  }

  // Observe charts section
  const chartSection = document.getElementById('membershipChart');
  if(chartSection) {
    const co = new IntersectionObserver((entries) => {
      if(entries[0].isIntersecting) { initCharts(); co.disconnect(); }
    }, { threshold: 0.2 });
    co.observe(chartSection);
  }

  // ── Hero image fallback: show initials if photo 404s ──────────────
  document.querySelectorAll('[id^="init-"]').forEach(span => {
    const img = span.previousElementSibling;
    if(img && img.tagName === 'IMG') {
      img.addEventListener('error', () => { img.style.display='none'; span.style.display='block'; });
    }
  });

  // ── Fundraising API fetch ──────────────────────────────────────────
  async function loadCampaigns() {
    const grid = document.getElementById('campaigns-grid');
    if (!grid) return;
    try {
      const res  = await fetch('{{ route('fundraising.api') }}');
      const data = await res.json();
      const items = (data.campaigns || []).slice(0, 3);

      if (!items.length) {
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:48px 24px;">
          <div style="font-size:2rem;margin-bottom:12px;">💰</div>
          <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--text-display);margin-bottom:8px;">{{ __('No Active Campaigns') }}</h3>
          <p style="color:var(--text-40);font-size:.85rem;">{{ __('Check back soon for new fundraising campaigns.') }}</p>
        </div>`;
        return;
      }

      grid.innerHTML = items.map((c, i) => {
        const pct  = Math.min(100, Math.round(c.progress_percentage || 0));
        const days = c.days_remaining !== null && c.days_remaining !== undefined ? c.days_remaining : null;
        const dColor = days !== null ? (days < 3 ? '#F87171' : days < 7 ? '#FBBF24' : '#34D399') : '#34D399';
        const raised = Number(c.total_raised || 0).toLocaleString();
        const goal   = Number(c.target_amount || 0).toLocaleString();
        return `
          <div class="card reveal" style="overflow:hidden;animation-delay:${i * 0.1}s;">
            <div style="height:160px;background:linear-gradient(135deg,var(--bg-800),var(--surface-2));
              background-image:url({{ asset('images/features-bg.jpg') }});background-size:cover;background-position:center;position:relative;">
              <div style="position:absolute;top:12px;right:12px;">
                <span class="badge badge-gold" style="font-size:.65rem;">${c.status || 'Active'}</span>
              </div>
            </div>
            <div style="padding:24px;">
              <h4 style="font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;color:var(--text-display);margin-bottom:8px;line-height:1.4;">${c.campaign_name || ''}</h4>
              <div class="progress-track" style="margin-bottom:10px;">
                <div class="progress-fill" id="cpf-${c.id}" style="width:0%;"></div>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <div>
                  <span style="font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:700;color:var(--blue-primary);">ETB ${raised}</span>
                  <span style="font-size:.75rem;color:var(--text-40);font-family:'DM Sans',sans-serif;"> / ETB ${goal}</span>
                </div>
                ${days !== null ? `<span style="font-size:.75rem;font-weight:600;font-family:'DM Sans',sans-serif;color:${dColor};">${days}d left</span>` : ''}
              </div>
              <a href="{{ route('fundraising.index') }}" class="btn-primary" style="display:block;text-align:center;padding:12px;">
                {{ __('Donate Now') }} ♥
              </a>
            </div>
          </div>`;
      }).join('');

      // Animate progress bars after render
      requestAnimationFrame(() => {
        items.forEach(c => {
          const el = document.getElementById('cpf-' + c.id);
          if (el) setTimeout(() => { el.style.width = Math.min(100, c.progress_percentage || 0) + '%'; }, 300);
        });
        // Re-run scroll reveal on new cards
        document.querySelectorAll('#campaigns-grid .reveal').forEach(el => ro.observe(el));
      });

    } catch (e) {
      console.warn('Fundraising load failed:', e);
      grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:32px;color:var(--text-40);font-size:.85rem;font-family:'DM Sans',sans-serif;">
        {{ __('Unable to load campaigns.') }} <a href="{{ route('fundraising.index') }}" style="color:var(--blue-primary);">{{ __('View all') }}</a>
      </div>`;
    }
  }

  // Load campaigns when section scrolls into view
  const campSection = document.getElementById('campaigns-grid');
  if (campSection) {
    const campObs = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting) { loadCampaigns(); campObs.disconnect(); }
    }, { threshold: 0.1 });
    campObs.observe(campSection);
  }

});
</script>
@endpush

{{-- ══════════════════════════════════════════
     RESPONSIVE + THEME TRANSITION STYLES
══════════════════════════════════════════ --}}
{{-- ══════════════════════════════════════════
     §14 — FAQ + JOIN CTA
══════════════════════════════════════════ --}}
<section class="section-pad" style="background:var(--bg-900);">
  <div class="container-lg">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:start;" id="faq-cta-grid">

      {{-- FAQ --}}
      <div>
        <div class="section-label">{{ __('Questions') }}</div>
        <h2 class="section-title">{{ __('Frequently Asked') }}</h2>

        @php $faqs = $faqs ?? collect([]); @endphp

        @if($faqs->count() > 0)
          @foreach($faqs as $faq)
            <div x-data="{ open: false }" style="border-bottom:1px solid var(--border-subtle);padding:16px 0;">
              <button @click="open=!open" style="width:100%;display:flex;align-items:center;justify-content:space-between;background:none;border:none;cursor:pointer;text-align:left;gap:12px;">
                <span style="font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:600;color:var(--text-display);">
                  {{ app()->getLocale() === 'am' ? ($faq->question_am ?? $faq->question) : $faq->question }}
                </span>
                <svg width="16" height="16" fill="none" stroke="var(--text-40)" viewBox="0 0 24 24"
                  :style="open ? 'transform:rotate(45deg);transition:transform .25s' : 'transition:transform .25s'">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/>
                </svg>
              </button>
              <div x-show="open" x-transition style="padding-top:12px;">
                <p style="font-size:.88rem;color:var(--text-40);line-height:1.75;margin:0;font-family:'DM Sans',sans-serif;">
                  {!! app()->getLocale() === 'am' ? ($faq->answer_am ?? $faq->answer) : $faq->answer !!}
                </p>
              </div>
            </div>
          @endforeach
        @else
          {{-- Fallback static FAQs --}}
          @foreach([
            ['q'=>__('Where are you located?'), 'a'=>__('We are located in Addis Ababa, Ayertena. Visit our Contact page for exact directions and a map.')],
            ['q'=>__('Who can join the Sunday school?'), 'a'=>__('Our programs are open to all ages — children, youth, young adults, and parents. Come as you are.')],
            ['q'=>__('How can I become a member?'), 'a'=>__('Contact our Internal Relations department or visit us during service hours on Sunday or Saturday.')],
            ['q'=>__('How can I volunteer?'), 'a'=>__('Send us a message via the Contact page and our team will reach out with available opportunities.')],
            ['q'=>__('What are the service times?'), 'a'=>__('Sunday school runs Sunday 2:00–5:00 PM and Saturday 3:00–5:30 PM at our Ayertena location.')],
          ] as $faq)
            <div x-data="{ open: false }" style="border-bottom:1px solid var(--border-subtle);padding:16px 0;">
              <button @click="open=!open" style="width:100%;display:flex;align-items:center;justify-content:space-between;background:none;border:none;cursor:pointer;text-align:left;gap:12px;">
                <span style="font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:600;color:var(--text-display);">{{ $faq['q'] }}</span>
                <svg width="16" height="16" fill="none" stroke="var(--text-40)" viewBox="0 0 24 24"
                  :style="open ? 'transform:rotate(45deg);transition:transform .25s' : 'transition:transform .25s'">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/>
                </svg>
              </button>
              <div x-show="open" x-transition style="padding-top:12px;">
                <p style="font-size:.88rem;color:var(--text-40);line-height:1.75;margin:0;font-family:'DM Sans',sans-serif;">{{ $faq['a'] }}</p>
              </div>
            </div>
          @endforeach
        @endif
      </div>

      {{-- Join CTA card --}}
      <div>
        <div style="background:linear-gradient(135deg,var(--surface),var(--surface-2));border:1px solid var(--border-subtle);border-radius:20px;padding:40px;position:relative;overflow:hidden;">
          <div style="position:absolute;bottom:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:var(--blue-primary);filter:blur(70px);opacity:.12;pointer-events:none;"></div>

          <div class="section-label">{{ __('Get Involved') }}</div>
          <h3 class="font-display" style="font-size:1.7rem;font-weight:700;color:var(--text-display);margin-bottom:12px;">{{ __('Join Our Community') }}</h3>
          <p style="font-size:.9rem;color:var(--text-40);margin-bottom:28px;line-height:1.7;font-family:'DM Sans',sans-serif;">
            {{ __('Get updates about events, programs, and announcements. Be part of something meaningful.') }}
          </p>

          <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:28px;">
            <a href="{{ route('contact') }}" class="btn-primary" style="justify-content:center;text-align:center;">{{ __('Contact Us') }}</a>
            <a href="{{ route('library') }}" class="btn-outline" style="justify-content:center;text-align:center;">{{ __('Explore Programs') }}</a>
          </div>

          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding-top:24px;border-top:1px solid var(--border-subtle);">
            @foreach([
              ['am'=>'ህጻናት','en'=>__('Children'),'count'=>\App\Models\Member::where('member_type','Kids')->count(),'color'=>'#FBBF24'],
              ['am'=>'አዳጊ','en'=>__('Youth'),'count'=>\App\Models\Member::where('member_type','Youth')->count(),'color'=>'#A78BFA'],
              ['am'=>'ወጣት','en'=>__('Adults'),'count'=>\App\Models\Member::where('member_type','Adult')->count(),'color'=>'#7FA3FF'],
              ['am'=>'ጠቅላ','en'=>__('Total'),'count'=>\App\Models\Member::count(),'color'=>'var(--gold)'],
            ] as $pg)
              <div style="padding:12px;border-radius:10px;background:var(--glass);border:1px solid var(--border-subtle);text-align:center;">
                <div class="font-amharic" style="font-size:.9rem;color:{{ $pg['color'] }};font-weight:700;">{{ $pg['am'] }}</div>
                <div style="font-size:1.2rem;font-weight:800;color:var(--text-display);font-family:'DM Sans',sans-serif;">{{ $pg['count'] }}</div>
                <div style="font-size:.68rem;color:var(--text-25);font-family:'DM Sans',sans-serif;">{{ $pg['en'] }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

@push('styles')
<style>
  #faq-cta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: start; }
  @media(max-width:768px){ #faq-cta-grid { grid-template-columns: 1fr; gap: 40px; } }
</style>
@endpush


@push('styles')
<style>

/* ── Smooth theme transition on every element ── */
*, *::before, *::after {
  transition-property: background-color, border-color, color, box-shadow;
  transition-duration: 300ms;
  transition-timing-function: ease;
}
/* But NOT layout/transform properties — would break animations */
.card, .btn-primary, .btn-gold, .btn-outline, .reveal { transition: none; }
.card {
  background: var(--surface);
  border: 1px solid var(--border-subtle);
  border-radius: 16px;
  transition: transform .3s cubic-bezier(.22,1,.36,1), box-shadow .3s, border-color .3s, background-color 300ms ease;
}

/* ── Body background ── */
body { background: var(--bg-base); }

/* ── Light mode: ensure sufficient contrast everywhere ── */
[data-theme="light"] .section-label { color: #B8860B; }
[data-theme="light"] .section-title  { color: #0A0F2E; }
[data-theme="light"] .section-sub    { color: rgba(10,15,46,.55); }
[data-theme="light"] .badge-blue     { background: rgba(26,68,247,.1); color: #1A44F7; border-color: rgba(26,68,247,.2); }
[data-theme="light"] .badge-green    { background: rgba(5,150,105,.1); color: #047857; border-color: rgba(5,150,105,.2); }
[data-theme="light"] .badge-red      { background: rgba(220,38,38,.1); color: #B91C1C; border-color: rgba(220,38,38,.2); }
[data-theme="light"] .badge-gold     { background: rgba(180,130,0,.1); color: #92660A; border-color: rgba(180,130,0,.2); }
[data-theme="light"] .progress-track { background: rgba(10,15,46,.1); }
[data-theme="light"] .btn-outline    { border-color: rgba(10,15,46,.2); color: #0A0F2E; }
[data-theme="light"] .btn-outline:hover { background: rgba(26,68,247,.06); border-color: var(--blue-primary); }

/* ── Live feed section 3-col → 1-col ── */
#live-feed-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 24px; }
@media(max-width:1100px){ #live-feed-grid { grid-template-columns: 1fr 1fr; } }
@media(max-width:720px) { #live-feed-grid { grid-template-columns: 1fr; } }

/* ── Impact section ── */
#impact-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 64px; align-items: start; }
@media(max-width:900px){ #impact-grid { grid-template-columns: 1fr; gap: 40px; } }

/* ── Counter grid ── */
#counter-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 20px; margin-bottom: 28px; }

/* ── Chart row ── */
#chart-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media(max-width:600px){ #chart-row { grid-template-columns: 1fr; } }

/* ── Blog grid ── */
#blog-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; }
@media(max-width:900px){ #blog-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:600px){ #blog-grid { grid-template-columns: 1fr; } }

/* ── Quick actions ── */
#actions-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; }
@media(max-width:900px){ #actions-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:480px){ #actions-grid { grid-template-columns: 1fr; } }

/* ── Fundraising ── */
#campaigns-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; }
@media(max-width:900px){ #campaigns-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:600px){ #campaigns-grid { grid-template-columns: 1fr; } }

/* ── Sunday school ── */
#sunday-grid { display: grid; grid-template-columns: 1fr 1.6fr; gap: 64px; align-items: start; }
@media(max-width:900px){ #sunday-grid { grid-template-columns: 1fr; gap: 40px; } }

/* ── Leaders ── */
#leaders-row1 { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; margin-bottom: 24px; }
#leaders-row2 { display: grid; grid-template-columns: repeat(4,1fr); gap: 20px; margin-bottom: 20px; }
#leaders-row3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; }
@media(max-width:900px){
  #leaders-row1 { grid-template-columns: repeat(3,1fr); }
  #leaders-row2 { grid-template-columns: repeat(2,1fr); }
  #leaders-row3 { grid-template-columns: repeat(3,1fr); }
}
@media(max-width:600px){
  #leaders-row1 { grid-template-columns: 1fr; }
  #leaders-row2 { grid-template-columns: repeat(2,1fr); }
  #leaders-row3 { grid-template-columns: 1fr; }
}

/* ── Timeline ── */
#timeline-line { display: block; }
.timeline-row { display: grid; grid-template-columns: 1fr 60px 1fr; gap: 0; align-items: center; margin-bottom: 48px; }
@media(max-width:768px){
  #timeline-line { display: none; }
  .timeline-row { grid-template-columns: 40px 1fr; gap: 16px; }
  .timeline-row .timeline-right { grid-column: 2; }
  .timeline-row .timeline-left  { grid-column: 2; }
  .timeline-row .timeline-marker { grid-column: 1; grid-row: 1; }
}

/* ── Tours grid ── */
#tours-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; }
@media(max-width:900px){ #tours-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:600px){ #tours-grid { grid-template-columns: 1fr; } }

/* ── Community stats ── */
#community-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; margin-bottom: 64px; }
@media(max-width:700px){ #community-stats { grid-template-columns: 1fr; } }

/* ── Hero ── */
#hero-grid { display: grid; grid-template-columns: 1fr 420px; gap: 48px; align-items: center; }
@media(max-width:1024px){
  #hero-grid { grid-template-columns: 1fr; }
  #hero-stats-card { display: none; }
}

/* ── Global padding ── */
@media(max-width:768px){
  .section-pad    { padding: 64px 16px !important; }
  .section-pad-sm { padding: 48px 16px !important; }
  .section-title  { font-size: clamp(1.6rem, 5vw, 2.4rem) !important; }
}
@media(max-width:480px){
  #hero h1 { font-size: 2rem !important; }
  .btn-primary, .btn-gold, .btn-outline { padding: 12px 20px !important; font-size: .82rem !important; }
}

/* ── Masonry gallery responsive ── */
#gallery-masonry { columns: 3; column-gap: 16px; }
@media(max-width:900px){ #gallery-masonry { columns: 2; } }
@media(max-width:480px){ #gallery-masonry { columns: 1; } }

/* ── Faith journey mobile: hide complex parallax, show simple slider ── */
@media(max-width:600px){
  #faith-journey { height: auto !important; min-height: 100vh; }
}

/* ── Alpine x-cloak ── */
[x-cloak] { display: none !important; }

/* ── Focus styles for accessibility ── */
a:focus-visible,
button:focus-visible {
  outline: 2px solid var(--blue-primary);
  outline-offset: 3px;
  border-radius: 4px;
}

/* ── Skip to content ── */
.skip-link {
  position: absolute; top: -100px; left: 16px; z-index: 9999;
  padding: 12px 20px; border-radius: 8px;
  background: var(--blue-primary); color: #fff;
  font-family: 'DM Sans', sans-serif; font-weight: 600;
  text-decoration: none; transition: top .2s;
}
.skip-link:focus { top: 16px; }
</style>
@endpush

@endsection
