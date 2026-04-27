@extends('layouts.public')

@section('title', $mediaItem->title)

@section('content')

{{-- ═══════════════════════════════════════════════════════
     HERO — Media Detail Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 60px;background:var(--dark-950);overflow:hidden;">
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/features-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90) 0%,rgba(26,68,247,.2) 50%,var(--overlay-95) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Media Detail') }}</div>
        <h1 class="display sr" style="font-size:clamp(2rem,5vw,3rem);margin-bottom:16px;color:var(--text-hero);">
            {{ $mediaItem->title }}
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:700px;margin:0 auto;font-size:1rem;line-height:1.7;">
            {{ strip_tags($mediaItem->description) }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     MEDIA PREVIEW
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:60px 24px;background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        <div class="card sr" style="padding:0;overflow:hidden;">
            <div style="background:var(--dark-950);display:flex;align-items:center;justify-content:center;min-height:300px;">
                @if($mediaItem->type === 'Photo')
                    <img src="{{ $mediaItem->file_url }}" alt="{{ $mediaItem->title }}" style="max-width:100%;max-height:80vh;object-fit:contain;display:block;">
                @else
                    <video controls style="max-width:100%;max-height:80vh;display:block;" preload="metadata" poster="{{ url('/placeholder.jpg') }}">
                        <source src="{{ $mediaItem->file_url }}" type="video/mp4">
                        <source src="{{ $mediaItem->file_url }}" type="video/webm">
                        Your browser does not support the video tag.
                    </video>
                @endif
            </div>

            <div style="padding:32px;">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-bottom:20px;">
                    <span style="font-size:.7rem;padding:4px 14px;border-radius:99px;background:{{ $mediaItem->type === 'Photo' ? 'rgba(74,222,128,.1)' : 'rgba(248,113,113,.1)' }};border:1px solid {{ $mediaItem->type === 'Photo' ? 'rgba(74,222,128,.2)' : 'rgba(248,113,113,.2)' }};color:{{ $mediaItem->type === 'Photo' ? '#86efac' : '#fca5a5' }};text-transform:uppercase;letter-spacing:.05em;">{{ $mediaItem->type }}</span>
                    @if($mediaItem->category)
                        <span style="font-size:.7rem;padding:4px 14px;border-radius:99px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);color:var(--text-40);">{{ $mediaItem->category->name }}</span>
                    @endif
                    @if($mediaItem->event_album)
                        <span style="font-size:.7rem;padding:4px 14px;border-radius:99px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);color:var(--text-40);">{{ $mediaItem->event_album }}</span>
                    @endif
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-top:24px;padding-top:24px;border-top:1px solid rgba(255,255,255,.06);">
                    <div>
                        <span style="font-size:.7rem;color:var(--text-40);display:block;margin-bottom:4px;">{{ __('File Size') }}</span>
                        <span style="color:var(--text-main);font-weight:500;">{{ $mediaItem->formatted_file_size }}</span>
                    </div>
                    <div>
                        <span style="font-size:.7rem;color:var(--text-40);display:block;margin-bottom:4px;">{{ __('Uploaded') }}</span>
                        <span style="color:var(--text-main);font-weight:500;">{{ $mediaItem->created_at?->format('M d, Y') }}</span>
                    </div>
                    @if(!empty($mediaItem->parsed_tags))
                        <div style="grid-column:1 / -1;">
                            <span style="font-size:.7rem;color:var(--text-40);display:block;margin-bottom:8px;">{{ __('Tags') }}</span>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                @foreach($mediaItem->parsed_tags as $tag)
                                    <span style="font-size:.75rem;padding:4px 12px;border-radius:99px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:var(--text-60);">{{ $tag }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div style="margin-top:32px;display:flex;gap:12px;flex-wrap:wrap;">
                    <a href="{{ $mediaItem->file_url }}" download class="btn btn-primary" style="padding:12px 28px;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        {{ __('Download') }}
                    </a>
                    <a href="{{ route('media') }}" class="btn btn-ghost" style="padding:12px 28px;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        {{ __('Back to Gallery') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
