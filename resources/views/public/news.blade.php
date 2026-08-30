@extends('layouts.public')

@section('title', __('News & Events'))

@section('content')

<x-public.page-hero
    :title="__('News & Events')"
    :subtitle="__('Stay informed with announcements and events from our community.')"
    :eyebrow="__('Updates')"
    :image="asset('images/hero-bg.webp')"
/>

<section class="ft-section pt-10">
    <div class="ft-container">
        <div class="flex flex-wrap gap-2 mb-10 border-b pb-0" style="border-color: var(--ft-border);">
            @foreach([
                'announcements' => __('Announcements'),
                'events' => __('Events'),
            ] as $tab => $label)
                <a href="{{ route('news', array_merge(request()->except('tab', 'page', 'announcements_page'), ['tab' => $tab])) }}"
                   class="px-5 py-3 text-sm font-semibold rounded-t-xl transition-colors
                   {{ ($activeTab ?? 'announcements') === $tab
                        ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-b-0'
                        : 'text-slate-500 hover:text-primary-500' }}"
                   style="{{ ($activeTab ?? 'announcements') === $tab ? 'border-color: var(--ft-border);' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if(($activeTab ?? 'announcements') === 'announcements')
            @if($announcements->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($announcements as $announcement)
                        <a href="{{ route('announcements.show', $announcement->id) }}" class="ft-surface rounded-2xl overflow-hidden no-underline group reveal">
                            <div class="aspect-[16/10] overflow-hidden bg-slate-200 dark:bg-slate-800">
                                <img src="{{ $announcement->image_url ?? asset('images/masonry-portfolio/masonry-portfolio-8.jpg') }}" alt="{{ $announcement->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy">
                            </div>
                            <div class="p-5">
                                @if($announcement->is_urgent)
                                    <span class="inline-block text-[0.65rem] font-bold uppercase tracking-widest text-red-500 mb-2">{{ __('Urgent') }}</span>
                                @endif
                                <h3 class="font-semibold ft-ink group-hover:text-primary-500 transition-colors font-['Noto_Sans_Ethiopic']">
                                    {{ Str::limit(app()->getLocale()==='am' ? ($announcement->title_am ?? $announcement->title) : $announcement->title, 70) }}
                                </h3>
                                <p class="text-sm mt-2 line-clamp-2" style="color: var(--ft-ink-muted);">
                                    {{ Str::limit(strip_tags(app()->getLocale()==='am' ? ($announcement->content_am ?? $announcement->content) : $announcement->content), 120) }}
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-10">{{ $announcements->withQueryString()->links() }}</div>
            @else
                <div class="ft-surface rounded-2xl p-12 text-center">
                    <p class="ft-ink font-semibold">{{ __('No announcements at this time.') }}</p>
                </div>
            @endif
        @endif

        @if(($activeTab ?? 'announcements') === 'events')
            <div class="flex flex-col gap-3">
                @forelse($upcomingEvents as $event)
                    @php $date = \Carbon\Carbon::parse($event->date_time); @endphp
                    <a href="{{ route('events.show', $event) }}" class="ft-event-row reveal no-underline group">
                        <div class="ft-event-row__thumb">
                            <img src="{{ $event->featured_image_url ?? asset('images/hero-bg.webp') }}" alt="{{ $event->name }}" loading="lazy">
                        </div>
                        <div class="flex-1 p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="sm:w-16 text-center sm:text-left shrink-0">
                                <div class="text-2xl font-extrabold text-primary-500 leading-none">{{ $date->format('d') }}</div>
                                <div class="text-xs font-semibold uppercase tracking-widest mt-1" style="color: var(--ft-ink-muted);">{{ $date->isoFormat('MMM') }}</div>
                            </div>
                            <div class="flex-1">
                                <div class="font-semibold text-lg ft-ink group-hover:text-primary-500">{{ $event->name }}</div>
                                <div class="text-sm mt-1" style="color: var(--ft-ink-muted);">
                                    {{ $date->format('h:i A') }}@if($event->location) · {{ $event->location }}@endif
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="ft-surface rounded-2xl p-12 text-center">
                        <p class="ft-ink font-semibold">{{ __('No Upcoming Events') }}</p>
                    </div>
                @endforelse
            </div>
        @endif

    </div>
</section>

@endsection
