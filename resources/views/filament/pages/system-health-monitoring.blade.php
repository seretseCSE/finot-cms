
<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Health Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $healthData = $this->getSystemHealthData();
            @endphp

            <!-- Storage Usage -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Storage Usage</p>
                        <p class="text-2xl font-bold {{ $healthData['storage_usage']['percentage'] > 40 ? 'text-orange-600' : 'text-green-600' }}">
                            {{ $healthData['storage_usage']['percentage'] }}%
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $healthData['storage_usage']['used'] }} / {{ $healthData['storage_usage']['total'] }}
                        </p>
                    </div>
                    <div class="{{ $healthData['storage_usage']['percentage'] > 40 ? 'text-orange-500' : 'text-green-500' }}">
                        <x-filament::icon
                            icon="{{ $healthData['storage_usage']['percentage'] > 40 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-server' }}"
                            class="h-8 w-8"
                        />
                    </div>
                </div>
            </div>

            <!-- Database Performance -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Database Query Time</p>
                        <p class="text-2xl font-bold {{ $healthData['db_query_time'] > 2 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $healthData['db_query_time'] }}s
                        </p>
                        <p class="text-xs text-gray-500">
                            Average response time
                        </p>
                    </div>
                    <div class="{{ $healthData['db_query_time'] > 2 ? 'text-red-500' : 'text-green-500' }}">
                        <x-filament::icon
                            icon="{{ $healthData['db_query_time'] > 2 ? 'heroicon-o-x-circle' : 'heroicon-o-database' }}"
                            class="h-8 w-8"
                        />
                    </div>
                </div>
            </div>

            <!-- Error Rate -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Error Rate</p>
                        <p class="text-2xl font-bold {{ $healthData['error_rate'] > 10 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $healthData['error_rate'] }}/hr
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $healthData['failed_logins'] }} failed logins (24h)
                        </p>
                    </div>
                    <div class="{{ $healthData['error_rate'] > 10 ? 'text-red-500' : 'text-green-500' }}">
                        <x-filament::icon
                            icon="{{ $healthData['error_rate'] > 10 ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle' }}"
                            class="h-8 w-8"
                        />
                    </div>
                </div>
            </div>

            <!-- Active Sessions -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Sessions</p>
                        <p class="text-2xl font-bold text-blue-600">
                            {{ $healthData['active_sessions'] }}
                        </p>
                        <p class="text-xs text-gray-500">
                            Currently logged in
                        </p>
                    </div>
                    <div class="text-blue-500">
                        <x-filament::icon
                            icon="heroicon-o-users"
                            class="h-8 w-8"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status Details -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">System Performance Metrics</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Memory Usage -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Resource Usage</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="text-sm font-medium">Memory Usage</span>
                                <span class="text-sm">{{ $healthData['memory_usage'] }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="text-sm font-medium">CPU Usage</span>
                                <span class="text-sm">{{ $healthData['cpu_usage'] }}%</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="text-sm font-medium">Server Uptime</span>
                                <span class="text-sm">{{ $healthData['uptime'] }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Indicators -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Performance Indicators</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="text-sm font-medium">Database Response</span>
                                <span class="text-sm {{ $healthData['db_query_time'] > 2 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $healthData['db_query_time'] }}s
                                </span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="text-sm font-medium">Error Rate</span>
                                <span class="text-sm {{ $healthData['error_rate'] > 10 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $healthData['error_rate'] }}/hr
                                </span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="text-sm font-medium">Failed Logins</span>
                                <span class="text-sm {{ $healthData['failed_logins'] > 5 ? 'text-orange-600' : 'text-green-600' }}">
                                    {{ $healthData['failed_logins'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Health Alerts -->
        @if($healthData['storage_usage']['percentage'] > 40 || $healthData['error_rate'] > 10 || $healthData['db_query_time'] > 2)
            <div class="space-y-3">
                @if($healthData['storage_usage']['percentage'] > 40)
                    <div class="bg-{{ $healthData['storage_usage']['percentage'] > 80 ? 'red' : 'orange' }}-50 dark:bg-{{ $healthData['storage_usage']['percentage'] > 80 ? 'red' : 'orange' }}-900 border border-{{ $healthData['storage_usage']['percentage'] > 80 ? 'red' : 'orange' }}-200 dark:border-{{ $healthData['storage_usage']['percentage'] > 80 ? 'red' : 'orange' }}-700 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 text-{{ $healthData['storage_usage']['percentage'] > 80 ? 'red' : 'orange' }}-400" />
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-{{ $healthData['storage_usage']['percentage'] > 80 ? 'red' : 'orange' }}-800 dark:text-{{ $healthData['storage_usage']['percentage'] > 80 ? 'red' : 'orange' }}-200">
                                    High Storage Usage
                                </h3>
                                <div class="mt-2 text-sm text-{{ $healthData['storage_usage']['percentage'] > 80 ? 'red' : 'orange' }}-700 dark:text-{{ $healthData['storage_usage']['percentage'] > 80 ? 'red' : 'orange' }}-300">
                                    <p>Storage usage is at {{ $healthData['storage_usage']['percentage'] }}%. Consider cleaning up old files or expanding storage.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($healthData['error_rate'] > 10)
                    <div class="bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5 text-red-400" />
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                    High Error Rate
                                </h3>
                                <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                    <p>Error rate is {{ $healthData['error_rate'] }} per hour. Check error logs for details.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($healthData['db_query_time'] > 2)
                    <div class="bg-orange-50 dark:bg-orange-900 border border-orange-200 dark:border-orange-700 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 text-orange-400" />
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-orange-800 dark:text-orange-200">
                                    Slow Database Response
                                </h3>
                                <div class="mt-2 text-sm text-orange-700 dark:text-orange-300">
                                    <p>Database query time is {{ $healthData['db_query_time'] }}s. Consider optimizing queries or checking server load.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-green-400" />
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800 dark:text-green-200">
                            System Health is Good
                        </h3>
                        <div class="mt-2 text-sm text-green-700 dark:text-green-300">
                            <p>All system metrics are within normal ranges. The system is operating optimally.</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
