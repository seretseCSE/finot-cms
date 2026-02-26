<x-filament-panels::page>
    @push('scripts')
    <script src="{{ asset('js/offline/attendance.js') }}" defer></script>
    <script>
        window.authUserId = {{ auth()->id() }};
    </script>
    @endpush

    @if($record->isLocked())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <strong>🔒 Locked Session</strong> - This session is locked and cannot be modified.
        </div>
    @elseif($isSessionCancelled)
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            <strong>⚠️ Session Cancelled</strong> - Student attendance will not be recorded.
        </div>
    @endif

    <form wire:submit.prevent="saveTeacherAttendance" class="mb-8">
        <h2 class="text-lg font-bold mb-4">Teacher Attendance</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            @foreach($teacherAttendance as $teacherId => $data)
                <div class="border p-4 rounded">
                    <div class="font-semibold">{{ $data['teacher_name'] }}</div>
                    <div class="text-sm text-gray-600">{{ $data['subject_name'] }}</div>

                    <div class="mt-2">
                        <label class="block text-sm font-medium">Status</label>
                        <select wire:model.live="teacherAttendance.{{ $teacherId }}.attendance_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">-- Select --</option>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Late">Late</option>
                            <option value="Permission">Permission</option>
                        </select>
                    </div>

                    @if(($teacherAttendance[$teacherId]['attendance_status'] ?? null) === 'Absent')
                        <div class="mt-2">
                            <label class="block text-sm font-medium">If Absent:</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" wire:model.live="teacherAttendance.{{ $teacherId }}.session_outcome" value="Cancelled" class="mr-2">
                                    <span>Session Cancelled</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" wire:model.live="teacherAttendance.{{ $teacherId }}.session_outcome" value="Substitute_Assigned" class="mr-2">
                                    <span>Substitute Assigned</span>
                                </label>
                            </div>

                            @if(($teacherAttendance[$teacherId]['session_outcome'] ?? 'Normal') === 'Substitute_Assigned')
                                <div class="mt-2">
                                    <label class="block text-sm font-medium">Substitute Teacher Name</label>
                                    <input type="text" wire:model.live="teacherAttendance.{{ $teacherId }}.substitute_teacher_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="mt-2">
                        <label class="block text-sm font-medium">Notes</label>
                        <textarea wire:model.live="teacherAttendance.{{ $teacherId }}.notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Save Teacher Attendance
            </button>
        </div>
    </form>

    @if(!$isSessionCancelled)
        <form wire:submit.prevent="saveStudentAttendance">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold">Student Attendance</h2>
                <div class="text-sm text-gray-600">{{ $attendanceSummary }}</div>
            </div>

            <div class="flex gap-2 mb-4">
                <button type="button" wire:click="markAllPresent" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
                    Mark All Present
                </button>
                <button type="button" wire:click="markAllAbsent" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                    Mark All Absent
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($studentAttendance as $studentId => $data)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $data['student_name'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $data['member_code'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <select wire:model.live="studentAttendance.{{ $studentId }}.status" @disabled($record->isLocked()) class="rounded-md border-gray-300 shadow-sm"
                                            onchange="if (!navigator.onLine && window.offlineAttendance) { window.offlineAttendance.saveAttendanceOffline({{ $studentId }}, {{ $record->id }}, this.value); }">
                                        <option value="">-- Select --</option>
                                        <option value="Present">Present</option>
                                        <option value="Absent">Absent</option>
                                        <option value="Excused">Excused</option>
                                        <option value="Late">Late</option>
                                        <option value="Permission">Permission</option>
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <button type="submit" @disabled($record->isLocked()) class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:bg-gray-400">
                    Save Student Attendance
                </button>
            </div>
        </form>
    @endif
</x-filament-panels::page>
