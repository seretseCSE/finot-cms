<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">
                Auto-Purge Settings
            </h2>
            {{ $this->getHeaderActions() }}
        </div>

        <!-- Form -->
        <form wire:submit="saveSettings">
            <div class="space-y-6">
                
                <!-- Data Retention Settings -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Data Retention Settings</h3>
                    <p class="text-sm text-gray-600 mb-6">
                        Configure how long to keep different types of data before automatic deletion
                    </p>
                    
                    <!-- Auto-Purge Toggle -->
                    <div class="mb-6">
                        {{ $this->getFormComponent('auto_purge_enabled') }}
                    </div>
                    
                    <!-- Purge Schedule -->
                    <div class="mb-6">
                        {{ $this->getFormComponent('purge_schedule') }}
                    </div>
                    
                    <!-- Retention Periods Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Error Logs & Security Audit -->
                        <div class="space-y-4">
                            {{ $this->getFormComponent('error_logs_retention_days') }}
                            {{ $this->getFormComponent('security_audit_retention_days') }}
                        </div>
                        
                        <!-- Session Logs & Read Notifications -->
                        <div class="space-y-4">
                            {{ $this->getFormComponent('session_logs_retention_days') }}
                            {{ $this->getFormComponent('read_notifications_retention_days') }}
                        </div>
                    </div>
                </div>

                <!-- Media & Files Settings -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Media & Files Settings</h3>
                    <p class="text-sm text-gray-600 mb-6">
                        Configure retention for uploaded files and media
                    </p>
                    
                    {{ $this->getFormComponent('media_files_retention_years') }}
                </div>

                <!-- Purge Notifications -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Purge Notifications</h3>
                    <p class="text-sm text-gray-600 mb-6">
                        Configure warnings before automatic deletion
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{ $this->getFormComponent('notify_before_purge') }}
                        <div wire:ignore>
                            {{ $this->getFormComponent('purge_notification_days') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-4 mt-8">
                {{ $this->getFormActions() }}
            </div>
        </form>
    </div>

    <!-- Information Panel -->
    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-900 mb-4">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1 4h1v4h1m-6 4h1v4h1M3 12h1v4h1m6 4h1v4h1"/>
            </svg>
            Information
        </h3>
        
        <div class="space-y-3 text-sm text-blue-800">
            <div>
                <strong>Auto-Purge:</strong> Automatically deletes old data based on configured retention periods.
            </div>
            <div>
                <strong>Schedule:</strong> Daily at 2:00 AM, Weekly on Sunday 2:00 AM, or Monthly on 1st day 2:00 AM.
            </div>
            <div>
                <strong>Warning:</strong> When enabled, notifications are sent before purging (configurable days).
            </div>
            <div>
                <strong>Test First:</strong> Use the "Test Purge Configuration" to see what would be deleted before running actual purge.
            </div>
            <div>
                <strong>Manual Purge:</strong> You can run immediate purge using "Run Manual Purge Now" button.
            </div>
        </div>
    </div>
</x-filament-panels::page>
