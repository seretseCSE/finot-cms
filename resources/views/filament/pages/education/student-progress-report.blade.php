<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Student Selection</h3>
            <form wire:submit.prevent="generateProgressReport">
                {{ $this->form }}
                <div class="mt-4">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        <x-filament::loading-indicator class="mr-2" />
                        Generate Progress Report
                    </x-filament::button>
                </div>
            </form>
        </div>

        @if($this->getProgressData())
            @php
                $data = $this->getProgressData();
            @endphp

            <!-- Student Header -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                            <x-filament::icon icon="heroicono-user" class="w-8 h-8 text-gray-500" />
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">{{ $data['student']->full_name }}</h2>
                            <p class="text-gray-600">{{ $data['student']->phone }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $data['current_education']->class->subject->name }} - {{ $data['current_education']->class->name }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <x-filament::button wire:click="generateReportCard">
                            <x-filament::icon icon="heroicono-document-arrow-down" class="w-4 h-4 mr-2" />
                            Generate Report Card
                        </x-filament::button>
                    </div>
                </div>
            </div>

            <!-- Performance Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicono-check-circle" class="w-6 h-6 text-green-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Attendance Rate</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $data['attendance']['rate'] }}%</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicono-chart-bar" class="w-6 h-6 text-blue-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Average Score</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $data['tests']['average_score'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicono-trophy" class="w-6 h-6 text-yellow-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Highest Score</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $data['tests']['highest_score'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicono-document-text" class="w-6 h-6 text-purple-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Total Tests</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $data['tests']['total_tests'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Breakdown -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold mb-4">Attendance Breakdown</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($data['attendance']['details'] as $status => $records)
                        <div class="text-center">
                            <div class="text-3xl font-bold {{ $status === 'Present' ? 'text-green-600' : ($status === 'Absent' ? 'text-red-600' : 'text-yellow-600') }}">
                                {{ $records->count() }}
                            </div>
                            <div class="text-sm text-gray-600">{{ $status }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Recent Test Results -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold mb-4">Recent Test Results</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($data['tests']['results']->take(10) as $result)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $result->test->title }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($result->created_at)->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-900">{{ $result->score }}</span>
                                            <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min($result->score, 100) }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($result->score >= 90)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">A</span>
                                        @elseif($result->score >= 80)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">B</span>
                                        @elseif($result->score >= 70)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">C</span>
                                        @elseif($result->score >= 60)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">D</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">F</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Progress Trend -->
            @if($data['progress_trend']->count() > 0)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold mb-4">Progress Trend</h3>
                    <div class="space-y-3">
                        @foreach($data['progress_trend'] as $month)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                <div>
                                    <span class="text-sm font-medium text-gray-900">{{ $month['month'] }}</span>
                                    <span class="text-sm text-gray-600 ml-2">({{ $month['test_count'] }} tests)</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-medium text-gray-900">{{ $month['average_score'] }}</span>
                                    <div class="w-24 bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ min($month['average_score'], 100) }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
