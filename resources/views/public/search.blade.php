@extends('layouts.public')

@section('title', __('Search') . ': ' . $query)

@section('content')
<section style="padding:80px 24px;background:var(--bg-950);min-height:100vh;">
    <div style="max-width:960px;margin:0 auto;">

        {{-- Header --}}
        <div style="margin-bottom:40px;">
            <h1 class="display sr" style="font-size:1.6rem;margin-bottom:8px;">
                {{ __('Search Results') }}: <span style="color:var(--gold);">"{{ $query }}"</span>
            </h1>

            <form action="{{ route('search') }}" method="GET" class="sr" style="display:flex;gap:8px;max-width:560px;">
                <div style="flex:1;position:relative;">
                    <svg style="position:absolute;left:14px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-40);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="q" value="{{ $query }}" placeholder="{{ __('Search...') }}" style="width:100%;padding:10px 10px 10px 40px;border-radius:8px;border:1.5px solid var(--border-subtle);background:var(--bg-900);color:var(--text-main);font-size:.9rem;outline:none;" autofocus onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='var(--border-subtle)'">
                </div>
                <button type="submit" class="btn btn-primary" style="padding:10px 20px;">{{ __('Search') }}</button>
            </form>

            {{-- Type filter --}}
            <div class="sr" style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
                <a href="{{ route('search', ['q' => $query, 'type' => 'all']) }}" style="padding:6px 16px;border-radius:16px;font-size:.78rem;text-decoration:none;{{ $type === 'all' ? 'background:var(--gold);color:var(--bg-950);font-weight:600;' : 'background:var(--glass);color:var(--text-60);border:1px solid var(--border-subtle);' }}">{{ __('All') }}</a>
                <a href="{{ route('search', ['q' => $query, 'type' => 'library']) }}" style="padding:6px 16px;border-radius:16px;font-size:.78rem;text-decoration:none;{{ $type === 'library' ? 'background:var(--gold);color:var(--bg-950);font-weight:600;' : 'background:var(--glass);color:var(--text-60);border:1px solid var(--border-subtle);' }}">{{ __('Library') }}</a>
                <a href="{{ route('search', ['q' => $query, 'type' => 'courses']) }}" style="padding:6px 16px;border-radius:16px;font-size:.78rem;text-decoration:none;{{ $type === 'courses' ? 'background:var(--gold);color:var(--bg-950);font-weight:600;' : 'background:var(--glass);color:var(--text-60);border:1px solid var(--border-subtle);' }}">{{ __('Courses') }}</a>
            </div>
        </div>

        {{-- Results --}}
        @if($libraryResults->isEmpty() && $courseResults->isEmpty() && $lessonResults->isEmpty())
            <div style="text-align:center;padding:80px 20px;">
                <x-tour-icon name="faith" size="48" class="" style="opacity:.3;" aria-hidden="true" />
                <p style="color:var(--text-40);font-size:1.05rem;">{{ __('No results found for') }} "{{ $query }}"</p>
                <p style="color:var(--text-40);font-size:.85rem;margin-top:8px;">{{ __('Try different keywords or browse categories.') }}</p>
            </div>
        @else
            {{-- Library Results --}}
            @if($libraryResults->isNotEmpty())
                <div class="sr" style="margin-bottom:36px;">
                    <h2 style="font-size:1.1rem;color:var(--text-display);margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        {{ __('Spiritual Library') }} ({{ $libraryResults->count() }})
                    </h2>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
                        @foreach($libraryResults as $res)
                            <a href="{{ route('library') }}" class="card" style="padding:16px 20px;text-decoration:none;border-radius:10px;display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;border-radius:8px;background:rgba(243,186,21,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><x-tour-icon name="faith" size="18" class="" aria-hidden="true" /></div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:.85rem;font-weight:500;color:var(--text-display);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $res->title }}</div>
                                    @if($res->category)
                                        <div style="font-size:.72rem;color:var(--text-40);">{{ $res->category->name }}</div>
                                    @endif
                                </div>
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-40);flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Course Results --}}
            @if($courseResults->isNotEmpty())
                <div class="sr" style="margin-bottom:36px;">
                    <h2 style="font-size:1.1rem;color:var(--text-display);margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                        {{ __('Courses') }} ({{ $courseResults->count() }})
                    </h2>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
                        @foreach($courseResults as $course)
                            <a href="{{ route('courses.show', $course) }}" class="card" style="padding:16px 20px;text-decoration:none;border-radius:10px;display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;border-radius:8px;background:rgba(26,68,247,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><x-tour-icon name="education" size="18" class="" aria-hidden="true" /></div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:.85rem;font-weight:500;color:var(--text-display);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" class="am">{{ $course->title_am ?? $course->title }}</div>
                                    <div style="font-size:.72rem;color:var(--text-40);">{{ $course->category?->name }}</div>
                                </div>
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-40);flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Lesson Results --}}
            @if($lessonResults->isNotEmpty())
                <div class="sr">
                    <h2 style="font-size:1.1rem;color:var(--text-display);margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        {{ __('Lesson Content') }} ({{ $lessonResults->count() }})
                    </h2>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
                        @foreach($lessonResults as $lesson)
                            <a href="{{ route('courses.lesson', [$lesson->course, $lesson]) }}" class="card" style="padding:16px 20px;text-decoration:none;border-radius:10px;">
                                <div style="font-size:.85rem;font-weight:500;color:var(--text-display);margin-bottom:2px;">{{ $lesson->title }}</div>
                                <div style="font-size:.72rem;color:var(--text-40);">{{ $lesson->course?->title }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</section>
@endsection
