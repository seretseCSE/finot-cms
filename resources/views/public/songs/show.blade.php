@extends('layouts.public')

@section('title', $song->title)

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Song Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:140px 24px 80px;background:var(--dark-950);overflow:hidden;">
    {{-- Subcategory hero image --}}
    @if($song->subcategory && $song->subcategory->image_url)
        <div style="position:absolute;inset:-10% 0;background:url('{{ $song->subcategory->image_url }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    @endif
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(5,10,28,.98) 0%,rgba(26,68,247,.1) 50%,rgba(5,10,28,.98) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:900px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Hymn Details') }}</div>
        <h1 class="display sr" style="font-size:clamp(2rem,4vw,3.2rem);margin-bottom:24px;line-height:1.2;color:var(--text-hero);">
            {{ $song->title }}
        </h1>

        <div class="sr" style="display:flex;align-items:center;justify-content:center;gap:20px;font-size:.85rem;color:var(--parchment-40);">
            @if($song->artist)
                <div style="display:flex;align-items:center;gap:8px;color:var(--gold);">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $song->artist }}
                </div>
            @endif
            @if($song->category)
                <div style="width:3px;height:3px;border-radius:50%;background:rgba(255,255,255,.2);"></div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="color:var(--blue-400);">{{ $song->category->name }}</span>
                </div>
            @endif
            @if($song->subcategory)
                <div style="width:3px;height:3px;border-radius:50%;background:rgba(255,255,255,.2);"></div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="color:var(--parchment-40);">{{ $song->subcategory->name }}</span>
                </div>
            @endif
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  SONG CONTENT
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:60px 24px 100px;background:var(--dark-900);position:relative;">
    <div style="max-width:900px;margin:0 auto;position:relative;z-index:1;">
        
        <div class="card sr" style="padding:48px;border-color:rgba(255,255,255,.05);">
            
            {{-- Media Players --}}
            @if($song->audio_url || $song->video_url)
                <div style="display:flex;flex-wrap:wrap;gap:24px;margin-bottom:48px;padding-bottom:32px;border-bottom:1px solid rgba(255,255,255,.05);">
                    @if($song->audio_url)
                        <div style="flex:1;min-width:300px;">
                            <div style="font-size:.7rem;text-transform:uppercase;color:var(--text-40);margin-bottom:12px;font-weight:700;">{{ __('Listen to Audio') }}</div>
                            <audio controls style="width:100%;height:40px;filter:invert(1) hue-rotate(180deg) brightness(1.5);">
                                <source src="{{ $song->audio_url }}" type="audio/mpeg">
                            </audio>
                            <button type="button" class="btn btn-ghost" data-offline-url="{{ $song->audio_url }}" data-offline-title="{{ $song->title }}" style="margin-top:12px;padding:8px 16px;font-size:.75rem;">{{ __('Save offline') }}</button>
                        </div>
                    @endif
                    @if($song->video_url)
                        <div style="flex:1;min-width:300px;">
                            <div style="font-size:.7rem;text-transform:uppercase;color:var(--text-40);margin-bottom:12px;font-weight:700;">{{ __('Watch Video') }}</div>
                            @if($song->is_embeddable)
                                <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;">
                                    <iframe src="{{ $song->embed_url }}" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" allowfullscreen loading="lazy"></iframe>
                                </div>
                            @else
                                <video controls style="width:100%;border-radius:12px;" preload="metadata">
                                    <source src="{{ $song->video_url }}" type="video/mp4">
                                    {{ __('Your browser does not support the video tag.') }}
                                </video>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            {{-- Lyrics --}}
            @if($song->lyrics)
                <div style="margin-bottom:48px;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:32px;">
                        <div style="width:1px;height:24px;background:var(--gold);"></div>
                        <h2 class="display" style="font-size:1.6rem;">{{ __('Lyrics') }}</h2>
                    </div>
                    <div class="am" style="font-size:1.15rem;line-height:2;color:var(--text-60);white-space:pre-line;text-align:center;">
                        {!! $song->formatted_lyrics !!}
                    </div>
                </div>
            @endif
        </div>

        {{-- Navigation --}}
        <div class="sr" style="margin-top:40px;display:flex;justify-content:center;">
            <a href="{{ route('songs.index') }}" class="btn btn-ghost">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Back to Song Library') }}
            </a>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  RELATED SONGS
     ═══════════════════════════════════════════════════════ --}}
@if($relatedSongs->isNotEmpty())
<section style="padding:80px 24px;background:var(--dark-950);">
    <div style="max-width:1200px;margin:0 auto;">
        <h2 class="display sr" style="font-size:1.8rem;margin-bottom:32px;text-align:center;">{{ __('Similar Hymns') }}</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;">
            @foreach($relatedSongs as $related)
                <a href="{{ route('songs.show', $related->id) }}" class="card sr" style="padding:24px;text-decoration:none;">
                    <h4 style="font-size:.95rem;font-weight:600;color:var(--text-display);margin-bottom:8px;line-height:1.4;">{{ $related->title }}</h4>
                    @if($related->artist)
                        <div style="font-size:.75rem;color:var(--gold);">{{ $related->artist }}</div>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection
