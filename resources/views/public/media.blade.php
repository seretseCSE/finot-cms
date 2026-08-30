@extends('layouts.public')

@section('title', __('Media Gallery'))

@section('content')

<x-public.page-hero
    :title="__('Visual Gallery')"
    :subtitle="__('Photos, videos, and songs from our spiritual life and community.')"
    :eyebrow="__('Moments in Time')"
    :image="asset('images/masonry-portfolio/masonry-portfolio-4.jpg')"
/>

<section class="ft-section pt-10">
    <div class="ft-container">
        <div class="flex flex-wrap gap-2 mb-8 border-b" style="border-color: var(--ft-border);">
            @foreach([
                'photos' => __('Photos'),
                'videos' => __('Videos'),
                'songs' => __('Songs'),
            ] as $tab => $label)
                <a href="{{ route('media', ['tab' => $tab]) }}"
                   class="px-5 py-3 text-sm font-semibold rounded-t-xl transition-colors
                   {{ ($activeTab ?? 'photos') === $tab
                        ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-b-0'
                        : 'text-slate-500 hover:text-primary-500' }}"
                   style="{{ ($activeTab ?? 'photos') === $tab ? 'border-color: var(--ft-border);' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if(($activeTab ?? 'photos') !== 'songs')
            <form method="GET" action="{{ route('media') }}" class="ft-surface rounded-2xl p-4 mb-8 flex flex-wrap gap-3">
                <input type="hidden" name="tab" value="{{ $activeTab ?? 'photos' }}">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search media...') }}"
                       class="flex-1 min-w-[200px] rounded-xl px-4 py-2.5 text-sm border bg-transparent" style="border-color: var(--ft-border); color: var(--ft-ink);">
                @if(($categories ?? collect())->isNotEmpty())
                    <select name="category" class="rounded-xl px-4 py-2.5 text-sm border bg-transparent" style="border-color: var(--ft-border); color: var(--ft-ink);">
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="btn-primary !py-2.5">{{ __('Filter') }}</button>
            </form>

            @if(!$mediaGroups || $mediaGroups->isEmpty())
                <div class="ft-surface rounded-2xl p-16 text-center">
                    <h3 class="text-xl font-bold ft-ink mb-2">{{ __('No Media Found') }}</h3>
                    <p style="color: var(--ft-ink-muted);">{{ __('Try adjusting your filters or check back later for new content.') }}</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($mediaGroups as $group)
                        @php $item = $group['main']; @endphp
                        <a href="{{ route('media.show', $item) }}" class="ft-surface rounded-2xl overflow-hidden no-underline group reveal">
                            <div class="aspect-[4/3] overflow-hidden bg-slate-200 dark:bg-slate-800">
                                <img src="{{ $item->file_url ?? asset('images/masonry-portfolio/masonry-portfolio-4.jpg') }}" alt="{{ $item->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy">
                            </div>
                            <div class="p-4">
                                <h3 class="font-semibold ft-ink group-hover:text-primary-500">{{ $item->title }}</h3>
                                <p class="text-xs mt-1" style="color: var(--ft-ink-muted);">
                                    {{ $group['count'] }} {{ __('items') }}
                                    @if($group['photos']) · {{ $group['photos'] }} {{ __('photos') }} @endif
                                    @if($group['videos']) · {{ $group['videos'] }} {{ __('videos') }} @endif
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-10">{{ $mediaGroups->links() }}</div>
            @endif
        @else
            @if(isset($songs) && $songs->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($songs as $song)
                        <a href="{{ route('songs.show', $song->id) }}" class="ft-surface rounded-2xl p-5 no-underline group reveal flex gap-4 items-center">
                            <div class="w-16 h-16 rounded-xl overflow-hidden shrink-0 bg-primary-500/10 flex items-center justify-center">
                                <img src="{{ asset('images/masonry-portfolio/masonry-portfolio-1.jpg') }}" alt="" class="w-full h-full object-cover opacity-80">
                            </div>
                            <div>
                                <h3 class="font-semibold ft-ink group-hover:text-primary-500 font-['Noto_Sans_Ethiopic']">{{ $song->title ?? $song->name }}</h3>
                                @if(!empty($song->artist) || !empty($song->composer))
                                    <p class="text-sm mt-1" style="color: var(--ft-ink-muted);">{{ $song->artist ?? $song->composer }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-10">{{ $songs->withQueryString()->links() }}</div>
            @else
                <div class="ft-surface rounded-2xl p-16 text-center">
                    <h3 class="text-xl font-bold ft-ink mb-2">{{ __('No songs found') }}</h3>
                </div>
            @endif
        @endif
    </div>
</section>

@endsection
