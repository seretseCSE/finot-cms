<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Events') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-navigation currentPage="events" />
    
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ __('Event Calendar') }}</h1>
            <p class="text-xl text-gray-600">{{ __('Stay updated with our upcoming events and activities.') }}</p>
        </div>
        
        <!-- Calendar View Toggle -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button class="p-2 text-gray-600 hover:text-gray-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <h2 class="text-xl font-semibold text-gray-900">{{ date('F Y') }}</h2>
                    <button class="p-2 text-gray-600 hover:text-gray-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
                <div class="flex space-x-2">
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        {{ __('Month') }}
                    </button>
                    <button class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        {{ __('Week') }}
                    </button>
                    <button class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        {{ __('List') }}
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Calendar Grid -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="grid grid-cols-7 gap-px bg-gray-200">
                <!-- Days of week -->
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-700">{{ __('Sun') }}</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-700">{{ __('Mon') }}</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-700">{{ __('Tue') }}</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-700">{{ __('Wed') }}</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-700">{{ __('Thu') }}</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-700">{{ __('Fri') }}</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-700">{{ __('Sat') }}</div>
                
                <!-- Calendar days (sample) -->
                @for ($day = 1; $day <= 35; $day++)
                    @php
                        $currentDay = $day - 3; // Adjust for month start
                        $isCurrentMonth = $currentDay > 0 && $currentDay <= 31;
                        $hasEvent = in_array($currentDay, [5, 12, 15, 20, 25]);
                    @endphp
                    <div class="bg-white p-3 min-h-[80px] @if($isCurrentMonth) @else bg-gray-50 @endif">
                        <div class="text-sm @if($hasEvent) font-bold text-blue-600 @else text-gray-700 @endif">
                            @if($isCurrentMonth) {{ $currentDay }} @else {{ $currentDay }} @endif
                        </div>
                        @if($hasEvent && $isCurrentMonth)
                            <div class="mt-1">
                                <div class="text-xs bg-blue-100 text-blue-800 rounded px-1 py-0.5 truncate">
                                    @if($currentDay == 5) {{ __('Worship') }} @endif
                                    @if($currentDay == 12) {{ __('Youth Meeting') }} @endif
                                    @if($currentDay == 15) {{ __('Community Service') }} @endif
                                    @if($currentDay == 20) {{ __('Bible Study') }} @endif
                                    @if($currentDay == 25) {{ __('Special Event') }} @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endfor
            </div>
        </div>
        
        <!-- Upcoming Events List -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-6">{{ __('Upcoming Events') }}</h3>
            
            <div class="space-y-4">
                <!-- Event 1 -->
                <div class="flex items-start space-x-4 p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="flex-shrink-0">
                        <div class="w-16 h-16 bg-blue-100 rounded-lg flex flex-col items-center justify-center">
                            <div class="text-xs text-blue-600 font-medium">{{ date('M') }}</div>
                            <div class="text-lg font-bold text-blue-600">5</div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-lg font-medium text-gray-900">{{ __('Sunday Worship Service') }}</h4>
                        <p class="text-gray-600">{{ __('Join us for our weekly worship service with inspiring music and message.') }}</p>
                        <div class="mt-2 text-sm text-gray-500">
                            <span>{{ __('10:00 AM - 12:00 PM') }}</span>
                            <span class="mx-2">•</span>
                            <span>{{ __('Main Sanctuary') }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Event 2 -->
                <div class="flex items-start space-x-4 p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="flex-shrink-0">
                        <div class="w-16 h-16 bg-green-100 rounded-lg flex flex-col items-center justify-center">
                            <div class="text-xs text-green-600 font-medium">{{ date('M') }}</div>
                            <div class="text-lg font-bold text-green-600">12</div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-lg font-medium text-gray-900">{{ __('Youth Group Meeting') }}</h4>
                        <p class="text-gray-600">{{ __('Weekly gathering for youth with games, discussion, and spiritual growth.') }}</p>
                        <div class="mt-2 text-sm text-gray-500">
                            <span>{{ __('6:00 PM - 8:00 PM') }}</span>
                            <span class="mx-2">•</span>
                            <span>{{ __('Youth Center') }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Event 3 -->
                <div class="flex items-start space-x-4 p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="flex-shrink-0">
                        <div class="w-16 h-16 bg-purple-100 rounded-lg flex flex-col items-center justify-center">
                            <div class="text-xs text-purple-600 font-medium">{{ date('M') }}</div>
                            <div class="text-lg font-bold text-purple-600">15</div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-lg font-medium text-gray-900">{{ __('Community Outreach Day') }}</h4>
                        <p class="text-gray-600">{{ __('Join us as we serve the local community through various outreach activities.') }}</p>
                        <div class="mt-2 text-sm text-gray-500">
                            <span>{{ __('9:00 AM - 2:00 PM') }}</span>
                            <span class="mx-2">•</span>
                            <span>{{ __('Various Locations') }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Event 4 -->
                <div class="flex items-start space-x-4 p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="flex-shrink-0">
                        <div class="w-16 h-16 bg-red-100 rounded-lg flex flex-col items-center justify-center">
                            <div class="text-xs text-red-600 font-medium">{{ date('M') }}</div>
                            <div class="text-lg font-bold text-red-600">20</div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-lg font-medium text-gray-900">{{ __('Bible Study Group') }}</h4>
                        <p class="text-gray-600">{{ __('Weekly Bible study with fellowship and discussion of scripture.') }}</p>
                        <div class="mt-2 text-sm text-gray-500">
                            <span>{{ __('7:00 PM - 8:30 PM') }}</span>
                            <span class="mx-2">•</span>
                            <span>{{ __('Conference Room') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
        </div>
    </footer>
</body>
</html>
