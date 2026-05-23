import TourStateManager from './TourStateManager.js';

export default class FeatureDiscoveryManager {
    constructor(stateManager) {
        this.stateManager = stateManager || new TourStateManager();
        this.discoveries = [];
        this.hints = [];
        this.currentHintIndex = 0;
        this.hintTimeout = null;
        this.config = {
            hintDelay: 2000,
            maxHints: 3,
            dismissDays: 7,
        };
    }

    init(config = {}) {
        Object.assign(this.config, config);
    }

    async discover() {
        try {
            const data = await this.stateManager.fetchFeatureDiscoveries();
            this.discoveries = data || [];
            return this.discoveries;
        } catch (e) {
            return [];
        }
    }

    async showHints() {
        if (this.hints.length === 0) return;

        this.currentHintIndex = 0;
        this._showNextHint();
    }

    async checkForNewFeatures() {
        const discoveries = await this.discover();

        if (discoveries.length > 0) {
            window.dispatchEvent(new CustomEvent('featureDiscoveries', {
                detail: { discoveries },
                bubbles: true,
            }));
        }

        return discoveries;
    }

    dismissHint(tourKey) {
        this.stateManager.dismissHint(tourKey);

        window.dispatchEvent(new CustomEvent('hintDismissed', {
            detail: { tour_key: tourKey },
            bubbles: true,
        }));
    }

    destroy() {
        if (this.hintTimeout) {
            clearTimeout(this.hintTimeout);
        }
    }

    _showNextHint() {
        if (this.currentHintIndex >= this.hints.length) {
            this.hints = [];
            this.currentHintIndex = 0;
            return;
        }

        const hint = this.hints[this.currentHintIndex];

        window.dispatchEvent(new CustomEvent('showHint', {
            detail: hint,
            bubbles: true,
        }));

        this.currentHintIndex++;
    }
}
