export default class TourProgress {
    constructor() {
        this.element = null;
    }

    render(currentStep, totalSteps, config = {}) {
        const percentage = totalSteps > 0 ? Math.round((currentStep / totalSteps) * 100) : 0;

        const container = document.createElement('div');
        container.className = 'tour-progress-component';
        container.setAttribute('role', 'progressbar');
        container.setAttribute('aria-valuenow', currentStep);
        container.setAttribute('aria-valuemin', '0');
        container.setAttribute('aria-valuemax', totalSteps);
        container.setAttribute('aria-label', `Tour progress: ${currentStep} of ${totalSteps} steps`);

        const showLabels = config.showLabels !== false;

        container.innerHTML = `
            <div class="tour-progress-track">
                <div class="tour-progress-bar-fill" style="width: ${percentage}%"></div>
            </div>
            ${showLabels ? `<span class="tour-progress-label">${currentStep} of ${totalSteps}</span>` : ''}
        `;

        return container;
    }

    update(currentStep, totalSteps) {
        if (!this.element) return;

        const percentage = totalSteps > 0 ? Math.round((currentStep / totalSteps) * 100) : 0;
        const fill = this.element.querySelector('.tour-progress-bar-fill');
        const label = this.element.querySelector('.tour-progress-label');

        if (fill) {
            fill.style.width = `${percentage}%`;
        }
        if (label) {
            label.textContent = `${currentStep} of ${totalSteps}`;
        }

        this.element.setAttribute('aria-valuenow', currentStep);
        this.element.setAttribute('aria-valuemax', totalSteps);
    }
}
