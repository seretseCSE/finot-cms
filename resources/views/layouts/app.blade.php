<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'ፍኖተ ጽድቅ'))</title>
    <meta name="description" content="@yield('meta_description', __('Finote Tsidik Sunday School — Faith, service, and fellowship since 1984 E.C.'))">

    <!-- Theme color for PWA / status bar -->
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ፍኖተ ጽድቅ">

    <!-- PWA manifest -->
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/logo.png') }}">

    <!-- Fonts: Inter + Noto Sans Ethiopic for Amharic -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

    <style>
        .font-amharic { font-family: 'Noto Sans Ethiopic', sans-serif; }

        /* Prevent FOUC */
        html { background-color: #050505; }
    </style>

    <script>
        (function() {
            document.documentElement.classList.add('dark');
            document.documentElement.style.colorScheme = 'dark';
        })();
    </script>
</head>
<body class="bg-base text-white min-h-screen flex flex-col">

    <x-navigation :currentPage="$currentPage ?? ''" />

    <!-- ═══ Flash Messages ═══ -->
    @if(session('success'))
        <div class="fixed top-20 right-6 z-50 max-w-sm animate-reveal">
            <div class="card-glass rounded-lg px-5 py-3 flex items-center gap-3 border border-accent/20">
                <svg class="w-4 h-4 text-accent shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <p class="text-sm text-white">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="fixed top-20 right-6 z-50 max-w-sm animate-reveal">
            <div class="bg-accent-dim border border-accent/30 rounded-lg px-5 py-3 flex items-center gap-3">
                <svg class="w-4 h-4 text-accent shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                <p class="text-sm text-white">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- ═══ Main Content ═══ -->
    <main class="flex-1 pt-16">
        @yield('content')
    </main>

    <!-- ═══ Footer ═══ -->
    <footer class="border-t border-white/5 bg-surface">
        <div class="max-w-7xl mx-auto px-6 py-16">
            <!-- Logo + Brand -->
            <div class="flex flex-col md:flex-row items-start gap-8 mb-14 pb-14 border-b border-white/5">
                <div class="flex items-center gap-4">
                    <img src="{{ asset('images/logo.png') }}" alt="ፍኖተ ጽድቅ" class="w-12 h-12 object-contain">
                    <div>
                        <div class="text-lg font-bold tracking-widest text-white uppercase leading-tight font-amharic">ፍኖተ ጽድቅ</div>
                        <div class="text-xs tracking-[0.15em] text-gray-400 uppercase">Finote Tsidik Sunday School</div>
                    </div>
                </div>
                <p class="text-sm text-gray-400 leading-relaxed max-w-lg">
                    {{ __('Faith, service, and fellowship — building a stronger community through the light of the Gospel since 1984 E.C.') }}
                </p>
            </div>

            <!-- Links -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-10 mb-14">
                <div>
                    <h4 class="label-upper text-gray-200 mb-5">Navigate</h4>
                    <ul class="space-y-3">
                        <li><a href="{{ route('tours.index') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Tours</a></li>
                        <li><a href="{{ route('events', ['month' => now()->month, 'year' => now()->year]) }}" class="text-sm text-gray-400 hover:text-white transition-colors">Events</a></li>
                        <li><a href="{{ route('news') }}" class="text-sm text-gray-400 hover:text-white transition-colors">News</a></li>
                        <li><a href="{{ route('media') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Media</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="label-upper text-gray-200 mb-5">Resources</h4>
                    <ul class="space-y-3">
                        <li><a href="{{ route('library') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Library</a></li>
                        <li><a href="{{ route('courses.index') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Courses</a></li>
                        <li><a href="{{ route('songs.index') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Songs</a></li>
                        <li><a href="{{ route('blog.index') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Blog</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="label-upper text-gray-200 mb-5">Connect</h4>
                    <ul class="space-y-3">
                        <li><a href="{{ route('contact') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Contact</a></li>
                        <li><a href="{{ route('about') }}" class="text-sm text-gray-400 hover:text-white transition-colors">About</a></li>
                        <li><a href="{{ route('fundraising.index') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Give</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="label-upper text-gray-200 mb-5">Location</h4>
                    <div class="space-y-3 text-sm text-gray-400">
                        <p>Addis Ababa, Ayertena</p>
                        <p class="font-amharic text-gray-500">አዲስ አበባ፣ አየርጤና</p>
                        <p class="pt-3">Sunday: 2:00 – 5:00 PM</p>
                        <p>Saturday: 3:00 – 5:30 PM</p>
                    </div>
                </div>
            </div>

            <!-- Bottom bar -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-4 pt-8 border-t border-white/5">
                <p class="text-xs text-gray-500">
                    &copy; {{ date('Y') }} ፍኖተ ጽድቅ ሰንበት ትምህርት ቤት &mdash; All Rights Reserved
                </p>
                <p class="text-xs text-gray-600">
                    Designed by <a href="#" class="text-gray-400 hover:text-white transition-colors">AudioVisual</a>
                </p>
            </div>
        </div>
    </footer>

    <!-- ═══ PWA Install Banner ═══ -->
    <div id="pwa-banner" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-50 max-w-md w-[calc(100%-3rem)] animate-reveal">
        <div class="card-glass rounded-xl px-5 py-4 flex items-center gap-4 border border-accent/15">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white">Install ፍኖተ ጽድቅ</p>
                <p class="text-xs text-gray-400 mt-0.5">Add to home screen for quick access.</p>
            </div>
            <button onclick="window.installPWA()" class="shrink-0 px-4 py-2 text-xs font-bold tracking-widest uppercase bg-accent text-white rounded hover:bg-accent-hover transition-all glow-blue-sm">Install</button>
            <button onclick="window.dismissPWA(); this.closest('#pwa-banner').classList.add('hidden')" class="shrink-0 text-gray-500 hover:text-white transition-colors text-lg leading-none">&times;</button>
        </div>
    </div>

    <!-- ═══ PWA Scripts ═══ -->
    <script>
        if ('serviceWorker' in navigator) {
            let deferredPrompt = null;

            fetch('/build-info.json')
                .then(r => r.json())
                .then(info => { window.__buildVersion = info.hash; })
                .catch(() => {});

            navigator.serviceWorker.register('/service-worker.js')
                .then(registration => {
                    console.log('SW registered:', registration.scope);

                    setInterval(() => registration.update(), 30000);

                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        if (newWorker) {
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    window.__pendingWorker = newWorker;
                                }
                            });
                        }
                    });

                    window.applyUpdate = function() {
                        if (window.__pendingWorker) {
                            window.__pendingWorker.postMessage({ type: 'SKIP_WAITING' });
                            window.location.reload();
                        }
                    };
                });

            window.addEventListener('beforeinstallprompt', e => {
                e.preventDefault();
                if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) return;
                deferredPrompt = e;
                const banner = document.getElementById('pwa-banner');
                const dismissed = localStorage.getItem('pwaDismiss');
                if (banner && (!dismissed || new Date(dismissed) <= new Date())) {
                    banner.classList.remove('hidden');
                }
            });

            window.installPWA = function() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(() => {
                        deferredPrompt = null;
                        document.getElementById('pwa-banner')?.classList.add('hidden');
                    });
                }
            };

            window.dismissPWA = function() {
                const d = new Date();
                d.setDate(d.getDate() + 7);
                localStorage.setItem('pwaDismiss', d.toISOString());
            };

            navigator.serviceWorker.addEventListener('message', event => {
                if (event.data?.type === 'RELOAD') window.location.reload();
            });
        }
    </script>

    @stack('scripts')
</body>
</html>
