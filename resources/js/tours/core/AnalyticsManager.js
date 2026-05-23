export default class AnalyticsManager {
    constructor() {
        this.enabled = true;
        this.sessionId = this._generateSessionId();
        this.startTime = null;
        this.eventBuffer = [];
        this.flushInterval = null;
    }

    init(config = {}) {
        this.enabled = config.analytics !== false;
        if (this.enabled && config.flushInterval) {
            this.flushInterval = setInterval(() => this.flush(), config.flushInterval);
        }
    }

    track(event, data = {}) {
        if (!this.enabled) return;

        const payload = {
            event,
            timestamp: new Date().toISOString(),
            session_id: this.sessionId,
            url: window.location.pathname,
            user_agent: navigator.userAgent,
            screen_size: `${window.innerWidth}x${window.innerHeight}`,
            ...data,
        };

        this._dispatchBrowserEvent(event, payload);
        this.eventBuffer.push(payload);

        if (this.eventBuffer.length >= 10) {
            this.flush();
        }
    }

    trackStarted(tourKey, metadata = {}) {
        this.startTime = Date.now();
        this.track('tourStarted', { tour_key: tourKey, ...metadata });
    }

    trackStepChanged(tourKey, stepKey, stepIndex, totalSteps) {
        this.track('tourStepChanged', {
            tour_key: tourKey,
            step_key: stepKey,
            step_index: stepIndex,
            total_steps: totalSteps,
            progress: Math.round(((stepIndex + 1) / totalSteps) * 100),
        });
    }

    trackCompleted(tourKey, metadata = {}) {
        const duration = this.startTime ? Date.now() - this.startTime : null;
        this.track('tourCompleted', {
            tour_key: tourKey,
            duration_ms: duration,
            ...metadata,
        });
        this.startTime = null;
    }

    trackSkipped(tourKey, stepKey = null) {
        const duration = this.startTime ? Date.now() - this.startTime : null;
        this.track('tourSkipped', {
            tour_key: tourKey,
            step_key: stepKey,
            duration_ms: duration,
        });
        this.startTime = null;
    }

    trackRestarted(tourKey) {
        this.track('tourRestarted', { tour_key: tourKey });
    }

    trackAbandoned(tourKey, stepKey = null) {
        const duration = this.startTime ? Date.now() - this.startTime : null;
        this.track('tourAbandoned', {
            tour_key: tourKey,
            step_key: stepKey,
            duration_ms: duration,
        });
        this.startTime = null;
    }

    trackFailed(tourKey, error, stepKey = null) {
        this.track('tourFailed', {
            tour_key: tourKey,
            step_key: stepKey,
            error: error?.message || String(error),
        });
    }

    trackHintDismissed(tourKey) {
        this.track('hintDismissed', { tour_key: tourKey });
    }

    trackHintClicked(tourKey) {
        this.track('hintClicked', { tour_key: tourKey });
    }

    flush() {
        if (this.eventBuffer.length === 0) return;

        const events = [...this.eventBuffer];
        this.eventBuffer = [];

        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/product-tour/analytics/batch', JSON.stringify({ events }));
        } else {
            fetch('/api/product-tour/analytics/batch', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ events }),
                keepalive: true,
            }).catch(() => {});
        }
    }

    destroy() {
        this.flush();
        if (this.flushInterval) {
            clearInterval(this.flushInterval);
        }
    }

    _dispatchBrowserEvent(name, detail) {
        try {
            window.dispatchEvent(new CustomEvent(name, {
                detail,
                bubbles: true,
                cancelable: true,
            }));
        } catch (e) {
        }
    }

    _generateSessionId() {
        return `tour_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }
}
