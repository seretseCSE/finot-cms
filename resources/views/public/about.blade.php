<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('About') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-navigation currentPage="about" />
    
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-6">{{ __('About Us') }}</h1>
            
            <div class="prose max-w-none">
                <p class="text-lg text-gray-600 mb-6">
                    {{ __('Welcome to our organization. We are dedicated to serving our community through various programs and initiatives.') }}
                </p>
                
                <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">{{ __('Our Mission') }}</h2>
                <p class="text-gray-600 mb-6">
                    {{ __('Our mission is to provide spiritual guidance, education, and support to our community members, fostering growth and development in all aspects of life.') }}
                </p>
                
                <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">{{ __('Our Vision') }}</h2>
                <p class="text-gray-600 mb-6">
                    {{ __('We envision a community where every individual has the opportunity to grow spiritually, intellectually, and socially, contributing to the betterment of society.') }}
                </p>
                
                <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">{{ __('Our Values') }}</h2>
                <ul class="list-disc list-inside text-gray-600 space-y-2">
                    <li>{{ __('Faith and spiritual growth') }}</li>
                    <li>{{ __('Education and continuous learning') }}</li>
                    <li>{{ __('Community service and outreach') }}</li>
                    <li>{{ __('Integrity and transparency') }}</li>
                    <li>{{ __('Respect and inclusivity') }}</li>
                </ul>
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
