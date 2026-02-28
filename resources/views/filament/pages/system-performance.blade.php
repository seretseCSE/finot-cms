<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header with Refresh Button -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">
                System Performance Metrics
            </h2>
            {{ $this->getHeaderActions() }}
        </div>

        @if(isset($systemMetrics['error']))
            <!-- Error State -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <h3 class="text-lg font-semibold text-red-800">Error Loading Metrics</h3>
                        <p class="text-red-600 mt-2">{{ $systemMetrics['error'] }}</p>
                    </div>
                </div>
            </div>
        @else
            <!-- Metrics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                
                <!-- CPU Usage -->
                <div class="bg-white rounded-lg shadow p-6 border-l-4 {{ $systemMetrics['cpu']['color'] == 'danger' ? 'border-red-500' : ($systemMetrics['cpu']['color'] == 'warning' ? 'border-yellow-500' : 'border-green-500') }}">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">CPU Usage</h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-{{ $systemMetrics['cpu']['color'] }}-100 text-{{ $systemMetrics['cpu']['color'] }}-800">
                            {{ $systemMetrics['cpu']['status'] }}
                        </span>
                    </div>
                    <div class="text-3xl font-bold text-{{ $systemMetrics['cpu']['color'] }}-600">
                        {{ $systemMetrics['cpu']['percentage'] }}%
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        Current processor load
                    </div>
                </div>

                <!-- Memory Usage -->
                <div class="bg-white rounded-lg shadow p-6 border-l-4 {{ $systemMetrics['memory']['color'] == 'danger' ? 'border-red-500' : ($systemMetrics['memory']['color'] == 'warning' ? 'border-yellow-500' : 'border-green-500') }}">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Memory Usage</h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-{{ $systemMetrics['memory']['color'] }}-100 text-{{ $systemMetrics['memory']['color'] }}-800">
                            {{ $systemMetrics['memory']['status'] }}
                        </span>
                    </div>
                    <div class="text-3xl font-bold text-{{ $systemMetrics['memory']['color'] }}-600">
                        {{ $systemMetrics['memory']['percentage'] }}%
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        {{ $systemMetrics['memory']['used'] }} / {{ $systemMetrics['memory']['limit'] }}
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                        <div class="bg-{{ $systemMetrics['memory']['color'] }}-500 h-2 rounded-full" style="width: {{ $systemMetrics['memory']['percentage'] }}%"></div>
                    </div>
                </div>

                <!-- Database Performance -->
                <div class="bg-white rounded-lg shadow p-6 border-l-4 {{ $systemMetrics['database']['color'] == 'danger' ? 'border-red-500' : ($systemMetrics['database']['color'] == 'warning' ? 'border-yellow-500' : 'border-green-500') }}">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Database Performance</h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-{{ $systemMetrics['database']['color'] }}-100 text-{{ $systemMetrics['database']['color'] }}-800">
                            {{ $systemMetrics['database']['status'] }}
                        </span>
                    </div>
                    <div class="text-3xl font-bold text-{{ $systemMetrics['database']['color'] }}-600">
                        {{ $systemMetrics['database']['query_time_ms'] }}ms
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        Query response time
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Database:</span>
                            <span class="font-medium">{{ $systemMetrics['database']['database_name'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Tables:</span>
                            <span class="font-medium">{{ $systemMetrics['database']['table_count'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Connection:</span>
                            <span class="font-medium text-{{ $systemMetrics['database']['connection_status'] == 'connected' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $systemMetrics['database']['connection_status'] }}
                            </span>
                        </div>
                        @if($systemMetrics['database']['slow_queries_count'] > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Slow Queries:</span>
                                <span class="font-medium text-yellow-600">{{ $systemMetrics['database']['slow_queries_count'] }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Storage Usage -->
                <div class="bg-white rounded-lg shadow p-6 border-l-4 {{ $systemMetrics['storage']['color'] == 'danger' ? 'border-red-500' : ($systemMetrics['storage']['color'] == 'warning' ? 'border-yellow-500' : 'border-green-500') }}">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Storage Usage</h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-{{ $systemMetrics['storage']['color'] }}-100 text-{{ $systemMetrics['storage']['color'] }}-800">
                            {{ $systemMetrics['storage']['status'] }}
                        </span>
                    </div>
                    <div class="text-3xl font-bold text-{{ $systemMetrics['storage']['color'] }}-600">
                        {{ $systemMetrics['storage']['percentage'] }}%
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        {{ $systemMetrics['storage']['used'] }} / {{ $systemMetrics['storage']['total'] }}
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                        <div class="bg-{{ $systemMetrics['storage']['color'] }}-500 h-2 rounded-full" style="width: {{ $systemMetrics['storage']['percentage'] }}%"></div>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Logs:</span>
                                <span class="font-medium">{{ $systemMetrics['storage']['directories']['logs'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Backups:</span>
                                <span class="font-medium">{{ $systemMetrics['storage']['directories']['backups'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Uploads:</span>
                                <span class="font-medium">{{ $systemMetrics['storage']['directories']['uploads'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Sessions -->
                <div class="bg-white rounded-lg shadow p-6 border-l-4 {{ $systemMetrics['sessions']['color'] == 'danger' ? 'border-red-500' : ($systemMetrics['sessions']['color'] == 'warning' ? 'border-yellow-500' : 'border-green-500') }}">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Active Sessions</h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-{{ $systemMetrics['sessions']['color'] }}-100 text-{{ $systemMetrics['sessions']['color'] }}-800">
                            {{ $systemMetrics['sessions']['status'] }}
                        </span>
                    </div>
                    <div class="text-3xl font-bold text-{{ $systemMetrics['sessions']['color'] }}-600">
                        {{ $systemMetrics['sessions']['active_last_30min'] }}
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        Active in last 30 minutes
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total Sessions:</span>
                            <span class="font-medium">{{ $systemMetrics['sessions']['total_sessions'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Activity Rate:</span>
                            <span class="font-medium">{{ $systemMetrics['sessions']['percentage'] }}%</span>
                        </div>
                    </div>
                </div>

                <!-- Cache Performance -->
                <div class="bg-white rounded-lg shadow p-6 border-l-4 {{ $systemMetrics['cache']['color'] == 'danger' ? 'border-red-500' : ($systemMetrics['cache']['color'] == 'warning' ? 'border-yellow-500' : 'border-green-500') }}">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Cache Performance</h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-{{ $systemMetrics['cache']['color'] }}-100 text-{{ $systemMetrics['cache']['color'] }}-800">
                            {{ $systemMetrics['cache']['status'] }}
                        </span>
                    </div>
                    <div class="text-3xl font-bold text-{{ $systemMetrics['cache']['color'] }}-600">
                        {{ $systemMetrics['cache']['hit_ratio'] }}%
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        Cache hit ratio
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                        <div class="bg-{{ $systemMetrics['cache']['color'] }}-500 h-2 rounded-full" style="width: {{ $systemMetrics['cache']['hit_ratio'] }}%"></div>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Cache Size:</span>
                                <span class="font-medium">{{ $systemMetrics['cache']['size'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Total Requests:</span>
                                <span class="font-medium">{{ $systemMetrics['cache']['total_requests'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Hits:</span>
                                <span class="font-medium text-green-600">{{ $systemMetrics['cache']['hits'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Misses:</span>
                                <span class="font-medium text-red-600">{{ $systemMetrics['cache']['misses'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Uptime -->
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">System Uptime</h3>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Running
                        </span>
                    </div>
                    <div class="text-lg font-bold text-green-600">
                        {{ $systemMetrics['uptime']['text'] }}
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        System running time
                    </div>
                </div>
            </div>

            <!-- Alert Section -->
            <div class="mt-8 space-y-4">
                @if($systemMetrics['cpu']['status'] == 'critical' || $systemMetrics['memory']['status'] == 'critical' || $systemMetrics['storage']['status'] == 'critical')
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                <h4 class="text-sm font-semibold text-red-800">Critical System Resources</h4>
                                <p class="text-red-600 text-sm mt-1">
                                    Some system resources are critically high. Please investigate immediately.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                @if($systemMetrics['database']['status'] == 'slow')
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                <h4 class="text-sm font-semibold text-yellow-800">Slow Database Performance</h4>
                                <p class="text-yellow-600 text-sm mt-1">
                                    Database queries are running slow. Consider optimization.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Last Updated -->
            <div class="mt-8 text-center text-sm text-gray-500">
                Last updated: {{ $systemMetrics['last_updated'] }}
            </div>
        @endif
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setInterval(() => {
            window.location.reload();
        }, 30000);
    </script>
</x-filament-panels::page>
