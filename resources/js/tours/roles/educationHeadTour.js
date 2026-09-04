export default {
    key: 'onboarding',
    label: 'Welcome, Education Head',
    description: 'Batches, semesters, offerings, scores setup, and roster reports.',
    steps: [
        {
            key: 'sidebar',
            selector: '[data-tour="sidebar"]',
            title: 'Education menus',
            description: 'Use Education Management for batches and enrollments, Attendance for sessions, Results for offerings and reports, and Class Work for announcements, homework, and materials.',
            side: 'right',
            align: 'center',
        },
        {
            key: 'batches',
            selector: '[data-tour="dashboard-content"]',
            title: 'Batches and semesters',
            description: 'Create a batch (Class of 2026), open a semester on a program year, Activate it, then add subject offerings and assessments.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'compute',
            selector: '[data-tour="roster-report"], [data-tour="dashboard-content"]',
            title: 'Compute and roster',
            description: 'Encoders save live scores. When you need official totals and ranks, use Compute results anytime, then open the Roster report.',
            side: 'bottom',
            align: 'center',
        },
        {
            key: 'done',
            selector: '[data-tour="dashboard-content"]',
            title: 'You are ready',
            description: 'Promote within a batch, or Fail / change batch to move a student while keeping passed credits.',
            side: 'bottom',
            align: 'center',
        },
    ],
};
