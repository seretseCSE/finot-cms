export default class TourTooltip {
    constructor() {
        this.element = null;
    }

    render({ title, description, currentStep, totalSteps, onNext, onPrev, onSkip, onDone }) {
        const container = document.createElement('div');
        container.className = 'tour-tooltip';
        container.setAttribute('role', 'dialog');
        container.setAttribute('aria-modal', 'true');
        container.setAttribute('aria-labelledby', 'tour-tooltip-title');

        container.innerHTML = `
            <div class="tour-tooltip-header">
                <div class="tour-tooltip-steps">
                    <span class="tour-step-indicator">${currentStep} of ${totalSteps}</span>
                    <div class="tour-progress-bar">
                        <div class="tour-progress-fill" style="width: ${(currentStep / totalSteps) * 100}%"></div>
                    </div>
                </div>
                <button class="tour-close-btn" aria-label="Close tour" data-action="skip">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="tour-tooltip-body">
                <h3 id="tour-tooltip-title" class="tour-tooltip-title">${title}</h3>
                <p class="tour-tooltip-description">${description}</p>
            </div>
            <div class="tour-tooltip-footer">
                <button class="tour-btn tour-btn-ghost" data-action="skip">Skip tour</button>
                <div class="tour-tooltip-nav">
                    ${currentStep > 1 ? '<button class="tour-btn tour-btn-secondary" data-action="prev">Back</button>' : ''}
                    ${currentStep < totalSteps
                        ? '<button class="tour-btn tour-btn-primary" data-action="next">Next</button>'
                        : '<button class="tour-btn tour-btn-primary" data-action="done">Done</button>'
                    }
                </div>
            </div>
        `;

        container.querySelector('[data-action="skip"]').addEventListener('click', onSkip);
        container.querySelector('[data-action="next"]')?.addEventListener('click', onNext);
        container.querySelector('[data-action="prev"]')?.addEventListener('click', onPrev);
        container.querySelector('[data-action="done"]')?.addEventListener('click', onDone);

        return container;
    }
}
