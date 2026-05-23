export default class StepRegistry {
    constructor() {
        this.steps = new Map();
        this.hooks = new Map();
    }

    register(tourKey, steps = []) {
        this.steps.set(tourKey, steps);
        return this;
    }

    get(tourKey) {
        return this.steps.get(tourKey) || [];
    }

    has(tourKey) {
        return this.steps.has(tourKey);
    }

    unregister(tourKey) {
        this.steps.delete(tourKey);
    }

    addStep(tourKey, step) {
        const steps = this.steps.get(tourKey) || [];
        steps.push(step);
        this.steps.set(tourKey, steps);
        return this;
    }

    addHook(tourKey, hookName, callback) {
        if (!this.hooks.has(tourKey)) {
            this.hooks.set(tourKey, new Map());
        }
        const tourHooks = this.hooks.get(tourKey);
        if (!tourHooks.has(hookName)) {
            tourHooks.set(hookName, []);
        }
        tourHooks.get(hookName).push(callback);
        return this;
    }

    runHook(tourKey, hookName, ...args) {
        const tourHooks = this.hooks.get(tourKey);
        if (!tourHooks) return;
        const callbacks = tourHooks.get(hookName) || [];
        callbacks.forEach(cb => cb(...args));
    }

    filterSteps(tourKey, predicate) {
        const steps = this.get(tourKey);
        this.steps.set(tourKey, steps.filter(predicate));
        return this;
    }

    getStepByKey(tourKey, stepKey) {
        return this.get(tourKey).find(s => s.key === stepKey) || null;
    }

    clear() {
        this.steps.clear();
        this.hooks.clear();
    }
}
