export default {
    key: 'attendance_tracking',
    label: 'Attendance Tracking',
    description: 'Track attendance for classes and services.',
    steps: [
        {
            key: 'attendance-overview',
            selector: '[data-tour="attendance-overview"]',
            title: 'Attendance Dashboard',
            description: 'View attendance records for all classes and sessions. Monitor participation rates in real-time.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'mark-attendance',
            selector: '[data-tour="mark-attendance"]',
            title: 'Mark Attendance',
            description: 'Select a class or session and mark attendance for each student. Supports bulk actions for efficiency.',
            side: 'bottom',
            align: 'end',
        },
        {
            key: 'attendance-sessions',
            selector: '[data-tour="attendance-sessions"]',
            title: 'Attendance Sessions',
            description: 'Manage attendance sessions, set dates, and track who attended each session.',
            side: 'left',
            align: 'center',
        },
        {
            key: 'attendance-reports',
            selector: '[data-tour="attendance-reports"]',
            title: 'Attendance Reports',
            description: 'Generate reports on attendance trends, identify patterns, and track improvements over time.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
