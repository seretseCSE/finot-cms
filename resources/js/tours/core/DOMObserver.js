export default class DOMObserver {
    constructor() {
        this.observer = null;
        this.pendingSelectors = new Map();
        this.retryTimers = new Map();
        this.config = {
            selectorTimeout: 5000,
            retryInterval: 500,
            maxRetries: 3,
        };
    }

    init(config = {}) {
        Object.assign(this.config, config);
        this._startObserver();
    }

    waitForElement(selector, timeout = null) {
        return new Promise((resolve, reject) => {
            const element = document.querySelector(selector);
            if (element) {
                resolve(element);
                return;
            }

            const maxTime = timeout || this.config.selectorTimeout;
            const startTime = Date.now();

            this.pendingSelectors.set(selector, {
                resolve,
                reject,
                startTime,
                timeout: maxTime,
                attempts: 0,
            });

            this._checkExistingElements();
        });
    }

    waitForAnyElement(selectors, timeout = null) {
        return Promise.race(
            selectors.map(sel => this.waitForElement(sel, timeout))
        );
    }

    destroy() {
        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }
        this.retryTimers.forEach(timer => clearTimeout(timer));
        this.retryTimers.clear();
        this.pendingSelectors.clear();
    }

    _startObserver() {
        if (this.observer) return;

        this.observer = new MutationObserver(() => {
            this._checkExistingElements();
        });

        this.observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'data-tour'],
        });
    }

    _checkExistingElements() {
        const now = Date.now();

        for (const [selector, pending] of this.pendingSelectors.entries()) {
            const element = document.querySelector(selector);
            if (element) {
                pending.resolve(element);
                this.pendingSelectors.delete(selector);
                continue;
            }

            if (now - pending.startTime >= pending.timeout) {
                pending.reject(new Error(`Timeout waiting for element: ${selector}`));
                this.pendingSelectors.delete(selector);
            }
        }
    }
}
