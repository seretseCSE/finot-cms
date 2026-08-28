export default {
    key: 'onboarding',
    label: 'Welcome, Student',
    description: 'Your results, attendance, and library in one place.',
    steps: [
        {
            key: 'tiles',
            selector: '[data-tour="portal-tiles"]',
            title: 'Your portal',
            description: 'Open My Results, My Attendance, Library, or Worksheets (the existing library).',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'results',
            selector: '[data-tour="tile-results"]',
            title: 'My Results',
            description: 'Approved marklists are saved here and cached for offline viewing.',
            side: 'bottom',
            align: 'start',
        },
        {
            key: 'library',
            selector: '[data-tour="tile-library"]',
            title: 'Library & worksheets',
            description: 'Download songs and library files for offline use when you choose Save offline.',
            side: 'bottom',
            align: 'start',
        },
    ],
};
