<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Member Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                        <x-filament::icon icon="heroicono-user" class="w-8 h-8 text-gray-500" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">{{ $record->full_name }}</h2>
                        <p class="text-gray-600">{{ $record->phone }}</p>
                        @if($record->member_id)
                            <p class="text-sm text-gray-500">ID: {{ $record->member_id }}</p>
                        @endif
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Member Since</p>
                    <p class="font-semibold">{{ $record->member_since?->toFormattedDateString() ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Timeline Events -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-6">Activity Timeline</h3>
            
            @if(empty($this->getTimelineEvents()))
                <div class="text-center py-12">
                    <x-filament::icon icon="heroicono-calendar" class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                    <p class="text-gray-500">No activity recorded yet</p>
                </div>
            @else
                <div class="relative">
                    <!-- Timeline Line -->
                    <div class="absolute left-8 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                    
                    @foreach($this->getTimelineEvents() as $event)
                        <div class="relative flex items-start mb-8 last:mb-0">
                            <!-- Timeline Dot -->
                            <div class="flex-shrink-0 w-16 h-16 rounded-full bg-white border-4 border-{{ $this->getEventColor($event['type'], $event['status']) }}-200 flex items-center justify-center z-10">
                                <x-filament::icon 
                                    :icon="$this->getEventIcon($event['type'], $event['status'])" 
                                    class="w-8 h-8 text-{{ $this->getEventColor($event['type'], $event['status']) }}-600" 
                                />
                            </div>
                            
                            <!-- Event Content -->
                            <div class="ml-6 flex-1 bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-900">{{ $event['title'] }}</h4>
                                        <p class="text-gray-600 mt-1">{{ $event['description'] }}</p>
                                        
                                        @if($event['status'] === 'Attended')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Attended</span>
                                        @elseif($event['status'] === 'Confirmed')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-primary-100 text-primary-800">Confirmed</span>
                                        @elseif($event['status'] === 'Pending')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        @elseif($event['status'] === 'Cancelled')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Cancelled</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">{{ $event['status'] }}</span>
                                        @endif

                                        <div class="flex items-center mt-3 space-x-4 text-sm text-gray-500">
                                            <span class="flex items-center">
                                                <x-filament::icon icon="heroicono-calendar" class="w-4 h-4 mr-1" />
                                                {{ \Carbon\Carbon::parse($event['date'])->format('M d, Y') }}
                                            </span>
                                            @if($event['time'])
                                                <span class="flex items-center">
                                                    <x-filament::icon icon="heroicono-clock" class="w-4 h-4 mr-1" />
                                                    {{ $event['time'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Status Badge -->
                                    <div class="ml-4">
                                        @php
                                            $badgeColors = [
                                                'success' => 'bg-green-100 text-green-800',
                                                'danger' => 'bg-red-100 text-red-800',
                                                'warning' => 'bg-yellow-100 text-yellow-800',
                                                'info' => 'bg-blue-100 text-blue-800',
                                                'primary' => 'bg-indigo-100 text-indigo-800',
                                                'secondary' => 'bg-gray-100 text-gray-800',
                                                'gray' => 'bg-gray-100 text-gray-800',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeColors[$this->getEventColor($event['type'], $event['status'])] ?? $badgeColors['gray'] }}">
                                            {{ $event['status'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-check-circle" class="w-6 h-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Total Attendance</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ $record->attendance()->where('status', 'Present')->count() }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-primary-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-banknotes" class="w-6 h-6 text-primary-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Total Contributions</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ number_format($record->contributions()->sum('amount'), 2) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-academic-cap" class="w-6 h-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Education Level</p>
                        <p class="text-lg font-bold text-gray-900">
                            {{ $record->educationHistory()->latest()->first()?->class->name ?? 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-100 rounded-lg p-3">
                        <x-filament::icon icon="heroicono-user-group" class="w-6 h-6 text-yellow-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Groups</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ $record->groupAssignments()->count() }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
