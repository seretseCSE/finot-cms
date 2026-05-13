<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">
                Auto-Purge Settings
            </h2>
            <div class="flex space-x-2">
                @foreach($this->getHeaderActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        </div>

        <!-- Current Settings Display -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Data Retention Settings -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Data Retention Settings</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-600">Error Logs Retention</span>
                        <span class="font-medium">{{ \App\Models\SiteSetting::get('error_logs_retention_days', 60) }} days</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-600">Security Audit Logs Retention</span>
                        <span class="font-medium">{{ \App\Models\SiteSetting::get('security_audit_retention_days', 30) }} days</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-600">Session Logs Retention</span>
                        <span class="font-medium">{{ \App\Models\SiteSetting::get('session_logs_retention_days', 90) }} days</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-600">Read Notifications Retention</span>
                        <span class="font-medium">{{ \App\Models\SiteSetting::get('read_notifications_retention_days', 90) }} days</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-gray-600">Media Files Retention</span>
                        <span class="font-medium">{{ \App\Models\SiteSetting::get('media_files_retention_years', 5) }} years</span>
                    </div>
                </div>
            </div>

            <!-- Schedule Settings -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Schedule Settings</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-600">Auto-Purge Status</span>
                        <span class="font-medium {{ \App\Models\SiteSetting::get('auto_purge_enabled', true) ? 'text-green-600' : 'text-red-600' }}">
                            {{ \App\Models\SiteSetting::get('auto_purge_enabled', true) ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-600">Purge Schedule</span>
                        <span class="font-medium">
                            {{ \App\Models\SiteSetting::get('purge_schedule', 'daily') == 'daily' ? 'Daily (2:00 AM)' :
                               (\App\Models\SiteSetting::get('purge_schedule', 'daily') == 'weekly' ? 'Weekly (Sunday 2:00 AM)' :
                               'Monthly (1st day 2:00 AM)') }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-600">Notify Before Purge</span>
                        <span class="font-medium {{ \App\Models\SiteSetting::get('notify_before_purge', true) ? 'text-green-600' : 'text-gray-500' }}">
                            {{ \App\Models\SiteSetting::get('notify_before_purge', true) ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-gray-600">Warning Days</span>
                        <span class="font-medium">{{ \App\Models\SiteSetting::get('purge_notification_days', 7) }} days</span>
                    </div>
                </div>
            </div>
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
