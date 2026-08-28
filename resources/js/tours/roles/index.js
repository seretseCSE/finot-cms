export { default as superAdminTour } from './superAdminTour.js';
export { default as adminTour } from './adminTour.js';
export { default as financeTour } from './financeTour.js';
export { default as hrTour } from './hrTour.js';
export { default as registrarTour } from './registrarTour.js';
export { default as teacherTour } from './teacherTour.js';
export { default as parentTour } from './parentTour.js';
export { default as encoderTour } from './encoderTour.js';
export { default as studentTour } from './studentTour.js';

export const roleTourMap = {
    superadmin: 'superAdminTour',
    admin: 'adminTour',
    finance: 'financeTour',
    hr: 'hrTour',
    registrar: 'registrarTour',
    teacher: 'teacherTour',
    parent: 'parentTour',
    data_encoder: 'encoderTour',
    student: 'studentTour',
};
