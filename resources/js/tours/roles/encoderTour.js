export default {
    key: 'onboarding',
    label: 'Welcome, Encoder',
    description: 'Enter assessment scores on the active semester. Saved scores are live.',
    steps: [
        {
            key: 'sidebar-welcome',
            selector: '[data-tour="sidebar"]',
            title: 'Your menus',
            description: 'You only need Results — Record assessments (or Record Marks). You do not manage enrollments or batches.',
            side: 'right',
            align: 'center',
        },
        {
            key: 'record-assessments',
            selector: '[data-tour="record-assessments"], [data-tour="record-marklist"]',
            title: 'Enter scores',
            description: 'Pick an active semester, subject assessment, load the roster, type scores, and Save. No submit or approval step.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'completion',
            selector: '[data-tour="dashboard-content"]',
            title: 'You are ready',
            description: 'If a student is missing or the semester is closed, ask Education Head.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
