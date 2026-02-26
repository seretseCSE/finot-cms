<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Song Details') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-navigation currentPage="songs" />
    
    <main class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-48"></div>
            
            <div class="p-8">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('Amazing Grace') }}</h1>
                    <div class="flex items-center text-gray-500 text-sm space-x-4">
                        <span>{{ __('Traditional Hymn') }}</span>
                        <span>•</span>
                        <span>{{ __('Duration: 3:45') }}</span>
                        <span>•</span>
                        <span>{{ __('Key: G Major') }}</span>
                    </div>
                </div>
                
                <!-- Audio Player -->
                <div class="bg-gray-100 rounded-lg p-6 mb-8">
                    <div class="flex items-center space-x-4">
                        <button class="w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center hover:bg-blue-700">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"></path>
                            </svg>
                        </button>
                        <div class="flex-1">
                            <div class="bg-gray-300 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: 35%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>1:20</span>
                                <span>3:45</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lyrics -->
                <div class="mb-8">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-4">{{ __('Lyrics') }}</h2>
                    <div class="prose max-w-none">
                        <div class="bg-gray-50 rounded-lg p-6">
                            <p class="text-gray-700 mb-4">
                                Amazing grace, how sweet the sound<br>
                                That saved a wretch like me<br>
                                I once was lost, but now am found<br>
                                Was blind but now I see
                            </p>
                            <p class="text-gray-700 mb-4">
                                'Twas grace that taught my heart to fear<br>
                                And grace my fears relieved<br>
                                How precious did that grace appear<br>
                                The hour I first believed
                            </p>
                            <p class="text-gray-700 mb-4">
                                Through many dangers, toils, and snares<br>
                                I have already come<br>
                                'Tis grace has brought me safe thus far<br>
                                And grace will lead me home
                            </p>
                            <p class="text-gray-700 mb-4">
                                When we've been there ten thousand years<br>
                                Bright shining as the sun<br>
                                We've no less days to sing God's praise<br>
                                Than when we've first begun
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Song Details -->
                <div class="grid md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Song Information') }}</h3>
                        <dl class="space-y-2">
                            <div class="flex">
                                <dt class="font-medium text-gray-600 w-32">{{ __('Writer:') }}</dt>
                                <dd class="text-gray-900">John Newton</dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-600 w-32">{{ __('Composer:') }}</dt>
                                <dd class="text-gray-900">Unknown (Traditional)</dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-600 w-32">{{ __('Year:') }}</dt>
                                <dd class="text-gray-900">1779</dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-600 w-32">{{ __('Category:') }}</dt>
                                <dd class="text-gray-900">{{ __('Hymns') }}</dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Related Songs') }}</h3>
                        <div class="space-y-2">
                            <a href="#" class="block text-blue-600 hover:text-blue-700">{{ __('How Great Thou Art') }}</a>
                            <a href="#" class="block text-blue-600 hover:text-blue-700">{{ __('Blessed Assurance') }}</a>
                            <a href="#" class="block text-blue-600 hover:text-blue-700">{{ __('Great Is Thy Faithfulness') }}</a>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                    <a href="{{ route('songs.index') }}" class="text-blue-600 hover:text-blue-700 font-medium">
                        ← {{ __('Back to Songs') }}
                    </a>
                    <div class="flex space-x-4">
                        <button class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            {{ __('Download PDF') }}
                        </button>
                        <button class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                            {{ __('Print') }}
                        </button>
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
