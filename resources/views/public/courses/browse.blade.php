@extends('layouts.public')

@section('title', $category->name)

@section('content')
<section class="py-20 md:py-32 bg-white dark:bg-slate-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="flex items-center gap-2 text-sm text-gray-400 dark:text-slate-500 mb-8 reveal flex-wrap" data-delay="0.05">
            <a href="{{ route('courses.index') }}" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">{{ __('Courses') }}</a>
            @foreach($category->breadcrumbs as $crumb)
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                @if($crumb->id === $category->id)
                    <span class="text-secondary-500 dark:text-secondary-400 font-medium font-['Noto_Sans_Ethiopic']">{{ $crumb->name_am ?? $crumb->name }}</span>
                @else
                    <a href="{{ route('courses.browse', $crumb->slug) }}" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors font-['Noto_Sans_Ethiopic']">{{ $crumb->name_am ?? $crumb->name }}</a>
                @endif
            @endforeach
        </div>

        <div class="card p-6 md:p-8 mb-10 reveal" data-delay="0.1">
            <div class="flex items-center gap-4">
                @if($category->icon)
                    <span class="text-3xl">{!! $category->icon !!}</span>
                @endif
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white font-['Noto_Sans_Ethiopic']">{{ $category->name_am ?? $category->name }}</h1>
                    <p class="text-sm text-gray-500 dark:text-slate-400">{{ $category->name }}</p>
                    @if($category->description)
                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-2">{{ $category->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        @if($subcategories->count() > 0)
            <div class="mb-10 reveal" data-delay="0.1">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ __('Subcategories') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($subcategories as $sub)
                        <a href="{{ route('courses.browse', $sub->slug) }}" class="card p-4 flex items-center gap-3 hover:border-primary-500/30 transition-all">
                            @if($sub->icon)
                                <span class="text-xl">{!! $sub->icon !!}</span>
                            @endif
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white font-['Noto_Sans_Ethiopic']">{{ $sub->name_am ?? $sub->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-slate-400">{{ $sub->activeCourses()->count() }} {{ __('courses') }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if($courses->count() > 0)
            <div class="mb-6 reveal" data-delay="0.1">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Courses') }}</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($courses as $course)
                    <a href="{{ route('courses.show', $course) }}" class="card overflow-hidden group flex flex-col">
                        @if($course->featured_image)
                            <div class="h-40 overflow-hidden bg-gray-100 dark:bg-slate-800">
                                <img src="{{ $course->featured_image }}" alt="{{ $course->title }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy">
                            </div>
                        @endif
                        <div class="p-5 flex-1 flex flex-col">
                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                @if($course->difficulty)
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                                        {{ $course->difficulty === 'Beginner' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400' : '' }}
                                        {{ $course->difficulty === 'Intermediate' ? 'bg-secondary-50 dark:bg-secondary-900/20 text-secondary-600 dark:text-secondary-400' : '' }}
                                        {{ $course->difficulty === 'Advanced' ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : '' }}">
                                        {{ __($course->difficulty) }}
                                    </span>
                                @endif
                            </div>

                            <h3 class="text-sm font-bold text-gray-900 dark:text-white leading-snug mb-1 font-['Noto_Sans_Ethiopic']">{{ $course->title_am ?? $course->title }}</h3>
                            <p class="text-xs text-gray-500 dark:text-slate-400 mb-2">{{ $course->title }}</p>

                            @if($course->description)
                                <p class="text-sm text-gray-600 dark:text-slate-400 leading-relaxed flex-1">{{ Str::limit(strip_tags($course->description), 100) }}</p>
                            @endif

                            <div class="flex items-center justify-between pt-3 mt-auto border-t border-gray-100 dark:border-slate-700/50">
                                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-slate-400">
                                    @if($course->lesson_count)
                                        <span>{{ $course->lesson_count }} {{ __('lessons') }}</span>
                                    @endif
                                    @if($course->duration)
                                        <span>{{ $course->duration }}</span>
                                    @endif
                                </div>
                                <span class="text-xs font-semibold text-primary-600 dark:text-primary-400 group-hover:translate-x-1 transition-transform">{{ __('Start') }} →</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10 reveal" data-delay="0.2">
                {{ $courses->links() }}
            </div>
        @else
            <div class="text-center py-16 reveal" data-delay="0.1">
                <p class="text-gray-600 dark:text-slate-400 font-medium">{{ __('No courses in this category yet.') }}</p>
            </div>
        @endif
    </div>
</section>
@endsection
