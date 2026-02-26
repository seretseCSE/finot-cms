<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Blog') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-navigation currentPage="blog" />
    
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ __('Our Blog') }}</h1>
            <p class="text-xl text-gray-600">{{ __('Stay updated with our latest news, events, and insights.') }}</p>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Sample Blog Post 1 -->
            <article class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="h-48 bg-gradient-to-r from-blue-500 to-purple-600"></div>
                <div class="p-6">
                    <div class="text-sm text-gray-500 mb-2">{{ date('F j, Y') }}</div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3">
                        <a href="#" class="hover:text-blue-600 transition-colors">{{ __('Community Outreach Success Stories') }}</a>
                    </h2>
                    <p class="text-gray-600 mb-4">{{ __('Discover how our recent community initiatives have made a positive impact on local families and individuals.') }}</p>
                    <a href="#" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
                        {{ __('Read more') }}
                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </article>
            
            <!-- Sample Blog Post 2 -->
            <article class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="h-48 bg-gradient-to-r from-green-500 to-teal-600"></div>
                <div class="p-6">
                    <div class="text-sm text-gray-500 mb-2">{{ date('F j, Y', strtotime('-1 week')) }}</div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3">
                        <a href="#" class="hover:text-blue-600 transition-colors">{{ __('Youth Program Milestones') }}</a>
                    </h2>
                    <p class="text-gray-600 mb-4">{{ __('Celebrating the achievements of our youth members and the growth of our mentorship programs.') }}</p>
                    <a href="#" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
                        {{ __('Read more') }}
                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </article>
            
            <!-- Sample Blog Post 3 -->
            <article class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="h-48 bg-gradient-to-r from-red-500 to-orange-600"></div>
                <div class="p-6">
                    <div class="text-sm text-gray-500 mb-2">{{ date('F j, Y', strtotime('-2 weeks')) }}</div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-3">
                        <a href="#" class="hover:text-blue-600 transition-colors">{{ __('Upcoming Events & Activities') }}</a>
                    </h2>
                    <p class="text-gray-600 mb-4">{{ __('Mark your calendars for these exciting events and community gatherings coming soon.') }}</p>
                    <a href="#" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
                        {{ __('Read more') }}
                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </article>
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
                    3
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
