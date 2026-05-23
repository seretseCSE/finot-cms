@extends('layouts.public')

@section('title', __('Song Library'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Songs Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    {{-- Parallax background image --}}
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/page-title-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90) 0%,rgba(26,68,247,.1) 50%,var(--overlay-95) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Hymns & Choirs') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;color:var(--text-hero);">
            {{ __('Song') }}
            <span style="color:var(--gold);">{{ __('Library') }}</span>
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ __('Explore our collection of inspirational hymns, choral music, and traditional Ethiopian Orthodox songs.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  FILTERS
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:60px 24px 20px;background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        <div class="card sr" style="padding:24px 32px;margin-bottom:40px;">
            <form method="GET" action="{{ route('songs.index') }}" style="display:flex;flex-wrap:wrap;gap:16px;">
                <div style="flex:1;min-width:280px;position:relative;">
                    <svg style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-40);" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search songs...') }}" style="width:100%;background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 16px 12px 42px;color:var(--text-main);outline:none;">
                </div>
                
                <select name="category" style="background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 20px;color:var(--text-main);outline:none;cursor:pointer;min-width:160px;">
                    <option value="" style="background:var(--bg-800);">{{ __('All Categories') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }} style="background:var(--bg-800);">{{ $category->name }}</option>
                    @endforeach
                </select>

                <div style="display:flex;gap:16px;align-items:center;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text-60);font-size:.85rem;">
                        <input type="checkbox" name="has_audio" value="1" {{ request('has_audio') ? 'checked' : '' }} style="accent-color:var(--blue-primary);"> {{ __('Audio') }}
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text-60);font-size:.85rem;">
                        <input type="checkbox" name="has_video" value="1" {{ request('has_video') ? 'checked' : '' }} style="accent-color:var(--blue-primary);"> {{ __('Video') }}
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" style="padding:12px 32px;">{{ __('Filter') }}</button>

                @if(request('search') || request('category') || request('has_audio') || request('has_video'))
                    <a href="{{ route('songs.index') }}" class="btn btn-ghost" style="padding:12px 24px;">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  SONGS GRID
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:0 24px 100px;background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        
        @if($songs->isEmpty())
            <div class="card sr" style="padding:80px;text-align:center;max-width:600px;margin:0 auto;">
                <x-tour-icon name="faith" size="48" class="" aria-hidden="true" />
                <h3 class="display" style="font-size:1.8rem;margin-bottom:12px;">{{ __('No Songs Found') }}</h3>
                <p style="color:var(--text-60);">{{ __('Try adjusting your filters or search terms.') }}</p>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;">
                @foreach($songs as $song)
                    <a href="{{ route('songs.show', $song->id) }}" class="card sr" style="padding:28px;display:flex;flex-direction:column;gap:16px;text-decoration:none;" data-delay="{{ $loop->index * 40 }}">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div style="width:44px;height:44px;border-radius:10px;background:var(--blue-glow);border:1px solid rgba(26,68,247,.2);display:flex;align-items:center;justify-content:center;">
                                <svg width="22" height="22" fill="var(--blue-400)" viewBox="0 0 24 24"><path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                            </div>
                            <div style="display:flex;gap:4px;">
                                @if($song->has_audio)
                                    <span style="font-size:.6rem;padding:2px 8px;border-radius:4px;background:rgba(255,255,255,.05);color:var(--parchment-40);">{{ __('Audio') }}</span>
                                @endif
                                @if($song->has_video)
                                    <span style="font-size:.6rem;padding:2px 8px;border-radius:4px;background:rgba(255,255,255,.05);color:var(--parchment-40);">{{ __('Video') }}</span>
                                @endif
                            </div>
                        </div>

                        <div>
                            <h3 style="font-size:1.1rem;font-weight:600;color:var(--text-display);margin-bottom:6px;line-height:1.4;transition:color .3s;">{{ $song->title }}</h3>
                            @if($song->artist)
                                <div style="font-size:.8rem;color:var(--gold);">{{ $song->artist }}</div>
                            @endif
                        </div>

                        @if($song->category)
                            <div style="font-size:.72rem;color:var(--text-40);padding-top:12px;border-top:1px solid rgba(255,255,255,.05);display:flex;align-items:center;gap:6px;">
                                <span style="width:6px;height:6px;border-radius:50%;background:var(--blue-primary);"></span>
                                {{ $song->category->name }}
                            </div>
                        @endif

                        <div style="margin-top:auto;display:flex;align-items:center;gap:6px;color:var(--text-60);font-size:.8rem;font-weight:600;">
                            {{ __('View Details') }}
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </div>
                    </a>
                @endforeach
            </div>

            <div style="margin-top:60px;display:flex;justify-content:center;">
                {{ $songs->links() }}
            </div>
        @endif
    </div>
</section>

@endsection
