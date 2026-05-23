import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

export default class DriverAdapter {
    constructor() {
        this.driver = null;
        this.active = false;
        this.currentStep = 0;
        this.totalSteps = 0;
        this.config = {
            animate: true,
            opacity: 0.75,
            padding: 10,
            allowClose: true,
            overlayClickNext: false,
            doneBtnText: 'Done',
            closeBtnText: 'Close',
            nextBtnText: 'Next',
            prevBtnText: 'Previous',
            showButtons: ['next', 'previous', 'close'],
            keyboardControl: true,
            scrollIntoViewOptions: {
                behavior: 'smooth',
                block: 'center',
            },
            popoverOptions: {
                defaultPosition: 'bottom',
            },
        };
        this.callbacks = {
            onStepChanged: null,
            onDestroyStarted: null,
            onCommitted: null,
        };
    }

    init(config = {}) {
        this.config = { ...this.config, ...config };
    }

    setupSteps(rawSteps, stepCallbacks = {}) {
        this.callbacks = { ...this.callbacks, ...stepCallbacks };

        const steps = rawSteps.map((step, index) => {
            return {
                element: step.element || step.selector,
                popover: {
                    title: step.title || '',
                    description: step.description || '',
                    side: step.side || this.config.popoverOptions.defaultPosition,
                    align: step.align || 'start',
                    popoverClass: this._buildPopoverClass(step),
                    ...this._buildPopoverConfig(step),
                },
                onHighlightStarted: () => {
                    if (step.onEnter) step.onEnter();
                    window.dispatchEvent(new CustomEvent('tourStepHighlighted', {
                        detail: { step, index },
                        bubbles: true,
                    }));
                },
                onHighlighted: () => {
                    if (step.onHighlight) step.onHighlight();
                },
                onDeselected: () => {
                    if (step.onLeave) step.onLeave();
                },
            };
        });

        return steps;
    }

    async start(steps, startIndex = 0) {
        if (this.active) {
            this.destroy();
        }

        this.totalSteps = steps.length;
        this.currentStep = startIndex;
        this.active = true;

        this.driver = driver({
            ...this.config,
            steps,
            startIndex,
            onStepChanged: (element, step, { index }) => {
                this.currentStep = index;
                if (this.callbacks.onStepChanged) {
                    this.callbacks.onStepChanged(element, step, index);
                }
            },
            onDestroyStarted: (element, step, { index }) => {
                if (step.onClose) step.onClose();
                if (this.callbacks.onDestroyStarted) {
                    this.callbacks.onDestroyStarted(element, step, index);
                }
            },
            onCommitted: (element, step, { index }) => {
                if (this.callbacks.onCommitted) {
                    this.callbacks.onCommitted(element, step, index);
                }
            },
        });

        this.driver.drive(startIndex);
    }

    next() {
        if (this.driver) {
            this.driver.moveNext();
        }
    }

    previous() {
        if (this.driver) {
            this.driver.movePrevious();
        }
    }

    goTo(index) {
        if (this.driver) {
            this.driver.drive(index);
            this.currentStep = index;
        }
    }

    hasNext() {
        return this.currentStep < this.totalSteps - 1;
    }

    hasPrevious() {
        return this.currentStep > 0;
    }

    isActive() {
        return this.active;
    }

    destroy() {
        if (this.driver) {
            try {
                this.driver.destroy();
            } catch (e) {
            }
            this.driver = null;
        }
        this.active = false;
        this.currentStep = 0;
        this.totalSteps = 0;
    }

    _buildPopoverClass(step) {
        const classes = ['driver-popover-custom'];
        if (step.darkMode) classes.push('driver-dark');
        if (step.mobileOptimized) classes.push('driver-mobile');
        if (step.reducedMotion) classes.push('driver-reduced-motion');
        if (step.className) classes.push(step.className);
        return classes.join(' ');
    }

    _buildPopoverConfig(step) {
        const config = {};

        if (step.title) {
            config.title = step.title;
        }
        if (step.description) {
            config.description = step.description;
        }
        if (step.popoverClass) {
            config.popoverClass = step.popoverClass;
        }

        return config;
    }
}
