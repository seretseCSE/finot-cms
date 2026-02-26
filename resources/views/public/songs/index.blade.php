<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Songs') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-navigation currentPage="songs" />
    
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ __('Song Library') }}</h1>
            <p class="text-xl text-gray-600">{{ __('Explore our collection of inspirational songs and hymns.') }}</p>
        </div>
        
        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col md:flex-row gap-4">
                <input type="text" placeholder="{{ __('Search songs...') }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                <select class="px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">{{ __('All Categories') }}</option>
                    <option value="">{{ __('Worship') }}</option>
                    <option value="">{{ __('Praise') }}</option>
                    <option value="">{{ __('Hymns') }}</option>
                    <option value="">{{ __('Contemporary') }}</option>
                </select>
                <button class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    {{ __('Search') }}
                </button>
            </div>
        </div>
        
        <!-- Songs Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Sample Song 1 -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ __('Amazing Grace') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Traditional Hymn') }}</p>
                        </div>
                        <div class="text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">{{ __('A timeless hymn about redemption and grace that has inspired generations.') }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">{{ __('Duration: 3:45') }}</span>
                        <a href="#" class="text-blue-600 hover:text-blue-700 text-sm font-medium">{{ __('View Lyrics') }}</a>
                    </div>
                </div>
            </div>
            
            <!-- Sample Song 2 -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ __('How Great Thou Art') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Worship Song') }}</p>
                        </div>
                        <div class="text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">{{ __('A powerful worship song celebrating the majesty and greatness of our Creator.') }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">{{ __('Duration: 4:20') }}</span>
                        <a href="#" class="text-blue-600 hover:text-blue-700 text-sm font-medium">{{ __('View Lyrics') }}</a>
                    </div>
                </div>
            </div>
            
            <!-- Sample Song 3 -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ __('Blessed Assurance') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Hymn of Faith') }}</p>
                        </div>
                        <div class="text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">{{ __('A beautiful hymn expressing confidence and assurance in faith.') }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">{{ __('Duration: 3:30') }}</span>
                        <a href="#" class="text-blue-600 hover:text-blue-700 text-sm font-medium">{{ __('View Lyrics') }}</a>
                    </div>
                </div>
            </div>
            
            <!-- Sample Song 4 -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ __('Great Is Thy Faithfulness') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Traditional Hymn') }}</p>
                        </div>
                        <div class="text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">{{ __('A timeless declaration of God\'s unwavering faithfulness throughout generations.') }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">{{ __('Duration: 4:00') }}</span>
                        <a href="#" class="text-blue-600 hover:text-blue-700 text-sm font-medium">{{ __('View Lyrics') }}</a>
                    </div>
                </div>
            </div>
            
            <!-- Sample Song 5 -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ __('It Is Well With My Soul') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Hymn of Peace') }}</p>
                        </div>
                        <div class="text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">{{ __('A profound hymn of peace and trust in God\'s sovereignty amid life\'s challenges.') }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">{{ __('Duration: 3:55') }}</span>
                        <a href="#" class="text-blue-600 hover:text-blue-700 text-sm font-medium">{{ __('View Lyrics') }}</a>
                    </div>
                </div>
            </div>
            
            <!-- Sample Song 6 -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ __('Holy Holy Holy') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Worship Hymn') }}</p>
                        </div>
                        <div class="text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">{{ __('A majestic worship hymn proclaiming the holiness and glory of God.') }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">{{ __('Duration: 3:15') }}</span>
                        <a href="#" class="text-blue-600 hover:text-blue-700 text-sm font-medium">{{ __('View Lyrics') }}</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pagination -->
        <div class="mt-12 flex justify-center">
            <nav class="flex items-center space-x-2">
                <button class="px-3 py-2 text-sm text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50" disabled>
                    {{ __('Previous') }}
                </button>
                <button class="px-3 py-2 text-sm text-white bg-blue-600 border border-blue-600 rounded-md">
                    1
                </button>
                <button class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    2
                </button>
                <button class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    {{ __('Next') }}
                </button>
            </nav>
        </div>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
        </div>
    </footer>
</body>
</html>
