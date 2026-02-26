@php
    $sessionLifetime = config('session.lifetime', 30); // minutes
    $warningTime = 5; // Show warning 5 minutes before timeout
    $checkInterval = 30; // Check every 30 seconds
@endphp

<div x-data="sessionTimeoutManager({
        sessionLifetime: {{ $sessionLifetime }},
        warningTime: {{ $warningTime }},
        checkInterval: {{ $checkInterval }},
        lastActivity: '{{ now()->timestamp }}',
        isActive: false,
        warningShown: false,
        timeoutModal: false
    })" x-init="init()" class="hidden">
    
    <!-- Session Timeout Warning Modal -->
    <div x-show="timeoutModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        
        <div class="flex min-h-screen items-center justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Session Timeout Warning
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Your session will expire in <span x-text="remainingMinutes" class="font-semibold text-yellow-600"></span> minutes due to inactivity.
                                </p>
                                <p class="text-sm text-gray-500 mt-1">
                                    Click "Stay Logged In" to extend your session, or you will be automatically logged out.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" 
                            @click="extendSession()"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Stay Logged In
                    </button>
                    <button type="button" 
                            @click="logoutNow()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Log Out Now
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Session Expired Notification (shown after redirect) -->
    <div x-show="sessionExpired" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform translate-y-2"
         class="fixed top-4 right-4 z-50 max-w-sm w-full bg-yellow-50 border border-yellow-200 rounded-md shadow-lg p-4"
         style="display: none;">
        
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-yellow-800">
                    Session Expired
                </p>
                <p class="mt-1 text-sm text-yellow-700">
                    Your session has expired due to inactivity. Please log in again.
                </p>
            </div>
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button @click="sessionExpired = false" class="inline-flex bg-yellow-50 rounded-md p-1.5 text-yellow-500 hover:bg-yellow-100">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('sessionTimeoutManager', (initialData) => ({
        ...initialData,
        remainingMinutes: 0,
        sessionExpired: false,
        checkTimer: null,
        
        init() {
            // Only activate for authenticated users
            if (document.body.classList.contains('filament')) {
                this.isActive = true;
                this.startActivityChecking();
                this.setupActivityListeners();
            }
        },
        
        startActivityChecking() {
            if (this.checkTimer) {
                clearInterval(this.checkTimer);
            }
            
            this.checkTimer = setInterval(() => {
                this.checkSessionTimeout();
            }, this.checkInterval * 1000);
        },
        
        setupActivityListeners() {
            // Track user activity
            const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
            
            events.forEach(event => {
                document.addEventListener(event, () => {
                    this.updateLastActivity();
                }, true);
            });
        },
        
        updateLastActivity() {
            this.lastActivity = Math.floor(Date.now() / 1000);
            
            // Hide warning if shown
            if (this.timeoutModal) {
                this.timeoutModal = false;
                this.warningShown = false;
            }
        },
        
        checkSessionTimeout() {
            if (!this.isActive) return;
            
            const now = Math.floor(Date.now() / 1000);
            const inactiveMinutes = Math.floor((now - this.lastActivity) / 60);
            const remainingMinutes = this.sessionLifetime - inactiveMinutes;
            
            this.remainingMinutes = Math.max(0, remainingMinutes);
            
            // Show warning 5 minutes before timeout
            if (remainingMinutes <= this.warningTime && remainingMinutes > 0 && !this.warningShown) {
                this.showTimeoutWarning();
                this.warningShown = true;
            }
            
            // Auto-logout when session expires
            if (remainingMinutes <= 0) {
                this.handleSessionTimeout();
            }
        },
        
        showTimeoutWarning() {
            this.timeoutModal = true;
        },
        
        extendSession() {
            // Make a request to extend the session
            fetch('/api/session/extend', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            })
            .then(response => {
                if (response.ok) {
                    this.updateLastActivity();
                    this.timeoutModal = false;
                    this.warningShown = false;
                } else {
                    // If request fails, logout
                    this.logoutNow();
                }
            })
            .catch(() => {
                // If network error, logout
                this.logoutNow();
            });
        },
        
        logoutNow() {
            window.location.href = '/admin/logout';
        },
        
        handleSessionTimeout() {
            this.sessionExpired = true;
            this.timeoutModal = false;
            
            // Show notification for 5 seconds, then redirect
            setTimeout(() => {
                window.location.href = '/admin/login';
            }, 5000);
        },
        
        destroy() {
            if (this.checkTimer) {
                clearInterval(this.checkTimer);
            }
        }
    }));
});
</script>
