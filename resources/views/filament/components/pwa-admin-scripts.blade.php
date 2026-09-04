{{-- Admin Panel PWA service worker (no install banner) --}}
@vite(['resources/js/admin.js'])

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js')
                .then((registration) => {
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
                    subscribePush(registration);
                })
                .catch(() => {});
        });

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            window.location.reload();
        });

        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'SYNC_COMPLETE') {
                window.dispatchEvent(new CustomEvent('offline-sync-complete', {
                    detail: event.data,
                }));
            }
        });
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
    }

    async function subscribePush(registration) {
        if (!('PushManager' in window) || !window.isSecureContext) return;
        try {
            const keyRes = await fetch('/push/vapid-public-key', { headers: { 'Accept': 'application/json' } });
            if (!keyRes.ok) return;
            const { publicKey } = await keyRes.json();
            if (!publicKey) return;

            let permission = Notification.permission;
            if (permission === 'default') {
                permission = await Notification.requestPermission();
            }
            if (permission !== 'granted') return;

            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey),
                });
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch('/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                },
                body: JSON.stringify(subscription.toJSON()),
            });
        } catch (e) {
            // Push optional — ignore failures
        }
    }
</script>
