{{-- ═══════════════════════════════════════════════════════
     11.  RECENT BLOG POSTS — From database
═══════════════════════════════════════════════════════ --}}
@if($recentPosts && $recentPosts->count() > 0)
<section id="blog" style="padding:80px 24px;background:linear-gradient(180deg,var(--dark-950),var(--dark-900));">
    <div style="max-width:1280px;margin:0 auto;">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:48px;">
            <div>
                <div class="sec-label sr">{{ __('News') }}</div>
                <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);">{{ __('Latest Posts') }}</h2>
            </div>
            <a href="{{ route('blog.index') }}" class="btn btn-ghost sr">{{ __('View All Posts') }}</a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;">
            @foreach($recentPosts as $post)
            <a href="{{ route('blog.show', $post->slug) }}" class="card sr" style="overflow:hidden;text-decoration:none;display:block;" data-delay="{{ $loop->index * 80 }}">
                @if($post->featured_image)
                <div style="height:200px;overflow:hidden;">
                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" style="width:100%;height:100%;object-fit:cover;transition:transform .5s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                </div>
                @else
                <div style="height:160px;background:linear-gradient(135deg,var(--dark-700),var(--blue-primary));display:flex;align-items:center;justify-content:center;">
                    <svg width="40" height="40" fill="none" stroke="rgba(255,255,255,.3)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                </div>
                @endif
                <div style="padding:20px;">
                    @if($post->tags)
                    <span style="display:inline-block;font-size:.7rem;padding:3px 10px;border-radius:99px;background:rgba(26,68,247,.12);border:1px solid rgba(26,68,247,.25);color:var(--blue-400);margin-bottom:10px;">{{ Str::limit($post->tags, 20) }}</span>
                    @endif
                    <h3 style="font-size:1rem;font-weight:600;color:var(--text-display);margin-bottom:8px;line-height:1.4;">{{ $post->title }}</h3>
                    <div style="display:flex;align-items:center;gap:8px;font-size:.75rem;color:var(--parchment-40);">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $post->published_at ? $post->published_at->diffForHumans() : ($post->publish_date ? $post->publish_date->format('M d, Y') : '') }}
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif