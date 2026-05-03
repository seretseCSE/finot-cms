<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Report Filters</h3>
            {{ $this->form }}
        </div>

        @if($isLoading)
            <!-- Loading Skeleton -->
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    @for($i = 1; $i <= 5; $i++)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <div class="animate-pulse">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-gray-200 rounded-lg p-3 w-12 h-12"></div>
                                    <div class="ml-4 flex-1">
                                        <div class="h-4 bg-gray-200 rounded w-20 mb-2"></div>
                                        <div class="h-8 bg-gray-200 rounded w-16"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="animate-pulse">
                        <div class="h-6 bg-gray-200 rounded w-32 mb-4"></div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sessions</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance Rate</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @for($i = 1; $i <= 5; $i++)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full bg-gray-200"></div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="h-4 bg-gray-200 rounded w-32 mb-2"></div>
                                                        <div class="h-4 bg-gray-200 rounded w-24"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="h-4 bg-gray-200 rounded w-8"></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="h-4 bg-gray-200 rounded w-8"></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="h-4 bg-gray-200 rounded w-12 mr-2"></div>
                                                    <div class="w-16 bg-gray-200 rounded h-2"></div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="h-6 bg-gray-200 rounded w-16"></div>
                                            </td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($reportData)
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicon-o-calendar" class="w-6 h-6 text-blue-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Total Sessions</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $reportData['summary']['total_sessions'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicon-o-users" class="w-6 h-6 text-green-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Total Students</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $reportData['summary']['total_students'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicon-o-check-circle" class="w-6 h-6 text-green-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Present Rate</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $reportData['summary']['present_rate'] }}%</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicon-o-check-circle" class="w-6 h-6 text-green-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Present</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $reportData['summary']['present'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-100 rounded-lg p-3">
                            <x-filament::icon icon="heroicon-o-x-circle" class="w-6 h-6 text-red-600" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600">Absent</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $reportData['summary']['absent'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Buttons -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Export Options</h3>
                    <div class="flex gap-2">
                        <x-filament::button wire:click="exportToExcel">
                            <x-filament::icon icon="heroicon-o-document-arrow-down" class="w-4 h-4 mr-2" />
                            Export Excel
                        </x-filament::button>
                        <x-filament::button wire:click="exportToPdf">
                            <x-filament::icon icon="heroicon-o-document-arrow-down" class="w-4 h-4 mr-2" />
                            Export PDF
                        </x-filament::button>
                    </div>
                </div>
            </div>

            <!-- Attendance by Student Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold mb-4">Attendance by Student</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sessions</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($reportData['by_student'] as $student)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <x-filament::icon icon="heroicon-o-user" class="w-5 h-5 text-gray-500" />
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $student['student']->full_name }}</div>
                                                <div class="text-sm text-gray-500">{{ $student['student']->phone }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student['total_sessions'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $student['present'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm text-gray-900">{{ $student['rate'] }}%</span>
                                            <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                                <div class="bg-green-600 h-2 rounded-full" style="width: {{ $student['rate'] }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($student['rate'] >= 90)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Excellent</span>
                                        @elseif($student['rate'] >= 75)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Good</span>
                                        @elseif($student['rate'] >= 60)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Fair</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Poor</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Attendance by Date Chart -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold mb-4">Attendance Trend by Date</h3>
                <div class="space-y-2">
                    @foreach($reportData['by_date'] as $date)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <div class="flex items-center space-x-4">
                                <span class="text-sm font-medium text-gray-900">{{ \Carbon\Carbon::parse($date['date'])->format('M d, Y') }}</span>
                                <span class="text-sm text-gray-600">{{ $date['present'] }}/{{ $date['total'] }} present</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm font-medium text-gray-900">{{ $date['rate'] }}%</span>
                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $date['rate'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Teacher Attendance by Subject -->
            @if(!empty($reportData['by_teacher_subject']))
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" x-data="{ activeSubject: '{{ array_key_first($reportData['by_teacher_subject']) }}' }">
                <h3 class="text-lg font-semibold mb-4">Teacher Attendance by Subject</h3>

                <!-- Subject Tabs -->
                <div class="border-b border-gray-200 mb-4">
                    <nav class="flex space-x-4 overflow-x-auto" aria-label="Subjects">
                        @foreach(array_keys($reportData['by_teacher_subject']) as $subject)
                            <button
                                type="button"
                                @click="activeSubject = '{{ $subject }}'"
                                :class="activeSubject === '{{ $subject }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors"
                            >
                                {{ $subject }}
                            </button>
                        @endforeach
                    </nav>
                </div>

                <!-- Subject Tables -->
                @foreach($reportData['by_teacher_subject'] as $subject => $teachers)
                <div x-show="activeSubject === '{{ $subject }}'" x-cloak>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sessions</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Present</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance Rate</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($teachers as $teacher)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $teacher['teacher_name'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $teacher['total_sessions'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $teacher['present'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <span class="text-sm text-gray-900">{{ $teacher['rate'] }}%</span>
                                                <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                                    <div class="bg-green-600 h-2 rounded-full" style="width: {{ $teacher['rate'] }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($teacher['rate'] >= 90)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Excellent</span>
                                            @elseif($teacher['rate'] >= 75)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Good</span>
                                            @elseif($teacher['rate'] >= 60)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Fair</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Poor</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
