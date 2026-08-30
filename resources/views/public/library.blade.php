@extends('layouts.public')

@section('title', __('Library'))

@section('content')

<x-public.page-hero
    :title="__('Resource Library')"
    :subtitle="__('Access our curated collection of spiritual books, educational documents, and sacred texts for your growth.')"
    :eyebrow="__('Knowledge Center')"
    :image="asset('images/masonry-portfolio/masonry-portfolio-8.jpg')"
/>

{{-- ═══════════════════════════════════════════════════════
     2.  STATS & FILTERS
     ═══════════════════════════════════════════════════════ --}}
<section class="ft-section pt-10">
    <div style="max-width:1200px;margin:0 auto;">
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:clamp(12px,3vw,20px);margin-bottom:clamp(24px,5vw,40px);">
            @foreach([
                ['val' => $totalResources, 'label' => __('Total Items'), 'color' => 'var(--blue-400)'],
                ['val' => $featuredResources, 'label' => __('Featured'), 'color' => 'var(--gold)'],
                ['val' => $categories->count(), 'label' => __('Categories'), 'color' => '#4ade80'],
                ['val' => $formatStat, 'label' => __('Top Format'), 'color' => '#f87171'],
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
                    <select name="category" id="library-category" style="background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 16px;color:var(--text-main);outline:none;cursor:pointer;flex:1;min-width:140px;font-size:16px;">
                        <option value="" style="background:var(--bg-800);">{{ __('All Categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }} style="background:var(--bg-800);">{{ $category->name }}</option>
                        @endforeach
                    </select>

                    <select name="subcategory" id="library-subcategory" style="background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 16px;color:var(--text-main);outline:none;cursor:pointer;flex:1;min-width:140px;font-size:16px;{{ $subcategories && $subcategories->count() > 0 ? '' : 'display:none;' }}" onchange="this.form.submit()">
                        <option value="" style="background:var(--bg-800);">{{ __('All Subcategories') }}</option>
                        @if($subcategories && $subcategories->count() > 0)
                            @foreach($subcategories as $subcategory)
                                <option value="{{ $subcategory->id }}" {{ request('subcategory') == $subcategory->id ? 'selected' : '' }} style="background:var(--bg-800);">{{ $subcategory->name }}</option>
                            @endforeach
                        @endif
                    </select>

                    <button type="submit" class="btn btn-primary" style="padding:12px 24px;flex:1;min-width:100px;">{{ __('Filter') }}</button>
                </div>

                @if(request('search') || request('category') || request('subcategory'))
                    <a href="{{ route('library') }}" class="btn btn-ghost" style="padding:12px 20px;width:100%;text-align:center;">{{ __('Clear') }}</a>
                @endif
            </form>

            {{-- Active filter tags --}}
            @if(request('category') || request('subcategory') || request('search'))
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;">
                @if(request('search'))
                    <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;background:rgba(25,65,245,.15);border:1px solid rgba(25,65,245,.25);color:var(--blue-400);font-size:.8rem;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        {{ request('search') }}
                        <a href="{{ route('library', array_merge(request()->except('search'), request()->only(['category','subcategory']))) }}" style="color:inherit;display:flex;"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></a>
                    </span>
                @endif
                @if(request('category'))
                    @php $activeCategory = $categories->firstWhere('id', request('category')); @endphp
                    @if($activeCategory)
                    <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;background:rgba(243,186,21,.1);border:1px solid rgba(243,186,21,.25);color:var(--gold);font-size:.8rem;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        {{ $activeCategory->name }}
                        <a href="{{ route('library', request()->except(['category','subcategory'])) }}" style="color:inherit;display:flex;"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></a>
                    </span>
                    @endif
                @endif
                @if(request('subcategory'))
                    @php $activeSubcategory = $subcategories?->firstWhere('id', request('subcategory')); @endphp
                    @if($activeSubcategory)
                    <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:99px;background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.25);color:#4ade80;font-size:.8rem;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        {{ $activeSubcategory->name }}
                        <a href="{{ route('library', request()->except('subcategory')) }}" style="color:inherit;display:flex;"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></a>
                    </span>
                    @endif
                @endif
            </div>
            @endif
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
                                @if($resource->file_type === 'audio')
                                    <svg width="22" height="22" fill="var(--blue-400)" viewBox="0 0 24 24"><path d="M12 2v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V4h4V2h-6z"/></svg>
                                @elseif($resource->file_type === 'video')
                                    <svg width="22" height="22" fill="var(--blue-400)" viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                                @elseif($resource->file_type === 'doc')
                                    <svg width="22" height="22" fill="var(--blue-400)" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                @else
                                    <svg width="22" height="22" fill="var(--blue-400)" viewBox="0 0 20 20"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>
                                @endif
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
                            <button type="button" class="btn btn-ghost" data-offline-url="{{ route('library.download', $resource) }}" data-offline-title="{{ $resource->title }}" style="padding:8px 12px;font-size:.75rem;">{{ __('Save offline') }}</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:60px;display:flex;justify-content:center;">
                {{ $resources->links() }}
            </div>
        @else
            <div class="sr" style="max-width:480px;margin:0 auto;">
                <x-empty-state-card type="library" />
            </div>
        @endif
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('library-category');
    const subcategorySelect = document.getElementById('library-subcategory');
    const allSubcategoriesLabel = '{{ __('All Subcategories') }}';

    categorySelect.addEventListener('change', function() {
        const categoryId = this.value;
        if (!categoryId) {
            subcategorySelect.style.display = 'none';
            subcategorySelect.innerHTML = '<option value="">' + allSubcategoriesLabel + '</option>';
            return;
        }

        fetch('{{ route('library.subcategories') }}?category=' + encodeURIComponent(categoryId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            let html = '<option value="">' + allSubcategoriesLabel + '</option>';
            data.forEach(s => {
                html += '<option value="' + s.id + '">' + s.name + '</option>';
            });
            subcategorySelect.innerHTML = html;
            subcategorySelect.style.display = '';
        })
        .catch(() => {
            subcategorySelect.style.display = 'none';
        });
    });
});
</script>

@endsection
