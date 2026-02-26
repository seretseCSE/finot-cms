@props(['currentPage' => ''])

<header class="bg-white shadow-sm border-b border-gray-200" x-data="{ mobileMenuOpen: false }">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="/" class="flex items-center">
                    <img src="{{ asset('storage/logo.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
                    <span class="ml-2 text-xl font-bold text-gray-900">{{ config('app.name') }}</span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="/" class="{{ $currentPage === 'home' ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium transition-colors">
                    {{ __('Home') }}
                </a>
                <a href="{{ route('about') }}" class="{{ $currentPage === 'about' ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium transition-colors">
                    {{ __('About') }}
                </a>
                <a href="{{ route('programs') }}" class="{{ $currentPage === 'programs' ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium transition-colors">
                    {{ __('Programs') }}
                </a>
                <a href="{{ route('blog.index') }}" class="{{ $currentPage === 'blog' ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium transition-colors">
                    {{ __('Blog') }}
                </a>
                <a href="{{ route('songs.index') }}" class="{{ $currentPage === 'songs' ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium transition-colors">
                    {{ __('Songs') }}
                </a>
                <a href="{{ route('media') }}" class="{{ $currentPage === 'media' ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium transition-colors">
                    {{ __('Media') }}
                </a>
                <a href="{{ route('events') }}" class="{{ $currentPage === 'events' ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium transition-colors">
                    {{ __('Events') }}
                </a>
                <a href="{{ route('library') }}" class="{{ $currentPage === 'library' ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium transition-colors">
                    {{ __('Library') }}
                </a>
                <a href="{{ route('fundraising') }}" class="{{ $currentPage === 'fundraising' ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium transition-colors">
                    {{ __('Fundraising') }}
                </a>
                <a href="{{ route('contact') }}" class="{{ $currentPage === 'contact' ? 'text-blue-600' : 'text-gray-700 hover:text-blue-600' }} px-3 py-2 text-sm font-medium transition-colors">
                    {{ __('Contact') }}
                </a>
            </div>

            <!-- Language Switcher & Mobile Menu Button -->
            <div class="flex items-center space-x-4">
                <!-- Language Switcher -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-1 text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                        </svg>
                        <span>{{ app()->getLocale() === 'am' ? 'አማ' : 'EN' }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-50">
                        <div class="py-1">
                            <form method="POST" action="{{ route('language.switch', 'en') }}" class="block">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'en' ? 'bg-blue-50 text-blue-600' : '' }}">
                                    English
                                </button>
                            </form>
                            <form method="POST" action="{{ route('language.switch', 'am') }}" class="block">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'am' ? 'bg-blue-50 text-blue-600' : '' }}">
                                    አማርኛ
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Mobile menu button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 rounded-md text-gray-700 hover:text-blue-600 hover:bg-gray-100">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div x-show="mobileMenuOpen" x-transition class="md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="/" class="{{ $currentPage === 'home' ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100' }} block px-3 py-2 rounded-md text-base font-medium">
                    {{ __('Home') }}
                </a>
                <a href="{{ route('about') }}" class="{{ $currentPage === 'about' ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100' }} block px-3 py-2 rounded-md text-base font-medium">
                    {{ __('About') }}
                </a>
                <a href="{{ route('programs') }}" class="{{ $currentPage === 'programs' ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100' }} block px-3 py-2 rounded-md text-base font-medium">
                    {{ __('Programs') }}
                </a>
                <a href="{{ route('blog.index') }}" class="{{ $currentPage === 'blog' ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100' }} block px-3 py-2 rounded-md text-base font-medium">
                    {{ __('Blog') }}
                </a>
                <a href="{{ route('songs.index') }}" class="{{ $currentPage === 'songs' ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100' }} block px-3 py-2 rounded-md text-base font-medium">
                    {{ __('Songs') }}
                </a>
                <a href="{{ route('media') }}" class="{{ $currentPage === 'media' ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100' }} block px-3 py-2 rounded-md text-base font-medium">
                    {{ __('Media') }}
                </a>
                <a href="{{ route('events') }}" class="{{ $currentPage === 'events' ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100' }} block px-3 py-2 rounded-md text-base font-medium">
                    {{ __('Events') }}
                </a>
                <a href="{{ route('library') }}" class="{{ $currentPage === 'library' ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100' }} block px-3 py-2 rounded-md text-base font-medium">
                    {{ __('Library') }}
                </a>
                <a href="{{ route('fundraising') }}" class="{{ $currentPage === 'fundraising' ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100' }} block px-3 py-2 rounded-md text-base font-medium">
                    {{ __('Fundraising') }}
                </a>
                <a href="{{ route('contact') }}" class="{{ $currentPage === 'contact' ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-100' }} block px-3 py-2 rounded-md text-base font-medium">
                    {{ __('Contact') }}
                </a>
            </div>
        </div>
    </nav>
</header>
