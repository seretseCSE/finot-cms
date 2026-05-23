export default {
    key: 'onboarding',
    label: 'Welcome to FINOTE Finance',
    description: 'Learn how to manage financial operations efficiently.',
    steps: [
        {
            key: 'sidebar-welcome',
            selector: '[data-tour="sidebar"]',
            title: 'Navigation Sidebar',
            description: 'Your financial tools are organized here. Access donations, contributions, and financial reports.',
            side: 'right',
            align: 'center',
        },
        {
            key: 'finance-section',
            selector: '[data-tour="finance-section"]',
            title: 'Financial Reports',
            description: 'View and generate financial reports including donation summaries and contribution ledgers.',
            side: 'right',
            align: 'start',
        },
        {
            key: 'donations-section',
            selector: '[data-tour="donations-section"]',
            title: 'Donations',
            description: 'Track incoming donations, manage donors, and view donation analytics.',
            side: 'right',
            align: 'start',
        },
        {
            key: 'completion',
            selector: '[data-tour="dashboard-content"]',
            title: 'Finance Ready!',
            description: 'You\'re all set to manage financial operations. Visit the financial reports section to get started.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
