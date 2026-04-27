<x-filament-panels::page>
    <!-- Event Information Form -->
    <div class="mb-6 bg-white shadow overflow-hidden sm:rounded-md">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Event Information</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Event Type *</label>
                    <select wire:model.live="eventType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">-- Select Event Type --</option>
                        <option value="meeting">Meeting</option>
                        <option value="session">Session</option>
                        <option value="event">Event</option>
                        <option value="training">Training</option>
                        <option value="conference">Conference</option>
                        <option value="workshop">Workshop</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Event Date *</label>
                    <input type="date" wire:model.live="eventDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department (Optional)</label>
                    <select wire:model.live="departmentId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">All Departments</option>
                        @foreach(App\Models\Department::where('is_active', true)->orderBy('name_am')->get() as $department)
                            <option value="{{ $department->id }}">{{ $department->name_am }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if($eventType && $eventDate)
        <!-- Event Summary and Quick Actions -->
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-blue-900">
                        {{ ucfirst($eventType) }} - {{ \Carbon\Carbon::parse($eventDate)->format('M j, Y') }}
                    </h3>
                    <p class="text-sm text-blue-700">{{ $this->attendanceSummary() }} | Attendance Rate: {{ $this->attendanceRate() }}</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="saveAttendance" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-semibold">
                        💾 Save Attendance
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded">
            <div class="flex justify-between items-center">
                <h3 class="text-md font-semibold text-gray-900">Bulk Actions</h3>
                <div class="flex gap-2">
                    <button type="button" wire:click="markAllPresent" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
                        Mark All Present
                    </button>
                    <button type="button" wire:click="markAllAbsent" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                        Mark All Absent
                    </button>
                    @if(count($selectedMembers) > 0)
                        <button type="button" wire:click="markSelectedPresent" class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-sm">
                            Mark Selected ({{ count($selectedMembers) }}) Present
                        </button>
                        <button type="button" wire:click="markSelectedAbsent" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm">
                            Mark Selected ({{ count($selectedMembers) }}) Absent
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Member Attendance Table -->
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Member Attendance</h3>
                
                @if(count($memberAttendance) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" wire:model.live="selectAllMembers" wire:change="toggleSelectAllMembers" class="rounded">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($memberAttendance as $memberId => $data)
                                    <tr @if(in_array($memberId, $selectedMembers)) class="bg-blue-50" @endif>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="checkbox" wire:model.live="selectedMembers.{{ $memberId }}" value="{{ $memberId }}" class="rounded">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $data['member_name'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">{{ $data['member_code'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">{{ $data['department'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <select wire:model.live="memberAttendance.{{ $memberId }}.status" class="rounded-md border-gray-300 shadow-sm">
                                                <option value="">-- Select --</option>
                                                <option value="Present">Present</option>
                                                <option value="Absent">Absent</option>
                                                <option value="Excused">Excused</option>
                                                <option value="Late">Late</option>
                                                <option value="Permission">Permission</option>
                                            </select>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="text" wire:model.live="memberAttendance.{{ $memberId }}.notes" 
                                                   placeholder="Add notes..." 
                                                   class="rounded-md border-gray-300 shadow-sm text-sm w-32">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-gray-500">No members found for the selected criteria.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Save Button at Bottom -->
        <div class="mt-6 flex justify-end">
            <button type="button" wire:click="saveAttendance" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-semibold">
                💾 Save Attendance
            </button>
        </div>
    @else
        <!-- Instructions when no event is selected -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
            <div class="text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Select Event Information</h3>
                <p class="mt-1 text-sm text-gray-500">Please select an event type and date to start marking attendance.</p>
            </div>
        </div>
    @endif
</x-filament-panels::page>
