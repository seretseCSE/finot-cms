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

                {{-- Social Share Buttons --}}
                <div style="margin-top:48px;padding-top:40px;border-top:1px solid rgba(255,255,255,.05);">
                    <h4 style="font-size:.85rem;color:var(--text-60);margin-bottom:16px;text-transform:uppercase;letter-spacing:.1em;">{{ __('Share This Post') }}</h4>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        @php
                            $shareUrl = urlencode(route('blog.show', $post->slug));
                            $shareTitle = urlencode($post->title);
                        @endphp
                        <a href="https://t.me/share/url?url={{ $shareUrl }}&text={{ $shareTitle }}" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:8px;background:#0088CC;color:#fff;text-decoration:none;font-size:.8rem;font-weight:600;transition:transform .2s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,136,204,.35)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                            {{ __('Telegram') }}
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:8px;background:#1877F2;color:#fff;text-decoration:none;font-size:.8rem;font-weight:600;transition:transform .2s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(24,119,242,.35)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            {{ __('Facebook') }}
                        </a>
                        <button type="button" onclick="copyShareLink('{{ route('blog.show', $post->slug) }}', this)" style="display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:8px;background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);color:#fff;border:none;text-decoration:none;font-size:.8rem;font-weight:600;cursor:pointer;transition:transform .2s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(220,39,67,.35)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.405a1.44 1.44 0 112.881.001 1.44 1.44 0 01-2.881-.001z"/></svg>
                            {{ __('Instagram') }}
                        </button>
                        <button type="button" onclick="copyShareLink('{{ route('blog.show', $post->slug) }}', this)" style="display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:8px;background:#000000;color:#fff;border:none;text-decoration:none;font-size:.8rem;font-weight:600;cursor:pointer;transition:transform .2s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.35)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61.01 3.91.03 0 0 .34 2.16.73 3.39.65 2.03 1.82 2.77 3.15 3.36.73.32 1.48.53 2.25.64v3.73c-1.27-.13-2.49-.52-3.54-1.12-.56-.32-1.09-.69-1.57-1.1 0 2.39.01 4.78 0 7.17-.03 1.54-.5 3.07-1.37 4.32-1.4 2.01-3.75 3.26-6.19 3.3-1.55.03-3.1-.46-4.36-1.33-2.1-1.44-3.44-3.91-3.46-6.47-.01-.54.03-1.08.12-1.61.4-2.34 1.87-4.41 3.92-5.57 1.23-.69 2.64-1.06 4.06-1.07.14 0 .28 0 .42.01v3.84c-.15-.02-.3-.04-.45-.04-1.34-.01-2.62.72-3.27 1.87-.3.53-.43 1.14-.41 1.74.06 1.49 1.12 2.81 2.55 3.18.56.14 1.15.16 1.72.06.89-.16 1.67-.66 2.19-1.37.27-.36.44-.79.52-1.23.1-.56.09-1.13.09-1.7V.02h3.28z"/></svg>
                            {{ __('TikTok') }}
                        </button>
                        <button type="button" onclick="copyShareLink('{{ route('blog.show', $post->slug) }}', this)" style="display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:8px;background:#FF0000;color:#fff;border:none;text-decoration:none;font-size:.8rem;font-weight:600;cursor:pointer;transition:transform .2s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(255,0,0,.35)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            {{ __('YouTube') }}
                        </button>
                    </div>
                </div>
            </div>
        </article>

        {{-- Comments Section --}}
        <section id="comments" class="sr" style="margin-top:48px;">
            <div class="card" style="padding:40px;">
                <h3 style="font-size:1.3rem;font-weight:600;color:var(--text-display);margin-bottom:8px;">{{ __('Comments') }}</h3>
                <p style="font-size:.85rem;color:var(--text-60);margin-bottom:28px;">{{ __('Join the discussion and share your thoughts.') }}</p>

                {{-- Top-level Comment Form --}}
                <form method="POST" action="{{ route('blog.comment.store', $post->slug) }}" style="margin-bottom:40px;">
                    @csrf
                    <div style="margin-bottom:12px;">
                        <textarea name="content" rows="3" required style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border-subtle);border-radius:8px;padding:12px 16px;color:var(--text-main);outline:none;font-size:.9rem;resize:vertical;" placeholder="{{ __('Write your comment here...') }}">{{ old('content') }}</textarea>
                        @error('content')<span style="color:#f87171;font-size:.75rem;margin-top:4px;display:block;">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:10px 28px;font-size:.85rem;">{{ __('Post Comment') }}</button>
                </form>

                {{-- Comments Tree --}}
                @if(empty($commentsTree))
                    <div style="text-align:center;padding:40px 20px;">
                        <svg width="40" height="40" fill="none" stroke="var(--text-40)" viewBox="0 0 24 24" style="margin-bottom:12px;opacity:.5;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <p style="color:var(--text-40);font-size:.9rem;">{{ __('No comments yet. Be the first to share your thoughts!') }}</p>
                    </div>
                @else
                    <div style="display:flex;flex-direction:column;gap:24px;">
                        @foreach($commentsTree as $comment)
                            @include('public.blog._comment', ['comment' => $comment, 'depth' => 0, 'post' => $post])
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

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

@push('scripts')
<script>
function copyShareLink(url, btn) {
    navigator.clipboard.writeText(url).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> {{ __("Copied!") }}';
        btn.style.background = '#10B981';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
        }, 2000);
    }).catch(() => {
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        const originalText = btn.innerHTML;
        btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> {{ __("Copied!") }}';
        btn.style.background = '#10B981';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
        }, 2000);
    });
}

function toggleReplyForm(commentId) {
    const form = document.getElementById('reply-form-' + commentId);
    if (!form) return;
    const isHidden = form.style.display === 'none';
    // Hide all other reply forms first
    document.querySelectorAll('[id^="reply-form-"]').forEach(el => el.style.display = 'none');
    form.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        const textarea = form.querySelector('textarea');
        if (textarea) textarea.focus();
    }
}
</script>
@endpush

@endsection
