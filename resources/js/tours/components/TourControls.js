export default class TourControls {
    constructor() {
        this.element = null;
    }

    render({ onNext, onPrev, onSkip, onDone, hasNext, hasPrev }) {
        const container = document.createElement('div');
        container.className = 'tour-controls';
        container.setAttribute('role', 'toolbar');
        container.setAttribute('aria-label', 'Tour navigation');

        container.innerHTML = `
            <button class="tour-controls-btn tour-controls-skip" data-action="skip" aria-label="Skip tour">
                Skip
            </button>
            <div class="tour-controls-nav">
                ${hasPrev
                    ? '<button class="tour-controls-btn tour-controls-prev" data-action="prev" aria-label="Previous step">Previous</button>'
                    : ''}
                ${hasNext
                    ? '<button class="tour-controls-btn tour-controls-next" data-action="next" aria-label="Next step">Next</button>'
                    : '<button class="tour-controls-btn tour-controls-done" data-action="done" aria-label="Finish tour">Done</button>'
                }
            </div>
        `;

        container.querySelector('[data-action="skip"]')?.addEventListener('click', onSkip);
        container.querySelector('[data-action="prev"]')?.addEventListener('click', onPrev);
        container.querySelector('[data-action="next"]')?.addEventListener('click', onNext);
        container.querySelector('[data-action="done"]')?.addEventListener('click', onDone);

        return container;
    }
}
