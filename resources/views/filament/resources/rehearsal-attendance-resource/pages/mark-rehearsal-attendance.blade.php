<x-filament-panels::page>
    @if($record->status === 'Completed')
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            <strong>⚠️ Completed Rehearsal</strong> - This rehearsal has been completed and attendance cannot be modified.
        </div>
    @endif

    <!-- Rehearsal Info and Quick Actions -->
    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-blue-900">{{ $record->ethiopian_date }} at {{ $record->formatted_time }}</h3>
                <p class="text-sm text-blue-700">Location: {{ $record->location }}</p>
                <p class="text-sm text-blue-700">{{ $this->attendanceSummary() }} | Attendance Rate: {{ $this->attendanceRate() }}</p>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="saveAttendance" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-semibold">
                    💾 Save Attendance
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded">
        <div class="flex justify-between items-center">
            <h3 class="text-md font-semibold text-gray-900">Filters</h3>
            <div class="flex gap-4">
                <select wire:model.live="memberType" class="rounded-md border-gray-300 shadow-sm">
                    <option value="">All Member Types</option>
                    <option value="Kids">Kids</option>
                    <option value="Youth">Youth</option>
                    <option value="Adult">Adult</option>
                </select>
                <button type="button" wire:click="loadMemberAttendance" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm flex items-center gap-1.5">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    Apply Filter
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Save Button at Bottom -->
    <div class="mt-6 flex justify-end">
        <button type="button" wire:click="saveAttendance" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-semibold">
            💾 Save Attendance
        </button>
    </div>
</x-filament-panels::page>
