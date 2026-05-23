export default {
    key: 'onboarding',
    label: 'Welcome to FINOTE Teacher',
    description: 'Learn to manage classes and track attendance.',
    steps: [
        {
            key: 'sidebar-welcome',
            selector: '[data-tour="sidebar"]',
            title: 'Navigation Sidebar',
            description: 'Access your teaching tools from the navigation sidebar.',
            side: 'right',
            align: 'center',
        },
        {
            key: 'education-section',
            selector: '[data-tour="education-section"]',
            title: 'Classes & Attendance',
            description: 'View your assigned classes, mark student attendance, and track participation.',
            side: 'right',
            align: 'start',
        },
        {
            key: 'completion',
            selector: '[data-tour="dashboard-content"]',
            title: 'Teacher Ready!',
            description: 'You\'re all set. Manage your classes and track attendance with ease.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
