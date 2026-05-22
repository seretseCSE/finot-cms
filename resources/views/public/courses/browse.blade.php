@extends('layouts.public')

@section('title', $category->name)

@section('content')
<section style="padding:80px 24px;background:var(--bg-950);min-height:100vh;">
    <div style="max-width:1280px;margin:0 auto;">

        {{-- Breadcrumbs --}}
        <div class="sr" style="display:flex;align-items:center;gap:8px;margin-bottom:32px;font-size:.82rem;color:var(--text-40);flex-wrap:wrap;">
            <a href="{{ route('courses.index') }}" style="color:var(--text-40);text-decoration:none;">{{ __('Courses') }}</a>
            @foreach($category->breadcrumbs as $crumb)
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                @if($crumb->id === $category->id)
                    <span style="color:var(--gold);" class="am">{{ $crumb->name_am ?? $crumb->name }}</span>
                @else
                    <a href="{{ route('courses.browse', $crumb->slug) }}" style="color:var(--text-40);text-decoration:none;" class="am">{{ $crumb->name_am ?? $crumb->name }}</a>
                @endif
            @endforeach
        </div>

        {{-- Category Header --}}
        <div class="card sr" style="padding:32px;border-radius:20px;margin-bottom:32px;">
            <div style="display:flex;align-items:center;gap:16px;">
                @if($category->icon)
                    <span style="font-size:2.5rem;">{!! $category->icon !!}</span>
                @endif
                <div>
                    <h1 class="display" style="font-size:1.6rem;margin-bottom:4px;" class="am">{{ $category->name_am ?? $category->name }}</h1>
                    <p style="font-size:.85rem;color:var(--text-40);">{{ $category->name }}</p>
                    @if($category->description)
                        <p style="font-size:.85rem;color:var(--text-60);margin-top:8px;">{{ $category->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Subcategories --}}
        @if($subcategories->count() > 0)
            <div class="sr" style="margin-bottom:32px;">
                <h2 class="display" style="font-size:1.1rem;margin-bottom:16px;color:var(--text-60);">{{ __('Subcategories') }}</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
                    @foreach($subcategories as $sub)
                        <a href="{{ route('courses.browse', $sub->slug) }}" class="card sr" style="padding:20px;text-decoration:none;border-radius:12px;display:flex;align-items:center;gap:12px;transition:all .2s;" onmouseover="this.style.borderColor='rgba(26,68,247,.3)'" onmouseout="this.style.borderColor='var(--border-subtle)'">
                            @if($sub->icon)
                                <span style="font-size:1.4rem;">{!! $sub->icon !!}</span>
                            @endif
                            <div>
                                <div style="font-weight:500;color:var(--text-display);font-size:.9rem;" class="am">{{ $sub->name_am ?? $sub->name }}</div>
                                <div style="font-size:.75rem;color:var(--text-40);">{{ $sub->activeCourses()->count() }} {{ __('courses') }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Courses in this Category --}}
        @if($courses->count() > 0)
            <div class="sr" style="margin-bottom:16px;">
                <h2 class="display" style="font-size:1.1rem;margin-bottom:4px;color:var(--text-60);">{{ __('Courses') }}</h2>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;">
                @foreach($courses as $course)
                    <a href="{{ route('courses.show', $course) }}" class="card sr" style="padding:0;text-decoration:none;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;">
                        @if($course->featured_image)
                            <div style="width:100%;height:160px;overflow:hidden;background:var(--bg-800);">
                                <img src="{{ $course->featured_image }}" alt="" style="width:100%;height:100%;object-fit:cover;">
                            </div>
                        @endif
                        <div style="padding:20px;flex:1;display:flex;flex-direction:column;">
                            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                                @if($course->difficulty)
                                    <span style="font-size:.68rem;padding:2px 8px;border-radius:10px;font-weight:500;{{ $course->difficulty === 'Beginner' ? 'background:rgba(16,185,129,.1);color:#6ee7b7;border:1px solid rgba(16,185,129,.2);' : ($course->difficulty === 'Advanced' ? 'background:rgba(239,68,68,.1);color:#fca5a5;border:1px solid rgba(239,68,68,.2);' : 'background:rgba(243,186,21,.1);color:var(--gold);border:1px solid rgba(243,186,21,.2);') }}">
                                        {{ __($course->difficulty) }}
                                    </span>
                                @endif
                            </div>
                            <h3 style="font-size:.95rem;font-weight:600;color:var(--text-display);margin-bottom:2px;line-height:1.3;">
                                <span class="am">{{ $course->title_am ?? $course->title }}</span>
                            </h3>
                            <p style="font-size:.78rem;color:var(--text-40);margin-bottom:8px;">{{ $course->title }}</p>
                            @if($course->description)
                                <p style="font-size:.8rem;color:var(--text-60);line-height:1.5;margin-bottom:12px;flex:1;">
                                    {{ Str::limit(strip_tags($course->description), 100) }}
                                </p>
                            @endif
                            <div style="display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid var(--border-subtle);margin-top:auto;">
                                <div style="display:flex;align-items:center;gap:10px;font-size:.75rem;color:var(--text-40);">
                                    @if($course->lesson_count)
                                        <span>{{ $course->lesson_count }} {{ __('lessons') }}</span>
                                    @endif
                                    @if($course->duration)
                                        <span>{{ $course->duration }}</span>
                                    @endif
                                </div>
                                <span style="font-size:.75rem;color:var(--blue-primary);font-weight:500;">{{ __('Start') }} →</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="sr" style="margin-top:36px;">
                {{ $courses->links() }}
            </div>
        @else
            <div style="text-align:center;padding:60px 20px;">
                <p style="color:var(--text-40);">{{ __('No courses in this category yet.') }}</p>
            </div>
        @endif
    </div>
</section>
@endsection
