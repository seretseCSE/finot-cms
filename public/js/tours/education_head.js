(() => {
    const steps = [
        {
            target: '[data-tour-target="dashboard"]',
            title: 'Education Department Overview',
            content: 'This dashboard shows key education metrics at a glance.',
            side: 'bottom',
        },
        {
            target: '[data-tour-target="academic-years"]',
            title: 'Academic Years',
            content: 'Manage academic years here. Only one can be active at a time.',
            side: 'right',
        },
        {
            target: '[data-tour-target="classes"]',
            title: 'Classes',
            content: 'Create and manage your class levels.',
            side: 'left',
        },
        {
            target: '[data-tour-target="subjects"]',
            title: 'Subjects',
            content: 'Create subjects that teachers will be assigned to.',
            side: 'left',
        },
        {
            target: '[data-tour-target="teachers"]',
            title: 'Teachers',
            content: 'Register and assign teachers to classes and subjects.',
            side: 'right',
        },
        {
            target: '[data-tour-target="enrollments"]',
            title: 'Enrollments',
            content: 'Enroll students into classes for the active year.',
            side: 'right',
        },
        {
            target: '[data-tour-target="attendance-sessions"]',
            title: 'Attendance Sessions',
            content: 'View and manage all attendance sessions.',
            side: 'left',
        },
        {
            target: '[data-tour-target="reports"]',
            title: 'Reports',
            content: 'Access student and teacher Attendance reports here.',
            side: 'right',
        },
        {
            target: '[data-tour-target="bulk-promote"]',
            title: 'Promotion Tip',
            content: 'Use Bulk Promote action on Enrollments at end of year.',
            side: 'bottom',
        },
    ];

    let currentStep = 0;
    let tour = null;

    function showStep(index) {
        if (index < 0 || index >= steps.length) return;
        const step = steps[index];
        const el = document.querySelector(step.target);
        if (!el) return;

        // Remove previous highlights
        document.querySelectorAll('.tour-highlight').forEach(e => e.classList.remove('tour-highlight'));

        // Highlight current element
        el.classList.add('tour-highlight');
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Create tooltip
        const existing = document.getElementById('tour-tooltip');
        if (existing) existing.remove();

        const tooltip = document.createElement('div');
        tooltip.id = 'tour-tooltip';
        tooltip.className = `tour-tooltip tour-tooltip-${step.side}`;
        tooltip.innerHTML = `<strong>${step.title}</strong><br>${step.content}`;
        document.body.appendChild(tooltip);

        // Position tooltip
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
        localStorage.removeItem('education_head_tour_completed');
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
        if (localStorage.getItem('education_head_tour_completed')) return;
        createTourOverlay();
        showStep(0);
    }

    // Auto-start if hash matches or first visit
    if (window.location.hash === '#tour' || !localStorage.getItem('education_head_tour_visited')) {
        startTour();
        localStorage.setItem('education_head_tour_visited', '1');
    }

    // Global start function
    window.startEducationHeadTour = startTour;
    window.endEducationHeadTour = endTour;
})();
