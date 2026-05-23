export default {
    key: 'onboarding',
    label: 'Welcome to FINOTE HR',
    description: 'Manage personnel and organizational records.',
    steps: [
        {
            key: 'sidebar-welcome',
            selector: '[data-tour="sidebar"]',
            title: 'Navigation Sidebar',
            description: 'Access member records, attendance tracking, and HR tools from here.',
            side: 'right',
            align: 'center',
        },
        {
            key: 'members-section',
            selector: '[data-tour="members-section"]',
            title: 'Member Records',
            description: 'View and manage member profiles, contact information, and family details.',
            side: 'right',
            align: 'start',
        },
        {
            key: 'completion',
            selector: '[data-tour="dashboard-content"]',
            title: 'HR Ready!',
            description: 'You\'re all set. Manage member records and HR tasks efficiently.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
