@extends('layouts.public')

@section('title', __('Library'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Library Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:clamp(60px,12vw,120px) clamp(12px,4vw,24px) clamp(40px,8vw,80px);background:var(--dark-950);overflow:hidden;">
    {{-- Parallax background image --}}
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/page-title-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90) 0%,rgba(26,68,247,.2) 50%,var(--overlay-95) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Knowledge Center') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;color:var(--text-hero);">
            {{ __('Resource') }}
            <span style="color:var(--gold);">{{ __('Library') }}</span>
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ __('Access our curated collection of spiritual books, educational documents, and sacred texts for your growth.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  STATS & FILTERS
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:clamp(40px,8vw,60px) clamp(12px,4vw,24px) clamp(15px,3vw,20px);background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:clamp(12px,3vw,20px);margin-bottom:clamp(24px,5vw,40px);">
            @foreach([
                ['val' => $totalResources, 'label' => __('Total Items'), 'color' => 'var(--blue-400)'],
                ['val' => $featuredResources, 'label' => __('Featured'), 'color' => 'var(--gold)'],
                ['val' => $categories->count(), 'label' => __('Categories'), 'color' => '#4ade80'],
                ['val' => 'PDF', 'label' => __('Format'), 'color' => '#f87171'],
            ] as $stat)
            <div class="card sr" style="padding:20px;text-align:center;border-color:rgba(255,255,255,.05);">
                <div style="font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:{{ $stat['color'] }};">{{ $stat['val'] }}</div>
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--parchment-40);margin-top:4px;">{{ $stat['label'] }}</div>
            </div>
            @endforeach
        </div>

        <div class="card sr" style="padding:clamp(16px,4vw,24px);margin-bottom:clamp(24px,5vw,40px);">
            <form action="{{ route('library') }}" method="GET" style="display:flex;flex-direction:column;gap:clamp(12px,3vw,16px);" id="library-filter-form">
                <div style="position:relative;">
                    <svg style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-40);" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search resources...') }}" style="width:100%;background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 16px 12px 42px;color:var(--text-main);outline:none;font-size:16px;">
                </div>
                
                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                    <select name="category" style="background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 16px;color:var(--text-main);outline:none;cursor:pointer;flex:1;min-width:140px;font-size:16px;" onchange="this.form.submit()">
                        <option value="" style="background:var(--bg-800);">{{ __('All Categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }} style="background:var(--bg-800);">{{ $category->name }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-primary" style="padding:12px 24px;flex:1;min-width:100px;">{{ __('Filter') }}</button>
                </div>

                @if(request('search') || request('category'))
                    <a href="{{ route('library') }}" class="btn btn-ghost" style="padding:12px 20px;width:100%;text-align:center;">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  RESOURCES GRID
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:0 clamp(12px,4vw,24px) clamp(60px,12vw,100px);background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        
        @if($resources->count() > 0)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:clamp(16px,4vw,24px);">
                @foreach($resources as $resource)
                    <div class="card sr" style="padding:clamp(20px,5vw,28px);display:flex;flex-direction:column;gap:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div style="width:44px;height:44px;border-radius:10px;background:var(--blue-glow);border:1px solid rgba(26,68,247,.2);display:flex;align-items:center;justify-content:center;">
                                <svg width="22" height="22" fill="var(--blue-400)" viewBox="0 0 20 20"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>
                            </div>
                            @if($resource->is_featured)
                                <span style="font-size:.65rem;padding:3px 10px;border-radius:99px;background:rgba(243,186,21,.1);border:1px solid rgba(243,186,21,.25);color:var(--gold);text-transform:uppercase;letter-spacing:.05em;">{{ __('Featured') }}</span>
                            @endif
                        </div>

                        <div>
                            <h3 style="font-size:1.05rem;font-weight:600;color:var(--text-display);margin-bottom:8px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $resource->title }}</h3>
                            @if($resource->category)
                                <div style="font-size:.75rem;color:var(--text-40);">{{ $resource->category->name }}</div>
                            @endif
                        </div>

                        @if($resource->description)
                            <p style="color:var(--text-60);font-size:.85rem;line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;flex:1;">
                                {{ $resource->description }}
                            </p>
                        @endif

                        <div style="padding-top:16px;border-top:1px solid rgba(255,255,255,.05);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                            <span style="font-size:.7rem;color:var(--parchment-40);">{{ $resource->formatted_file_size }}</span>
                            <a href="{{ route('library.download', $resource) }}" class="btn btn-primary" style="padding:8px 16px;font-size:.75rem;gap:6px;flex:1;min-width:100px;text-align:center;justify-content:center;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                {{ __('Download') }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:60px;display:flex;justify-content:center;">
                {{ $resources->links() }}
            </div>
        @else
            <div class="card sr" style="padding:80px;text-align:center;max-width:600px;margin:0 auto;">
                <div style="font-size:3rem;margin-bottom:24px;">📚</div>
                <h3 class="display" style="font-size:1.8rem;margin-bottom:12px;">{{ __('No Resources Found') }}</h3>
                <p style="color:var(--text-60);">{{ __('Try adjusting your filters or check back later for new content.') }}</p>
            </div>
        @endif
    </div>
</section>

@endsection
