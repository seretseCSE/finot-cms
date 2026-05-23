{{-- Admin Panel PWA Service Worker Registration, Install Prompt & Product Tour --}}
@vite(['resources/js/admin.js'])

<div id="pwa-install-banner-admin" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 max-w-md w-full px-4 hidden">
    <div class="bg-primary-600 text-white p-4 rounded-lg shadow-lg flex items-center justify-between">
        <div>
            <div class="font-semibold">Install FINOT Admin App</div>
            <div class="text-sm text-white/90">Add to your home screen for quick access.</div>
        </div>
        <div class="flex items-center gap-2 ml-4">
            <button onclick="window.installPWAAdmin(); document.getElementById('pwa-install-banner-admin').classList.add('hidden');" class="bg-white text-primary-700 px-3 py-1.5 rounded text-sm font-medium hover:bg-primary-50 transition-colors">
                Install
            </button>
            <button onclick="window.dismissPwaPromptAdmin(); document.getElementById('pwa-install-banner-admin').classList.add('hidden');" class="text-white/90 hover:text-white text-lg leading-none px-2" aria-label="Dismiss">
                &times;
            </button>
        </div>
    </div>
</div>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js')
                .then((registration) => {
                    console.log('Admin SW registered:', registration.scope);
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        if (newWorker) {
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    newWorker.postMessage({ type: 'SKIP_WAITING' });
                                }
                            });
                        }
                    });
                })
                .catch((error) => {
                    console.log('Admin SW registration failed:', error);
                });
        });

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            window.location.reload();
        });

        // Listen for messages from service worker
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'SYNC_COMPLETE') {
                window.dispatchEvent(new CustomEvent('offline-sync-complete', {
                    detail: event.data,
                }));
            }
        });

        // PWA Install Prompt handling
        let deferredPromptAdmin;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) return;
            deferredPromptAdmin = e;
            window.dispatchEvent(new CustomEvent('pwa:install-available-admin'));
        });

        window.installPWAAdmin = () => {
            if (deferredPromptAdmin) {
                deferredPromptAdmin.prompt();
                deferredPromptAdmin.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted PWA install');
                    }
                    deferredPromptAdmin = null;
                });
            }
        };

        window.dismissPwaPromptAdmin = () => {
            const dismissUntil = new Date();
            dismissUntil.setDate(dismissUntil.getDate() + 7);
            localStorage.setItem('pwaPromptDismissedUntilAdmin', dismissUntil.toISOString());
        };

        // Show install prompt after first visit to admin
        let adminVisitCount = parseInt(localStorage.getItem('pwaAdminVisitCount') || '0');
        adminVisitCount++;
        localStorage.setItem('pwaAdminVisitCount', adminVisitCount.toString());

        window.addEventListener('pwa:install-available-admin', () => {
            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) return;
            const dismissedUntil = localStorage.getItem('pwaPromptDismissedUntilAdmin');
            if (dismissedUntil && new Date(dismissedUntil) > new Date()) {
                return;
            }
            const banner = document.getElementById('pwa-install-banner-admin');
            if (banner) banner.classList.remove('hidden');
        });
    }
</script>
