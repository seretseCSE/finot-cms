<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-navigation currentPage="home" />

    <main>
        <section class="bg-gradient-to-r from-blue-600 to-purple-700 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
                <div class="max-w-3xl">
                    <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">
                        {{ __('Welcome to') }} {{ config('app.name') }}
                    </h1>
                    <p class="mt-5 text-lg text-white/90">
                        {{ __('Faith, service, and fellowship — building a stronger community together.') }}
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('about') }}" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-blue-700 hover:bg-white/90">
                            {{ __('About Us') }}
                        </a>
                        <a href="{{ route('tours.index') }}" class="inline-flex items-center justify-center rounded-md border border-white/80 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">
                            {{ __('Tours') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">{{ __('Announcements') }}</h2>
                            <a href="{{ route('blog.index') }}" class="text-sm text-blue-600 hover:text-blue-700">{{ __('View all') }}</a>
                        </div>
                        <div class="space-y-4 text-sm">
                            <div>
                                <div class="font-medium text-gray-900">{{ __('Sunday service schedule updated') }}</div>
                                <div class="text-gray-600">{{ __('Please arrive 15 minutes early this week.') }}</div>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">{{ __('Youth program registration open') }}</div>
                                <div class="text-gray-600">{{ __('Register for the next season of activities.') }}</div>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">{{ __('Volunteer sign-up') }}</div>
                                <div class="text-gray-600">{{ __('Join the community service team this month.') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">{{ __('Upcoming Events') }}</h2>
                            <a href="{{ route('events') }}" class="text-sm text-blue-600 hover:text-blue-700">{{ __('Calendar') }}</a>
                        </div>
                        <div class="space-y-4 text-sm">
                            <div class="flex gap-3">
                                <div class="w-12 h-12 rounded-lg bg-blue-50 flex flex-col items-center justify-center">
                                    <div class="text-xs text-blue-700 font-medium">{{ date('M') }}</div>
                                    <div class="text-base font-bold text-blue-700">5</div>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ __('Worship Service') }}</div>
                                    <div class="text-gray-600">10:00 AM</div>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-12 h-12 rounded-lg bg-green-50 flex flex-col items-center justify-center">
                                    <div class="text-xs text-green-700 font-medium">{{ date('M') }}</div>
                                    <div class="text-base font-bold text-green-700">12</div>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ __('Youth Meeting') }}</div>
                                    <div class="text-gray-600">6:00 PM</div>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-12 h-12 rounded-lg bg-purple-50 flex flex-col items-center justify-center">
                                    <div class="text-xs text-purple-700 font-medium">{{ date('M') }}</div>
                                    <div class="text-base font-bold text-purple-700">15</div>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ __('Community Outreach') }}</div>
                                    <div class="text-gray-600">9:00 AM</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Quick Links') }}</h2>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <a href="{{ route('programs') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Programs') }}</a>
                            <a href="{{ route('songs.index') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Songs') }}</a>
                            <a href="{{ route('media') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Media') }}</a>
                            <a href="{{ route('library') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Library') }}</a>
                            <a href="{{ route('fundraising') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Fundraising') }}</a>
                            <a href="{{ route('contact') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Contact') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-12 bg-white border-y border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-8 items-start">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">{{ __('Fundraising Campaigns') }}</h2>
                        <p class="mt-2 text-gray-600">{{ __('Support active initiatives and help us expand our impact.') }}</p>

                        <div class="mt-6 space-y-4">
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between">
                                    <div class="font-semibold text-gray-900">{{ __('Youth Center Renovation') }}</div>
                                    <div class="text-sm text-gray-600">60%</div>
                                </div>
                                <div class="mt-2 h-2 bg-gray-100 rounded-full">
                                    <div class="h-2 bg-blue-600 rounded-full" style="width: 60%"></div>
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between">
                                    <div class="font-semibold text-gray-900">{{ __('Community Food Bank') }}</div>
                                    <div class="text-sm text-gray-600">57%</div>
                                </div>
                                <div class="mt-2 h-2 bg-gray-100 rounded-full">
                                    <div class="h-2 bg-green-600 rounded-full" style="width: 57%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <a href="{{ route('fundraising') }}" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                                {{ __('See fundraising') }}
                            </a>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">{{ __('FAQs') }}</h2>
                        <div class="mt-6 space-y-3">
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="font-medium text-gray-900">{{ __('Where are you located?') }}</div>
                                <div class="mt-1 text-sm text-gray-600">{{ __('See the address on the Contact page.') }}</div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="font-medium text-gray-900">{{ __('How can I volunteer?') }}</div>
                                <div class="mt-1 text-sm text-gray-600">{{ __('Send us a message via Contact and we will respond.') }}</div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="font-medium text-gray-900">{{ __('How do I switch language?') }}</div>
                                <div class="mt-1 text-sm text-gray-600">{{ __('Use the language switcher in the header.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl bg-gray-900 px-6 py-10 sm:px-10 text-white flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div>
                        <h2 class="text-2xl font-bold">{{ __('Stay connected') }}</h2>
                        <p class="mt-1 text-white/80">{{ __('Get updates about events, programs, and announcements.') }}</p>
                    </div>
                    <a href="{{ route('contact') }}" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-gray-900 hover:bg-white/90">
                        {{ __('Contact us') }}
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
        </div>
    </footer>
</body>
</html>
