@extends('layouts.public')

@section('title', $course->title)

@section('content')
<section style="padding:80px 24px;background:var(--bg-950);min-height:100vh;">
    <div style="max-width:900px;margin:0 auto;">

        {{-- Breadcrumb --}}
        <div class="sr" style="display:flex;align-items:center;gap:8px;margin-bottom:32px;font-size:.82rem;color:var(--text-40);flex-wrap:wrap;">
            <a href="{{ route('courses.index') }}" style="color:var(--text-40);text-decoration:none;">{{ __('Courses') }}</a>
            @if($course->category)
                @foreach($course->category->breadcrumbs as $crumb)
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    <a href="{{ route('courses.browse', $crumb->slug) }}" style="color:var(--text-40);text-decoration:none;" class="am">{{ $crumb->name_am ?? $crumb->name }}</a>
                @endforeach
            @endif
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            <span style="color:var(--gold);" class="am">{{ $course->title_am ?? $course->title }}</span>
        </div>

        {{-- Course Header --}}
        <div class="card sr" style="padding:32px;border-radius:20px;margin-bottom:32px;">
            <div style="display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap;">
                @if($course->featured_image)
                    <div style="width:200px;height:200px;border-radius:16px;overflow:hidden;flex-shrink:0;background:var(--bg-800);">
                        <img src="{{ $course->featured_image }}" alt="" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                @endif
                <div style="flex:1;min-width:240px;">
                    <h1 class="display" style="font-size:1.8rem;margin-bottom:6px;" class="am">{{ $course->title_am ?? $course->title }}</h1>
                    <p style="font-size:.85rem;color:var(--text-40);margin-bottom:12px;">{{ $course->title }}</p>

                    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                        @if($course->instructor)
                            <span style="font-size:.8rem;color:var(--text-60);">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:middle;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $course->instructor }}
                            </span>
                        @endif
                        @if($course->difficulty)
                            <span style="font-size:.8rem;color:var(--text-60);">{{ __($course->difficulty) }}</span>
                        @endif
                        @if($course->duration)
                            <span style="font-size:.8rem;color:var(--text-60);">{{ $course->duration }}</span>
                        @endif
                        <span style="font-size:.8rem;color:var(--text-60);">{{ $course->lesson_count }} {{ __('lessons') }}</span>
                    </div>

                    @if($course->description)
                        <p style="font-size:.88rem;color:var(--text-60);line-height:1.7;">{{ $course->description }}</p>
                    @endif
                    @if($course->description_am)
                        <p class="am" style="font-size:.88rem;color:var(--text-60);line-height:1.9;margin-top:8px;">{{ $course->description_am }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Lessons List --}}
        <h2 class="display sr" style="font-size:1.3rem;margin-bottom:20px;">{{ __('Course Lessons') }}</h2>

        @if($course->activeLessons->count() > 0)
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach($course->activeLessons as $index => $lesson)
                    <a href="{{ route('courses.lesson', [$course, $lesson]) }}" class="card sr" style="padding:20px 24px;text-decoration:none;border-radius:12px;display:flex;align-items:center;gap:16px;transition:all .2s;" onmouseover="this.style.borderColor='rgba(26,68,247,.3)'" onmouseout="this.style.borderColor='var(--border-subtle)'">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(26,68,247,.1);border:1px solid rgba(26,68,247,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700;font-size:.85rem;color:var(--blue-primary);">
                            {{ $index + 1 }}
                        </div>

                        <div style="flex:1;min-width:0;">
                            <h3 style="font-size:.95rem;font-weight:600;color:var(--text-display);margin-bottom:2px;">
                                <span class="am">{{ $lesson->title_am ?? $lesson->title }}</span>
                            </h3>
                            <p style="font-size:.8rem;color:var(--text-40);">{{ $lesson->title }}</p>
                        </div>

                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-40);flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @endforeach
            </div>
        @else
            <div style="text-align:center;padding:60px 20px;">
                <p style="color:var(--text-40);">{{ __('No lessons published yet.') }}</p>
            </div>
        @endif
    </div>
</section>
@endsection
