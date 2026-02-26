// PWA Tour Management
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
        if (this.visitCount !== 3) return;
        if (this.getCookie('pwa_install_dismissed_until')) return;
        window.dispatchEvent(new CustomEvent('pwa:show-install-prompt'));
    }

    setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
        document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
    }

    getCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\\[\\]\\\\\/\+^])/g, '\\$1') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    async installPWA() {
        if (!this.deferredInstallPrompt) return;
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
                setTimeout(() => this.startTour(), 1000);
            }
        } catch (error) {
            console.error('Failed to load user role:', error);
        }
    }

    initTourSystem() {
        // Load Driver.js if not already loaded
        if (!window.driver) {
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
        // Add restart tour button to user menu
        const observer = new MutationObserver(() => {
            const userMenu = document.querySelector('[data-testid="user-menu-button"]');
            if (userMenu && !document.querySelector('#restart-tour-btn')) {
                const restartBtn = document.createElement('button');
                restartBtn.id = 'restart-tour-btn';
                restartBtn.className = 'w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2';
                restartBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Restart Tour
                `;
                restartBtn.onclick = () => this.restartTour();
                
                // Find the dropdown menu and add the button
                const dropdown = userMenu.closest('div')?.querySelector('[role="menu"]');
                if (dropdown) {
                    dropdown.appendChild(restartBtn);
                }
            }
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
    }

    startTour() {
        if (!window.driver) return;
        
        const tourSteps = this.getTourSteps(this.currentRole);
        
        const driverObj = window.driver.js.driver({
            showProgress: true,
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
                element: '[data-testid="sidebar"]',
                popover: {
                    title: 'Navigation Menu',
                    description: 'Access all features from here'
                }
            },
            {
                element: '[data-testid="user-menu-button"]',
                popover: {
                    title: 'User Menu',
                    description: 'Your profile and settings'
                }
            }
        ];

        const roleSpecificSteps = {
            superadmin: [
                {
                    element: '[href="/admin/system-settings"]',
                    popover: {
                        title: 'System Settings',
                        description: 'Configure system-wide settings'
                    }
                },
                {
                    element: '[href="/admin/users"]',
                    popover: {
                        title: 'User Management',
                        description: 'Manage all system users'
                    }
                },
                {
                    element: '[href="/admin/backup"]',
                    popover: {
                        title: 'System Backup',
                        description: 'Create and restore system backups'
                    }
                }
            ],
            admin: [
                {
                    element: '[href="/admin/dashboard"]',
                    popover: {
                        title: 'Dashboard',
                        description: 'View system overview and statistics'
                    }
                },
                {
                    element: '[href="/admin/reports"]',
                    popover: {
                        title: 'Reports',
                        description: 'Generate various system reports'
                    }
                }
            ],
            hr_head: [
                {
                    element: '[href="/admin/members"]',
                    popover: {
                        title: 'Members',
                        description: 'Manage church members'
                    }
                },
                {
                    element: '[href="/admin/attendance"]',
                    popover: {
                        title: 'Attendance',
                        description: 'Track member attendance'
                    }
                }
            ],
            finance_head: [
                {
                    element: '[href="/admin/contributions"]',
                    popover: {
                        title: 'Contributions',
                        description: 'Track and manage contributions'
                    }
                },
                {
                    element: '[href="/admin/finance-reports"]',
                    popover: {
                        title: 'Finance Reports',
                        description: 'View financial reports and analytics'
                    }
                }
            ],
            nibret_hisab_head: [
                {
                    element: '[href="/admin/inventory"]',
                    popover: {
                        title: 'Inventory',
                        description: 'Manage church assets and inventory'
                    }
                },
                {
                    element: '[href="/admin/asset-tracking"]',
                    popover: {
                        title: 'Asset Tracking',
                        description: 'Track church assets and equipment'
                    }
                }
            ],
            inventory_staff: [
                {
                    element: '[href="/admin/inventory"]',
                    popover: {
                        title: 'Inventory Management',
                        description: 'Add and update inventory items'
                    }
                },
                {
                    element: '[href="/admin/stock-movements"]',
                    popover: {
                        title: 'Stock Movements',
                        description: 'Track inventory in/out movements'
                    }
                }
            ],
            education_head: [
                {
                    element: '[href="/admin/academic-years"]',
                    popover: {
                        title: 'Academic Years',
                        description: 'Manage academic years and terms'
                    }
                },
                {
                    element: '[href="/admin/classes"]',
                    popover: {
                        title: 'Classes',
                        description: 'Manage educational classes'
                    }
                },
                {
                    element: '[href="/admin/education-reports"]',
                    popover: {
                        title: 'Education Reports',
                        description: 'View attendance and performance reports'
                    }
                }
            ],
            education_monitor: [
                {
                    element: '[href="/admin/attendance"]',
                    popover: {
                        title: 'Attendance Tracking',
                        description: 'Mark student attendance'
                    }
                },
                {
                    element: '[href="/admin/student-progress"]',
                    popover: {
                        title: 'Student Progress',
                        description: 'Monitor student academic progress'
                    }
                }
            ],
            worship_monitor: [
                {
                    element: '[href="/admin/rehearsals"]',
                    popover: {
                        title: 'Rehearsals',
                        description: 'Schedule and manage worship rehearsals'
                    }
                },
                {
                    element: '[href="/admin/worship-schedule"]',
                    popover: {
                        title: 'Worship Schedule',
                        description: 'View and manage worship service schedule'
                    }
                }
            ],
            mezmur_head: [
                {
                    element: '[href="/admin/choir-members"]',
                    popover: {
                        title: 'Choir Members',
                        description: 'Manage choir membership and roles'
                    }
                },
                {
                    element: '[href="/admin/music-library"]',
                    popover: {
                        title: 'Music Library',
                        description: 'Manage songs and music resources'
                    }
                }
            ],
            av_head: [
                {
                    element: '[href="/admin/media"]',
                    popover: {
                        title: 'Media Management',
                        description: 'Upload and organize media files'
                    }
                },
                {
                    element: '[href="/admin/live-streaming"]',
                    popover: {
                        title: 'Live Streaming',
                        description: 'Configure and manage live streaming'
                    }
                }
            ],
            charity_head: [
                {
                    element: '[href="/admin/aid-programs"]',
                    popover: {
                        title: 'Aid Programs',
                        description: 'Manage charitable aid programs'
                    }
                },
                {
                    element: '[href="/admin/beneficiaries"]',
                    popover: {
                        title: 'Beneficiaries',
                        description: 'Manage aid beneficiaries and distributions'
                    }
                }
            ],
            tour_head: [
                {
                    element: '[href="/admin/tours"]',
                    popover: {
                        title: 'Tour Management',
                        description: 'Create and manage church tours'
                    }
                },
                {
                    element: '[href="/admin/tour-registrations"]',
                    popover: {
                        title: 'Tour Registrations',
                        description: 'Manage tour registrations and payments'
                    }
                }
            ],
            internal_relations_head: [
                {
                    element: '[href="/admin/communications"]',
                    popover: {
                        title: 'Communications',
                        description: 'Manage internal communications and announcements'
                    }
                },
                {
                    element: '[href="/admin/events"]',
                    popover: {
                        title: 'Events',
                        description: 'Organize church events and activities'
                    }
                }
            ],
            department_secretary: [
                {
                    element: '[href="/admin/department-documents"]',
                    popover: {
                        title: 'Department Documents',
                        description: 'Manage department-specific documents'
                    }
                },
                {
                    element: '[href="/admin/meeting-minutes"]',
                    popover: {
                        title: 'Meeting Minutes',
                        description: 'Record and manage meeting minutes'
                    }
                }
            ],
            staff: [
                {
                    element: '[href="/admin/dashboard"]',
                    popover: {
                        title: 'Dashboard',
                        description: 'View your personalized dashboard'
                    }
                },
                {
                    element: '[href="/admin/profile"]',
                    popover: {
                        title: 'Profile',
                        description: 'Update your personal information'
                    }
                }
            ]
        };

        return [...baseSteps, ...(roleSpecificSteps[role] || [])];
    }

    async restartTour() {
        try {
            await fetch('/api/tour/restart', { method: 'POST' });
            this.startTour();
        } catch (error) {
            console.error('Failed to restart tour:', error);
        }
    }

    async markTourCompleted() {
        try {
            await fetch('/api/tour/complete', { method: 'POST' });
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
