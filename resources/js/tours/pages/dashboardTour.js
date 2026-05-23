export default {
    key: 'dashboard_overview',
    label: 'Dashboard Overview',
    description: 'Learn about your dashboard widgets and key metrics.',
    steps: [
        {
            key: 'welcome-widget',
            selector: '[data-tour="welcome-widget"]',
            title: 'Welcome Dashboard',
            description: 'This is your personalized dashboard. View key metrics and quick actions at a glance.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'stats-widgets',
            selector: '[data-tour="stats-widgets"]',
            title: 'Key Statistics',
            description: 'Monitor important numbers like total members, recent donations, and attendance rates.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'charts-section',
            selector: '[data-tour="charts-section"]',
            title: 'Visual Analytics',
            description: 'Interactive charts show trends over time for donations, attendance, and membership growth.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'recent-activity',
            selector: '[data-tour="recent-activity"]',
            title: 'Recent Activity',
            description: 'Stay up-to-date with the latest actions and changes made across the system.',
            side: 'top',
            align: 'center',
        },
    ],
};
