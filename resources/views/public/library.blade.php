<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Library') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-navigation currentPage="library" />
    
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ __('Library') }}</h1>
            <p class="text-xl text-gray-600">{{ __('Access our collection of books, resources, and educational materials.') }}</p>
        </div>
        
        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col md:flex-row gap-4">
                <input type="text" placeholder="{{ __('Search books, resources...') }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                <select class="px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">{{ __('All Categories') }}</option>
                    <option value="">{{ __('Theology') }}</option>
                    <option value="">{{ __('Biblical Studies') }}</option>
                    <option value="">{{ __('Christian Living') }}</option>
                    <option value="">{{ __('Church History') }}</option>
                    <option value="">{{ __('Children\'s Books') }}</option>
                </select>
                <button class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    {{ __('Search') }}
                </button>
            </div>
        </div>
        
        <!-- Library Stats -->
        <div class="grid md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-blue-600 mb-2">1,250+</div>
                <div class="text-gray-600">{{ __('Total Books') }}</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-green-600 mb-2">85</div>
                <div class="text-gray-600">{{ __('New Arrivals') }}</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-purple-600 mb-2">320</div>
                <div class="text-gray-600">{{ __('Digital Resources') }}</div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <div class="text-3xl font-bold text-orange-600 mb-2">45</div>
                <div class="text-gray-600">{{ __('Audio Books') }}</div>
            </div>
        </div>
        
        <!-- Books Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- Book 1 -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="h-64 bg-gradient-to-r from-blue-400 to-blue-600 rounded-t-lg flex items-center justify-center">
                    <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"></path>
                    </svg>
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-1">{{ __('The Purpose Driven Life') }}</h3>
                    <p class="text-sm text-gray-500 mb-2">Rick Warren</p>
                    <p class="text-xs text-gray-600 mb-3">{{ __('A spiritual guide to finding your purpose and meaning in life.') }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">{{ __('Christian Living') }}</span>
                        <button class="text-blue-600 hover:text-blue-700 text-sm font-medium">{{ __('Borrow') }}</button>
                    </div>
                </div>
            </div>
            
            <!-- Book 2 -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="h-64 bg-gradient-to-r from-green-400 to-green-600 rounded-t-lg flex items-center justify-center">
                    <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"></path>
                    </svg>
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-1">{{ __('Knowing God') }}</h3>
                    <p class="text-sm text-gray-500 mb-2">J.I. Packer</p>
                    <p class="text-xs text-gray-600 mb-3">{{ __('A deep exploration of knowing God personally and intimately.') }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">{{ __('Theology') }}</span>
                        <button class="text-blue-600 hover:text-blue-700 text-sm font-medium">{{ __('Borrow') }}</button>
                    </div>
                </div>
            </div>
            
            <!-- Book 3 -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="h-64 bg-gradient-to-r from-purple-400 to-purple-600 rounded-t-lg flex items-center justify-center">
                    <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"></path>
                    </svg>
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-1">{{ __('Mere Christianity') }}</h3>
                    <p class="text-sm text-gray-500 mb-2">C.S. Lewis</p>
                    <p class="text-xs text-gray-600 mb-3">{{ __('A rational defense of Christian faith and moral teachings.') }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">{{ __('Apologetics') }}</span>
                        <button class="text-blue-600 hover:text-blue-700 text-sm font-medium">{{ __('Borrow') }}</button>
                    </div>
                </div>
            </div>
            
            <!-- Book 4 -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                <div class="h-64 bg-gradient-to-r from-red-400 to-red-600 rounded-t-lg flex items-center justify-center">
                    <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"></path>
                    </svg>
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-1">{{ __('The Cost of Discipleship') }}</h3>
                    <p class="text-sm text-gray-500 mb-2">Dietrich Bonhoeffer</p>
                    <p class="text-xs text-gray-600 mb-3">{{ __('A challenging look at what it means to follow Christ.') }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">{{ __('Discipleship') }}</span>
                        <button class="text-blue-600 hover:text-blue-700 text-sm font-medium">{{ __('Borrow') }}</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Digital Resources Section -->
        <div class="mt-12 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-6">{{ __('Digital Resources') }}</h3>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="text-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h4 class="font-medium text-gray-900 mb-2">{{ __('E-Books') }}</h4>
                    <p class="text-sm text-gray-600">{{ __('Access our digital book collection online.') }}</p>
                </div>
                
                <div class="text-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                        </svg>
                    </div>
                    <h4 class="font-medium text-gray-900 mb-2">{{ __('Audio Books') }}</h4>
                    <p class="text-sm text-gray-600">{{ __('Listen to audiobooks on the go.') }}</p>
                </div>
                
                <div class="text-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h4 class="font-medium text-gray-900 mb-2">{{ __('Video Lectures') }}</h4>
                    <p class="text-sm text-gray-600">{{ __('Watch educational video content.') }}</p>
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
