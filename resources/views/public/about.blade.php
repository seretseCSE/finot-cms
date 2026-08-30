@extends('layouts.public')

@section('title', $page?->title ?? __('About'))

@section('content')

<x-public.page-hero
    :title="($page?->title ?? __('About')) . ' ' . __('Us')"
    :subtitle="__('Discover the journey, mission, and community of Finot-Tsidik Sunday School — a legacy of faith since 1984 E.C.')"
    :eyebrow="__('Our Identity')"
    :image="asset('images/cta-bg.webp')"
/>

<section class="ft-section">
    <div class="ft-container">
        @if($page && $page->content)
            @php
                $contentSections = preg_split('/<h2[^>]*>(.*?)<\/h2>/', $page->content, -1, PREG_SPLIT_DELIM_CAPTURE);
                $icons = [
                    'book-open', 'lightbulb', 'sparkles', 'heart', 'bolt', 'device-phone'
                ];
                $gradients = [
                    'from-blue-500/20 to-blue-600/10',
                    'from-purple-500/20 to-purple-600/10',
                    'from-secondary-400/20 to-amber-600/10',
                    'from-purple-500/20 to-purple-600/10',
                    'from-red-500/20 to-red-600/10',
                    'from-blue-500/20 to-blue-600/10',
                ];
                $iconColors = [
                    'text-blue-400', 'text-purple-400', 'text-secondary-400', 'text-purple-400', 'text-red-400', 'text-blue-400'
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                @for($i = 0; $i < count($contentSections); $i += 2)
                    @if(isset($contentSections[$i + 1]) && !empty(trim($contentSections[$i + 1])))
                        @php
                            $title = strip_tags($contentSections[$i]);
                            $content = $contentSections[$i + 1];
                            $iconIdx = floor($i / 2) % count($icons);
                        @endphp

                        <div class="card p-6 md:p-8 hover:-translate-y-2 hover:shadow-2xl transition-all duration-500 reveal group" data-delay="{{ 0.05 + ($i/2) * 0.1 }}">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br {{ $gradients[$iconIdx] }} flex items-center justify-center mb-6 group-hover:scale-110 group-hover:-translate-y-1 transition-transform duration-500">
                                @if($icons[$iconIdx] === 'book-open')
                                <svg class="w-8 h-8 {{ $iconColors[$iconIdx] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                </svg>
                                @elseif($icons[$iconIdx] === 'lightbulb')
                                <svg class="w-8 h-8 {{ $iconColors[$iconIdx] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                                </svg>
                                @elseif($icons[$iconIdx] === 'sparkles')
                                <svg class="w-8 h-8 {{ $iconColors[$iconIdx] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                </svg>
                                @elseif($icons[$iconIdx] === 'heart')
                                <svg class="w-8 h-8 {{ $iconColors[$iconIdx] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                </svg>
                                @elseif($icons[$iconIdx] === 'bolt')
                                <svg class="w-8 h-8 {{ $iconColors[$iconIdx] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                                </svg>
                                @else
                                <svg class="w-8 h-8 {{ $iconColors[$iconIdx] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                                </svg>
                                @endif
                            </div>

                            <h3 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ $title }}</h3>
                            <div class="prose prose-gray dark:prose-invert max-w-none text-gray-600 dark:text-slate-400 leading-relaxed">
                                @sanitize($content)
                            </div>
                        </div>
                    @endif
                @endfor
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <div class="card p-6 md:p-8 hover:-translate-y-2 hover:shadow-2xl transition-all duration-500 reveal group" data-delay="0.1">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500/20 to-blue-600/10 flex items-center justify-center mb-6 group-hover:scale-110 group-hover:-translate-y-1 transition-transform duration-500">
                        <svg class="w-8 h-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                    </div>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ __('Our Mission') }}</h3>
                    <p class="text-xs font-semibold tracking-widest uppercase text-primary-500 dark:text-primary-400 mb-4">{{ __('Nurturing Faith, Building Community') }}</p>
                    <div class="text-gray-600 dark:text-slate-400 leading-relaxed space-y-3">
                        <p>{{ __('Our mission is to provide spiritual guidance, education, and support to our community members, fostering growth and development in all aspects of life.') }}</p>
                        <p>{{ __('We believe in the power of the Gospel to transform lives and the importance of preserving the rich traditions of the Ethiopian Orthodox Tewahedo Church.') }}</p>
                    </div>
                </div>

                <div class="card p-6 md:p-8 hover:-translate-y-2 hover:shadow-2xl transition-all duration-500 reveal group" data-delay="0.2">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-500/20 to-purple-600/10 flex items-center justify-center mb-6 group-hover:scale-110 group-hover:-translate-y-1 transition-transform duration-500">
                        <svg class="w-8 h-8 text-purple-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                        </svg>
                    </div>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ __('Our Vision') }}</h3>
                    <p class="text-xs font-semibold tracking-widest uppercase text-purple-500 dark:text-purple-400 mb-4">{{ __('A Lighthouse of Spiritual Wisdom') }}</p>
                    <div class="text-gray-600 dark:text-slate-400 leading-relaxed space-y-3">
                        <p>{{ __('We envision a community where every individual has the opportunity to grow spiritually, intellectually, and socially, contributing to the betterment of society.') }}</p>
                        <p>{{ __('Through steadfast faith and community, we strive to be a beacon of hope and a center for Orthodox teachings for generations to come.') }}</p>
                    </div>
                </div>

                <div class="card p-6 md:p-8 hover:-translate-y-2 hover:shadow-2xl transition-all duration-500 reveal group" data-delay="0.3">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-secondary-400/20 to-amber-600/10 flex items-center justify-center mb-6 group-hover:scale-110 group-hover:-translate-y-1 transition-transform duration-500">
                        <svg class="w-8 h-8 text-secondary-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                        </svg>
                    </div>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ __('Our Values') }}</h3>
                    <p class="text-xs font-semibold tracking-widest uppercase text-secondary-500 dark:text-secondary-400 mb-4">{{ __('Core Principles & Beliefs') }}</p>
                    <ul class="space-y-3">
                        @foreach([
                            __('Faith and spiritual growth'),
                            __('Education and continuous learning'),
                            __('Community service and outreach'),
                            __('Integrity and transparency'),
                            __('Respect and inclusivity')
                        ] as $val)
                        <li class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-slate-800/40 border border-gray-100 dark:border-slate-700/30 hover:bg-gray-100 dark:hover:bg-slate-800/60 hover:translate-x-1 transition-all duration-300">
                            <svg class="w-5 h-5 text-secondary-500 dark:text-secondary-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span class="text-sm text-gray-700 dark:text-slate-300">{{ $val }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if($page && $page->content_am)
            <div class="card p-8 md:p-12 mt-12 border-secondary-500/20 reveal" data-delay="0.2">
                @if($page->title_am)
                    <h2 class="text-2xl md:text-3xl font-bold text-secondary-500 dark:text-secondary-400 mb-8 text-center font-['Noto_Sans_Ethiopic']">{{ $page->title_am }}</h2>
                @endif
                <div class="text-gray-700 dark:text-slate-300 leading-relaxed text-lg font-['Noto_Sans_Ethiopic']">
                    @sanitize($page->content_am)
                </div>
            </div>
        @endif
    </div>
</section>

<section class="py-20 md:py-32 bg-gray-50 dark:bg-slate-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
            @php
                $statItems = [
                    ['count' => $stats['kids'] ?? 0,  'label' => __('Children'),     'color' => 'text-emerald-400', 'icon' => 'users'],
                    ['count' => $stats['youth'] ?? 0,  'label' => __('Youth'),        'color' => 'text-primary-400', 'icon' => 'book'],
                    ['count' => $stats['adults'] ?? 0, 'label' => __('Young Adults'), 'color' => 'text-secondary-400', 'icon' => 'group'],
                    ['count' => $stats['total'] ?? 0,  'label' => __('Total Members'), 'color' => 'text-white', 'icon' => 'heart'],
                ];
            @endphp
            @foreach($statItems as $stat)
            <div class="card text-center p-6 md:p-8 reveal" data-delay="{{ 0.1 + $loop->index * 0.1 }}">
                @if($stat['icon'] === 'users')
                <svg class="w-8 h-8 mx-auto {{ $stat['color'] }} mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                @elseif($stat['icon'] === 'book')
                <svg class="w-8 h-8 mx-auto {{ $stat['color'] }} mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
                @elseif($stat['icon'] === 'group')
                <svg class="w-8 h-8 mx-auto {{ $stat['color'] }} mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
                @else
                <svg class="w-8 h-8 mx-auto {{ $stat['color'] }} mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                </svg>
                @endif
                <div class="text-3xl md:text-4xl font-bold {{ $stat['color'] }}" data-counter="{{ $stat['count'] }}">{{ $stat['count'] }}</div>
                <div class="text-xs tracking-widest uppercase text-gray-500 dark:text-slate-400 mt-2">{{ $stat['label'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="py-20 md:py-32 bg-gradient-to-br from-gray-50 to-white dark:from-slate-900 dark:to-slate-950 text-center reveal">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-5">{{ __('Be Part of Our Journey') }}</h2>
        <p class="text-gray-600 dark:text-slate-400 max-w-xl mx-auto mb-9 text-lg">
            {{ __('Whether you want to learn, volunteer, or support us, there is a place for you here at Finot-Tsidik.') }}
        </p>
        <a href="{{ route('contact') }}" class="btn-primary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            {{ __('Contact Us') }}
        </a>
    </div>
</section>

@endsection
