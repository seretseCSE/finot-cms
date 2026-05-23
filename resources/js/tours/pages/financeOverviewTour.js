export default {
    key: 'finance_overview',
    label: 'Financial Overview',
    description: 'Track donations, contributions, and financial reports.',
    steps: [
        {
            key: 'financial-summary',
            selector: '[data-tour="financial-summary"]',
            title: 'Financial Summary',
            description: 'Get a high-level view of your church\'s financial health including income, expenses, and balances.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'contribution-tracking',
            selector: '[data-tour="contribution-tracking"]',
            title: 'Contribution Tracking',
            description: 'Track member contributions, tithes, and offerings. View giving patterns and generate statements.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'financial-reports',
            selector: '[data-tour="financial-reports"]',
            title: 'Financial Reports',
            description: 'Generate comprehensive financial reports including P&L statements, balance sheets, and custom reports.',
            side: 'right',
            align: 'center',
        },
        {
            key: 'banking-section',
            selector: '[data-tour="banking-section"]',
            title: 'Banking & Revenue',
            description: 'Manage bank accounts, track revenue streams, and reconcile transactions.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
