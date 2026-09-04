export default {
    key: 'onboarding',
    label: 'Welcome, Education Monitor',
    description: 'Take and lock class attendance. You do not enter exam scores.',
    steps: [
        {
            key: 'sidebar',
            selector: '[data-tour="sidebar"]',
            title: 'Attendance menus',
            description: 'Open student and teacher attendance from your Education / Attendance menus.',
            side: 'right',
            align: 'center',
        },
        {
            key: 'done',
            selector: '[data-tour="dashboard-content"]',
            title: 'Your scope',
            description: 'Mark Present/Absent/Late/Excused, then lock the session. Enrollments and scores belong to Education Head.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
