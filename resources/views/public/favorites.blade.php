@extends('layouts.public')

@section('title', __('My Favorites'))

@section('content')
<section style="padding:80px 24px;background:var(--bg-950);min-height:100vh;">
    <div style="max-width:1280px;margin:0 auto;">
        <div style="text-align:center;margin-bottom:48px;">
            <div class="sec-label sr" style="justify-content:center;">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            </div>
            <h1 class="display sr" style="font-size:clamp(2rem,4vw,2.8rem);">{{ __('My Favorites') }}</h1>
        </div>

        @if($resources->isEmpty() && $courses->isEmpty())
            <div style="text-align:center;padding:80px 20px;">
                <div style="margin-bottom:20px;opacity:.2;">
                    <svg width="64" height="64" fill="currentColor" viewBox="0 0 24 24"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </div>
                <p style="color:var(--text-40);font-size:1.1rem;margin-bottom:8px;">{{ __('No favorites yet.') }}</p>
                <p style="color:var(--text-40);font-size:.9rem;">{{ __('Browse the spiritual library or courses and tap the heart to save items here.') }}</p>
                <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;">
                    <a href="{{ route('library') }}" class="btn btn-primary">{{ __('Browse Library') }}</a>
                    <a href="{{ route('courses.index') }}" class="btn btn-ghost">{{ __('Browse Courses') }}</a>
                </div>
            </div>
        @else
            {{-- Library Favorites --}}
            @if($resources->isNotEmpty())
                <h2 class="display sr" style="font-size:1.2rem;margin-bottom:20px;">{{ __('Spiritual Library') }}</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:48px;">
                    @foreach($resources as $resource)
                        <a href="{{ route('library') }}" class="card sr" style="padding:20px;text-decoration:none;border-radius:14px;position:relative;">
                            <button class="remove-fav" data-type="App\Models\LibraryResource" data-id="{{ $resource->id }}" onclick="event.preventDefault();event.stopPropagation();removeFav(this)" style="position:absolute;top:10px;right:10px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:.72rem;transition:all .2s;" onmouseover="this.style.background='rgba(239,68,68,.2)'" onmouseout="this.style.background='rgba(239,68,68,.1)'">
                                {{ __('Remove') }}
                            </button>
                            <h3 style="font-size:.9rem;font-weight:600;color:var(--text-display);margin-bottom:4px;">{{ $resource->title }}</h3>
                            <p style="font-size:.78rem;color:var(--text-40);">{{ $resource->category?->name }}</p>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Course Favorites --}}
            @if($courses->isNotEmpty())
                <h2 class="display sr" style="font-size:1.2rem;margin-bottom:20px;">{{ __('Courses') }}</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
                    @foreach($courses as $course)
                        <a href="{{ route('courses.show', $course) }}" class="card sr" style="padding:20px;text-decoration:none;border-radius:14px;position:relative;">
                            <button class="remove-fav" data-type="App\Models\Course" data-id="{{ $course->id }}" onclick="event.preventDefault();event.stopPropagation();removeFav(this)" style="position:absolute;top:10px;right:10px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:.72rem;" onmouseover="this.style.background='rgba(239,68,68,.2)'" onmouseout="this.style.background='rgba(239,68,68,.1)'">
                                {{ __('Remove') }}
                            </button>
                            <h3 style="font-size:.9rem;font-weight:600;color:var(--text-display);margin-bottom:4px;" class="am">{{ $course->title_am ?? $course->title }}</h3>
                            <p style="font-size:.78rem;color:var(--text-40);">{{ $course->category?->name }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
window.removeFav = function(btn) {
    const formData = new FormData();
    formData.append('favorable_type', btn.dataset.type);
    formData.append('favorable_id', btn.dataset.id);
    formData.append('_token', '{{ csrf_token() }}');

    fetch("{{ route('favorites.toggle') }}", { method: 'POST', body: formData })
        .then(r => r.json())
        .then(() => {
            btn.closest('.card').style.opacity = '0';
            setTimeout(() => btn.closest('.card').remove(), 300);
        });
};
</script>
@endpush
