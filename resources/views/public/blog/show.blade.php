@extends('layouts.public')

@section('title', $post->title)

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Post Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:140px 24px 80px;background:var(--dark-950);overflow:hidden;">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(5,10,28,.98) 0%,rgba(26,68,247,.1) 50%,rgba(5,10,28,.98) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:900px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Article') }}</div>
        <h1 class="display sr" style="font-size:clamp(2rem,4vw,3.2rem);margin-bottom:24px;line-height:1.2;">
            {{ $post->title }}
        </h1>
        
        <div class="sr" style="display:flex;align-items:center;justify-content:center;gap:20px;font-size:.85rem;color:var(--parchment-40);">
            <div style="display:flex;align-items:center;gap:8px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ $post->published_at?->format('F j, Y') }}
            </div>
            @if($post->author)
                <div style="width:3px;height:3px;border-radius:50%;background:rgba(255,255,255,.2);"></div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:24px;height:24px;border-radius:50%;background:var(--blue-glow);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;color:var(--blue-400);">{{ substr($post->author->name,0,1) }}</div>
                    {{ $post->author->name }}
                </div>
            @endif
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  ARTICLE CONTENT
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:60px 24px 100px;background:var(--dark-900);position:relative;">
    <div style="max-width:900px;margin:0 auto;position:relative;z-index:1;">
        
        <article class="card sr" style="padding:0;overflow:hidden;border-color:rgba(255,255,255,.05);">
            @if($post->featured_image_url)
                <div style="width:100%;height:450px;overflow:hidden;border-bottom:1px solid rgba(255,255,255,.05);">
                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" style="width:100%;height:100%;object-fit:cover;">
                </div>
            @endif

            <div style="padding:60px 48px;line-height:1.8;color:var(--parchment-60);">
                <div class="prose prose-invert max-w-none" style="font-size:1.1rem;">
                    {!! $post->content !!}
                </div>

                @if($post->content_am)
                    <div style="margin-top:60px;padding-top:40px;border-top:1px solid rgba(255,255,255,.05);">
                        <h3 class="am" style="font-size:1.5rem;color:var(--gold);margin-bottom:24px;">በአማርኛ</h3>
                        <div class="am prose prose-invert max-w-none" style="font-size:1.1rem;">
                            {!! $post->content_am !!}
                        </div>
                    </div>
                @endif
                
                @if($post->parsed_tags)
                    <div style="margin-top:60px;display:flex;flex-wrap:wrap;gap:10px;">
                        @foreach($post->parsed_tags as $tag)
                            <span style="font-size:.7rem;padding:4px 12px;border-radius:99px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);color:var(--parchment-40);">#{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </article>

        {{-- Navigation --}}
        <div class="sr" style="margin-top:40px;display:flex;justify-content:center;">
            <a href="{{ route('blog.index') }}" class="btn btn-ghost">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Back to All Posts') }}
            </a>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  RELATED POSTS
     ═══════════════════════════════════════════════════════ --}}
@if($relatedPosts->isNotEmpty())
<section style="padding:80px 24px;background:var(--dark-950);">
    <div style="max-width:1200px;margin:0 auto;">
        <h2 class="display sr" style="font-size:1.8rem;margin-bottom:32px;text-align:center;">{{ __('Related Reading') }}</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;">
            @foreach($relatedPosts as $related)
                <a href="{{ route('blog.show', $related->slug) }}" class="card sr" style="padding:0;overflow:hidden;text-decoration:none;">
                    <div style="height:160px;overflow:hidden;">
                        @if($related->featured_image_url)
                            <img src="{{ $related->featured_image_url }}" alt="{{ $related->title }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--dark-800),var(--blue-primary));"></div>
                        @endif
                    </div>
                    <div style="padding:20px;">
                        <h4 style="font-size:.95rem;font-weight:600;color:#fff;margin-bottom:8px;line-height:1.4;">{{ $related->title }}</h4>
                        <div style="font-size:.72rem;color:var(--parchment-40);">{{ $related->published_at?->format('M d, Y') }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection
