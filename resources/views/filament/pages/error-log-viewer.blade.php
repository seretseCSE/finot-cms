<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Error Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @php
                $stats = $this->getRecentErrorStats();
            @endphp
            
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Last 24 Hours</p>
                        <p class="text-2xl font-bold {{ $stats['last_24h'] > 10 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $stats['last_24h'] }}
                        </p>
                        <p class="text-xs text-gray-500">errors logged</p>
                    </div>
                    <div class="{{ $stats['last_24h'] > 10 ? 'text-red-500' : 'text-green-500' }}">
                        <x-filament::icon 
                            icon="{{ $stats['last_24h'] > 10 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle' }}"
                            class="h-8 w-8"
                        />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Last 7 Days</p>
                        <p class="text-2xl font-bold text-blue-600">
                            {{ $stats['last_week'] }}
                        </p>
                        <p class="text-xs text-gray-500">total errors</p>
                    </div>
                    <div class="text-blue-500">
                        <x-filament::icon 
                            icon="heroicon-o-calendar-days"
                            class="h-8 w-8"
                        />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Critical (24h)</p>
                        <p class="text-2xl font-bold {{ $stats['critical_24h'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $stats['critical_24h'] }}
                        </p>
                        <p class="text-xs text-gray-500">critical errors</p>
                    </div>
                    <div class="{{ $stats['critical_24h'] > 0 ? 'text-red-500' : 'text-green-500' }}">
                        <x-filament::icon 
                            icon="{{ $stats['critical_24h'] > 0 ? 'heroicon-o-x-circle' : 'heroicon-o-shield-check' }}"
                            class="h-8 w-8"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Error Logs -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Error Logs</h3>
                <p class="text-sm text-gray-500">Last 100 error entries (2-month retention)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Level
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Message
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                File
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                User
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Time
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @php
                            $errorLogs = $this->getErrorLogs();
                        @endphp
                        @forelse($errorLogs as $error)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $this->getErrorLevelColor($error['level']) }}-100 text-{{ $this->getErrorLevelColor($error['level']) }}-800">
                                        {{ strtoupper($error['level']) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        {{ Str::limit($error['message'], 100) }}
                                        @if(strlen($error['message']) > 100)
                                            <button class="text-blue-600 hover:text-blue-800 text-xs ml-2" 
                                                    onclick="this.parentElement.nextElementSibling.classList.toggle('hidden')">
                                                Show more
                                            </button>
                                        @endif
                                    </div>
                                    @if(strlen($error['message']) > 100)
                                        <div class="hidden text-sm text-gray-600 dark:text-gray-300 mt-1">
                                            {{ $error['message'] }}
                                            <button class="text-blue-600 hover:text-blue-800 text-xs" 
                                                    onclick="this.parentElement.classList.add('hidden')">
                                                Show less
                                            </button>
                                        </div>
                                    @endif
                                    @if($error['exception'])
                                        <div class="text-xs text-red-600 dark:text-red-400 mt-1">
                                            Exception: {{ Str::limit($error['exception'], 150) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if($error['file'])
                                        {{ basename($error['file']) }}
                                        @if($error['line'])
                                            :{{ $error['line'] }}
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    @if($error['user_id'])
                                        <span class="text-blue-600">User {{ $error['user_id'] }}</span>
                                    @else
                                        <span class="text-gray-400">Guest</span>
                                    @endif
                                    @if($error['ip_address'])
                                        <div class="text-xs">{{ $error['ip_address'] }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $error['created_at']->format('M j, Y H:i:s') }}</div>
                                    @if($error['method'] && $error['url'])
                                        <div class="text-xs">{{ $error['method'] }} {{ Str::limit($error['url'], 50) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center">
                                        <x-filament::icon icon="heroicon-o-bug-ant" class="h-12 w-12 text-gray-400 mb-3" />
                                        <p class="text-lg font-medium">No error logs found</p>
                                        <p class="text-sm">System is running smoothly!</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Log Retention Notice -->
        <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 text-blue-400" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        Log Retention Policy
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        <p>Error logs are automatically retained for 2 months. Use the "Clear Old Logs" button to manually remove logs older than 2 months.</p>
                        <p class="mt-1">Critical errors trigger immediate in-app notifications to Super Admin users.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
