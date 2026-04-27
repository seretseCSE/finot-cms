<x-filament-panels::page>
    <div class="space-y-8 p-2">

        {{-- Modern Filters Card --}}
        <x-filament::card class="shadow-xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Contribution Matrix</h2>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">Manage monthly member contributions efficiently</p>
                </div>

                <button 
                    wire:click="refreshData"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-2xl font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all active:scale-95">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh Data
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mt-8">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Academic Year</label>
                    <select wire:model.live="academicYear" 
                            class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-2xl focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm transition-all">
                        <option value="">Select Academic Year</option>
                        @foreach($this->getFilterOptions()['academic_years'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Department</label>
                    <select wire:model.live="department" 
                            class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-2xl focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm transition-all">
                        <option value="">All Departments</option>
                        @foreach($this->getFilterOptions()['departments'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Member Type</label>
                    <select wire:model.live="type" 
                            class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-2xl focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm transition-all">
                        <option value="">All Types</option>
                        @foreach($this->getFilterOptions()['types'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Status</label>
                    <select wire:model.live="status" 
                            class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-2xl focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm transition-all">
                        <option value="">All Statuses</option>
                        @foreach($this->getFilterOptions()['statuses'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="hidden lg:block"></div>
            </div>
        </x-filament::card>

        {{-- Modern Matrix Table --}}
        <x-filament::card class="shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full table-fixed divide-y divide-gray-100 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 z-20">
                        <tr>
                            <th class="w-72 px-8 py-6 text-left text-xs font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                Member Name
                            </th>
                            
                            {{-- Equal width month columns --}}
                            @foreach(range(1, 12) as $m)
                                <th class="w-40 px-8 py-6 text-right text-xs font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                    {{ substr($this->months[$m], 0, 3) }}
                                </th>
                            @endforeach

                            <th class="w-40 px-8 py-6 text-right text-xs font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                Total  
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
                        @forelse($this->members as $member)
                            @php
                                $totalYearly = 0;
                                foreach(range(1, 12) as $m) {
                                    if ($this->grid[$member->id][$m] ?? false) {
                                        $totalYearly += $this->getMemberGroupAmount($member, $m);
                                    }
                                }
                            @endphp
                            
                            <tr class="hover:bg-blue-50 dark:hover:bg-gray-800 transition-colors duration-150 group">
                                <td class="px-8 py-5 whitespace-nowrap">
                                    <div class="font-medium text-gray-900 dark:text-white text-base">
                                        {{ $member->first_name }} {{ $member->father_name }}
                                    </div>
                                </td>

                                @foreach(range(1, 12) as $m)
                                    <td class="px-2 py-5 text-center">
                                        <div class="flex justify-center">
                                            <input 
                                                type="checkbox" 
                                                wire:click="toggle({{ $member->id }}, {{ $m }})"
                                                {{ ($this->grid[$member->id][$m] ?? false) ? 'checked' : '' }}
                                                class="w-5 h-5 rounded-lg border-gray-300 text-blue-600 focus:ring-blue-500 focus:ring-offset-0 cursor-pointer transition-transform hover:scale-110"
                                            >
                                        </div>
                                    </td>
                                @endforeach

                                <td class="px-8 py-5 text-right">
                                    <span class="inline-block px-6 py-2 bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 font-semibold rounded-3xl text-sm">
                                        {{ number_format($totalYearly, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="py-24 text-center">
                                    <div class="mx-auto w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-3xl flex items-center justify-center mb-6">
                                        <span class="text-4xl text-gray-300 dark:text-gray-600">📋</span>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400 text-lg font-medium">No members found</p>
                                    <p class="text-sm text-gray-400 mt-2">Adjust filters to see contributions</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>