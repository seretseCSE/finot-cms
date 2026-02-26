(() => {
    const steps = [
        {
            target: '[data-tour-target="dashboard"]',
            title: 'Dashboard',
            content: 'Your key tasks at a glance.',
            side: 'bottom',
        },
        {
            target: '[data-tour-target="attendance-sessions"]',
            title: 'Attendance Sessions',
            content: 'Create sessions and mark student attendance here.',
            side: 'right',
        },
        {
            target: '[data-tour-target="mark-attendance"]',
            title: 'Mark Attendance',
            content: 'Click to open the attendance marking form.',
            side: 'left',
        },
        {
            target: '[data-tour-target="offline-mode"]',
            title: 'Offline Mode',
            content: 'You can mark attendance offline. It will sync when you reconnect.',
            side: 'bottom',
        },
        {
            target: '[data-tour-target="lock-session"]',
            title: 'Lock Session',
            content: 'Remember to lock sessions before the 30-day deadline.',
            side: 'right',
        },
        {
            target: '[data-tour-target="sync-conflicts"]',
            title: 'Sync Conflicts',
            content: 'Review any sync conflicts that occurred during offline mode.',
            side: 'left',
        },
    ];

    let currentStep = 0;
    let tour = null;

    function showStep(index) {
        if (index < 0 || index >= steps.length) return;
        const step = steps[index];
        const el = document.querySelector(step.target);
        if (!el) return;

        document.querySelectorAll('.tour-highlight').forEach(e => e.classList.remove('tour-highlight'));
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
            left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        }

        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;

        currentStep = index;
    }

    function nextStep() {
        if (currentStep < steps.length - 1) {
            showStep(currentStep + 1);
        } else {
            endTour();
        }
    }

    function prevStep() {
        if (currentStep > 0) {
            showStep(currentStep - 1);
        }
    }

    function endTour() {
        document.querySelectorAll('.tour-highlight').forEach(e => e.classList.remove('tour-highlight'));
        const tooltip = document.getElementById('tour-tooltip');
        if (tooltip) tooltip.remove();
        if (tour) tour.remove();
        localStorage.removeItem('education_monitor_tour_completed');
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

        document.getElementById('tour-prev').disabled = currentStep === 0;
        document.getElementById('tour-next').disabled = currentStep === steps.length - 1;
    }

    function startTour() {
        if (localStorage.getItem('education_monitor_tour_completed')) return;
        createTourOverlay();
        showStep(0);
    }

    if (window.location.hash === '#tour' || !localStorage.getItem('education_monitor_tour_visited')) {
        startTour();
        localStorage.setItem('education_monitor_tour_visited', '1');
    }

    window.startEducationMonitorTour = startTour;
    window.endEducationMonitorTour = endTour;
})();
