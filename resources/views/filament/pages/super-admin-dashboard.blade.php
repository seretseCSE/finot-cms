<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $stats = $this->getSystemStats();
            $activity = $this->getRecentActivity();
            $deptStats = $this->getDepartmentStats();
        @endphp

        <!-- Key System Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Users -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Users</p>
                        <p class="text-2xl font-bold text-blue-600">{{ $stats['users']['total'] }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $stats['users']['active'] }} active • 
                            {{ $stats['users']['admin'] }} admin • 
                            {{ $stats['users']['superadmin'] }} superadmin
                        </p>
                    </div>
                    <div class="text-blue-500">
                        <x-filament::icon icon="heroicon-o-users" class="h-8 w-8" />
                    </div>
                </div>
            </div>

            <!-- Members -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Church Members</p>
                        <p class="text-2xl font-bold text-green-600">{{ $stats['members']['total'] }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $stats['members']['active'] }} active • 
                            {{ $stats['members']['new_this_month'] }} new this month
                        </p>
                    </div>
                    <div class="text-green-500">
                        <x-filament::icon icon="heroicon-o-user-group" class="h-8 w-8" />
                    </div>
                </div>
            </div>

            <!-- Financial -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">This Month</p>
                        <p class="text-2xl font-bold text-purple-600">
                            {{ number_format($stats['financial']['donations_this_month'], 2) }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $stats['financial']['transactions_today'] }} transactions today • 
                            Total: {{ number_format($stats['financial']['total_donations'], 2) }}
                        </p>
                    </div>
                    <div class="text-purple-500">
                        <x-filament::icon icon="heroicon-o-banknotes" class="h-8 w-8" />
                    </div>
                </div>
            </div>

            <!-- System Health -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">System Status</p>
                        <p class="text-2xl font-bold {{ $stats['system']['storage_usage']['percentage'] > 40 ? 'text-orange-600' : 'text-green-600' }}">
                            {{ $stats['system']['storage_usage']['percentage'] }}%
                        </p>
                        <p class="text-xs text-gray-500">
                            Storage • {{ $stats['system']['last_backup'] }} • 
                            {{ $stats['system']['audit_logs_today'] }} audit logs today
                        </p>
                    </div>
                    <div class="{{ $stats['system']['storage_usage']['percentage'] > 40 ? 'text-orange-500' : 'text-green-500' }}">
                        <x-filament::icon 
                            icon="{{ $stats['system']['storage_usage']['percentage'] > 40 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-server-stack' }}"
                            class="h-8 w-8"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Overview -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Department Overview</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- HR Department -->
                    <div class="text-center">
                        <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
                            <x-filament::icon icon="heroicon-o-briefcase" class="h-8 w-8 text-blue-600 mx-auto mb-2" />
                            <h4 class="font-medium text-gray-900 dark:text-white">HR</h4>
                            <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                <p>{{ $deptStats['hr']['members'] }} members</p>
                                <p>{{ $deptStats['hr']['staff'] }} staff</p>
                            </div>
                        </div>
                    </div>

                    <!-- Finance Department -->
                    <div class="text-center">
                        <div class="bg-green-50 dark:bg-green-900 rounded-lg p-4">
                            <x-filament::icon icon="heroicon-o-banknotes" class="h-8 w-8 text-green-600 mx-auto mb-2" />
                            <h4 class="font-medium text-gray-900 dark:text-white">Finance</h4>
                            <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                <p>{{ $deptStats['finance']['donations'] }} donations</p>
                                <p>{{ $deptStats['finance']['transactions'] }} transactions</p>
                                <p>{{ $deptStats['finance']['staff'] }} staff</p>
                            </div>
                        </div>
                    </div>

                    <!-- Education Department -->
                    <div class="text-center">
                        <div class="bg-purple-50 dark:bg-purple-900 rounded-lg p-4">
                            <x-filament::icon icon="heroicon-o-academic-cap" class="h-8 w-8 text-purple-600 mx-auto mb-2" />
                            <h4 class="font-medium text-gray-900 dark:text-white">Education</h4>
                            <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                <p>{{ $deptStats['education']['students'] }} students</p>
                                <p>{{ $deptStats['education']['teachers'] }} teachers</p>
                                <p>{{ $deptStats['education']['classes'] }} classes</p>
                            </div>
                        </div>
                    </div>

                    <!-- Media Department -->
                    <div class="text-center">
                        <div class="bg-orange-50 dark:bg-orange-900 rounded-lg p-4">
                            <x-filament::icon icon="heroicon-o-photo" class="h-8 w-8 text-orange-600 mx-auto mb-2" />
                            <h4 class="font-medium text-gray-900 dark:text-white">Media</h4>
                            <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                <p>{{ $deptStats['media']['announcements'] }} announcements</p>
                                <p>{{ $deptStats['media']['blog_posts'] }} blog posts</p>
                                <p>{{ $deptStats['media']['media_files'] }} media files</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Logins -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Logins</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @forelse($activity['recent_logins'] as $login)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $login->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $login->email }}</p>
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $login->last_login_at ? $login->last_login_at->diffForHumans() : 'Never' }}
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-gray-500">No recent logins</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Critical System Events -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Critical System Events</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @forelse($activity['critical_errors'] as $error)
                            <div class="flex items-start space-x-3 p-3 bg-red-50 dark:bg-red-900 rounded-lg">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 text-red-500 mt-0.5" />
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ Str::limit($error->message, 80) }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $error->created_at ? Carbon::parse($error->created_at)->diffForHumans() : 'Unknown' }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-gray-500">No critical issues</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Quick Actions</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="{{ route('filament.admin.pages.backup-restore') }}" 
                       class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                        <x-filament::icon icon="heroicon-o-archive-box-arrow-down" class="h-8 w-8 text-blue-600 mb-2" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Backup System</span>
                    </a>

                    <a href="{{ route('filament.admin.pages.global-oversight') }}" 
                       class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                        <x-filament::icon icon="heroicon-o-globe-alt" class="h-8 w-8 text-green-600 mb-2" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Global Oversight</span>
                    </a>

                    <a href="{{ route('filament.admin.pages.system-health-monitoring') }}" 
                       class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                        <x-filament::icon icon="heroicon-o-heart" class="h-8 w-8 text-red-600 mb-2" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">System Health</span>
                    </a>

                    <a href="{{ route('filament.admin.pages.export-audit-logs') }}" 
                       class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                        <x-filament::icon icon="heroicon-o-document-arrow-down" class="h-8 w-8 text-purple-600 mb-2" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Export Logs</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
