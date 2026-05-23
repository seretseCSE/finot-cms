{{-- ═══════════════════════════════════════════════════════
     12.  LIBRARY RESOURCES — Featured downloads
═══════════════════════════════════════════════════════ --}}
<section id="library" style="padding:80px 24px;background:var(--dark-950);">
    <div style="max-width:1280px;margin:0 auto;">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:44px;">
            <div>
                <div class="sec-label sr">{{ __('Knowledge') }}</div>
                <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);">{{ __('Library Resources') }}</h2>
            </div>
            <a href="{{ route('library') }}" class="btn btn-ghost sr">{{ __('View All Resources') }}</a>
        </div>

        @if($featuredLibraryResources && $featuredLibraryResources->count() > 0)
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;">
            @foreach($featuredLibraryResources as $resource)
            <div class="card sr" style="overflow:hidden;" data-delay="{{ $loop->index * 70 }}">
                <div style="height:100px;background:linear-gradient(135deg,var(--dark-700),var(--blue-primary));display:flex;align-items:center;justify-content:center;position:relative;">
                    <svg style="width:40px;color:var(--gold);opacity:.6;" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/>
                    </svg>
                    <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(5,10,28,.5),transparent);"></div>
                </div>
                <div style="padding:16px;">
                    <h3 style="font-size:.88rem;font-weight:600;color:var(--text-display);margin-bottom:6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $resource->title }}</h3>
                    @if($resource->category)
                    <span style="font-size:.68rem;padding:2px 8px;border-radius:99px;background:rgba(26,68,247,.15);border:1px solid rgba(26,68,247,.25);color:var(--blue-400);display:inline-block;margin-bottom:8px;">{{ $resource->category->name }}</span>
                    @endif
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:.72rem;color:var(--parchment-40);">{{ $resource->formatted_file_size }}</span>
                        <a href="{{ route('library.download', $resource) }}" style="display:flex;align-items:center;gap:5px;font-size:.78rem;font-weight:600;color:var(--gold);text-decoration:none;">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            {{ __('Download') }}
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($totalLibraryResources > 0)
        <div style="text-align:center;margin-top:24px;">
            <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 16px;border-radius:99px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);color:#86efac;font-size:.78rem;">
                <svg width="13" height="13" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ __(':count resources available', ['count' => $totalLibraryResources]) }}
            </span>
        </div>
        @endif
        @else
        <div class="sr" style="max-width:420px;margin:0 auto;">
            <x-empty-state-card type="library" title="{{ __('No Featured Resources') }}" message="{{ __('Our library is growing! New resources are being thoughtfully curated for you.') }}" ctaText="{{ __('Browse Library') }}" ctaUrl="{{ route('library') }}" />
        </div>
        @endif
    </div>
</section>