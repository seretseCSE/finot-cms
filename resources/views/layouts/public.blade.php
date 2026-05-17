<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $isResourcePage = Request::is('songs*') || Request::is('library*') || Request::is('media*');
    @endphp
    <!-- Disable caching for development -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>@yield('title', 'Finote Tsidik') | {{ config('app.name') }}</title>
    <meta name="description" content="@yield('meta_description', __('Finot-Tsidik Sunday School — Faith, service, and fellowship since 1984 E.C.'))">

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1A44F7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/logo.png') }}">

    <!-- Fonts: Inter (authority) + Playfair Display (grace) + Noto Sans Ethiopic (Amharic) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* ═══════════════════════════════
           DESIGN SYSTEM — Netlify Brand
           Blue #1A44F7 + Gold #F3BA15
        ═══════════════════════════════ */
        :root {
            /* Branding colors remain consistent */
            --blue-primary:  #1A44F7;
            --blue-600:      #2952FF;
            --blue-500:      #4D6FFF;
            --blue-400:      #7B94FF;
            --blue-glow:     rgba(26,68,247,0.30);
            --gold:          #F3BA15;
            --gold-light:    #FFCF42;
            --gold-dim:      rgba(243,186,21,0.10);
            --gold-border:   rgba(243,186,21,0.18);

            /* Theme-aware variables — Default (Dark) */
            --bg-950:        #050A1C;
            --bg-900:        #0A1230;
            --bg-800:        #101B48;
            --bg-700:        #182660;
            
            --text-main:     #E8E4DC;
            --text-60:       rgba(232,228,220,0.60);
            --text-40:       rgba(232,228,220,0.40);
            --text-display:  #FFFFFF;
            --text-hero:     #FFFFFF;
            
            --glass:         rgba(255,255,255,0.04);
            --glass-hover:   rgba(255,255,255,0.08);
            --border-subtle: rgba(255,255,255,0.08);
            --overlay-98:    rgba(5,10,28,0.98);
            --overlay-95:    rgba(5,10,28,0.95);
            --overlay-90:    rgba(5,10,28,0.90);
            --overlay-85:    rgba(5,10,28,0.85);
            --overlay-80:    rgba(5,10,28,0.80);
            --overlay-40:    rgba(5,10,28,0.40);

            --r:    12px;
            --r-lg: 20px;

            /* Backward compatibility for existing pages */
            --dark-950:      var(--bg-950);
            --dark-900:      var(--bg-900);
            --dark-800:      var(--bg-800);
            --dark-700:      var(--bg-700);
            --parchment:     var(--text-main);
            --parchment-60:  var(--text-60);
            --parchment-40:  var(--text-40);
        }

        [data-theme="light"] {
            --bg-950:        #F8F9FA;
            --bg-900:        #FFFFFF;
            --bg-800:        #F1F3F5;
            --bg-700:        #E9ECEF;

            --gold:          #C8960A;
            --gold-accent:   #C8960A;

            --text-main:     #0F172A;
            --text-60:       #475569;
            --text-40:       #475569;
            --text-display:  #0F172A;
            --text-hero:     #0F172A;

            --glass:         rgba(0,0,0,0.07);
            --glass-hover:   rgba(0,0,0,0.11);
            --border-subtle: rgba(15,23,42,0.15);
            --blue-glow:     rgba(26,68,247,0.15);
            --overlay-98:    rgba(248,249,250,0.98);
            --overlay-95:    rgba(248,249,250,0.95);
            --overlay-90:    rgba(248,249,250,0.90);
            --overlay-85:    rgba(248,249,250,0.85);
            --overlay-80:    rgba(248,249,250,0.80);
            --overlay-40:    rgba(248,249,250,0.30);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-950);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
            transition: background .3s, color .3s;
        }

        /* ── Scroll Reveal ── */
        .sr { opacity: 0; transform: translateY(32px); transition: opacity .7s cubic-bezier(.22,1,.36,1), transform .7s cubic-bezier(.22,1,.36,1); }
        .sr.in  { opacity: 1; transform: none; }
        .sr-l   { opacity: 0; transform: translateX(-32px); transition: opacity .7s cubic-bezier(.22,1,.36,1), transform .7s cubic-bezier(.22,1,.36,1); }
        .sr-l.in{ opacity: 1; transform: none; }
        .sr-r   { opacity: 0; transform: translateX(32px);  transition: opacity .7s cubic-bezier(.22,1,.36,1), transform .7s cubic-bezier(.22,1,.36,1); }
        .sr-r.in{ opacity: 1; transform: none; }

        /* ── Typography ── */
        .display { font-family: 'Playfair Display', serif; font-weight: 600; line-height: 1.12; color: var(--text-display); }
        .am       { font-family: 'Noto Sans Ethiopic', sans-serif; }

        /* ── Section chrome ── */
        .sec-label {
            display: inline-flex; align-items: center; gap: 10px;
            font-size: .7rem; letter-spacing: .2em; text-transform: uppercase;
            color: var(--gold); margin-bottom: 12px; font-weight: 600;
        }
        .sec-label::before, .sec-label::after {
            content: ''; display: block; width: 24px; height: 1px;
            background: var(--gold); opacity: .4;
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 12px 28px; border-radius: 8px; font-family: 'Inter', sans-serif;
            font-weight: 600; font-size: .875rem; text-decoration: none;
            border: none; cursor: pointer; transition: transform .2s, box-shadow .2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--blue-primary), var(--blue-600));
            color: #fff; box-shadow: 0 4px 20px var(--blue-glow);
        }
        .btn-primary:hover  { transform: translateY(-2px); box-shadow: 0 8px 28px var(--blue-glow); color: #fff; }
        .btn-gold {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--bg-950); box-shadow: 0 4px 20px rgba(243,186,21,.25);
        }
        .btn-gold:hover  { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(243,186,21,.35); color: var(--dark-950); }
        .btn-ghost {
            background: transparent; color: var(--text-main);
            border: 1.5px solid var(--border-subtle);
        }
        .btn-ghost:hover { background: var(--glass); border-color: var(--blue-primary); transform: translateY(-2px); }

        /* ── Glass card ── */
        .card {
            background: var(--bg-900); border: 1px solid var(--border-subtle);
            border-radius: var(--r); backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            transition: background .3s, border-color .3s, transform .3s, box-shadow .3s;
        }
        .card:hover {
            background: var(--glass-hover); border-color: rgba(26,68,247,.25);
            transform: translateY(-5px); box-shadow: 0 12px 40px rgba(26,68,247,.15);
        }

        /* ── Tilet geometric overlay ── */
        .tilet {
            background-image:
                repeating-linear-gradient(45deg,  transparent, transparent 18px, rgba(243,186,21,.015) 18px, rgba(243,186,21,.015) 19px),
                repeating-linear-gradient(-45deg, transparent, transparent 18px, rgba(26,68,247,.02)  18px, rgba(26,68,247,.02)  19px);
        }

        /* ── Progress bar ── */
        .prog-track { height:5px; background:rgba(255,255,255,.08); border-radius:99px; overflow:hidden; }
        .prog-fill  { height:100%; border-radius:99px; background:linear-gradient(90deg, var(--blue-primary), var(--gold)); transition: width 1.4s cubic-bezier(.22,1,.36,1); }

        /* ── Skeleton ── */
        .skel {
            background: linear-gradient(90deg, rgba(255,255,255,.04) 0%, rgba(255,255,255,.09) 50%, rgba(255,255,255,.04) 100%);
            background-size: 200% 100%; animation: shimmer 1.6s infinite; border-radius: 8px;
        }
        @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

        /* ── Ethiopian cross SVG (reusable) ── */
        .cross-deco { pointer-events: none; opacity: .07; }


        /* ── FAQ accordion ── */
        .faq-item { border-bottom: 1px solid var(--border-subtle); }
        .faq-btn {
            width: 100%; background: transparent; border: none; cursor: pointer;
            text-align: left; padding: 18px 0; display: flex; justify-content: space-between;
            align-items: center; color: var(--text-main); font-family: 'Inter', sans-serif;
            font-size: .95rem; font-weight: 500; gap: 16px;
        }
        .faq-icon { width: 22px; height: 22px; border-radius: 50%; border: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background .2s, border-color .2s; }
        .faq-icon svg { transition: transform .3s; }
        .faq-btn.open .faq-icon { background: rgba(26,68,247,.15); border-color: var(--blue-primary); }
        .faq-btn.open .faq-icon svg { transform: rotate(45deg); }
        .faq-body { overflow: hidden; max-height: 0; transition: max-height .4s cubic-bezier(.22,1,.36,1); }
        .faq-body.open { max-height: 200px; }
        .faq-body p { padding: 0 0 18px; font-size: .875rem; color: var(--text-60); line-height: 1.7; }

        /* ── Responsive helpers ── */
        @media(max-width:1024px) {
            .md\:grid-cols-2 { grid-template-columns: 1fr 1fr !important; }
            .md\:grid-cols-3 { grid-template-columns: repeat(2, 1fr) !important; }
        }

        @media(max-width:768px) {
            .hide-mobile { display: none !important; }
            .show-mobile { display: block !important; }
            
            /* Mobile typography adjustments */
            .display { font-size: clamp(1.8rem, 6vw, 2.5rem) !important; }
            .sec-label { font-size: .65rem !important; margin-bottom: 8px !important; }
            
            /* Mobile spacing */
            section { padding: 60px 20px !important; }
            .card { padding: 24px 20px !important; }
            
            /* Mobile grid adjustments */
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr !important; gap: 24px !important; }
            
            /* Mobile button adjustments */
            .btn { padding: 10px 20px !important; font-size: .8rem !important; }
            .btn-mobile-full { width: 100% !important; justify-content: center !important; }
            
            /* Mobile text adjustments */
            h1 { font-size: clamp(2rem, 8vw, 3rem) !important; line-height: 1.2 !important; }
            h2 { font-size: clamp(1.6rem, 6vw, 2.2rem) !important; line-height: 1.3 !important; }
            h3 { font-size: clamp(1.3rem, 5vw, 1.7rem) !important; line-height: 1.4 !important; }
            p { font-size: .95rem !important; line-height: 1.6 !important; }
            
            /* Disable parallax on mobile for performance */
            .hero-parallax { transform: none !important; transition: none !important; }
        }
        
        @media(max-width:480px) {
            section { padding: 48px 16px !important; }
            .card { padding: 20px 16px !important; }
            .btn { padding: 8px 16px !important; font-size: .75rem !important; }
            .display { font-size: clamp(1.6rem, 7vw, 2rem) !important; }
        }

        /* ── Nav dropdown ── */
        .nav-dropdown .dropdown-menu { opacity:0; visibility:hidden; transform:translateY(8px); pointer-events:none; }
        .nav-dropdown:hover .dropdown-menu { opacity:1; visibility:visible; transform:translateY(0); pointer-events:auto; }
        .nav-dropdown:hover > a svg { transform:rotate(180deg); }
        .dropdown-item:hover { background:var(--glass-hover); color:var(--text-display) !important; }
        .mobile-sub-toggle.open svg { transform:rotate(180deg); }
    </style>

    @stack('styles')
</head>
    <script>
        // Critical: Apply theme before paint
        (function() {
            const t = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <x-navigation :currentPage="$currentPage ?? ''" />

    @if(session('success'))
        <div style="position:fixed;top:74px;right:20px;z-index:800;padding:13px 20px;background:rgba(16,185,129,.14);border:1px solid rgba(16,185,129,.35);color:#6ee7b7;border-radius:10px;font-size:.85rem;backdrop-filter:blur(12px);">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="position:fixed;top:74px;right:20px;z-index:800;padding:13px 20px;background:rgba(239,68,68,.14);border:1px solid rgba(239,68,68,.35);color:#fca5a5;border-radius:10px;font-size:.85rem;backdrop-filter:blur(12px);">
            {{ session('error') }}
        </div>
    @endif

    <main>@yield('content')</main>

    <x-footer />

    <!-- PWA Install Banner -->
    <div id="pwa-banner" style="position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:600;max-width:420px;width:calc(100% - 40px);display:none;">
        <div style="background:rgba(10,18,48,.95);border:1px solid rgba(243,186,21,.15);padding:16px 20px;border-radius:14px;box-shadow:0 8px 40px rgba(0,0,0,.5);backdrop-filter:blur(20px);display:flex;align-items:center;gap:12px;">
            <div style="flex:1">
                <div style="font-weight:600;font-size:.9rem;">Install App</div>
                <div style="font-size:.78rem;color:var(--text-60);">Add to home screen for quick access.</div>
            </div>
            <button onclick="window.installPWA()" class="btn btn-primary" style="padding:8px 16px;font-size:.8rem;">Install</button>
            <button onclick="window.dismissPWA();document.getElementById('pwa-banner').style.display='none'" style="background:transparent;border:none;color:var(--text-60);cursor:pointer;font-size:1.4rem;line-height:1;">&times;</button>
        </div>
    </div>

    <!-- PWA Update Toast -->
    <div id="pwa-update-toast" style="position:fixed;bottom:20px;left:50%;transform:translateX(-50%) translateY(20px);z-index:700;max-width:420px;width:calc(100% - 40px);display:none;opacity:0;transition:opacity .4s,transform .4s;">
        <div style="background:rgba(10,18,48,.95);border:1px solid rgba(26,68,247,.25);padding:16px 20px;border-radius:14px;box-shadow:0 8px 40px rgba(0,0,0,.5);backdrop-filter:blur(20px);display:flex;align-items:center;gap:12px;">
            <div style="flex:1">
                <div style="font-weight:600;font-size:.9rem;">Update Available</div>
                <div style="font-size:.78rem;color:var(--text-60);">A new version is ready.</div>
            </div>
            <button onclick="window.applyUpdate()" class="btn btn-primary" style="padding:8px 16px;font-size:.8rem;">Update</button>
            <button onclick="window.dismissUpdate()" style="background:transparent;border:none;color:var(--text-60);cursor:pointer;font-size:1.4rem;line-height:1;">&times;</button>
        </div>
    </div>

    <script>

    /* ─── Scroll Reveal ─── */
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if(e.isIntersecting){
                const delay = +(e.target.dataset.delay||0);
                setTimeout(()=>e.target.classList.add('in'), delay);
                io.unobserve(e.target);
            }
        });
    }, {threshold: 0.1});
    document.querySelectorAll('.sr,.sr-l,.sr-r').forEach((el,i) => {
        if(!el.dataset.delay){
            const siblings=[...el.parentElement.querySelectorAll('.sr,.sr-l,.sr-r')];
            el.dataset.delay = siblings.indexOf(el)*90;
        }
        io.observe(el);
    });

    /* ─── Nav scroll state ─── */
    const nav=document.getElementById('main-nav');
    window.addEventListener('scroll',()=>nav?.classList.toggle('nav-scrolled',scrollY>60),{passive:true});

    /* ─── FAQ accordion ─── */
    document.querySelectorAll('.faq-btn').forEach(btn=>{
        btn.addEventListener('click',()=>{
            const body=btn.nextElementSibling;
            const isOpen=btn.classList.contains('open');
            document.querySelectorAll('.faq-btn').forEach(b=>{ b.classList.remove('open'); b.nextElementSibling.classList.remove('open'); });
            if(!isOpen){ btn.classList.add('open'); body.classList.add('open'); }
        });
    });

    /* ─── Counter animation ─── */
    window.runCounters = function(){
        document.querySelectorAll('[data-count]').forEach(el=>{
            if(el.dataset.done) return;
            el.dataset.done='1';
            const target=+el.dataset.count, dur=1800, step=target/(dur/16);
            let cur=0;
            const t=setInterval(()=>{ cur=Math.min(cur+step,target); el.textContent=Math.round(cur).toLocaleString(); if(cur>=target)clearInterval(t); },16);
        });
    };
    const statsIo=new IntersectionObserver(e=>{
        if(e[0].isIntersecting){ window.runCounters(); statsIo.disconnect(); }
    },{threshold:.3});
    const statsEl=document.getElementById('stats-section');
    if(statsEl) statsIo.observe(statsEl);

    /* ─── Parallax hero ─── */
    const heroBg=document.querySelector('.hero-parallax');
    if(heroBg){
        window.addEventListener('scroll',()=>{
            heroBg.style.transform=`translateY(${scrollY*.35}px)`;
        },{passive:true});
    }

    /* ─── PWA ─── */
    if('serviceWorker' in navigator){
        let currentVersion = null;
        
        // Check current build version
        fetch('/build-info.json')
            .then(response => response.json())
            .then(buildInfo => {
                currentVersion = buildInfo.hash;
                console.log('Current build version:', currentVersion);
            })
            .catch(() => console.log('Could not fetch build info'));
        
        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
                console.log('Service Worker registered');
                
                // Check for updates every 30 seconds
                setInterval(() => {
                    registration.update();
                }, 30000);
                
                // Listen for updates — show toast instead of confirm
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    if (newWorker) {
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                window.pendingWorker = newWorker;
                                const toast = document.getElementById('pwa-update-toast');
                                if (toast) {
                                    toast.style.display = 'block';
                                    requestAnimationFrame(() => {
                                        toast.style.opacity = '1';
                                        toast.style.transform = 'translateX(-50%) translateY(0)';
                                    });
                                }
                            }
                        });
                    }
                });

                window.applyUpdate = function() {
                    if (window.pendingWorker) {
                        window.pendingWorker.postMessage({ type: 'SKIP_WAITING' });
                        window.location.reload();
                    }
                };

                window.dismissUpdate = function() {
                    const toast = document.getElementById('pwa-update-toast');
                    if (toast) {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(-50%) translateY(20px)';
                        setTimeout(() => { toast.style.display = 'none'; }, 400);
                    }
                };
            })
            .catch(error => console.error('Service Worker registration failed:', error));
        
        // Handle PWA install prompt
        let dp;
        window.addEventListener('beforeinstallprompt',e=>{
            e.preventDefault();
            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) return;
            dp=e;
            const d=localStorage.getItem('pwaDismiss');
            if(!d||new Date(d)<=new Date()) document.getElementById('pwa-banner').style.display='block';
        });
        
        window.installPWA=()=>{ 
            if(dp){ 
                dp.prompt(); 
                dp.userChoice.then(()=>{ 
                    dp=null; 
                    document.getElementById('pwa-banner').style.display='none'; 
                }); 
            } 
        };
        
        window.dismissPWA=()=>{ 
            const d=new Date(); 
            d.setDate(d.getDate()+7); 
            localStorage.setItem('pwaDismiss',d.toISOString()); 
        };
        
        // Listen for service worker messages
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data && event.data.type === 'RELOAD') {
                window.location.reload();
            }
        });
    }
    </script>

    @stack('scripts')
</body>
</html>
