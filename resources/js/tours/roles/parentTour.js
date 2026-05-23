export default {
    key: 'onboarding',
    label: 'Welcome to FINOTE Parent Portal',
    description: 'Track your children\'s progress and church activities.',
    steps: [
        {
            key: 'sidebar-welcome',
            selector: '[data-tour="sidebar"]',
            title: 'Navigation Sidebar',
            description: 'Access your family dashboard and children\'s information from here.',
            side: 'right',
            align: 'center',
        },
        {
            key: 'children-section',
            selector: '[data-tour="children-section"]',
            title: 'Children\'s Progress',
            description: 'View your children\'s attendance, grades, and church activity participation.',
            side: 'right',
            align: 'start',
        },
        {
            key: 'completion',
            selector: '[data-tour="dashboard-content"]',
            title: 'All Set!',
            description: 'You can now track your children\'s church activities and educational progress.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
