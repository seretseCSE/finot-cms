export default {
    key: 'onboarding',
    label: 'Welcome, Parent',
    description: 'Follow your children’s class notices, homework, materials, and results.',
    steps: [
        {
            key: 'tiles',
            selector: '[data-tour="parent-tiles"], [data-tour="my-children"]',
            title: 'Your dashboard',
            description: 'Open My Children, class announcements, homework, and materials.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'children',
            selector: '[data-tour="my-children"]',
            title: 'My Children',
            description: 'Each card shows a linked child — class, results, and attendance.',
            side: 'bottom',
            align: 'start',
        },
    ],
};
