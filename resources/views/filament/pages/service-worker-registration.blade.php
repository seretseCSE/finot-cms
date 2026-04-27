<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js')
                .then((registration) => {
                    console.log('Admin SW registered:', registration.scope);
                })
                .catch((error) => {
                    console.log('Admin SW registration failed:', error);
                });
        });

        // PWA Install Prompt handling
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            window.dispatchEvent(new CustomEvent('pwa:install-available'));
        });

        window.installPWA = () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted PWA install');
                    }
                    deferredPrompt = null;
                });
            }
        };

        window.dismissPwaPromptFor7Days = () => {
            const dismissUntil = new Date();
            dismissUntil.setDate(dismissUntil.getDate() + 7);
            localStorage.setItem('pwaPromptDismissedUntil', dismissUntil.toISOString());
        };
    }
</script>
