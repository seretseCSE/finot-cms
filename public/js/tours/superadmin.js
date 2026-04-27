(() => {
    const steps = [
        { target: '[data-tour-target="dashboard"]', title: 'Superadmin Dashboard', content: 'High-level system overview and critical alerts.', side: 'bottom' },
        { target: '[data-tour-target="system-settings"]', title: 'System Settings', content: 'Configure global settings for the platform.', side: 'right' },
        { target: '[data-tour-target="users-roles"]', title: 'Users & Roles', content: 'Manage users, roles, and permissions.', side: 'left' },
        { target: '[data-tour-target="audit-logs"]', title: 'Audit Logs', content: 'Review system audit logs for accountability.', side: 'bottom' },
    ];

    let currentStep = 0;

    function showStep(index) {
        if (index < 0 || index >= steps.length) return;
        const step = steps[index];
        const el = document.querySelector(step.target);
        if (!el) return;

        document.querySelectorAll('.tour-highlight').forEach((e) => e.classList.remove('tour-highlight'));
        el.classList.add('tour-highlight');
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });

        const existing = document.getElementById('tour-tooltip');
        if (existing) existing.remove();

        const tooltip = document.createElement('div');
        tooltip.id = 'tour-tooltip';
        tooltip.className = `tour-tooltip tour-tooltip-${step.side}`;
        tooltip.innerHTML = `<strong>${step.title}</strong><br>${step.content}`;
        document.body.appendChild(tooltip);

        const rect = el.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        let top = rect.bottom + 8;
        let left = rect.left;

        if (step.side === 'right') left = rect.right - tooltipRect.width - 8;
        if (step.side === 'left') left = rect.left - tooltipRect.width + 8;
        if (step.side === 'bottom') {
            top = rect.bottom + 8;
            left = rect.left + rect.width / 2 - tooltipRect.width / 2;
        }

        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;

        currentStep = index;

        const prev = document.getElementById('tour-prev');
        const next = document.getElementById('tour-next');
        const stepText = document.getElementById('tour-step');
        if (prev) prev.disabled = currentStep === 0;
        if (next) next.disabled = currentStep === steps.length - 1;
        if (stepText) stepText.textContent = `Step ${currentStep + 1} / ${steps.length}`;
    }

    function nextStep() { currentStep < steps.length - 1 ? showStep(currentStep + 1) : endTour(); }
    function prevStep() { if (currentStep > 0) showStep(currentStep - 1); }

    function endTour() {
        document.querySelectorAll('.tour-highlight').forEach((e) => e.classList.remove('tour-highlight'));
        const tooltip = document.getElementById('tour-tooltip');
        if (tooltip) tooltip.remove();
        const overlay = document.getElementById('tour-overlay');
        if (overlay) overlay.remove();
        localStorage.removeItem('superadmin_tour_completed');
    }

    function createTourOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'tour-overlay';
        overlay.innerHTML = `
            <div class="tour-nav">
                <button id="tour-prev" disabled>← Prev</button>
                <span id="tour-step">Step ${currentStep + 1} / ${steps.length}</span>
                <button id="tour-next">Next →</button>
                <button id="tour-close">×</button>
            </div>
        `;
        document.body.appendChild(overlay);
        document.getElementById('tour-prev').onclick = prevStep;
        document.getElementById('tour-next').onclick = nextStep;
        document.getElementById('tour-close').onclick = endTour;
    }

    function startTour() {
        if (localStorage.getItem('superadmin_tour_completed')) return;
        createTourOverlay();
        showStep(0);
    }

    if (window.location.hash === '#tour' || !localStorage.getItem('superadmin_tour_visited')) {
        startTour();
        localStorage.setItem('superadmin_tour_visited', '1');
    }

    window.startSuperadminTour = startTour;
    window.endSuperadminTour = endTour;
})();
