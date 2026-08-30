<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') | {{ config('app.name') }}@else{{ config('app.name') }}@endif</title>
    <meta name="description" content="@yield('meta_description', __('Finote Tsidik Sunday School — Faith, service, and fellowship since 1984 E.C.'))">

    @hasSection('seo_title')
        <x-public.seo-meta
            :title="$__env->yieldContent('seo_title')"
            :description="$__env->yieldContent('seo_description', __('Finote Tsidik Sunday School — Faith, service, and fellowship since 1984 E.C.'))"
            :image="$__env->yieldContent('seo_image', asset('images/hero-bg.jpg'))"
            :type="$__env->yieldContent('seo_type', 'website')"
        />
    @else
        <x-public.seo-meta />
    @endif

    @stack('structured-data')

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1A44F7" id="theme-color-meta">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo2.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/logo2.png') }}">

    <link rel="preload" as="image" href="{{ asset('images/logo2.png') }}" fetchpriority="high">

    <link rel="stylesheet" href="/fonts/public/fonts.css">

    <script>
    (function() {
        var theme = localStorage.getItem('theme');
        if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            var meta = document.getElementById('theme-color-meta');
            if (meta) meta.content = '#0A0A0F';
        }
    })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="min-h-screen flex flex-col ft-page transition-colors duration-300">

    {{-- Site-wide Organization structured data --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "Finote Tsidik Sunday School",
        "alternateName": "ፍኖተ ጽድቅ ሰንበት ት/ቤት",
        "url": "{{ config('app.url') }}",
        "logo": "{{ asset('images/logo2.png') }}",
        "description": "{{ __('Finote Tsidik Sunday School — Faith, service, and fellowship since 1984 E.C.') }}",
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "Ayertena",
            "addressRegion": "Addis Ababa",
            "addressCountry": "ET"
        },
        "sameAs": []
    }
    </script>

    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[300] focus:px-4 focus:py-2 focus:bg-white focus:text-slate-900 focus:rounded-lg focus:shadow-lg">{{ __('Skip to content') }}</a>

    <div id="scroll-progress" class="fixed top-0 left-0 right-0 h-0.5 z-[200] pointer-events-none" style="transform-origin:left;transform:scaleX(0);background:linear-gradient(90deg,#1A44F7,#F3BA15,#1A44F7);background-size:200% 100%;" aria-hidden="true"></div>

    <x-navigation :currentPage="$currentPage ?? ''" />

    @if(session('success'))
        <div class="fixed top-20 right-5 z-[800] px-5 py-3 bg-green-500/10 backdrop-blur-xl border border-green-500/20 text-green-300 rounded-xl text-sm shadow-lg" role="alert">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="fixed top-20 right-5 z-[800] px-5 py-3 bg-red-500/10 backdrop-blur-xl border border-red-500/20 text-red-300 rounded-xl text-sm shadow-lg" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <main id="main-content" class="flex-1 relative z-10" tabindex="-1">@yield('content')</main>

    <x-footer />

    <div id="offline-storage-meter" class="fixed bottom-20 right-5 z-[500] max-w-[280px] text-xs text-slate-400 bg-black/40 backdrop-blur px-3 py-2 rounded-lg hidden sm:block"></div>
    <button type="button" data-offline-clear class="fixed bottom-20 right-5 translate-y-8 z-[500] text-[10px] uppercase tracking-wide text-slate-500 hover:text-white bg-transparent border-none cursor-pointer">Clear offline</button>

    <div id="pwa-banner" class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[700] max-w-[420px] w-[calc(100%-40px)] hidden" role="status">
        <div class="glass-card p-4 flex items-center gap-3">
            <div class="flex-1">
                <div class="font-semibold text-sm text-white">{{ __('Install App') }}</div>
                <div class="text-xs text-slate-400">{{ __('Add to home screen for quick access.') }}</div>
            </div>
            <button onclick="window.installPWA()" class="btn-primary text-xs px-4 py-2">{{ __('Install') }}</button>
            <button onclick="window.dismissPWA();document.getElementById('pwa-banner').style.display='none'" aria-label="{{ __('Dismiss') }}" class="text-slate-400 hover:text-white bg-transparent border-none cursor-pointer text-xl leading-none">&times;</button>
        </div>
    </div>

    <div id="pwa-update-toast" class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[700] max-w-[420px] w-[calc(100%-40px)] hidden opacity-0 transition-all duration-400" role="status">
        <div class="glass-card p-4 flex items-center gap-3" style="border-color:rgba(26,68,247,0.3)">
            <div class="flex-1">
                <div class="font-semibold text-sm text-white">{{ __('Update Available') }}</div>
                <div class="text-xs text-slate-400">{{ __('A new version is ready.') }}</div>
            </div>
            <button onclick="window.applyUpdate()" class="btn-primary text-xs px-4 py-2">{{ __('Update') }}</button>
            <button onclick="window.dismissUpdate()" aria-label="Dismiss" class="text-slate-400 hover:text-white bg-transparent border-none cursor-pointer text-xl leading-none">&times;</button>
        </div>
    </div>

    <x-mobile-contact-bar />
    <x-tour-icons-sprite />

    <script>
    (function () {
        'use strict';

        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    var el = entry.target;
                    var delay = parseFloat(el.dataset.delay || 0) * 1000;
                    setTimeout(function () { el.classList.add('visible'); }, delay);
                    observer.unobserve(el);
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
            document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale').forEach(function (el) {
                observer.observe(el);
            });
        }

        document.querySelectorAll('.faq-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var isOpen = btn.getAttribute('aria-expanded') === 'true';
                document.querySelectorAll('.faq-btn').forEach(function (b) {
                    b.setAttribute('aria-expanded', 'false');
                    b.classList.remove('open');
                    var body = b.nextElementSibling;
                    if (body && body.classList.contains('faq-body')) {
                        body.classList.remove('open');
                        body.style.maxHeight = null;
                    }
                });
                if (!isOpen) {
                    btn.setAttribute('aria-expanded', 'true');
                    btn.classList.add('open');
                    var body = btn.nextElementSibling;
                    if (body && body.classList.contains('faq-body')) {
                        body.classList.add('open');
                        body.style.maxHeight = body.scrollHeight + 'px';
                    }
                }
            });
        });

        if ('serviceWorker' in navigator) {
            var deferredPrompt = null;
            navigator.serviceWorker.register('/service-worker.js').then(function (registration) {
                setInterval(function () { registration.update(); }, 30000);
                registration.addEventListener('updatefound', function () {
                    var newWorker = registration.installing;
                    if (!newWorker) return;
                    newWorker.addEventListener('statechange', function () {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            window._pendingWorker = newWorker;
                            var toast = document.getElementById('pwa-update-toast');
                            if (toast) {
                                toast.style.display = 'block';
                                requestAnimationFrame(function () {
                                    toast.style.opacity = '1';
                                    toast.style.transform = 'translateX(-50%) translateY(0)';
                                });
                            }
                        }
                    });
                });
            }).catch(function () {});
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) return;
                deferredPrompt = e;
                var dismissed = localStorage.getItem('pwaDismiss');
                if (!dismissed || new Date(dismissed) <= new Date()) {
                    document.getElementById('pwa-banner').style.display = 'block';
                }
            });
            window.installPWA = function () {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function () {
                        deferredPrompt = null;
                        document.getElementById('pwa-banner').style.display = 'none';
                    });
                }
            };
            window.dismissPWA = function () {
                var d = new Date(); d.setDate(d.getDate() + 7);
                localStorage.setItem('pwaDismiss', d.toISOString());
            };
            window.applyUpdate = function () {
                if (window._pendingWorker) {
                    window._pendingWorker.postMessage({ type: 'SKIP_WAITING' });
                    window.location.reload();
                }
            };
            window.dismissUpdate = function () {
                var toast = document.getElementById('pwa-update-toast');
                if (toast) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(-50%) translateY(20px)';
                    setTimeout(function () { toast.style.display = 'none'; }, 400);
                }
            };
            navigator.serviceWorker.addEventListener('message', function (event) {
                if (event.data && event.data.type === 'RELOAD') window.location.reload();
            });
        }
    })();
    </script>

    @stack('scripts')
</body>
</html>
