<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Search Tours</x-slot>

            <div class="max-w-xl">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="query"
                    placeholder="Search by place or description..."
                    class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                />
            </div>
        </x-filament::section>

        @php $results = $this->getResults(); @endphp

        @if(!empty($query) && empty($results))
            <x-filament::section>
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    No tours found matching "{{ $query }}".
                </div>
            </x-filament::section>
        @endif

        @if(!empty($results))
            <x-filament::section>
                <x-slot name="heading">Search Results</x-slot>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Place</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Capacity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($results as $tour)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ $tour['place'] }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $tour['tour_date'] ? \Carbon\Carbon::parse($tour['tour_date'])->format('M d, Y') : '-' }}</td>
                                    <td class="px-4 py-2 text-sm">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @match($tour['status']) {
                                                'Draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                'Published' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                                'In Progress' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                                'Completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                                'Cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                                default => 'bg-gray-100 text-gray-800',
                                            }
                                        ">{{ $tour['status'] }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white text-right">{{ $tour['max_capacity'] ?? 'Unlimited' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
