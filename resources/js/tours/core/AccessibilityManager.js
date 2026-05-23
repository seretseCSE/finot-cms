export default class AccessibilityManager {
    constructor() {
        this.previousFocus = null;
        this.focusTrapActive = false;
        this.focusableElements = [];
        this.config = {
            keyboardNavigation: true,
            focusTrap: true,
            screenReaderSupport: true,
            reducedMotion: false,
        };
    }

    init(config = {}) {
        Object.assign(this.config, config);

        if (this.config.reducedMotion) {
            this.config.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        if (this.config.keyboardNavigation) {
            this._bindKeyboardEvents();
        }
    }

    trapFocus(container) {
        if (!this.config.focusTrap) return;

        this.previousFocus = document.activeElement;
        this.focusTrapActive = true;
        this.focusableElements = this._getFocusableElements(container);

        if (this.focusableElements.length > 0) {
            this.focusableElements[0].focus();
        }

        container.addEventListener('keydown', this._handleFocusTrap);
    }

    releaseFocus() {
        if (!this.focusTrapActive) return;

        this.focusTrapActive = false;
        document.removeEventListener('keydown', this._handleFocusTrap);

        if (this.previousFocus && this.previousFocus.focus) {
            this.previousFocus.focus();
        }

        this.previousFocus = null;
    }

    announce(message, priority = 'polite') {
        if (!this.config.screenReaderSupport) return;

        let announcer = document.getElementById('tour-sr-announcer');
        if (!announcer) {
            announcer = document.createElement('div');
            announcer.id = 'tour-sr-announcer';
            announcer.setAttribute('aria-live', priority);
            announcer.setAttribute('aria-atomic', 'true');
            announcer.className = 'sr-only';
            announcer.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
            document.body.appendChild(announcer);
        }

        announcer.textContent = '';
        requestAnimationFrame(() => {
            announcer.textContent = message;
        });
    }

    announceStep(stepTitle, stepNumber, totalSteps) {
        this.announce(
            `Step ${stepNumber} of ${totalSteps}: ${stepTitle}. Press Escape to skip the tour or use Tab to navigate.`,
            'assertive'
        );
    }

    setReducedMotion(reduce) {
        this.config.reducedMotion = reduce;
    }

    isReducedMotion() {
        return this.config.reducedMotion;
    }

    destroy() {
        this.releaseFocus();
        const announcer = document.getElementById('tour-sr-announcer');
        if (announcer) announcer.remove();
    }

    _bindKeyboardEvents() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.focusTrapActive) {
                window.dispatchEvent(new CustomEvent('tourEscape'));
            }
        });
    }

    _handleFocusTrap(e) {
        if (e.key !== 'Tab') return;

        const focusable = this.focusableElements;
        if (focusable.length === 0) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    _getFocusableElements(container) {
        return Array.from(
            container.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            )
        ).filter(el => !el.disabled && el.offsetParent !== null);
    }
}
