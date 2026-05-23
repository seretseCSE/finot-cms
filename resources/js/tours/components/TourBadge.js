export default class TourBadge {
    constructor() {
        this.element = null;
    }

    render(count, config = {}) {
        const container = document.createElement('span');
        container.className = 'tour-badge';
        container.setAttribute('role', 'status');
        container.setAttribute('aria-label', `${count} new feature${count !== 1 ? 's' : ''} available`);

        const variant = config.variant || 'primary';
        const size = config.size || 'sm';
        const showPulse = config.showPulse !== false;

        container.className += ` tour-badge-${variant} tour-badge-${size}`;
        if (showPulse) {
            container.className += ' tour-badge-pulse';
        }

        container.innerHTML = `
            <span class="tour-badge-count">${count > 99 ? '99+' : count}</span>
        `;

        return container;
    }

    update(count) {
        if (!this.element) return;
        const countEl = this.element.querySelector('.tour-badge-count');
        if (countEl) {
            countEl.textContent = count > 99 ? '99+' : count;
        }
    }
}
