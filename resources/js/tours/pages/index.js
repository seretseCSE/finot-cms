export { default as dashboardTour } from './dashboardTour.js';
export { default as membersTour } from './membersTour.js';
export { default as donationsTour } from './donationsTour.js';
export { default as attendanceTour } from './attendanceTour.js';
export { default as financeOverviewTour } from './financeOverviewTour.js';

export const pageTourMap = {
    'dashboard': 'dashboardTour',
    'members': 'membersTour',
    'donations': 'donationsTour',
    'attendance': 'attendanceTour',
    'finance': 'financeOverviewTour',
};
