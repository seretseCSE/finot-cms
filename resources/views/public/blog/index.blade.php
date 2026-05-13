@extends('layouts.public')

@section('title', __('Our Blog'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Blog Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(5,10,28,.95) 0%,rgba(26,68,247,.1) 50%,rgba(5,10,28,.98) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('News & Insights') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;">
            {{ __('The') }}
            <span style="color:var(--gold);">{{ __('Blog') }}</span>
        </h1>
        <p class="sr" style="color:var(--parchment-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ __('Stay updated with our latest news, spiritual teachings, and community stories.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  FILTERS
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:60px 24px 20px;background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        <div class="card sr" style="padding:24px 32px;margin-bottom:40px;">
            <form method="GET" action="{{ route('blog.index') }}" style="display:flex;flex-wrap:wrap;gap:16px;">
                <div style="flex:1;min-width:280px;position:relative;">
                    <svg style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--parchment-40);" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search posts...') }}" style="width:100%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:12px 16px 12px 42px;color:#fff;outline:none;">
                </div>
                
                <select name="tag" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:12px 20px;color:#fff;outline:none;cursor:pointer;min-width:160px;">
                    <option value="" style="background:var(--dark-900);">{{ __('All Tags') }}</option>
                    @foreach($allTags as $tag)
                        <option value="{{ $tag }}" {{ request('tag') == $tag ? 'selected' : '' }} style="background:var(--dark-900);">{{ $tag }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary" style="padding:12px 32px;">{{ __('Filter') }}</button>

                @if(request('search') || request('tag'))
                    <a href="{{ route('blog.index') }}" class="btn btn-ghost" style="padding:12px 24px;">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  POSTS GRID
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:0 24px 100px;background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        
        @if($posts->isEmpty())
            <div class="card sr" style="padding:80px;text-align:center;max-width:600px;margin:0 auto;">
                <div style="font-size:3rem;margin-bottom:24px;">📝</div>
                <h3 class="display" style="font-size:1.8rem;margin-bottom:12px;">{{ __('No Posts Found') }}</h3>
                <p style="color:var(--parchment-60);">{{ __('Try adjusting your filters or check back later for new content.') }}</p>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:32px;">
                @foreach($posts as $post)
                    <a href="{{ route('blog.show', $post->slug) }}" class="card sr" style="padding:0;overflow:hidden;display:flex;flex-direction:column;text-decoration:none;">
                        <div style="height:220px;overflow:hidden;position:relative;">
                            @if($post->featured_image_url)
                                <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" style="width:100%;height:100%;object-fit:cover;transition:transform .5s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            @else
                                <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--dark-800),var(--blue-primary));display:flex;align-items:center;justify-content:center;">
                                    <svg width="50" height="50" fill="none" stroke="rgba(255,255,255,.1)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                                </div>
                            @endif
                        </div>

                        <div style="padding:32px;flex:1;display:flex;flex-direction:column;gap:16px;">
                            <div style="display:flex;align-items:center;gap:12px;font-size:.78rem;color:var(--parchment-40);">
                                <span>{{ $post->published_at?->format('M d, Y') }}</span>
                                @if($post->author)
                                    <span style="width:3px;height:3px;border-radius:50%;background:rgba(255,255,255,.2);"></span>
                                    <span>{{ $post->author->name }}</span>
                                @endif
                            </div>

                            <h3 style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:600;color:#fff;line-height:1.3;transition:color .3s;">
                                {{ $post->title }}
                            </h3>

                            <p style="color:var(--parchment-60);font-size:.9rem;line-height:1.7;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;flex:1;">
                                {{ strip_tags($post->content) }}
                            </p>

                            @if($post->parsed_tags)
                                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
                                    @foreach(array_slice($post->parsed_tags, 0, 3) as $tag)
                                        <span style="font-size:.65rem;padding:3px 10px;border-radius:99px;background:rgba(26,68,247,.1);border:1px solid rgba(26,68,247,.2);color:var(--blue-400);">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <div style="padding-top:20px;border-top:1px solid rgba(255,255,255,.05);display:flex;align-items:center;gap:6px;color:var(--gold);font-size:.85rem;font-weight:600;">
                                {{ __('Read Full Post') }}
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div style="margin-top:60px;display:flex;justify-content:center;">
                {{ $posts->links() }}
            </div>
        @endif
    </div>
</section>

@endsection
