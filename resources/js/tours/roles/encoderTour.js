export default {
    key: 'onboarding',
    label: 'Welcome, Encoder',
    description: 'Learn how to record class and subject marklists.',
    steps: [
        {
            key: 'sidebar-welcome',
            selector: '[data-tour="sidebar"]',
            title: 'Navigation',
            description: 'Open Education Management to record marks for a class and subject.',
            side: 'right',
            align: 'center',
        },
        {
            key: 'record-marks',
            selector: '[data-tour="record-marklist"]',
            title: 'Record marks',
            description: 'Choose class, term, and subject, then enter excellent / good / needs work. Submit for Education Head approval.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'completion',
            selector: '[data-tour="dashboard-content"]',
            title: 'You are ready',
            description: 'You cannot import members or manage enrollments. Focus on accurate marklists.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
