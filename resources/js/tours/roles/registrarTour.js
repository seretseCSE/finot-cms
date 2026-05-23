export default {
    key: 'onboarding',
    label: 'Welcome to FINOTE Registrar',
    description: 'Learn to manage members and enrollments.',
    steps: [
        {
            key: 'sidebar-welcome',
            selector: '[data-tour="sidebar"]',
            title: 'Navigation Sidebar',
            description: 'Access member management and enrollment tools from here.',
            side: 'right',
            align: 'center',
        },
        {
            key: 'members-section',
            selector: '[data-tour="members-section"]',
            title: 'Members Management',
            description: 'Register new members, update profiles, and manage family groups.',
            side: 'right',
            align: 'start',
        },
        {
            key: 'education-section',
            selector: '[data-tour="education-section"]',
            title: 'Enrollments',
            description: 'Manage student enrollments in classes and educational programs.',
            side: 'right',
            align: 'start',
        },
        {
            key: 'completion',
            selector: '[data-tour="dashboard-content"]',
            title: 'Registrar Ready!',
            description: 'You\'re all set. Manage member registrations and enrollments efficiently.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
