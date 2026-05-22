@extends('layouts.public')

@section('title', $lesson->title)
@section('meta_description', strip_tags($lesson->content ?? ''))

@push('styles')
<style>
.reading-mode {
    --bg-parchment: #F5E6C8;
    --bg-parchment-dark: #EDE0C8;
    --bg-parchment-darker: #E5D5B5;
    --text-parchment: #2C1810;
    --text-parchment-60: #5C4033;
    --text-parchment-40: #8B7355;
    --gold-manuscript: #B8860B;
    --red-holy: #C8102E;
    --border-manuscript: #8B7355;
    --border-manuscript-light: #C4A97D;
}

html[data-reading="true"] body,
html[data-reading="true"] {
    background: var(--bg-parchment) !important;
    color: var(--text-parchment) !important;
    font-family: 'Noto Sans Ethiopic', 'Nyala', 'Georgia', serif !important;
}

.reading-container {
    max-width: 820px;
    margin: 0 auto;
    padding: 60px 32px 100px;
    position: relative;
    line-height: 1.9;
    font-size: 1.05rem;
}

.reading-container.am {
    font-family: 'Noto Sans Ethiopic', 'Nyala', serif;
    font-size: 1.1rem;
    line-height: 2;
}

.reading-container .drop-cap::first-letter {
    font-size: 4em;
    float: left;
    line-height: 0.8;
    margin-right: 12px;
    margin-top: 8px;
    color: var(--gold-manuscript);
    font-family: 'Georgia', 'Noto Sans Ethiopic', serif;
    font-weight: 700;
}

.reading-container .holy-name {
    color: var(--red-holy);
    font-weight: 600;
}

.reading-container h1 {
    font-size: 2rem;
    text-align: center;
    border-bottom: 2px solid var(--gold-manuscript);
    padding-bottom: 20px;
    margin-bottom: 32px;
}

.reading-container h1::after {
    content: '☦';
    display: block;
    font-size: 1.4rem;
    color: var(--gold-manuscript);
    margin-top: 8px;
    opacity: 0.6;
}

.reading-toolbar {
    position: sticky;
    top: 68px;
    z-index: 50;
    background: var(--bg-parchment-dark);
    border-bottom: 1px solid var(--border-manuscript-light);
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
}

.reading-toolbar button {
    background: var(--bg-parchment);
    border: 1px solid var(--border-manuscript-light);
    color: var(--text-parchment);
    padding: 6px 14px;
    border-radius: 8px;
    font-size: .82rem;
    cursor: pointer;
    transition: background .2s;
    font-family: inherit;
}

.reading-toolbar button:hover { background: var(--bg-parchment-darker); }

.lang-toggle { display: flex; gap: 2px; }
.lang-toggle button { padding: 6px 16px; border-radius: 6px; font-weight: 600; }
.lang-toggle button.active { background: var(--gold-manuscript); color: #fff; border-color: var(--gold-manuscript); }
</style>
@endpush

@section('content')
<script>document.documentElement.setAttribute('data-reading', 'true');</script>
<div>

    {{-- Toolbar --}}
    <div class="reading-toolbar">
        <div class="lang-toggle">
            <button id="lang-en" class="active" onclick="setLang('en')">EN</button>
            <button id="lang-am" class="am" onclick="setLang('am')">አማ</button>
        </div>
        <button onclick="changeFontSize(-1)">A−</button>
        <span style="font-size:.75rem;color:var(--text-parchment-40);min-width:32px;text-align:center;" id="font-size-label">100%</span>
        <button onclick="changeFontSize(1)">A+</button>
        <button onclick="toggleReadingMode()">{{ __('Light/Dark') }}</button>

        @auth
            <button id="favorite-btn" data-type="App\Models\Course" data-id="{{ $course->id }}" style="display:flex;align-items:center;gap:4px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <span id="fav-label">{{ __('Save') }}</span>
            </button>
        @endauth

        @if($prev || $next)
            <div style="display:flex;gap:4px;">
                @if($prev)
                    <a href="{{ route('courses.lesson', [$course, $prev]) }}" style="padding:6px 12px;background:var(--bg-parchment);border:1px solid var(--border-manuscript-light);border-radius:6px;color:var(--text-parchment);text-decoration:none;font-size:.78rem;">← {{ __('Prev') }}</a>
                @endif
                @if($next)
                    <a href="{{ route('courses.lesson', [$course, $next]) }}" style="padding:6px 12px;background:var(--bg-parchment);border:1px solid var(--border-manuscript-light);border-radius:6px;color:var(--text-parchment);text-decoration:none;font-size:.78rem;">{{ __('Next') }} →</a>
                @endif
            </div>
        @endif
    </div>

    {{-- Content --}}
    <article class="reading-container" id="reading-container" lang="en">
        <h1 id="title-en" class="drop-cap">{{ $lesson->title }}</h1>
        @if($lesson->title_am)
            <h1 id="title-am" style="display:none;" class="am">{{ $lesson->title_am }}</h1>
        @endif

        <div id="content-en">
            @if($lesson->content)
                {!! $lesson->content !!}
            @else
                <p style="text-align:center;color:var(--text-parchment-40);padding:60px 0;">{{ __('Content coming soon.') }}</p>
            @endif
        </div>

        <div id="content-am" style="display:none;" lang="am" class="am">
            @if($lesson->content_am)
                {!! $lesson->content_am !!}
            @else
                <p style="text-align:center;color:var(--text-parchment-40);padding:60px 0;">{{ __('ይዘት ገና አልተጨመረም።') }}</p>
            @endif
        </div>

        <div style="text-align:center;padding:40px 0 20px;opacity:.3;">
            <svg width="48" height="48" viewBox="0 0 100 100" fill="none">
                <rect x="43" y="6" width="14" height="88" rx="2" fill="var(--gold-manuscript)"/>
                <rect x="6" y="43" width="88" height="14" rx="2" fill="var(--gold-manuscript)"/>
            </svg>
        </div>
    </article>

    {{-- Lesson Navigation --}}
    <div style="max-width:820px;margin:0 auto;padding:0 32px 60px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        @if($prev)
            <a href="{{ route('courses.lesson', [$course, $prev]) }}" style="display:flex;align-items:center;gap:8px;padding:12px 20px;background:var(--bg-parchment-dark);border:1px solid var(--border-manuscript-light);border-radius:12px;text-decoration:none;color:var(--text-parchment);"
               onmouseover="this.style.background='var(--bg-parchment-darker)'" onmouseout="this.style.background='var(--bg-parchment-dark)'">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                <div><div style="font-size:.72rem;color:var(--text-parchment-40);">{{ __('Previous') }}</div><div style="font-size:.85rem;font-weight:500;">{{ Str::limit($prev->title, 30) }}</div></div>
            </a>
        @else
            <div></div>
        @endif
        @if($next)
            <a href="{{ route('courses.lesson', [$course, $next]) }}" style="display:flex;align-items:center;gap:8px;padding:12px 20px;background:var(--bg-parchment-dark);border:1px solid var(--border-manuscript-light);border-radius:12px;text-decoration:none;color:var(--text-parchment);text-align:right;"
               onmouseover="this.style.background='var(--bg-parchment-darker)'" onmouseout="this.style.background='var(--bg-parchment-dark)'">
                <div><div style="font-size:.72rem;color:var(--text-parchment-40);">{{ __('Next') }}</div><div style="font-size:.85rem;font-weight:500;">{{ Str::limit($next->title, 30) }}</div></div>
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        @else
            <div></div>
        @endif
    </div>

    {{-- Back to Course --}}
    <div style="text-align:center;padding-bottom:60px;">
        <a href="{{ route('courses.show', $course) }}" style="display:inline-flex;align-items:center;gap:6px;padding:10px 24px;background:var(--bg-parchment-dark);border:1px solid var(--border-manuscript-light);border-radius:10px;text-decoration:none;color:var(--text-parchment);font-size:.85rem;" onmouseover="this.style.background='var(--bg-parchment-darker)'" onmouseout="this.style.background='var(--bg-parchment-dark)'">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m7 7l-7-7 7-7"/></svg>
            {{ __('Back to Course') }}
        </a>
    </div>
    </div>
@endsection

@push('scripts')
<script>
let currentLang = 'en';
let baseFontSize = 1.05;
const container = document.getElementById('reading-container');

function setLang(lang) {
    currentLang = lang;
    document.getElementById('lang-en').classList.toggle('active', lang === 'en');
    document.getElementById('lang-am').classList.toggle('active', lang === 'am');
    container.lang = lang;
    container.classList.toggle('am', lang === 'am');
    document.getElementById('content-en').style.display = lang === 'en' ? 'block' : 'none';
    document.getElementById('content-am').style.display = lang === 'am' ? 'block' : 'none';
    const tEn = document.getElementById('title-en');
    const tAm = document.getElementById('title-am');
    if (tEn) tEn.style.display = lang === 'en' ? 'block' : 'none';
    if (tAm) tAm.style.display = lang === 'am' ? 'block' : 'none';
}

function changeFontSize(dir) {
    baseFontSize = Math.max(0.75, Math.min(1.8, baseFontSize + (dir * 0.05)));
    container.style.fontSize = baseFontSize + 'rem';
    document.getElementById('font-size-label').textContent = Math.round(baseFontSize * 100) + '%';
}

function toggleReadingMode() {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    document.documentElement.setAttribute('data-theme', isLight ? 'dark' : 'light');
}

// Favorite toggle
const favBtn = document.getElementById('favorite-btn');
if (favBtn) {
    const type = favBtn.dataset.type;
    const id = favBtn.dataset.id;
    const label = document.getElementById('fav-label');

    fetch('/favorites/status?favorable_type=' + encodeURIComponent(type) + '&favorable_id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.favorited) {
                favBtn.style.color = 'var(--red-holy)';
                label.textContent = '{{ __("Saved") }}';
            }
        });

    favBtn.addEventListener('click', function () {
        fetch('/favorites/toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ favorable_type: type, favorable_id: id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.favorited) {
                favBtn.style.color = 'var(--red-holy)';
                label.textContent = '{{ __("Saved") }}';
            } else {
                favBtn.style.color = '';
                label.textContent = '{{ __("Save") }}';
            }
        });
    });
}
</script>
@endpush
