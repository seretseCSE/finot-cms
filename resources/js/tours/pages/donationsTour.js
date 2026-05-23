export default {
    key: 'donations',
    label: 'Donations Management',
    description: 'Manage and track donations.',
    steps: [
        {
            key: 'donations-overview',
            selector: '[data-tour="donations-overview"]',
            title: 'Donations Overview',
            description: 'View all donations at a glance. Track totals, filter by date range, and monitor giving trends.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'record-donation',
            selector: '[data-tour="record-donation"]',
            title: 'Record a Donation',
            description: 'Click to record a new donation. Select the donor, amount, payment method, and designate the fund.',
            side: 'bottom',
            align: 'end',
        },
        {
            key: 'donation-reports',
            selector: '[data-tour="donation-reports"]',
            title: 'Donation Reports',
            description: 'Generate detailed donation reports for accounting, tax purposes, and stewardship analysis.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'donor-management',
            selector: '[data-tour="donor-management"]',
            title: 'Donor Management',
            description: 'View donor profiles, giving history, and contribution patterns for better engagement.',
            side: 'right',
            align: 'center',
        },
    ],
};
