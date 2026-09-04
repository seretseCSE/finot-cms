export default {
    key: 'onboarding',
    label: 'Welcome, Student',
    description: 'Your class announcements, homework, materials, results, and attendance.',
    steps: [
        {
            key: 'tiles',
            selector: '[data-tour="student-tiles"]',
            title: 'Your dashboard',
            description: 'Shortcuts to class announcements, homework, materials, results, and attendance.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'announcements',
            selector: '[data-tour="tile-class-announcements"]',
            title: 'Class Announcements',
            description: 'Notices for your class only — exams and reminders. You also get app notifications.',
            side: 'bottom',
            align: 'start',
        },
        {
            key: 'homework',
            selector: '[data-tour="tile-homework"]',
            title: 'Homework',
            description: 'Open assignments and download files from your teachers.',
            side: 'bottom',
            align: 'start',
        },
        {
            key: 'results',
            selector: '[data-tour="tile-results"], [data-tour="student-results"]',
            title: 'My Results',
            description: 'Scores and ranks appear after staff save them.',
            side: 'bottom',
            align: 'start',
        },
    ],
};
