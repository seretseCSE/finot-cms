@extends('layouts.public')

@section('title', __('Welcome to Finote Tsidik'))

@section('content')

{{-- Hero Section --}}
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

{{-- Upcoming Tours Section --}}
@if($upcomingTours->count() > 0)
<section class="py-12 bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-3xl font-bold text-gray-900">{{ __('Upcoming Tours') }}</h2>
                <p class="text-lg text-gray-600 mt-2">{{ __('Join us for spiritual journeys and fellowship') }}</p>
            </div>
            <a href="{{ route('tours.index') }}" class="hidden sm:inline-flex items-center justify-center rounded-md bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('View All Tours') }}
            </a>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($upcomingTours as $tour)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 border border-gray-200">
                    @if($tour->image)
                        <div class="h-48 overflow-hidden">
                            <img src="{{ asset('storage/' . $tour->image) }}" alt="{{ $tour->place }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                        </div>
                    @else
                        <div class="h-48 bg-gradient-to-r from-blue-400 to-purple-500 flex items-center justify-center">
                            <div class="text-white text-center">
                                <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                </svg>
                                <p class="text-lg font-semibold">{{ $tour->place }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ $tour->place }}</h3>
                        <p class="text-gray-600 mb-4 text-sm line-clamp-2">{{ Str::limit($tour->description, 120) }}</p>

                        <div class="space-y-2 mb-4">
                            <div class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span>{{ $tour->ethiopian_date }}</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>{{ $tour->start_time }}</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                                <span>{{ $tour->formatted_cost }}</span>
                            </div>
                        </div>

                        @if($tour->is_registration_open)
                            <a href="{{ route('tour.register', $tour->id) }}" class="block w-full text-center inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                {{ __('Register Now') }}
                            </a>
                        @else
                            <button disabled class="block w-full text-center inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">
                                {{ __('Registration Closed') }}
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="text-center mt-8 sm:hidden">
            <a href="{{ route('tours.index') }}" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('View All Tours') }}
            </a>
        </div>
    </div>
</section>
@endif

{{-- Stats & Links Section --}}
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
                    <a href="{{ route('news') }}" class="text-sm text-blue-600 hover:text-blue-700">{{ __('Calendar') }}</a>
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
                    <a href="{{ route('courses.index') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Programs') }}</a>
                    <a href="{{ route('songs.index') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Songs') }}</a>
                    <a href="{{ route('media') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Media') }}</a>
                    <a href="{{ route('library') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Library') }}</a>
                    <a href="{{ route('contact') }}" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">{{ __('Contact') }}</a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Fundraising Progress Section --}}
<section class="py-12 bg-gradient-to-br from-blue-50 to-purple-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">{{ __('Fundraising Progress') }}</h2>
            <p class="text-lg text-gray-600">{{ __('Support our mission and community through various campaigns') }}</p>
        </div>
        
        <div id="fundraising-progress" class="grid lg:grid-cols-3 gap-6">
            <div class="col-span-full text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <p class="text-gray-500 mt-2">{{ __('Loading fundraising data...') }}</p>
            </div>
        </div>

        <div class="text-center mt-8">
            <a href="{{ route('fundraising.index') }}" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('View All Campaigns') }}
            </a>
        </div>
    </div>
</section>

{{-- Featured Library Resources Section --}}
<section class="py-12 bg-white border-y border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-3xl font-bold text-gray-900">{{ __('Library Resources') }}</h2>
                <p class="text-lg text-gray-600 mt-2">{{ __('Access our collection of educational materials and documents') }}</p>
            </div>
            <a href="{{ route('library') }}" class="hidden sm:inline-flex items-center justify-center rounded-md bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('View All Resources') }}
            </a>
        </div>

        @if($featuredLibraryResources->count() > 0)
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($featuredLibraryResources as $resource)
                    <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow border border-gray-200 overflow-hidden">
                        <div class="h-40 bg-gradient-to-r from-blue-400 to-blue-600 flex items-center justify-center">
                            <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"></path>
                            </svg>
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 mb-1 line-clamp-2" title="{{ $resource->title }}">{{ $resource->title }}</h3>
                            @if($resource->category)
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">{{ $resource->category->name }}</span>
                            @endif
                            @if($resource->description)
                                <p class="text-sm text-gray-600 mt-2 line-clamp-2">{{ $resource->description }}</p>
                            @endif
                            <div class="flex items-center justify-between mt-3">
                                <span class="text-xs text-gray-500">{{ $resource->formatted_file_size }}</span>
                                <a href="{{ route('library.download', $resource) }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    {{ __('Download') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 bg-gray-50 rounded-lg">
                <div class="text-gray-400 mb-4">
                    <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">{{ __('No Featured Resources') }}</h3>
                <p class="text-gray-500">{{ __('Check the Library page to browse all available resources.') }}</p>
                <a href="{{ route('library') }}" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700 mt-4">
                    {{ __('Browse Library') }}
                </a>
            </div>
        @endif

        <div class="text-center mt-8 sm:hidden">
            <a href="{{ route('library') }}" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                {{ __('View All Resources') }}
            </a>
        </div>

        @if($totalLibraryResources > 0)
            <div class="mt-6 text-center">
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    {{ __(':count resources available', ['count' => $totalLibraryResources]) }}
                </span>
            </div>
        @endif
    </div>
</section>

{{-- FAQs Section --}}
<section class="py-12 bg-white border-y border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-8 items-start">
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

{{-- CTA Section --}}
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

@endsection

@push('scripts')
<script>
    async function loadFundraisingData() {
        try {
            const response = await fetch('{{ route('fundraising.api') }}');
            const data = await response.json();
            
            const container = document.getElementById('fundraising-progress');
            
            if (data.campaigns.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full text-center py-8">
                        <div class="text-gray-400 mb-4">
                            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">{{ __('No Active Campaigns') }}</h3>
                        <p class="text-gray-500">{{ __('Check back later for new fundraising campaigns.') }}</p>
                    </div>
                `;
                return;
            }
            
            const campaignsToShow = data.campaigns.slice(0, 3);
            
            container.innerHTML = campaignsToShow.map(campaign => `
                <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                    <div class="h-32 bg-gradient-to-br from-blue-400 to-purple-500 flex items-end">
                        <div class="p-4">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-sm">
                                ${campaign.status}
                            </span>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-2">${campaign.campaign_name}</h3>
                        
                        ${campaign.campaign_category ? `
                        <div class="mb-3">
                            <span class="inline-block px-2 py-1 rounded text-xs font-medium
                                ${campaign.campaign_category === 'Building' ? 'bg-red-100 text-red-800' : ''}
                                ${campaign.campaign_category === 'Missionary' ? 'bg-green-100 text-green-800' : ''}
                                ${campaign.campaign_category === 'Charity' ? 'bg-yellow-100 text-yellow-800' : ''}
                                ${campaign.campaign_category === 'General' ? 'bg-gray-100 text-gray-800' : ''}">
                                ${campaign.campaign_category}
                            </span>
                        </div>
                        ` : ''}
                        
                        ${campaign.description ? `
                        <p class="text-gray-600 mb-4 line-clamp-2">${campaign.description}</p>
                        ` : ''}
                        
                        <div class="mb-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-2">
                                <span><strong>ETB ${Number(campaign.total_raised).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong> raised</span>
                                <span>${campaign.progress_percentage}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full transition-all duration-500" 
                                     style="width: ${Math.min(100, campaign.progress_percentage)}%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>Goal: ETB ${Number(campaign.target_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                ${campaign.days_remaining !== null ? `<span>${campaign.days_remaining} days left</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
            
        } catch (error) {
            console.error('Error loading fundraising data:', error);
            document.getElementById('fundraising-progress').innerHTML = `
                <div class="col-span-full text-center py-8">
                    <p class="text-gray-500">{{ __('Unable to load fundraising data. Please try again later.') }}</p>
                </div>
            `;
        }
    }
    
    document.addEventListener('DOMContentLoaded', loadFundraisingData);
</script>
@endpush
