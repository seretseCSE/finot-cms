<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Report Filters</x-slot>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <select wire:model.live="status" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="all">All Statuses</option>
                        <option value="Draft">Draft</option>
                        <option value="Published">Published</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date Range</label>
                    <select wire:model.live="date_range" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="all">All Time</option>
                        <option value="month">Last Month</option>
                        <option value="quarter">Last Quarter</option>
                        <option value="year">Last Year</option>
                    </select>
                </div>
            </div>
        </x-filament::section>

        @php $data = $this->getReportData(); @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Total Tours</p>
                <p class="text-2xl font-bold dark:text-white">{{ $data['totalTours'] ?? 0 }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Total Passengers</p>
                <p class="text-2xl font-bold dark:text-white">{{ $data['totalPassengers'] ?? 0 }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Confirmed Passengers</p>
                <p class="text-2xl font-bold dark:text-white">{{ $data['totalConfirmed'] ?? 0 }}</p>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">Tours</x-slot>

            @if(empty($data['tours']))
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    No tours found.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Place</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Passengers</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Confirmed</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($data['tours'] as $tour)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $tour->place }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $tour->tour_date?->format('M d, Y') }}</td>
                                    <td class="px-4 py-2 text-sm">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ match($tour->status) {
                                            'Draft'       => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                            'Published'   => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                            'In Progress' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                            'Completed'   => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                            'Cancelled'   => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                            default       => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        } }}">
                                            {{ $tour->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white text-right">{{ $tour->passengers->count() }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white text-right">{{ $tour->passengers->where('status', 'Confirmed')->count() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
