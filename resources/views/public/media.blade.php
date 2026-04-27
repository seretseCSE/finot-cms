@extends('layouts.public')

@section('title', __('Media Gallery'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Media Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    {{-- Parallax background image --}}
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/features-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90) 0%,rgba(26,68,247,.2) 50%,var(--overlay-95) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Moments in Time') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;color:var(--text-hero);">
            {{ __('Visual') }}
            <span style="color:var(--gold);">{{ __('Gallery') }}</span>
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ __('Experience our spiritual life, events, and community activities through our collection of photos and videos.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  FILTERS
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:60px 24px 20px;background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        <div class="card sr" style="padding:24px 32px;margin-bottom:40px;">
            <form method="GET" action="{{ route('media') }}" style="display:flex;flex-wrap:wrap;gap:16px;">
                <div style="flex:1;min-width:280px;position:relative;">
                    <svg style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-40);" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search media...') }}" style="width:100%;background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 16px 12px 42px;color:var(--text-main);outline:none;">
                </div>
                
                <select name="type" style="background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 20px;color:var(--text-main);outline:none;cursor:pointer;min-width:140px;">
                    <option value="" style="background:var(--dark-900);">{{ __('All Types') }}</option>
                    <option value="Photo" {{ request('type') == 'Photo' ? 'selected' : '' }} style="background:var(--dark-900);">{{ __('Photos') }}</option>
                    <option value="Video" {{ request('type') == 'Video' ? 'selected' : '' }} style="background:var(--dark-900);">{{ __('Videos') }}</option>
                </select>

                <select name="category" style="background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 20px;color:var(--text-main);outline:none;cursor:pointer;min-width:160px;">
                    <option value="" style="background:var(--bg-800);">{{ __('All Categories') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }} style="background:var(--bg-800);">{{ $category->name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary" style="padding:12px 32px;">{{ __('Filter') }}</button>

                @if(request('search') || request('type') || request('category'))
                    <a href="{{ route('media') }}" class="btn btn-ghost" style="padding:12px 24px;">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  MEDIA GRID
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:0 24px 100px;background:var(--dark-900);">
    <div style="max-width:1280px;margin:0 auto;">
        
        @if($mediaItems->isEmpty())
            <div class="card sr" style="padding:80px;text-align:center;max-width:600px;margin:0 auto;">
                <div style="font-size:3rem;margin-bottom:24px;">📷</div>
                <h3 class="display" style="font-size:1.8rem;margin-bottom:12px;">{{ __('No Media Found') }}</h3>
                <p style="color:var(--text-60);">{{ __('Try adjusting your filters or check back later for new content.') }}</p>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;">
                @foreach($mediaItems as $item)
                    <div class="card sr" style="padding:0;overflow:hidden;display:flex;flex-direction:column;cursor:pointer;" data-delay="{{ $loop->index * 50 }}">
                        <div style="aspect-ratio:4/3;overflow:hidden;position:relative;background:var(--dark-800);">
                            @if($item->type === 'Photo')
                                <img src="{{ $item->file_url }}" alt="{{ $item->title }}" style="width:100%;height:100%;object-fit:cover;transition:transform .5s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            @else
                                <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--dark-800),var(--blue-primary));display:flex;align-items:center;justify-content:center;">
                                    <div style="width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;color:#fff;">
                                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                </div>
                            @endif
                            
                            {{-- Hover Overlay --}}
                            <div style="position:absolute;inset:0;background:var(--overlay-40);opacity:0;transition:opacity .3s;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0">
                                <div style="width:40px;height:40px;border-radius:50%;background:var(--gold);color:var(--dark-950);display:flex;align-items:center;justify-content:center;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                            </div>
                        </div>

                        <div style="padding:24px;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                                <span style="font-size:.65rem;padding:3px 10px;border-radius:99px;background:{{ $item->type === 'Photo' ? 'rgba(74,222,128,.1)' : 'rgba(248,113,113,.1)' }};border:1px solid {{ $item->type === 'Photo' ? 'rgba(74,222,128,.2)' : 'rgba(248,113,113,.2)' }};color:{{ $item->type === 'Photo' ? '#86efac' : '#fca5a5' }};text-transform:uppercase;letter-spacing:.05em;">{{ $item->type }}</span>
                                @if($item->category)
                                    <span style="font-size:.65rem;padding:3px 10px;border-radius:99px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);color:var(--text-40);">{{ $item->category->name }}</span>
                                @endif
                            </div>
                            <h3 style="font-size:1rem;font-weight:600;color:var(--text-display);line-height:1.4;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;">{{ $item->title }}</h3>
                            @if($item->description)
                                <p style="color:var(--text-60);font-size:.78rem;line-height:1.6;margin-top:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ strip_tags($item->description) }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:60px;display:flex;justify-content:center;">
                {{ $mediaItems->links() }}
            </div>
        @endif
    </div>
</section>

@endsection
