<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Custom Subtitle -->
        <div class="-mt-4 mb-2">
            <p class="text-gray-600">Real-time system monitoring and analytics</p>
        </div>

        <!-- System Health Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            @php
                $healthStats = $this->getSystemHealthStats();
            @endphp
            
            <!-- Server Uptime -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Server Uptime</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $healthStats['uptime']['formatted'] }}</p>
                        <p class="text-xs text-gray-500">Load: {{ number_format($healthStats['uptime']['load_average'][0], 2) }}</p>
                    </div>
                </div>
            </div>

            <!-- Storage Usage -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 @if($healthStats['storage_usage']['status'] == 'critical') border-red-500 @elseif($healthStats['storage_usage']['status'] == 'warning') border-yellow-500 @else border-green-500 @endif">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 @if($healthStats['storage_usage']['status'] == 'critical') text-red-500 @elseif($healthStats['storage_usage']['status'] == 'warning') text-yellow-500 @else text-green-500 @endif" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Storage Usage</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $healthStats['storage_usage']['percentage'] }}%</p>
                        <p class="text-xs text-gray-500">{{ $healthStats['storage_usage']['used'] }} / {{ $healthStats['storage_usage']['total'] }}</p>
                    </div>
                </div>
            </div>

            <!-- Database Query Time -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">DB Query Time</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $healthStats['db_query_time'] }}ms</p>
                        <p class="text-xs text-gray-500">Average response</p>
                    </div>
                </div>
            </div>

            <!-- Active Sessions -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-indigo-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Sessions</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $healthStats['active_sessions'] }}</p>
                        <p class="text-xs text-gray-500">Currently logged in</p>
                    </div>
                </div>
            </div>

            <!-- Error Rate -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 @if($healthStats['error_rate']['status'] == 'critical') border-red-500 @elseif($healthStats['error_rate']['status'] == 'warning') border-yellow-500 @else border-green-500 @endif">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 @if($healthStats['error_rate']['status'] == 'critical') text-red-500 @elseif($healthStats['error_rate']['status'] == 'warning') text-yellow-500 @else text-green-500 @endif" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Error Rate (24h)</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $healthStats['error_rate']['rate'] }}%</p>
                        <p class="text-xs text-gray-500">{{ $healthStats['error_rate']['error_logs'] }} / {{ $healthStats['error_rate']['total_logs'] }}</p>
                    </div>
                </div>
            </div>

            <!-- Failed Logins -->
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Failed Logins</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $healthStats['failed_logins'] }}</p>
                        <p class="text-xs text-gray-500">Total attempts</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Overview Stats -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">System Overview</h3>
            </div>
            <div class="p-6">
                @php
                    $overviewStats = $this->getSystemOverviewStats();
                @endphp
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Members -->
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600">{{ number_format($overviewStats['total_members']) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Total Members</div>
                    </div>

                    <!-- Contributions -->
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600">{{ number_format($overviewStats['contributions_this_year'], 2) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Contributions This Year (ETB)</div>
                    </div>

                    <!-- Active Tours -->
                    <div class="text-center">
                        <div class="text-3xl font-bold text-purple-600">{{ number_format($overviewStats['active_tours']) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Active Tours</div>
                    </div>

                    <!-- Total Users -->
                    <div class="text-center">
                        <div class="text-3xl font-bold text-indigo-600">{{ number_format($overviewStats['total_users']) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Total Users</div>
                    </div>

                    <!-- Members -->
                    <div class="text-center">
                        <div class="text-3xl font-bold text-pink-600">{{ number_format($overviewStats['total_members']) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Members</div>
                    </div>

                    <!-- Teachers -->
                    <div class="text-center">
                        <div class="text-3xl font-bold text-yellow-600">{{ number_format($overviewStats['teachers']) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Teachers</div>
                    </div>

                    <!-- Parents -->
                    <div class="text-center">
                        <div class="text-3xl font-bold text-orange-600">{{ number_format($overviewStats['parents']) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Parents</div>
                    </div>

                    <!-- Departments -->
                    <div class="text-center">
                        <div class="text-3xl font-bold text-teal-600">{{ number_format($overviewStats['departments']) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Departments</div>
                    </div>
                </div>

                <!-- Additional Stats Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8 pt-6 border-t border-gray-200">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ number_format($overviewStats['enrollments_this_year']) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Enrollments This Year</div>
                    </div>

                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ number_format($overviewStats['academic_years']) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Academic Years</div>
                    </div>

                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ number_format($overviewStats['attendance_sessions_today']) }}</div>
                        <div class="text-sm text-gray-600 mt-1">Attendance Sessions Today</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- User Registrations Chart -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">User Registrations (Last 30 Days)</h3>
                </div>
                <div class="p-6">
                    @php
                        $chartData = $this->getChartData();
                        $userRegs = $chartData['user_registrations'];
                    @endphp
                    <div class="h-64">
                        <canvas id="userRegistrationsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Contributions Chart -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Contributions (Last 30 Days)</h3>
                </div>
                <div class="p-6">
                    @php
                        $contributions = $chartData['contributions'];
                    @endphp
                    <div class="h-64">
                        <canvas id="contributionsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Logs Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Recent Error Logs (Last 100 Entries)</h3>
                <span class="text-sm text-gray-500">Last 2 months retention</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Context</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @php
                            $errorLogs = $this->getErrorLogs();
                        @endphp
                        @forelse($errorLogs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $log['timestamp'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($log['level'] == 'CRITICAL') bg-red-100 text-red-800
                                        @elseif($log['level'] == 'ERROR') bg-red-100 text-red-800
                                        @elseif($log['level'] == 'WARNING') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ $log['level'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs truncate">{{ $log['message'] }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div class="max-w-xs truncate font-mono text-xs">{{ $log['context'] }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No error logs found in the last 2 months.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Chart.js Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // User Registrations Chart
        const userRegsCtx = document.getElementById('userRegistrationsChart').getContext('2d');
        new Chart(userRegsCtx, {
            type: 'line',
            data: {
                labels: @json($userRegs['labels']),
                datasets: [{
                    label: 'New Users',
                    data: @json($userRegs['data']),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Contributions Chart
        const contributionsCtx = document.getElementById('contributionsChart').getContext('2d');
        new Chart(contributionsCtx, {
            type: 'bar',
            data: {
                labels: @json($contributions['labels']),
                datasets: [{
                    label: 'Contributions (ETB)',
                    data: @json($contributions['data']),
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</x-filament-panels::page>
