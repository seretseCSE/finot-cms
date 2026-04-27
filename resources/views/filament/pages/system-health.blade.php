<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Alert Banners -->
        @if(!empty($this->getSystemAlerts()))
            <div class="space-y-2">
                @foreach($this->getSystemAlerts() as $alert)
                    <div class="rounded-lg border p-4 @if($alert['type'] === 'critical') bg-red-50 border-red-200 @elseif($alert['type'] === 'warning') bg-yellow-50 border-yellow-200 @else bg-blue-50 border-blue-200 @endif">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                @if($alert['type'] === 'critical')
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                @elseif($alert['type'] === 'warning')
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                @else
                                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium @if($alert['type'] === 'critical') text-red-800 @elseif($alert['type'] === 'warning') text-yellow-800 @else text-blue-800 @endif">
                                    {{ $alert['title'] }}
                                </h3>
                                <div class="mt-2 text-sm @if($alert['type'] === 'critical') text-red-700 @elseif($alert['type'] === 'warning') text-yellow-700 @else text-blue-700 @endif">
                                    {{ $alert['message'] }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- System Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Server Uptime -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Server Uptime</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $this->getServerInfo()['uptime_text'] ?? 'N/A' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Storage Usage -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-{{ $this->getStorageColor() }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Storage Usage</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $this->getStorageInfo()['percentage'] ?? 0 }}%</dd>
                            <div class="mt-2">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-{{ $this->getStorageColor() }}-500 h-2 rounded-full" style="width: {{ $this->getStorageInfo()['percentage'] ?? 0 }}%"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">{{ $this->getStorageInfo()['used'] ?? 'N/A' }} / {{ $this->getStorageInfo()['total'] ?? 'N/A' }}</p>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Database Query Time -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-{{ $this->getQueryTimeColor() }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Avg Query Time</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $this->getDatabaseInfo()['avg_query_time'] ?? 'N/A' }}</dd>
                            <p class="text-xs text-gray-500 mt-1">{{ $this->getDatabaseInfo()['connections'] ?? 0 }} connections</p>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Active Users -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Active Users</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $this->getUserInfo()['active_users'] ?? 0 }}</dd>
                            <p class="text-xs text-gray-500 mt-1">{{ $this->getUserInfo()['online_users'] ?? 0 }} online</p>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Information Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Server Details -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Server Information</h3>
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Load Average (1/5/15 min)</span>
                        <span class="text-sm font-medium">
                            {{ $this->getServerInfo()['load_average']['1min'] ?? 'N/A' }} / 
                            {{ $this->getServerInfo()['load_average']['5min'] ?? 'N/A' }} / 
                            {{ $this->getServerInfo()['load_average']['15min'] ?? 'N/A' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Memory Usage</span>
                        <span class="text-sm font-medium text-{{ $this->getMemoryColor() }}-600">
                            {{ $this->getServerInfo()['memory_usage']['percentage'] ?? 0 }}%
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">CPU Usage</span>
                        <span class="text-sm font-medium text-{{ $this->getCpuColor() }}-600">
                            {{ $this->getServerInfo()['cpu_usage']['percentage'] ?? 0 }}%
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Memory Peak</span>
                        <span class="text-sm font-medium">{{ $this->getServerInfo()['memory_usage']['peak'] ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>

            <!-- Database Details -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Database Information</h3>
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Database Size</span>
                        <span class="text-sm font-medium">{{ $this->getDatabaseInfo()['size'] ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Slow Queries</span>
                        <span class="text-sm font-medium">{{ $this->getDatabaseInfo()['slow_queries'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Active Connections</span>
                        <span class="text-sm font-medium">{{ $this->getDatabaseInfo()['connections'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- Error Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Error Information</h3>
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Error Rate (24h)</span>
                        <span class="text-sm font-medium text-{{ $this->getErrorColor() }}-600">
                            {{ $this->getErrorInfo()['error_rate_24h'] ?? 0 }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Critical Errors</span>
                        <span class="text-sm font-medium">{{ $this->getErrorInfo()['critical_errors'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Recent Exceptions</span>
                        <span class="text-sm font-medium">{{ $this->getErrorInfo()['recent_exceptions'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">System Information</h3>
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">PHP Version</span>
                        <span class="text-sm font-medium">{{ $this->getSystemInfo()['php_version'] ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Laravel Version</span>
                        <span class="text-sm font-medium">{{ $this->getSystemInfo()['laravel_version'] ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Environment</span>
                        <span class="text-sm font-medium">{{ $this->getSystemInfo()['environment'] ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Debug Mode</span>
                        <span class="text-sm font-medium @if($this->getSystemInfo()['debug_mode']) text-red-600 @else text-green-600 @endif">
                            {{ $this->getSystemInfo()['debug_mode'] ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Cache Driver</span>
                        <span class="text-sm font-medium">{{ $this->getSystemInfo()['cache_driver'] ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Session Driver</span>
                        <span class="text-sm font-medium">{{ $this->getSystemInfo()['session_driver'] ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Livewire Polling -->
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ url, options }) => {
                // Auto-refresh every 30 seconds
                if (url.includes('system-health')) {
                    setTimeout(() => {
                        @this.refreshHealthData();
                    }, 30000);
                }
            });
        });
    </script>
</x-filament-panels::page>
