<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Report Filters</x-slot>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <select wire:model.live="report_type" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="enrollment">Enrollment Report</option>
                        <option value="teacher_attendance">Teacher Attendance</option>
                        <option value="student_attendance">Student Attendance</option>
                    </select>
                </div>

                <div>
                    <input type="date" wire:model.live="date_from" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>

                <div>
                    <input type="date" wire:model.live="date_to" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
            </div>
        </x-filament::section>

        @php $data = $this->getReportData(); @endphp

        @if($report_type === 'enrollment')
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Enrollments</p>
                    <p class="text-2xl font-bold">{{ $data['total_enrollments'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Active</p>
                    <p class="text-2xl font-bold text-green-600">{{ $data['active_enrollments'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Withdrawn</p>
                    <p class="text-2xl font-bold text-red-600">{{ $data['withdrawn'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Completed</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $data['completed'] ?? 0 }}</p>
                </div>
            </div>

            @if(!empty($data['by_class']))
                <x-filament::section>
                    <x-slot name="heading">Enrollments by Class</x-slot>
                    <div class="space-y-2">
                        @foreach($data['by_class'] as $class => $count)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <span class="font-medium">{{ $class }}</span>
                                <span class="text-blue-600 font-bold">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        @endif

        @if($report_type === 'teacher_attendance')
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Records</p>
                    <p class="text-2xl font-bold">{{ $data['total_records'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Present</p>
                    <p class="text-2xl font-bold text-green-600">{{ $data['present'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Absent</p>
                    <p class="text-2xl font-bold text-red-600">{{ $data['absent'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Late</p>
                    <p class="text-2xl font-bold text-yellow-600">{{ $data['late'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Rate</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $data['attendance_rate'] ?? 0 }}%</p>
                </div>
            </div>

            @if(!empty($data['by_teacher']))
                <x-filament::section>
                    <x-slot name="heading">Attendance by Teacher</x-slot>
                    <div class="space-y-2">
                        @foreach($data['by_teacher'] as $teacher)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <span class="font-medium">{{ $teacher['name'] }}</span>
                                <span class="text-blue-600 font-bold">{{ $teacher['rate'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        @endif

        @if($report_type === 'student_attendance')
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Records</p>
                    <p class="text-2xl font-bold">{{ $data['total_records'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Present</p>
                    <p class="text-2xl font-bold text-green-600">{{ $data['present'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Absent</p>
                    <p class="text-2xl font-bold text-red-600">{{ $data['absent'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Excused</p>
                    <p class="text-2xl font-bold text-yellow-600">{{ $data['excused'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Rate</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $data['attendance_rate'] ?? 0 }}%</p>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
