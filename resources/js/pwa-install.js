class PWAInstallManager {
    constructor() {
        this.visitCount = parseInt(localStorage.getItem('visitCount') || '0');
        this.deferredInstallPrompt = null;
        this.init();
    }

    init() {
        this.incrementVisitCount();
        this.captureInstallPrompt();
        this.checkPwaPrompt();
    }

    captureInstallPrompt() {
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
        if (isStandalone) return;
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            this.deferredInstallPrompt = event;
            window.dispatchEvent(new CustomEvent('pwa:install-available'));
        });
    }

    incrementVisitCount() {
        this.visitCount++;
        localStorage.setItem('visitCount', this.visitCount.toString());
    }

    checkPwaPrompt() {
        if (this.visitCount < 1) return;
        if (this.getCookie('pwa_install_dismissed_until')) return;
        window.dispatchEvent(new CustomEvent('pwa:show-install-prompt'));
    }

    setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
        document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
    }

    getCookie(name) {
        const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + escaped + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    async installPWA() {
        if (!this.deferredInstallPrompt) {
            this.showManualInstallInstructions();
            return;
        }
        try {
            this.deferredInstallPrompt.prompt();
            await this.deferredInstallPrompt.userChoice;
        } catch (error) {
            console.error('PWA install prompt failed:', error);
        } finally {
            this.deferredInstallPrompt = null;
            window.dispatchEvent(new CustomEvent('pwa:hide-install-prompt'));
        }
    }

    showManualInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        const isAndroid = /Android/.test(navigator.userAgent);
        let message = '';
        if (isIOS) {
            message = 'To install on iOS:\n1. Tap the Share button in Safari.\n2. Scroll down and tap "Add to Home Screen".\n3. Tap "Add".';
        } else if (isAndroid) {
            message = 'To install on Android:\n1. Tap the Menu (three dots) in Chrome.\n2. Tap "Add to Home Screen" or "Install App".\n3. Tap "Install".';
        } else {
            message = 'To install on Desktop:\n1. Click the Install icon in the address bar.\n2. Or open Chrome menu \u2192 "Cast, save, and share" \u2192 "Install page as app".';
        }
        alert(message);
    }

    dismissPwaPromptFor7Days() {
        this.setCookie('pwa_install_dismissed_until', '1', 7);
        window.dispatchEvent(new CustomEvent('pwa:hide-install-prompt'));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.pwaInstallManager = new PWAInstallManager();
});

window.PWAInstallManager = PWAInstallManager;
