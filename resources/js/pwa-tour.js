// PWA & Product Tour Management
class PWATourManager {
    constructor() {
        this.visitCount = parseInt(localStorage.getItem('visitCount') || '0');
        this.currentRole = null;
        this.deferredInstallPrompt = null;
        this.init();
    }

    init() {
        this.incrementVisitCount();
        this.captureInstallPrompt();
        this.checkPwaPrompt();
        this.loadUserRole();
        this.initTourSystem();
    }

    captureInstallPrompt() {
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
        if (isStandalone) return;
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            this.deferredInstallPrompt = event;
            window.dispatchEvent(new CustomEvent('pwa:install-available'));
        });
    }

    incrementVisitCount() {
        this.visitCount++;
        localStorage.setItem('visitCount', this.visitCount.toString());
    }

    checkPwaPrompt() {
        if (this.visitCount < 1) return;
        if (this.getCookie('pwa_install_dismissed_until')) return;
        window.dispatchEvent(new CustomEvent('pwa:show-install-prompt'));
    }

    setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
        document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
    }

    getCookie(name) {
        const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + escaped + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    async installPWA() {
        if (!this.deferredInstallPrompt) {
            this.showManualInstallInstructions();
            return;
        }
        try {
            this.deferredInstallPrompt.prompt();
            await this.deferredInstallPrompt.userChoice;
        } catch (error) {
            console.error('PWA install prompt failed:', error);
        } finally {
            this.deferredInstallPrompt = null;
            window.dispatchEvent(new CustomEvent('pwa:hide-install-prompt'));
        }
    }

    showManualInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        const isAndroid = /Android/.test(navigator.userAgent);
        let message = '';
        if (isIOS) {
            message = 'To install on iOS:\n1. Tap the Share button in Safari.\n2. Scroll down and tap "Add to Home Screen".\n3. Tap "Add".';
        } else if (isAndroid) {
            message = 'To install on Android:\n1. Tap the Menu (three dots) in Chrome.\n2. Tap "Add to Home Screen" or "Install App".\n3. Tap "Install".';
        } else {
            message = 'To install on Desktop:\n1. Click the Install icon in the address bar.\n2. Or open Chrome menu → "Cast, save, and share" → "Install page as app".';
        }
        alert(message);
    }

    dismissPwaPromptFor7Days() {
        this.setCookie('pwa_install_dismissed_until', '1', 7);
        window.dispatchEvent(new CustomEvent('pwa:hide-install-prompt'));
    }

    async loadUserRole() {
        try {
            const response = await fetch('/api/tour/status');
            const data = await response.json();
            this.currentRole = data.current_role;

            if (data.should_show_tour) {
                setTimeout(() => this.startTour(), 1500);
            }
        } catch (error) {
            console.error('Failed to load user role:', error);
        }
    }

    initTourSystem() {
        if (!window.driver) {
            // Load Driver.js CSS
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://unpkg.com/driver.js@1.3.1/dist/driver.css';
            document.head.appendChild(css);

            // Load Driver.js JS
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/driver.js@1.3.1/dist/driver.js.iife.js';
            script.onload = () => this.setupDriver();
            document.head.appendChild(script);
        } else {
            this.setupDriver();
        }
    }

    setupDriver() {
        window.driver.js.driver = window.driver;
        this.addRestartTourButton();
    }

    addRestartTourButton() {
        const observer = new MutationObserver(() => {
            const userMenu = document.querySelector('.fi-user-menu-trigger');
            if (userMenu && !document.querySelector('#restart-tour-btn')) {
                const restartBtn = document.createElement('button');
                restartBtn.id = 'restart-tour-btn';
                restartBtn.className = 'fi-dropdown-list-item flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700';
                restartBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Restart Tour
                `;
                restartBtn.onclick = () => this.restartTour();

                const dropdown = userMenu.closest('div')?.querySelector('.fi-dropdown-panel');
                if (dropdown) {
                    const list = dropdown.querySelector('.fi-dropdown-list');
                    if (list) {
                        list.appendChild(restartBtn);
                    } else {
                        dropdown.appendChild(restartBtn);
                    }
                }
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    startTour() {
        if (!window.driver) return;

        const tourSteps = this.getTourSteps(this.currentRole);
        if (!tourSteps.length) return;

        const driverObj = window.driver.js.driver({
            showProgress: true,
            animate: true,
            overlayOpacity: 0.75,
            steps: tourSteps,
            onDestroyStarted: () => {
                if (!driverObj.hasNextStep()) {
                    this.markTourCompleted();
                }
                driverObj.destroy();
            }
        });

        driverObj.drive();
    }

    getTourSteps(role) {
        const baseSteps = [
            {
                element: '.fi-main-sidebar',
                popover: {
                    title: 'Navigation Menu',
                    description: 'Access all features and modules from the sidebar navigation.'
                }
            },
            {
                element: '.fi-user-menu-trigger',
                popover: {
                    title: 'User Menu',
                    description: 'Access your profile, settings, and logout from here.'
                }
            }
        ];

        const roleSpecificSteps = {
            superadmin: [
                { element: '[href="/admin/global-church-settings"]', popover: { title: 'Global Settings', description: 'Configure system-wide church settings.' } },
                { element: '[href="/admin/users"]', popover: { title: 'User Management', description: 'Manage all system users, roles, and permissions.' } },
                { element: '[href="/admin/backup-restore"]', popover: { title: 'Backup & Restore', description: 'Create and restore system backups.' } },
                { element: '[href="/admin/audit-logs"]', popover: { title: 'Audit Logs', description: 'View system audit trails and activity logs.' } },
            ],
            admin: [
                { element: '[href="/admin/dashboard"]', popover: { title: 'Dashboard', description: 'View system overview, statistics, and pending tasks.' } },
                { element: '[href="/admin/members"]', popover: { title: 'Members', description: 'Manage church members and their profiles.' } },
                { element: '[href="/admin/users"]', popover: { title: 'Users', description: 'Manage staff users and their access levels.' } },
            ],
            hr_head: [
                { element: '[href="/admin/members"]', popover: { title: 'Members', description: 'Manage church members, registrations, and profiles.' } },
                { element: '[href="/admin/member-groups"]', popover: { title: 'Member Groups', description: 'Organize members into groups and ministries.' } },
                { element: '[href="/admin/attendance-sessions"]', popover: { title: 'Attendance', description: 'Track member attendance for services and events.' } },
            ],
            finance_head: [
                { element: '[href="/admin/contribution-matrix"]', popover: { title: 'Contribution Matrix', description: 'Track and manage member contributions by month.' } },
                { element: '[href="/admin/donations"]', popover: { title: 'Donations', description: 'Record and manage donations from visitors and organizations.' } },
                { element: '[href="/admin/financial-overview-page"]', popover: { title: 'Financial Overview', description: 'View financial summaries and analytics.' } },
                { element: '[href="/admin/bank-accounts"]', popover: { title: 'Bank Accounts', description: 'Manage church bank accounts and balances.' } },
            ],
            nibret_hisab_head: [
                { element: '[href="/admin/inventories"]', popover: { title: 'Inventory', description: 'Manage church assets, equipment, and inventory items.' } },
                { element: '[href="/admin/stock-movements"]', popover: { title: 'Stock Movements', description: 'Track inventory in/out movements and transfers.' } },
                { element: '[href="/admin/inventories/analytics"]', popover: { title: 'Inventory Analytics', description: 'View inventory reports and usage analytics.' } },
            ],
            inventory_staff: [
                { element: '[href="/admin/inventories"]', popover: { title: 'Inventory Management', description: 'Add, update, and track inventory items.' } },
                { element: '[href="/admin/stock-movements"]', popover: { title: 'Stock Movements', description: 'Record inventory movements and stock changes.' } },
            ],
            education_head: [
                { element: '[href="/admin/academic-years"]', popover: { title: 'Academic Years', description: 'Manage academic years, terms, and schedules.' } },
                { element: '[href="/admin/school-classes"]', popover: { title: 'Classes', description: 'Manage educational classes and curricula.' } },
                { element: '[href="/admin/student-enrollments"]', popover: { title: 'Enrollments', description: 'Manage student enrollments and registrations.' } },

            ],
            education_monitor: [
                { element: '[href="/admin/attendance-sessions"]', popover: { title: 'Attendance Tracking', description: 'Mark and review student attendance.' } },
                { element: '[href="/admin/student-progress-report"]', popover: { title: 'Student Progress', description: 'Monitor student academic progress over time.' } },
            ],
            worship_monitor: [
                { element: '[href="/admin/rehearsals"]', popover: { title: 'Rehearsals', description: 'Schedule and manage worship rehearsals.' } },
                { element: '[href="/admin/songs"]', popover: { title: 'Song Library', description: 'Manage worship songs, lyrics, and media.' } },
            ],
            mezmur_head: [
                { element: '[href="/admin/songs"]', popover: { title: 'Song Library', description: 'Manage songs, lyrics, audio, and video files.' } },
                { element: '[href="/admin/song-categories"]', popover: { title: 'Song Categories', description: 'Organize songs by category and subcategory.' } },
                { element: '[href="/admin/rehearsals"]', popover: { title: 'Rehearsals', description: 'Schedule and track rehearsal attendance.' } },
            ],
            av_head: [
                { element: '[href="/admin/media"]', popover: { title: 'Media Management', description: 'Upload, organize, and manage photos and videos.' } },
                { element: '[href="/admin/media-categories"]', popover: { title: 'Media Categories', description: 'Categorize media for easy browsing.' } },
                { element: '[href="/admin/blog-posts"]', popover: { title: 'Blog Posts', description: 'Create and publish blog posts and announcements.' } },
            ],
            charity_head: [
                { element: '[href="/admin/beneficiaries"]', popover: { title: 'Beneficiaries', description: 'Manage aid beneficiaries and their needs.' } },
                { element: '[href="/admin/aid-distributions"]', popover: { title: 'Aid Distributions', description: 'Record and track aid distribution activities.' } },
                { element: '[href="/admin/charity-report"]', popover: { title: 'Charity Reports', description: 'View charity and aid distribution reports.' } },
            ],
            tour_head: [
                { element: '[href="/admin/tours"]', popover: { title: 'Tour Management', description: 'Create and manage church tours and trips.' } },
                { element: '[href="/admin/tour-passengers"]', popover: { title: 'Tour Passengers', description: 'Manage tour registrations and passenger lists.' } },
                { element: '[href="/admin/tour-report"]', popover: { title: 'Tour Reports', description: 'Generate tour analytics and reports.' } },
            ],
            internal_relations_head: [
                { element: '[href="/admin/events"]', popover: { title: 'Events', description: 'Organize and manage church events and activities.' } },
                { element: '[href="/admin/announcements"]', popover: { title: 'Announcements', description: 'Create and publish announcements for members.' } },
                { element: '[href="/admin/documents"]', popover: { title: 'Documents', description: 'Manage department documents and archives.' } },
            ],
            department_secretary: [
                { element: '[href="/admin/documents"]', popover: { title: 'Documents', description: 'Manage department-specific documents and files.' } },
                { element: '[href="/admin/announcements"]', popover: { title: 'Announcements', description: 'Publish and manage announcements.' } },
            ],
            staff: [
                { element: '[href="/admin/dashboard"]', popover: { title: 'Dashboard', description: 'View your personalized dashboard and tasks.' } },
                { element: '[href="/admin/edit-profile"]', popover: { title: 'Profile', description: 'Update your personal information and preferences.' } },
            ]
        };

        return [...baseSteps, ...(roleSpecificSteps[role] || [])];
    }

    async restartTour() {
        try {
            await fetch('/api/tour/restart', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } });
            this.startTour();
        } catch (error) {
            console.error('Failed to restart tour:', error);
        }
    }

    async markTourCompleted() {
        try {
            await fetch('/api/tour/complete', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } });
        } catch (error) {
            console.error('Failed to mark tour completed:', error);
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.pwaTourManager = new PWATourManager();
});

// Export for global access
window.PWATourManager = PWATourManager;
