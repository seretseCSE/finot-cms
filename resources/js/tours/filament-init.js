import { initProductTour, reinitializeProductTour, startTour } from './index.js';
import '../../css/tours/tours.css';
import '../../css/tours/dark-mode.css';
import '../../css/tours/mobile.css';

let tourManager = null;
let reinitTimer = null;

function initTourSystem() {
    const root = document.getElementById('product-tour-root');
    if (!root) return;

    const role = root.dataset.userRole;
    const panel = root.dataset.panel;

    if (!role) return;

    document.body.dataset.userRole = role;

    initProductTour({
        autoStartDelay: 800,
        animate: true,
        keyboardControl: true,
        analytics: true,
    }).then(manager => {
        tourManager = manager;

        window.__tourManager = manager;

        window.dispatchEvent(new CustomEvent('productTourReady', {
            detail: { manager, role, panel },
            bubbles: true,
        }));
    });
}

function reinitTourSystem() {
    clearTimeout(reinitTimer);
    reinitTimer = setTimeout(() => {
        reinitializeProductTour().then(manager => {
            tourManager = manager;
            window.__tourManager = manager;

            window.dispatchEvent(new CustomEvent('productTourReinitialized', {
                detail: { manager },
                bubbles: true,
            }));
        });
    }, 300);
}

function setupLivewireHooks() {
    document.addEventListener('livewire:navigated', () => reinitTourSystem());
    document.addEventListener('livewire:initialized', () => reinitTourSystem());
    document.addEventListener('livewire:load', () => reinitTourSystem());
    window.addEventListener('popstate', () => reinitTourSystem());
}

function setupMutationObserver() {
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === 1 && (
                        node.matches?.('[data-tour]') ||
                        node.querySelector?.('[data-tour]')
                    )) {
                        reinitTourSystem();
                        return;
                    }
                }
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });

    return observer;
}

function setupEventListeners() {
    document.addEventListener('click', (e) => {
        const restartBtn = e.target.closest('[data-restart-tour]');
        if (restartBtn) {
            const tourKey = restartBtn.dataset.restartTour || 'onboarding';
            e.preventDefault();
            startTour(tourKey);
        }

        const menuItem = e.target.closest('.fi-user-menu-item');
        if (menuItem && menuItem.textContent.includes('Restart Tour')) {
            e.preventDefault();
            startTour('onboarding');
        }
    });

    window.addEventListener('product-tour:replay', () => {
        startTour('onboarding');
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initTourSystem();
        setupLivewireHooks();
        setupMutationObserver();
        setupEventListeners();
    });
} else {
    setTimeout(() => {
        initTourSystem();
        setupLivewireHooks();
        setupMutationObserver();
        setupEventListeners();
    }, 100);
}

window.addEventListener('productTourReinitialized', (e) => {
    const manager = e.detail?.manager;
    if (manager && manager.isActive && !manager.isActive()) {
        manager.checkAndAutoStart();
    }
});
