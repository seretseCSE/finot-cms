<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Class Selection</h3>
            <form wire:submit.prevent="generateClassReport">
                {{ $this->form }}
                <div class="mt-4">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        <x-filament::loading-indicator class="mr-2" />
                        Generate Class Report
                    </x-filament::button>
                </div>
            </form>
        </div>

        @if($this->getClassPerformanceData())
            @php
                $data = $this->getClassPerformanceData();
            @endphp

            <!-- Class Header -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">{{ $data['class']->subject->name }} - {{ $data['class']->name }}</h2>
                        <p class="text-gray-600">{{ $data['class']->academicYear->name }}</p>
                        @if($data['class']->teacher)
                            <p class="text-sm text-gray-500">Teacher: {{ $data['class']->teacher->full_name }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <x-filament::button wire:click="exportClassReport">
                            <x-filament::icon icon="heroicono-document-arrow-down" class="w-4 h-4 mr-2" />
                            Export Report
                        </x-filament::button>
                    </div>
                </div>
            </div>

            <!-- Class Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicono-users" class="w-6 h-6 text-blue-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Total Students</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $data['class_stats']['total_students'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicono-check-circle" class="w-6 h-6 text-green-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Avg Attendance</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $data['class_stats']['average_attendance_rate'] }}%</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicono-chart-bar" class="w-6 h-6 text-purple-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Avg Test Score</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $data['class_stats']['average_test_score'] }}</p>
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
                            <p class="text-2xl font-bold text-gray-900">{{ $data['class_stats']['highest_test_score'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Distribution -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Attendance Distribution -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold mb-4">Attendance Distribution</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-green-600">Excellent (90%+)</span>
                            <span class="text-sm font-bold">{{ $data['attendance_distribution']['excellent'] }} students</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: {{ ($data['attendance_distribution']['excellent'] / max($data['class_stats']['total_students'], 1)) * 100 }}%"></div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-blue-600">Good (75-89%)</span>
                            <span class="text-sm font-bold">{{ $data['attendance_distribution']['good'] }} students</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ ($data['attendance_distribution']['good'] / max($data['class_stats']['total_students'], 1)) * 100 }}%"></div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-yellow-600">Fair (60-74%)</span>
                            <span class="text-sm font-bold">{{ $data['attendance_distribution']['fair'] }} students</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-600 h-2 rounded-full" style="width: {{ ($data['attendance_distribution']['fair'] / max($data['class_stats']['total_students'], 1)) * 100 }}%"></div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-red-600">Poor (<60%)</span>
                            <span class="text-sm font-bold">{{ $data['attendance_distribution']['poor'] }} students</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-red-600 h-2 rounded-full" style="width: {{ ($data['attendance_distribution']['poor'] / max($data['class_stats']['total_students'], 1)) * 100 }}%"></div>
                        </div>
                    </div>
                </div>

                <!-- Test Score Distribution -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold mb-4">Test Score Distribution</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-green-600">Excellent (90-100)</span>
                            <span class="text-sm font-bold">{{ $data['test_distribution']['excellent'] }} students</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: {{ ($data['test_distribution']['excellent'] / max($data['class_stats']['total_students'], 1)) * 100 }}%"></div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-blue-600">Good (80-89)</span>
                            <span class="text-sm font-bold">{{ $data['test_distribution']['good'] }} students</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ ($data['test_distribution']['good'] / max($data['class_stats']['total_students'], 1)) * 100 }}%"></div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-yellow-600">Fair (70-79)</span>
                            <span class="text-sm font-bold">{{ $data['test_distribution']['fair'] }} students</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-600 h-2 rounded-full" style="width: {{ ($data['test_distribution']['fair'] / max($data['class_stats']['total_students'], 1)) * 100 }}%"></div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-red-600">Poor (<70)</span>
                            <span class="text-sm font-bold">{{ $data['test_distribution']['poor'] }} students</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-red-600 h-2 rounded-full" style="width: {{ ($data['test_distribution']['poor'] / max($data['class_stats']['total_students'], 1)) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Performance Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold mb-4">Individual Student Performance</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Test Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Tests</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($data['students'] as $student)
                                @php
                                    $attendanceData = $data['student_attendance'][$student->id] ?? [];
                                    $testData = $data['student_tests'][$student->id] ?? [];
                                    $attendanceRate = $attendanceData['attendance_rate'] ?? 0;
                                    $avgScore = $testData['average_score'] ?? 0;
                                    $overallPerformance = ($attendanceRate + $avgScore) / 2;
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <x-filament::icon icon="heroicono-user" class="w-5 h-5 text-gray-500" />
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $student->full_name }}</div>
                                                <div class="text-sm text-gray-500">{{ $student->phone }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-900">{{ $attendanceRate }}%</span>
                                            <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                                <div class="bg-green-600 h-2 rounded-full" style="width: {{ $attendanceRate }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-900">{{ $avgScore }}</span>
                                            <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min($avgScore, 100) }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $testData['total_tests'] ?? 0 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($overallPerformance >= 85)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Excellent</span>
                                        @elseif($overallPerformance >= 70)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Good</span>
                                        @elseif($overallPerformance >= 55)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Fair</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Needs Attention</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
