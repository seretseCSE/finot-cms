<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Fundraising') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-navigation currentPage="fundraising" />
    
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ __('Support Our Mission') }}</h1>
            <p class="text-xl text-gray-600">{{ __('Your generous contributions help us serve our community and spread our message.') }}</p>
        </div>
        
        <!-- Active Campaigns -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Active Campaigns') }}</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Campaign 1 -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="h-48 bg-gradient-to-r from-blue-500 to-purple-600"></div>
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ __('Youth Center Renovation') }}</h3>
                        <p class="text-gray-600 mb-4">{{ __('Help us renovate our youth center to provide a safe and inspiring space for young people.') }}</p>
                        
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span>{{ __('Raised') }}: $15,000</span>
                                <span>{{ __('Goal') }}: $25,000</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-blue-600 h-3 rounded-full" style="width: 60%"></div>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">60% {{ __('funded') }}</div>
                        </div>
                        
                        <button class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition-colors">
                            {{ __('Donate Now') }}
                        </button>
                    </div>
                </div>
                
                <!-- Campaign 2 -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="h-48 bg-gradient-to-r from-green-500 to-teal-600"></div>
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ __('Community Food Bank') }}</h3>
                        <p class="text-gray-600 mb-4">{{ __('Support our food bank program that helps families in need throughout the year.') }}</p>
                        
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span>{{ __('Raised') }}: $8,500</span>
                                <span>{{ __('Goal') }}: $15,000</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-green-600 h-3 rounded-full" style="width: 57%"></div>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">57% {{ __('funded') }}</div>
                        </div>
                        
                        <button class="w-full bg-green-600 text-white py-2 rounded-md hover:bg-green-700 transition-colors">
                            {{ __('Donate Now') }}
                        </button>
                    </div>
                </div>
                
                <!-- Campaign 3 -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="h-48 bg-gradient-to-r from-red-500 to-orange-600"></div>
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ __('Educational Scholarships') }}</h3>
                        <p class="text-gray-600 mb-4">{{ __('Provide scholarships for students to pursue their educational goals.') }}</p>
                        
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span>{{ __('Raised') }}: $12,000</span>
                                <span>{{ __('Goal') }}: $20,000</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-red-600 h-3 rounded-full" style="width: 60%"></div>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">60% {{ __('funded') }}</div>
                        </div>
                        
                        <button class="w-full bg-red-600 text-white py-2 rounded-md hover:bg-red-700 transition-colors">
                            {{ __('Donate Now') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Donation Options -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Ways to Give') }}</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- One-time Donation -->
                <div class="text-center p-6 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">{{ __('One-Time Gift') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('Make a single donation to support our work.') }}</p>
                </div>
                
                <!-- Monthly Donation -->
                <div class="text-center p-6 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">{{ __('Monthly Partner') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('Become a monthly supporter for sustained impact.') }}</p>
                </div>
                
                <!-- Tribute Gift -->
                <div class="text-center p-6 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">{{ __('Tribute Gift') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('Honor someone special with your donation.') }}</p>
                </div>
                
                <!-- Corporate Sponsor -->
                <div class="text-center p-6 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">{{ __('Corporate Sponsor') }}</h3>
                    <p class="text-sm text-gray-600">{{ __('Partner with us as a corporate sponsor.') }}</p>
                </div>
            </div>
        </div>
        
        <!-- Impact Stories -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">{{ __('Your Impact') }}</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600 mb-2">500+</div>
                    <div class="text-gray-600">{{ __('Families Helped') }}</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600 mb-2">100+</div>
                    <div class="text-gray-600">{{ __('Students Supported') }}</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-purple-600 mb-2">50+</div>
                    <div class="text-gray-600">{{ __('Community Projects') }}</div>
                </div>
            </div>
        </div>
        
        <!-- Quick Donation Form -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-8 text-white">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold mb-2">{{ __('Make a Quick Donation') }}</h2>
                <p>{{ __('Every contribution makes a difference.') }}</p>
            </div>
            
            <div class="max-w-md mx-auto">
                <div class="grid grid-cols-4 gap-2 mb-4">
                    <button class="bg-white bg-opacity-20 hover:bg-opacity-30 py-3 rounded-md font-medium">$25</button>
                    <button class="bg-white bg-opacity-20 hover:bg-opacity-30 py-3 rounded-md font-medium">$50</button>
                    <button class="bg-white bg-opacity-20 hover:bg-opacity-30 py-3 rounded-md font-medium">$100</button>
                    <button class="bg-white bg-opacity-20 hover:bg-opacity-30 py-3 rounded-md font-medium">$250</button>
                </div>
                
                <div class="flex gap-2">
                    <input type="number" placeholder="{{ __('Custom amount') }}" class="flex-1 px-4 py-3 rounded-md text-gray-900">
                    <button class="bg-white text-blue-600 px-6 py-3 rounded-md font-medium hover:bg-gray-100">
                        {{ __('Donate') }}
                    </button>
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
