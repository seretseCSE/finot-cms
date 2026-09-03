export default {
    key: 'onboarding',
    label: 'Welcome, Student',
    description: 'Your dashboard, results, attendance, and library in one place.',
    steps: [
        {
            key: 'tiles',
            selector: '[data-tour="student-tiles"]',
            title: 'Your dashboard',
            description: 'Open My Results, My Attendance, Library, or Request withdrawal from here.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'results',
            selector: '[data-tour="tile-results"]',
            title: 'My Results',
            description: 'Approved marklists are saved here.',
            side: 'bottom',
            align: 'start',
        },
        {
            key: 'library',
            selector: '[data-tour="tile-library"]',
            title: 'Library',
            description: 'Open books, worksheets, and documents from the same admin menu as other roles.',
            side: 'bottom',
            align: 'start',
        },
    ],
};
