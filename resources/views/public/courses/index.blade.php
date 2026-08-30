@extends('layouts.public')

@section('title', __('Courses'))

@section('content')

<x-public.page-hero
    :title="__('Courses')"
    :subtitle="__('Structured courses on the Orthodox faith, scripture, and church tradition.')"
    :eyebrow="__('Learning')"
    :image="asset('images/masonry-portfolio/masonry-portfolio-8.jpg')"
/>

<section class="ft-section">
    <div class="ft-container">

        <div class="text-center max-w-2xl mx-auto mb-12 md:mb-16">
            <form action="{{ route('courses.index') }}" method="GET" class="max-w-md mx-auto flex gap-2 reveal">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('Search courses...') }}"
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border bg-transparent text-sm outline-none"
                           style="border-color: var(--ft-border); color: var(--ft-ink);">
                </div>
                <button type="submit" class="btn-primary px-5 py-2.5 text-sm">{{ __('Search') }}</button>
            </form>
        </div>

        @if($categories->count() > 0)
            <div class="mb-12 reveal">
                <h2 class="text-lg font-semibold ft-ink mb-4">{{ __('Browse by Category') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    @foreach($categories as $cat)
                        @if($cat->slug)
                        <a href="{{ route('courses.browse', $cat->slug) }}" class="ft-surface p-5 flex items-center gap-3 rounded-2xl hover:border-primary-500/30 transition-all no-underline">
                        @else
                        <div class="ft-surface p-5 flex items-center gap-3 rounded-2xl opacity-60">
                        @endif
                            @if($cat->icon)
                                <span class="text-2xl">{!! $cat->icon !!}</span>
                            @endif
                            <div>
                                <div class="text-sm font-semibold ft-ink font-['Noto_Sans_Ethiopic']">{{ $cat->name_am ?? $cat->name }}</div>
                                <div class="text-xs" style="color: var(--ft-ink-muted);">{{ $cat->name }}</div>
                            </div>
                        @if($cat->slug)</a>@else</div>@endif
                    @endforeach
                </div>
            </div>
        @endif

        @if($courses->count() > 0)
            <div class="mb-6 reveal">
                <h2 class="text-lg font-semibold ft-ink">{{ __('Latest Courses') }}</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($courses as $course)
                    <a href="{{ route('courses.show', $course) }}" class="ft-surface overflow-hidden group flex flex-col rounded-2xl no-underline">
                        <div class="h-44 overflow-hidden bg-slate-200 dark:bg-slate-800">
                            <img src="{{ $course->featured_image ?: asset('images/masonry-portfolio/masonry-portfolio-8.jpg') }}" alt="{{ $course->title }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy">
                        </div>
                        <div class="p-6 flex-1 flex flex-col">
                            <div class="flex items-center gap-2 mb-3 flex-wrap">
                                @if($course->category)
                                    <span class="text-xs font-medium text-primary-600 dark:text-primary-400 bg-primary-500/10 px-2.5 py-0.5 rounded-full">{{ $course->category->name }}</span>
                                @endif
                            </div>
                            <h3 class="text-base font-bold ft-ink leading-snug mb-1 font-['Noto_Sans_Ethiopic']">{{ $course->title_am ?? $course->title }}</h3>
                            <p class="text-xs mb-3" style="color: var(--ft-ink-muted);">{{ $course->title }}</p>
                            @if($course->description)
                                <p class="text-sm leading-relaxed flex-1" style="color: var(--ft-ink-muted);">{{ Str::limit(strip_tags($course->description), 120) }}</p>
                            @endif
                            <div class="flex items-center justify-between pt-4 mt-auto border-t" style="border-color: var(--ft-border);">
                                <div class="flex items-center gap-3 text-xs" style="color: var(--ft-ink-muted);">
                                    @if($course->lesson_count)
                                        <span>{{ $course->lesson_count }} {{ __('lessons') }}</span>
                                    @endif
                                </div>
                                <span class="text-xs font-semibold text-primary-500">{{ __('Start') }} →</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-12 reveal">
                {{ $courses->links() }}
            </div>
        @elseif(!$search)
            <div class="text-center py-20 reveal">
                <p class="font-medium" style="color: var(--ft-ink-muted);">{{ __('No courses available yet.') }}</p>
            </div>
        @else
            <div class="text-center py-20 reveal">
                <p class="font-medium" style="color: var(--ft-ink-muted);">{{ __('No courses match your search.') }}</p>
            </div>
        @endif
    </div>
</section>
@endsection
