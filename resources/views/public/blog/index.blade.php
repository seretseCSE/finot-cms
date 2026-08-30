@extends('layouts.public')

@section('title', __('Blog'))
@section('seo_title', __('Blog'))
@section('seo_description', __('Stories and reflections from Finote Tsidik Sunday School.'))

@section('content')

<x-public.page-hero
    :title="__('Blog')"
    :subtitle="__('Stories and reflections from our community.')"
    :eyebrow="__('Stories')"
    :image="asset('images/hero-bg.webp')"
/>

<section class="ft-section pt-10">
    <div class="ft-container">
        @if($posts->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($posts as $post)
                    @php
                        $title = app()->getLocale() === 'am' ? ($post->title_am ?? $post->title) : $post->title;
                        $excerpt = app()->getLocale() === 'am' ? ($post->content_am ?? $post->content) : $post->content;
                    @endphp
                    <a href="{{ route('blog.show', $post->slug) }}" class="ft-surface rounded-2xl overflow-hidden no-underline group reveal">
                        <div class="aspect-[16/10] overflow-hidden bg-slate-200 dark:bg-slate-800">
                            <img src="{{ $post->featured_image_url ?? asset('images/blog/blog-1.jpg') }}" alt="{{ $title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy">
                        </div>
                        <div class="p-5">
                            <h3 class="font-semibold ft-ink group-hover:text-primary-500 transition-colors font-['Noto_Sans_Ethiopic']">
                                {{ Str::limit($title, 70) }}
                            </h3>
                            <p class="text-sm mt-2 line-clamp-2" style="color: var(--ft-ink-muted);">
                                {{ Str::limit(strip_tags($excerpt), 120) }}
                            </p>
                            @if($post->published_at)
                                <p class="text-xs mt-3" style="color: var(--ft-ink-muted);">{{ $post->published_at->diffForHumans() }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @else
            <div class="ft-surface rounded-2xl p-12 text-center">
                <p class="ft-ink font-semibold">{{ __('No posts yet.') }}</p>
            </div>
        @endif
    </div>
</section>

@endsection
