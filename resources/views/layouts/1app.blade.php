<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'FINOT CMS'))</title>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1B4F72">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FINOTE">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Tailwind CDN for public pages -->
    <script src="https://cdn.tailwindcss.com"></script>

    @stack('styles')
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <!-- Logo / Brand -->
                <a href="/" class="flex items-center space-x-2">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <span class="text-lg font-semibold text-gray-900">{{ config('app.name', 'FINOT') }}</span>
                </a>

                <!-- Navigation Links -->
                <div class="flex items-center space-x-6">
                    <a href="{{ route('tours.index') }}" class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
                        Tours
                    </a>

                    @auth
                        <a href="{{ url('/admin') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">
                            Dashboard
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
                                Log in
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="container mx-auto px-4 mt-4">
            <div class="p-4 bg-green-50 border border-green-200 rounded-md">
                <p class="text-green-800">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="container mx-auto px-4 mt-4">
            <div class="p-4 bg-red-50 border border-red-200 rounded-md">
                <p class="text-red-800">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Main Content -->
    <main class="flex-1">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="container mx-auto px-4 py-6">
            <p class="text-center text-sm text-gray-500">
                &copy; {{ date('Y') }} {{ config('app.name', 'FINOT CMS') }}. All rights reserved.
            </p>
        </div>
    </footer>

    <!-- PWA Install Prompt -->
    <div id="pwa-install-banner" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 max-w-md w-full px-4 hidden">
        <div class="bg-blue-600 text-white p-4 rounded-lg shadow-lg flex items-center justify-between">
            <div>
                <div class="font-semibold">Install FINOT App</div>
                <div class="text-sm text-white/90">Add to your home screen for quick access.</div>
            </div>
            <div class="flex items-center gap-2 ml-4">
                <button onclick="window.installPWA();" class="bg-white text-blue-700 px-3 py-1.5 rounded text-sm font-medium hover:bg-blue-50 transition-colors">
                    Install
                </button>
                <button onclick="window.dismissPwaPromptFor7Days(); document.getElementById('pwa-install-banner').classList.add('hidden');" class="text-white/90 hover:text-white text-lg leading-none px-2" aria-label="Dismiss">
                    &times;
                </button>
            </div>
        </div>
    </div>

    <!-- PWA Manual Install Modal -->
    <div id="pwa-manual-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden" onclick="if(event.target===this) window.closePwaManualModal();">
        <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Install FINOT App</h3>
            <div id="pwa-manual-ios" class="hidden text-sm text-gray-600 mb-4">
                <p class="mb-2">To install on iOS:</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Tap the <strong>Share</strong> button in Safari.</li>
                    <li>Scroll down and tap <strong>Add to Home Screen</strong>.</li>
                    <li>Tap <strong>Add</strong> in the top right.</li>
                </ol>
            </div>
            <div id="pwa-manual-android" class="hidden text-sm text-gray-600 mb-4">
                <p class="mb-2">To install on Android:</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Tap the <strong>Menu</strong> (three dots) in Chrome.</li>
                    <li>Tap <strong>Add to Home Screen</strong> or <strong>Install App</strong>.</li>
                    <li>Tap <strong>Install</strong>.</li>
                </ol>
            </div>
            <div id="pwa-manual-desktop" class="hidden text-sm text-gray-600 mb-4">
                <p class="mb-2">To install on Desktop:</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Click the <strong>Install</strong> icon in the address bar.</li>
                    <li>Or open Chrome menu &rarr; <strong>Cast, save, and share</strong> &rarr; <strong>Install page as app</strong>.</li>
                </ol>
            </div>
            <button onclick="window.closePwaManualModal();" class="w-full bg-blue-600 text-white py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors">Got it</button>
        </div>
    </div>

    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js')
                    .then((registration) => {
                        console.log('SW registered:', registration.scope);
                    })
                    .catch((error) => {
                        console.log('SW registration failed:', error);
                    });
            });

            // PWA Install Prompt handling
            let deferredPrompt;
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                const banner = document.getElementById('pwa-install-banner');
                const dismissedUntil = localStorage.getItem('pwaPromptDismissedUntil');
                if (banner && (!dismissedUntil || new Date(dismissedUntil) <= new Date())) {
                    banner.classList.remove('hidden');
                }
            });

            window.installPWA = () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted PWA install');
                        }
                        deferredPrompt = null;
                        document.getElementById('pwa-install-banner').classList.add('hidden');
                    });
                } else {
                    // Manual fallback for iOS Safari / browsers without beforeinstallprompt
                    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                    const isAndroid = /Android/.test(navigator.userAgent);
                    document.getElementById('pwa-manual-ios').classList.toggle('hidden', !isIOS);
                    document.getElementById('pwa-manual-android').classList.toggle('hidden', !isAndroid);
                    document.getElementById('pwa-manual-desktop').classList.toggle('hidden', isIOS || isAndroid);
                    document.getElementById('pwa-manual-modal').classList.remove('hidden');
                    document.getElementById('pwa-install-banner').classList.add('hidden');
                }
            };

            window.closePwaManualModal = () => {
                document.getElementById('pwa-manual-modal').classList.add('hidden');
            };

            window.dismissPwaPromptFor7Days = () => {
                const dismissUntil = new Date();
                dismissUntil.setDate(dismissUntil.getDate() + 7);
                localStorage.setItem('pwaPromptDismissedUntil', dismissUntil.toISOString());
            };
        }
    </script>

    @stack('scripts')
</body>
</html>
