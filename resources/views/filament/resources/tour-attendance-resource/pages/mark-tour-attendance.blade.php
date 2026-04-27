<x-filament-panels::page>
    @if($record->status === 'Completed')
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            <strong>⚠️ Completed Tour Session</strong> - This tour attendance session has been completed and attendance cannot be modified.
        </div>
    @endif

    <!-- Tour Info and Quick Actions -->
    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-blue-900">{{ $record->tour->place }} - {{ $record->ethiopian_session_date }}</h3>
                <p class="text-sm text-blue-700">Tour Date: {{ $record->tour->tour_date->format('M j, Y') }}</p>
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
                    Mark All Not Present
                </button>
                @if(count($selectedPassengers) > 0)
                    <button type="button" wire:click="markSelectedPresent" class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-sm">
                        Mark Selected ({{ count($selectedPassengers) }}) Present
                    </button>
                    <button type="button" wire:click="markSelectedAbsent" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm">
                        Mark Selected ({{ count($selectedPassengers) }}) Not Present
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Passenger Attendance Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Passenger Attendance</h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" wire:model.live="selectAllPassengers" wire:change="toggleSelectAllPassengers" class="rounded">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passenger Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passenger Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($passengerAttendance as $passengerId => $data)
                            <tr @if(in_array($passengerId, $selectedPassengers)) class="bg-blue-50" @endif>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" wire:model.live="selectedPassengers.{{ $passengerId }}" value="{{ $passengerId }}" class="rounded">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $data['passenger_name'] }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ $data['passenger_code'] }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ $data['member_name'] }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ $data['phone'] }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ $data['passenger_count'] }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($data['registration_type'] === 'Member') bg-green-100 text-green-800
                                        @elseif($data['registration_type'] === 'Guest') bg-blue-100 text-blue-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ $data['registration_type'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <select wire:model.live="passengerAttendance.{{ $passengerId }}.status" class="rounded-md border-gray-300 shadow-sm">
                                        <option value="">-- Select --</option>
                                        <option value="Present">Present</option>
                                        <option value="Not Present">Not Present</option>
                                    </select>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="text" wire:model.live="passengerAttendance.{{ $passengerId }}.notes" 
                                           placeholder="Add notes..." 
                                           class="rounded-md border-gray-300 shadow-sm text-sm">
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
