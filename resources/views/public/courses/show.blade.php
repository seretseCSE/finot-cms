@extends('layouts.public')

@section('title', $course->title)

@section('content')
<section class="py-20 md:py-32 bg-white dark:bg-slate-900 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="flex items-center gap-2 text-sm text-gray-400 dark:text-slate-500 mb-8 reveal flex-wrap" data-delay="0.05">
            <a href="{{ route('courses.index') }}" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">{{ __('Courses') }}</a>
            @if($course->category)
                @foreach($course->category->breadcrumbs as $crumb)
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    @if($crumb->slug)
                        <a href="{{ route('courses.browse', $crumb->slug) }}" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors font-['Noto_Sans_Ethiopic']">{{ $crumb->name_am ?? $crumb->name }}</a>
                    @else
                        <span class="text-gray-500 dark:text-slate-400 font-['Noto_Sans_Ethiopic']">{{ $crumb->name_am ?? $crumb->name }}</span>
                    @endif
                @endforeach
            @endif
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            <span class="text-secondary-500 dark:text-secondary-400 font-medium font-['Noto_Sans_Ethiopic']">{{ $course->title_am ?? $course->title }}</span>
        </div>

        <div class="card p-6 md:p-8 mb-10 reveal" data-delay="0.1">
            <div class="flex flex-col md:flex-row gap-6">
                @if($course->featured_image)
                    <div class="w-full md:w-56 h-48 md:h-56 rounded-xl overflow-hidden shrink-0 bg-gray-100 dark:bg-slate-800">
                        <img src="{{ $course->featured_image }}" alt="{{ $course->title }}" class="w-full h-full object-cover" loading="lazy">
                    </div>
                @endif
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white mb-2 font-['Noto_Sans_Ethiopic']">{{ $course->title_am ?? $course->title }}</h1>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mb-4">{{ $course->title }}</p>

                    <div class="flex flex-wrap gap-3 mb-4">
                        @if($course->instructor)
                            <span class="inline-flex items-center gap-1.5 text-sm text-gray-600 dark:text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                                {{ $course->instructor }}
                            </span>
                        @endif
                        @if($course->difficulty)
                            <span class="text-sm text-gray-600 dark:text-slate-400">{{ __($course->difficulty) }}</span>
                        @endif
                        @if($course->duration)
                            <span class="text-sm text-gray-600 dark:text-slate-400">{{ $course->duration }}</span>
                        @endif
                        <span class="text-sm text-gray-600 dark:text-slate-400">{{ $course->lesson_count }} {{ __('lessons') }}</span>
                    </div>

                    @if($course->description)
                        <p class="text-gray-600 dark:text-slate-400 leading-relaxed text-sm">{{ $course->description }}</p>
                    @endif
                    @if($course->description_am)
                        <p class="text-gray-600 dark:text-slate-400 leading-relaxed mt-3 font-['Noto_Sans_Ethiopic']">{{ $course->description_am }}</p>
                    @endif
                </div>
            </div>
        </div>

        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6 reveal" data-delay="0.1">{{ __('Course Lessons') }}</h2>

        @if($course->activeLessons->count() > 0)
            <div class="space-y-2 reveal" data-delay="0.15">
                @foreach($course->activeLessons as $index => $lesson)
                    <a href="{{ route('courses.lesson', [$course, $lesson]) }}" class="card p-4 flex items-center gap-4 hover:border-primary-500/30 transition-all group">
                        <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800/30 flex items-center justify-center shrink-0 font-bold text-sm text-primary-600 dark:text-primary-400">
                            {{ $index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors font-['Noto_Sans_Ethiopic']">{{ $lesson->title_am ?? $lesson->title }}</h3>
                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ $lesson->title }}</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 dark:text-slate-500 group-hover:text-primary-500 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @endforeach
            </div>
        @else
            <div class="text-center py-16 reveal" data-delay="0.15">
                <p class="text-gray-600 dark:text-slate-400 font-medium">{{ __('No lessons published yet.') }}</p>
            </div>
        @endif
    </div>
</section>
@endsection
