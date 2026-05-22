@extends('layouts.public')

@section('title', __('Courses'))

@section('content')
<section style="padding:80px 24px;background:var(--bg-950);min-height:100vh;">
    <div style="max-width:1280px;margin:0 auto;">

        {{-- Header --}}
        <div style="text-align:center;margin-bottom:48px;">
            <div class="sec-label sr" style="justify-content:center;margin-bottom:16px;">
                <span class="am">ትምህርት</span>
            </div>
            <h1 class="display sr" style="font-size:clamp(2rem,4vw,3rem);margin-bottom:12px;">
                {{ __('Courses') }}
            </h1>
            <p class="sr" style="color:var(--text-60);max-width:560px;margin:0 auto 32px;">
                {{ __('Structured courses on the Orthodox faith, scripture, and church tradition.') }}
            </p>

            {{-- Search --}}
            <form action="{{ route('courses.index') }}" method="GET" class="sr" style="max-width:480px;margin:0 auto;display:flex;gap:8px;">
                <div style="flex:1;position:relative;">
                    <svg style="position:absolute;left:14px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-40);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('Search courses...') }}" style="width:100%;padding:12px 12px 12px 40px;border-radius:10px;border:1.5px solid var(--border-subtle);background:var(--bg-900);color:var(--text-main);font-size:.9rem;outline:none;" onfocus="this.style.borderColor='var(--blue-primary)'" onblur="this.style.borderColor='var(--border-subtle)'">
                </div>
                <button type="submit" class="btn btn-primary" style="padding:10px 20px;">{{ __('Search') }}</button>
            </form>
        </div>

        {{-- Root Categories --}}
        @if($categories->count() > 0)
            <div class="sr" style="margin-bottom:48px;">
                <h2 class="display" style="font-size:1.2rem;margin-bottom:16px;color:var(--text-60);">{{ __('Browse by Category') }}</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
                    @foreach($categories as $cat)
                        <a href="{{ route('courses.browse', $cat->slug) }}" class="card sr" style="padding:24px;text-decoration:none;border-radius:14px;display:flex;align-items:center;gap:14px;transition:all .2s;" onmouseover="this.style.borderColor='rgba(26,68,247,.3)'" onmouseout="this.style.borderColor='var(--border-subtle)'">
                            @if($cat->icon)
                                <span style="font-size:1.8rem;">{!! $cat->icon !!}</span>
                            @endif
                            <div>
                                <div style="font-weight:600;color:var(--text-display);" class="am">{{ $cat->name_am ?? $cat->name }}</div>
                                <div style="font-size:.78rem;color:var(--text-40);margin-top:2px;">{{ $cat->name }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Course Grid --}}
        @if($courses->count() > 0)
            <div class="sr" style="margin-bottom:24px;">
                <h2 class="display" style="font-size:1.2rem;margin-bottom:16px;color:var(--text-60);">{{ __('Latest Courses') }}</h2>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;">
                @foreach($courses as $course)
                    <a href="{{ route('courses.show', $course) }}" class="card sr" style="padding:0;text-decoration:none;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;">
                        @if($course->featured_image)
                            <div style="width:100%;height:180px;overflow:hidden;background:var(--bg-800);">
                                <img src="{{ $course->featured_image }}" alt="" style="width:100%;height:100%;object-fit:cover;">
                            </div>
                        @endif
                        <div style="padding:24px;flex:1;display:flex;flex-direction:column;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                                @if($course->category)
                                    <span style="font-size:.7rem;padding:3px 10px;border-radius:12px;background:rgba(26,68,247,.1);color:var(--blue-primary);border:1px solid rgba(26,68,247,.15);font-weight:500;">
                                        {{ $course->category->name }}
                                    </span>
                                @endif
                                @if($course->difficulty)
                                    <span style="font-size:.7rem;padding:3px 10px;border-radius:12px;font-weight:500;{{ $course->difficulty === 'Beginner' ? 'background:rgba(16,185,129,.1);color:#6ee7b7;border:1px solid rgba(16,185,129,.2);' : ($course->difficulty === 'Advanced' ? 'background:rgba(239,68,68,.1);color:#fca5a5;border:1px solid rgba(239,68,68,.2);' : 'background:rgba(243,186,21,.1);color:var(--gold);border:1px solid rgba(243,186,21,.2);') }}">
                                        {{ __($course->difficulty) }}
                                    </span>
                                @endif
                            </div>

                            <h3 style="font-size:1.05rem;font-weight:600;color:var(--text-display);margin-bottom:6px;line-height:1.3;">
                                <span class="am">{{ $course->title_am ?? $course->title }}</span>
                            </h3>
                            <p style="font-size:.82rem;color:var(--text-40);margin-bottom:4px;">{{ $course->title }}</p>

                            @if($course->description)
                                <p style="font-size:.82rem;color:var(--text-60);line-height:1.6;margin-bottom:16px;flex:1;">
                                    {{ Str::limit(strip_tags($course->description), 120) }}
                                </p>
                            @endif

                            <div style="display:flex;align-items:center;justify-content:space-between;padding-top:16px;border-top:1px solid var(--border-subtle);margin-top:auto;">
                                <div style="display:flex;align-items:center;gap:12px;font-size:.78rem;color:var(--text-40);">
                                    @if($course->lesson_count)
                                        <span>{{ $course->lesson_count }} {{ __('lessons') }}</span>
                                    @endif
                                    @if($course->duration)
                                        <span>{{ $course->duration }}</span>
                                    @endif
                                </div>
                                <span style="font-size:.78rem;color:var(--blue-primary);font-weight:500;">
                                    {{ __('Start') }} →
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="sr" style="margin-top:48px;">
                {{ $courses->links() }}
            </div>
        @elseif(!$search)
            <div style="text-align:center;padding:80px 20px;">
                <div style="font-size:3rem;margin-bottom:16px;opacity:.3;">📚</div>
                <p style="color:var(--text-40);font-size:1.05rem;">{{ __('No courses available yet.') }}</p>
            </div>
        @else
            <div style="text-align:center;padding:80px 20px;">
                <p style="color:var(--text-40);font-size:1.05rem;">{{ __('No courses match your search.') }}</p>
            </div>
        @endif
    </div>
</section>
@endsection
