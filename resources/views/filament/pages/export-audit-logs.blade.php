<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Export Form -->
        <x-filament::section>
            <x-filament::section.header>
                <x-filament::section.heading>
                    Export Configuration
                </x-filament::section.heading>
                <x-filament::section.description>
                    Configure your audit log export parameters and filters.
                </x-filament::section.description>
            </x-filament::section.header>

            <form wire:submit="export">
                {{ $this->form }}
                
                <div class="mt-6 flex space-x-3">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        <x-filament::loading-indicator wire:loading wire:target="export" />
                        Export Logs
                    </x-filament::button>
                    
                    <x-filament::button type="button" wire:click="preview">
                        <x-filament::loading-indicator wire:loading wire:target="preview" />
                        Preview
                    </x-filament::button>
                    
                    <x-filament::button type="button" wire:click="resetFilters" color="gray">
                        Reset
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <!-- Statistics -->
        <x-filament::section>
            <x-filament::section.header>
                <x-filament::section.heading>
                    Audit Log Statistics
                </x-filament::section.heading>
            </x-filament::section.header>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-sm font-medium text-gray-500">Total Logs</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ number_format($this->getStatistics()['total_logs']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">All time records</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-sm font-medium text-gray-500">Last 24 Hours</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ number_format($this->getStatistics()['last_24h']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Recent activity</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-sm font-medium text-gray-500">Last 7 Days</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ number_format($this->getStatistics()['last_7d']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Weekly activity</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-sm font-medium text-gray-500">Last 30 Days</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ number_format($this->getStatistics()['last_30d']) }}</p>
                    <p class="mt-1 text-sm text-gray-500">Monthly activity</p>
                </div>
            </div>
        </x-filament::section>

        <!-- Top Users -->
        <x-filament::section>
            <x-filament::section.header>
                <x-filament::section.heading>
                    Most Active Users
                </x-filament::section.heading>
            </x-filament::section.header>

            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($this->getStatistics()['top_users'] as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $user['name'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ number_format($user['count']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No user activity data available
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <!-- Top Actions -->
        <x-filament::section>
            <x-filament::section.header>
                <x-filament::section.heading>
                    Most Common Actions
                </x-filament::section.heading>
            </x-filament::section.header>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                @forelse ($this->getStatistics()['top_actions'] as $action => $count)
                    <div class="bg-white p-3 rounded-lg shadow text-center">
                        <div class="text-lg font-semibold text-gray-900">{{ number_format($count) }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ ucfirst($action) }}</div>
                    </div>
                @empty
                    <div class="col-span-full text-center text-sm text-gray-500">
                        No action data available
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        <!-- Export Guidelines -->
        <x-filament::section>
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Export Guidelines</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Large exports may take several minutes to process</li>
                                <li>Excel format is recommended for detailed analysis</li>
                                <li>CSV format is best for data import into other systems</li>
                                <li>PDF format provides a readable summary report</li>
                                <li>All export actions are logged in the audit trail</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
