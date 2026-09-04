import TourManager from './core/TourManager.js';
import StepRegistry from './core/StepRegistry.js';
import { roleTourMap } from './roles/index.js';
import { pageTourMap } from './pages/index.js';

const tourManager = new TourManager();
const stepRegistry = new StepRegistry();

let initialized = false;

function getRoleKey() {
    return document.body.dataset.userRole || null;
}

function loadTourDefinition(roleKey) {
    if (!roleKey) return null;

    const importKey = roleTourMap[roleKey];
    if (!importKey) return null;

    const tours = {
        superadmin: () => import('./roles/superAdminTour.js'),
        admin: () => import('./roles/adminTour.js'),
        finance: () => import('./roles/financeTour.js'),
        hr: () => import('./roles/hrTour.js'),
        registrar: () => import('./roles/registrarTour.js'),
        teacher: () => import('./roles/teacherTour.js'),
        parent: () => import('./roles/parentTour.js'),
        data_encoder: () => import('./roles/encoderTour.js'),
        student: () => import('./roles/studentTour.js'),
        education_head: () => import('./roles/educationHeadTour.js'),
        education_monitor: () => import('./roles/educationMonitorTour.js'),
    };

    const loader = tours[roleKey];
    if (!loader) return null;

    return loader().then(mod => mod.default);
}

function loadPageTour(pageKey) {
    if (!pageKey) return null;

    const pages = {
        dashboard: () => import('./pages/dashboardTour.js'),
        members: () => import('./pages/membersTour.js'),
        donations: () => import('./pages/donationsTour.js'),
        attendance: () => import('./pages/attendanceTour.js'),
        finance: () => import('./pages/financeOverviewTour.js'),
    };

    const loader = pages[pageKey];
    if (!loader) return null;

    return loader().then(mod => mod.default);
}

function getCurrentPage() {
    const path = window.location.pathname;
    const segments = path.split('/').filter(Boolean);
    return segments[1] || 'dashboard';
}

export async function initProductTour(config = {}) {
    if (initialized) return tourManager;

    const mergedConfig = {
        autoStartDelay: 800,
        animate: true,
        opacity: 0.75,
        padding: 10,
        keyboardControl: true,
        analytics: true,
        ...config,
    };

    await tourManager.init(mergedConfig);

    const roleKey = getRoleKey();
    if (!roleKey) {
        initialized = true;
        return tourManager;
    }

    try {
        const roleTour = await loadTourDefinition(roleKey);
        if (roleTour) {
            const steps = (roleTour.steps || []).map((step, index) => ({
                ...step,
                index,
            }));

            stepRegistry.register(roleTour.key, steps);
        }
    } catch (e) {
    }

    try {
        const pageKey = getCurrentPage();
        const pageTour = await loadPageTour(pageKey);
        if (pageTour) {
            const existingSteps = stepRegistry.get(pageTour.key) || [];
            const mergedSteps = [...existingSteps, ...(pageTour.steps || [])];
            stepRegistry.register(pageTour.key, mergedSteps);
        }
    } catch (e) {
    }

    const status = await tourManager.checkAndAutoStart();

    initialized = true;
    return tourManager;
}

export function getTourManager() {
    return tourManager;
}

export async function startTour(tourKey, panel = 'admin') {
    return tourManager.startTour(tourKey, panel);
}

export async function reinitializeProductTour(config = {}) {
    const roleKey = getRoleKey();
    const pageKey = getCurrentPage();

    stepRegistry.clear();

    try {
        const roleTour = await loadTourDefinition(roleKey);
        if (roleTour) {
            const roleSteps = (roleTour.steps || []).map((step, index) => ({
                ...step,
                index,
            }));
            stepRegistry.register(roleTour.key, roleSteps);
        }
    } catch (e) {
    }

    try {
        const pageTour = await loadPageTour(pageKey);
        if (pageTour) {
            const existingSteps = stepRegistry.get(pageTour.key) || [];
            const mergedSteps = [
                ...existingSteps,
                ...(pageTour.steps || []),
            ];
            stepRegistry.register(pageTour.key, mergedSteps);
        }
    } catch (e) {
    }

    const status = await tourManager.checkAndAutoStart();

    return tourManager;
}

export default tourManager;
