<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Media Gallery') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-navigation currentPage="media" />
    
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ __('Media Gallery') }}</h1>
            <p class="text-xl text-gray-600">{{ __('Explore our collection of photos, videos, and audio recordings.') }}</p>
        </div>
        
        <!-- Media Type Tabs -->
        <div class="bg-white rounded-lg shadow-md mb-8">
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <button class="py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600">
                        {{ __('Photos') }}
                    </button>
                    <button class="py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        {{ __('Videos') }}
                    </button>
                    <button class="py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        {{ __('Audio') }}
                    </button>
                </nav>
            </div>
        </div>
        
        <!-- Photos Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- Photo 1 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                <div class="aspect-w-16 aspect-h-12 bg-gradient-to-r from-blue-400 to-purple-500 h-48"></div>
                <div class="p-4">
                    <h3 class="font-medium text-gray-900 mb-1">{{ __('Community Gathering') }}</h3>
                    <p class="text-sm text-gray-500">{{ date('F j, Y') }}</p>
                </div>
            </div>
            
            <!-- Photo 2 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                <div class="aspect-w-16 aspect-h-12 bg-gradient-to-r from-green-400 to-teal-500 h-48"></div>
                <div class="p-4">
                    <h3 class="font-medium text-gray-900 mb-1">{{ __('Youth Program Event') }}</h3>
                    <p class="text-sm text-gray-500">{{ date('F j, Y', strtotime('-1 week')) }}</p>
                </div>
            </div>
            
            <!-- Photo 3 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                <div class="aspect-w-16 aspect-h-12 bg-gradient-to-r from-red-400 to-orange-500 h-48"></div>
                <div class="p-4">
                    <h3 class="font-medium text-gray-900 mb-1">{{ __('Worship Service') }}</h3>
                    <p class="text-sm text-gray-500">{{ date('F j, Y', strtotime('-2 weeks')) }}</p>
                </div>
            </div>
            
            <!-- Photo 4 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                <div class="aspect-w-16 aspect-h-12 bg-gradient-to-r from-yellow-400 to-red-500 h-48"></div>
                <div class="p-4">
                    <h3 class="font-medium text-gray-900 mb-1">{{ __('Community Outreach') }}</h3>
                    <p class="text-sm text-gray-500">{{ date('F j, Y', strtotime('-3 weeks')) }}</p>
                </div>
            </div>
            
            <!-- Photo 5 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                <div class="aspect-w-16 aspect-h-12 bg-gradient-to-r from-indigo-400 to-purple-500 h-48"></div>
                <div class="p-4">
                    <h3 class="font-medium text-gray-900 mb-1">{{ __('Music Performance') }}</h3>
                    <p class="text-sm text-gray-500">{{ date('F j, Y', strtotime('-1 month')) }}</p>
                </div>
            </div>
            
            <!-- Photo 6 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                <div class="aspect-w-16 aspect-h-12 bg-gradient-to-r from-pink-400 to-rose-500 h-48"></div>
                <div class="p-4">
                    <h3 class="font-medium text-gray-900 mb-1">{{ __('Educational Workshop') }}</h3>
                    <p class="text-sm text-gray-500">{{ date('F j, Y', strtotime('-1 month')) }}</p>
                </div>
            </div>
            
            <!-- Photo 7 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                <div class="aspect-w-16 aspect-h-12 bg-gradient-to-r from-cyan-400 to-blue-500 h-48"></div>
                <div class="p-4">
                    <h3 class="font-medium text-gray-900 mb-1">{{ __('Family Event') }}</h3>
                    <p class="text-sm text-gray-500">{{ date('F j, Y', strtotime('-2 months')) }}</p>
                </div>
            </div>
            
            <!-- Photo 8 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                <div class="aspect-w-16 aspect-h-12 bg-gradient-to-r from-emerald-400 to-green-500 h-48"></div>
                <div class="p-4">
                    <h3 class="font-medium text-gray-900 mb-1">{{ __('Celebration Ceremony') }}</h3>
                    <p class="text-sm text-gray-500">{{ date('F j, Y', strtotime('-2 months')) }}</p>
                </div>
            </div>
        </div>
        
        <!-- Load More Button -->
        <div class="mt-12 text-center">
            <button class="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                {{ __('Load More Photos') }}
            </button>
        </div>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
        </div>
    </footer>
</body>
</html>
