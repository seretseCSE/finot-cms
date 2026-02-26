<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Blog Post') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-navigation currentPage="blog" />
    
    <main class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <article class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="h-64 bg-gradient-to-r from-blue-500 to-purple-600"></div>
            
            <div class="p-8">
                <div class="text-center mb-8">
                    <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ __('Community Outreach Success Stories') }}</h1>
                    <div class="flex items-center justify-center text-gray-500 text-sm">
                        <time>{{ date('F j, Y') }}</time>
                        <span class="mx-2">•</span>
                        <span>{{ __('By Admin') }}</span>
                    </div>
                </div>
                
                <div class="prose max-w-none">
                    <p class="text-lg text-gray-600 mb-6">
                        {{ __('We are thrilled to share the incredible impact our community outreach programs have had over the past few months. Through dedication and collaboration, we have been able to touch countless lives and make meaningful differences in our community.') }}
                    </p>
                    
                    <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">{{ __('Recent Achievements') }}</h2>
                    <p class="text-gray-600 mb-6">
                        {{ __('Our food distribution program has served over 500 families, providing essential groceries and meals to those in need. The response from volunteers has been overwhelming, with over 100 community members stepping up to help with collection, sorting, and distribution.') }}
                    </p>
                    
                    <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">{{ __('Education Initiative Success') }}</h2>
                    <p class="text-gray-600 mb-6">
                        {{ __('The after-school tutoring program has seen remarkable results, with 85% of participating students showing improved grades and confidence. Our volunteer tutors have worked tirelessly to provide personalized attention and support to each student.') }}
                    </p>
                    
                    <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">{{ __('Looking Ahead') }}</h2>
                    <p class="text-gray-600 mb-6">
                        {{ __('As we move forward, we are excited to expand our reach and introduce new programs to serve even more community members. We are currently planning a senior assistance program and a youth mentorship initiative that will launch next month.') }}
                    </p>
                    
                    <p class="text-gray-600 mb-6">
                        {{ __('Thank you to everyone who has contributed to these efforts. Your support, whether through volunteering, donations, or simply spreading the word, makes all the difference.') }}
                    </p>
                </div>
                
                <div class="mt-12 pt-8 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            <span>{{ __('Categories:') }}</span>
                            <a href="#" class="ml-2 text-blue-600 hover:text-blue-700">{{ __('Community') }}</a>,
                            <a href="#" class="ml-1 text-blue-600 hover:text-blue-700">{{ __('Outreach') }}</a>
                        </div>
                        <div class="flex space-x-4">
                            <a href="{{ route('blog') }}" class="text-blue-600 hover:text-blue-700 font-medium">
                                ← {{ __('Back to Blog') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </main>
    
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
        </div>
    </footer>
</body>
</html>
