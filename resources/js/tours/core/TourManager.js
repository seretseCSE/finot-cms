import DriverAdapter from './DriverAdapter.js';
import StepRegistry from './StepRegistry.js';
import TourStateManager from './TourStateManager.js';
import AnalyticsManager from './AnalyticsManager.js';
import DOMObserver from './DOMObserver.js';
import AccessibilityManager from './AccessibilityManager.js';
import FeatureDiscoveryManager from './FeatureDiscoveryManager.js';

export default class TourManager {
    constructor() {
        this.driver = new DriverAdapter();
        this.stepRegistry = new StepRegistry();
        this.stateManager = new TourStateManager();
        this.analytics = new AnalyticsManager();
        this.domObserver = new DOMObserver();
        this.accessibility = new AccessibilityManager();
        this.featureDiscovery = new FeatureDiscoveryManager(this.stateManager);

        this.currentTour = null;
        this.currentStep = 0;
        this.config = null;
        this.initialized = false;
        this.pendingTour = null;
        this.abandonTimer = null;
        this.active = false;
    }

    async init(config = {}) {
        if (this.initialized) return;

        this.config = {
            autoStartDelay: 800,
            abandonTimeout: 1800000,
            retrySelectors: true,
            selectorTimeout: 5000,
            retryInterval: 500,
            maxRetries: 3,
            ...config,
        };

        this.driver.init({
            animate: config.animate !== false,
            opacity: 0.75,
            padding: config.padding || 10,
            keyboardControl: config.keyboardControl !== false,
        });

        this.analytics.init({
            analytics: config.analytics !== false,
        });

        this.domObserver.init({
            selectorTimeout: this.config.selectorTimeout,
            retryInterval: this.config.retryInterval,
            maxRetries: this.config.maxRetries,
        });

        this.accessibility.init({
            keyboardNavigation: config.keyboardNavigation !== false,
            focusTrap: config.focusTrap !== false,
            screenReaderSupport: config.screenReaderSupport !== false,
            reducedMotion: config.reducedMotion !== false,
        });

        this.featureDiscovery.init({
            hintDelay: config.hintDelay || 2000,
        });

        this.initialized = true;

        window.addEventListener('tourEscape', () => {
            this._handleAbandon();
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.active) {
                this._startAbandonTimer();
            } else if (!document.hidden) {
                this._clearAbandonTimer();
            }
        });
    }

    async checkAndAutoStart(panel = 'admin') {
        try {
            const status = await this.stateManager.fetchStatus(panel);

            if (!status.available) return false;

            const autoStartTourKey = status.auto_start_tour;
            if (autoStartTourKey) {
                setTimeout(async () => {
                    await this.startTour(autoStartTourKey, panel);
                }, this.config.autoStartDelay);
                return true;
            }

            if (status.feature_discoveries?.length > 0) {
                window.dispatchEvent(new CustomEvent('featureDiscoveries', {
                    detail: { discoveries: status.feature_discoveries },
                    bubbles: true,
                }));
            }

            return false;
        } catch (e) {
            return false;
        }
    }

    async startTour(tourKey, panel = 'admin') {
        try {
            const tourData = await this.stateManager.startTour(tourKey, panel);

            if (!tourData.definition || !tourData.definition.steps) {
                return;
            }

            const steps = tourData.definition.steps;

            const processedSteps = await this._processSteps(steps, tourKey);
            if (processedSteps.length === 0) return;

            const tourConfig = tourData.definition;
            this.currentTour = tourKey;
            this.currentStep = tourData.resume?.step || 0;
            this.active = true;

            this.driver.callbacks = {
                onStepChanged: (element, step, index) => {
                    this.currentStep = index;
                    this._handleStepChange(element, step, index, processedSteps.length);
                },
                onDestroyStarted: (element, step) => {
                    if (step?.popover?.onClose) {
                        step.popover.onClose();
                    }
                    this._handleClose(tourKey);
                },
                onCommitted: () => {
                    this._handleComplete(tourKey);
                },
            };

            this.analytics.trackStarted(tourKey, {
                panel,
                page: window.location.pathname,
                total_steps: processedSteps.length,
                resumed: !!tourData.resume,
            });

            const startIndex = tourData.resume?.step || 0;

            this.driver.start(processedSteps, startIndex);

            this.accessibility.announce(
                `Tour started: ${tourConfig.label || tourKey}. Step ${startIndex + 1} of ${processedSteps.length}.`,
                'assertive'
            );
        } catch (e) {
            this.analytics.trackFailed(tourKey, e);
        }
    }

    async resumeTour(tourKey, panel = 'admin') {
        return this.startTour(tourKey, panel);
    }

    async skipTour(tourKey = null, panel = 'admin') {
        const key = tourKey || this.currentTour;
        if (!key) return;

        try {
            const currentStepKey = this._getCurrentStepKey();
            await this.stateManager.skipTour(key, panel);
            this.analytics.trackSkipped(key, currentStepKey);
            this._cleanup();
        } catch (e) {
        }
    }

    async restartTour(tourKey, panel = 'admin') {
        try {
            await this.stateManager.restartTour(tourKey, panel);
            this.analytics.trackRestarted(tourKey);
            this._cleanup();
            await this.startTour(tourKey, panel);
        } catch (e) {
        }
    }

    async completeTour(tourKey = null, panel = 'admin', metadata = {}) {
        const key = tourKey || this.currentTour;
        if (!key) return;

        try {
            await this.stateManager.completeTour(key, metadata, panel);
            this.analytics.trackCompleted(key, metadata);
            this._cleanup();

            window.dispatchEvent(new CustomEvent('tourCompleted', {
                detail: { tour_key: key, metadata },
                bubbles: true,
            }));
        } catch (e) {
        }
    }

    nextStep() {
        if (this.driver.hasNext()) {
            this.driver.next();
        } else {
            this._handleComplete(this.currentTour);
        }
    }

    previousStep() {
        this.driver.previous();
    }

    goToStep(index) {
        this.driver.goTo(index);
    }

    async saveProgress() {
        if (!this.currentTour) return;
        const total = this.driver.totalSteps || 1;
        const percentage = Math.round(((this.currentStep + 1) / total) * 100);

        try {
            await this.stateManager.saveProgress(
                this.currentTour,
                this.currentStep + 1,
                percentage
            );
        } catch (e) {
        }
    }

    destroy() {
        this._cleanup();
        this.domObserver.destroy();
        this.accessibility.destroy();
        this.analytics.destroy();
        this.initialized = false;
    }

    isActive() {
        return this.active;
    }

    getCurrentTour() {
        return this.currentTour;
    }

    getCurrentStep() {
        return this.currentStep;
    }

    _processSteps(steps, tourKey) {
        const registered = this.stepRegistry.get(tourKey);
        const allSteps = [...steps];

        if (registered.length > 0) {
            allSteps.push(...registered);
        }

        const userRole = document.body.dataset.userRole;
        const filteredSteps = allSteps.filter(step => {
            if (step.roles && step.roles.length > 0 && userRole) {
                return step.roles.includes(userRole);
            }
            return true;
        });

        return Promise.all(
            filteredSteps.map(async (step, index) => {
                return {
                    ...step,
                    index,
                    onEnter: async () => {
                        if (step.selector && this.config.retrySelectors) {
                            try {
                                await this.domObserver.waitForElement(
                                    step.selector,
                                    this.config.selectorTimeout
                                );
                            } catch (e) {
                                this.analytics.trackFailed(tourKey, e, step.key);
                            }
                        }
                        if (step.onEnter) step.onEnter();
                    },
                    onClose: () => {
                        if (step.onClose) {
                            step.onClose();
                        }
                    },
                    onHighlight: () => {
                        this.accessibility.announceStep(
                            step.title || `Step ${index + 1}`,
                            index + 1,
                            filteredSteps.length
                        );
                        window.dispatchEvent(new CustomEvent('tourStepChanged', {
                            detail: {
                                tour_key: tourKey,
                                step_key: step.key || index,
                                step_index: index,
                                total_steps: filteredSteps.length,
                            },
                            bubbles: true,
                        }));
                        if (step.onHighlight) step.onHighlight();
                    },
                };
            })
        );
    }

    _handleStepChange(element, step, index, totalSteps) {
        this.currentStep = index;

        const stepKey = step?.key || index;

        this.analytics.trackStepChanged(this.currentTour, stepKey, index, totalSteps);

        window.dispatchEvent(new CustomEvent('tourStepChanged', {
            detail: {
                tour_key: this.currentTour,
                step_key: stepKey,
                step_index: index,
                total_steps: totalSteps,
            },
            bubbles: true,
        }));

        this.saveProgress();

        this.accessibility.announceStep(
            step?.title || `Step ${index + 1}`,
            index + 1,
            totalSteps
        );
    }

    _handleComplete(tourKey) {
        if (!tourKey) return;
        this.completeTour(tourKey);
    }

    _handleClose(tourKey) {
        this._cleanup();
        this.analytics.trackSkipped(tourKey);
    }

    _handleAbandon() {
        if (!this.currentTour || !this.active) return;

        const stepKey = this._getCurrentStepKey();
        this.analytics.trackAbandoned(this.currentTour, stepKey);
        this._cleanup();

        window.dispatchEvent(new CustomEvent('tourAbandoned', {
            detail: {
                tour_key: this.currentTour,
                step_key: stepKey,
            },
            bubbles: true,
        }));
    }

    _startAbandonTimer() {
        this._clearAbandonTimer();
        this.abandonTimer = setTimeout(() => {
            this._handleAbandon();
        }, this.config.abandonTimeout);
    }

    _clearAbandonTimer() {
        if (this.abandonTimer) {
            clearTimeout(this.abandonTimer);
            this.abandonTimer = null;
        }
    }

    _getCurrentStepKey() {
        if (!this.active || !this.driver.driver) return null;
        try {
            const state = this.driver.driver.getState?.();
            return state?.step?.key || null;
        } catch (e) {
            return null;
        }
    }

    _cleanup() {
        this.driver.destroy();
        this.currentTour = null;
        this.currentStep = 0;
        this.active = false;
        this._clearAbandonTimer();
    }
}
