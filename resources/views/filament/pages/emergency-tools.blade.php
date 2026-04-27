<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Warning Banner -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5H18c1.546 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-medium text-red-800">⚠️ Emergency Tools</h3>
                    <p class="mt-2 text-sm text-red-700">
                        These tools perform critical system operations. Use with extreme caution. All actions are logged and cannot be undone.
                    </p>
                </div>
            </div>
        </div>

        <!-- Emergency Tools Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- Force Logout All Users -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v4" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-gray-900">Force Logout All Users</h3>
                        <p class="text-sm text-gray-600">Terminate all active user sessions</p>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500 mb-3">
                        This will immediately log out all users including yourself. They will need to log in again.
                    </p>
                    <button 
                        wire:click="forceLogoutAllUsers"
                        wire:confirm="Are you sure? This will log out ALL users immediately."
                        class="w-full bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition-colors text-sm font-medium"
                    >
                        Force Logout All Users
                    </button>
                </div>
            </div>

            <!-- Clear All Caches -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-gray-900">Clear All Caches</h3>
                        <p class="text-sm text-gray-600">Clear application and system caches</p>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500 mb-3">
                        Clears application, configuration, route, view caches and compiled files.
                    </p>
                    <button 
                        wire:click="clearAllCaches"
                        wire:confirm="Are you sure? This will clear all system caches and may temporarily slow down the application."
                        class="w-full bg-orange-600 text-white px-4 py-2 rounded-md hover:bg-orange-700 transition-colors text-sm font-medium"
                    >
                        Clear All Caches
                    </button>
                </div>
            </div>

            <!-- Database Optimization -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-gray-900">Database Optimization</h3>
                        <p class="text-sm text-gray-600">Optimize database and rebuild caches</p>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500 mb-3">
                        Optimizes database tables and rebuilds all caches for better performance.
                    </p>
                    <button 
                        wire:click="runDatabaseOptimization"
                        wire:confirm="Are you sure? This will optimize the database and rebuild caches."
                        class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium"
                    >
                        Run Database Optimization
                    </button>
                </div>
            </div>

            <!-- Purge Old Logs -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-gray-900">Purge Old Logs</h3>
                        <p class="text-sm text-gray-600">Delete log files older than specified date</p>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500 mb-3">
                        Permanently delete log files older than selected date to free up disk space.
                    </p>
                    <div class="space-y-3">
                        <input 
                            type="date" 
                            wire:model="purgeDate"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                            max="{{ now()->subDays(1)->format('Y-m-d') }}"
                        />
                        <button 
                            wire:click="purgeOldLogs(purgeDate)"
                            wire:confirm="Are you sure? This will permanently delete all log files older than the selected date."
                            class="w-full bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 transition-colors text-sm font-medium"
                        >
                            Purge Old Logs
                        </button>
                    </div>
                </div>
            </div>

            <!-- Toggle Maintenance Mode -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c-.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-gray-900">Maintenance Mode</h3>
                        <p class="text-sm text-gray-600">Enable/disable application maintenance</p>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500 mb-3">
                        Enable maintenance mode to show a maintenance page to all visitors.
                    </p>
                    <div class="space-y-2">
                        <button 
                            wire:click="toggleMaintenanceMode(true)"
                            wire:confirm="Are you sure? This will enable maintenance mode and block all user access."
                            class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition-colors text-sm font-medium"
                        >
                            Enable Maintenance Mode
                        </button>
                        <button 
                            wire:click="toggleMaintenanceMode(false)"
                            wire:confirm="Are you sure? This will disable maintenance mode and restore normal access."
                            class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors text-sm font-medium"
                        >
                            Disable Maintenance Mode
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-gray-900">System Status</h3>
                        <p class="text-sm text-gray-600">Check system health status</p>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-gray-500 mb-3">
                        Check the current status of system components and services.
                    </p>
                    <button 
                        wire:click="checkSystemStatus"
                        class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors text-sm font-medium"
                    >
                        Check System Status
                    </button>
                </div>
            </div>

        </div>

        <!-- Recent Activity Log -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Emergency Actions</h3>
            <div class="text-sm text-gray-600">
                <p>All emergency actions are logged in the activity log for audit purposes.</p>
                <p class="mt-2">Recent actions include:</p>
                <ul class="mt-2 space-y-1">
                    <li>• Force logout all users</li>
                    <li>• Clear all caches</li>
                    <li>• Database optimization</li>
                    <li>• Purge old logs</li>
                    <li>• Maintenance mode toggle</li>
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page>
