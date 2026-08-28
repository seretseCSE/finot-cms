@extends('layouts.public')

@section('title', 'Finote Tsidik Sunday School')

@php
    $yearsOfService = (int) date('Y') - 1992;
    $galleryPhotos = ($recentPhotos && $recentPhotos->isNotEmpty())
        ? $recentPhotos
        : collect([
            (object)['file_url' => asset('images/unsplash/worship-music.jpg'), 'title' => __('Worship'), 'tags' => null],
            (object)['file_url' => asset('images/unsplash/children-reading.jpg'), 'title' => __('Children'), 'tags' => null],
            (object)['file_url' => asset('images/unsplash/team-community.jpg'), 'title' => __('Community'), 'tags' => null],
            (object)['file_url' => asset('images/unsplash/event-celebration.jpg'), 'title' => __('Events'), 'tags' => null],
            (object)['file_url' => asset('images/unsplash/bible-study.jpg'), 'title' => __('Study'), 'tags' => null],
            (object)['file_url' => asset('images/unsplash/leadership-team.jpg'), 'title' => __('Leadership'), 'tags' => null],
        ]);
    $storyPhoto = $galleryPhotos->first()->file_url ?? asset('images/features-bg.webp');
@endphp

@section('content')

{{-- 1. HERO --}}
<section id="hero" class="ft-hero"> 
    <div class="ft-hero__overlay" aria-hidden="true"></div>

    <div class="ft-hero__content">
        <h1 class="reveal">
            <span class="ft-hero__amharic">ፍኖተ ጽድቅ</span>
            <span class="ft-hairline" aria-hidden="true"></span>
            <span class="ft-hero__english">{{ __('Finote Tsidik Sunday School') }}</span>
        </h1>
        <p class="ft-hero__tagline reveal" data-delay="0.12">
            {{ __('A Place to Belong') }}
        </p>
        <div class="ft-hero__actions reveal" data-delay="0.22">
            <a href="{{ route('contact') }}" class="btn-primary">{{ __('Enroll Your Child') }}</a>
            <a href="{{ route('about') }}" class="ft-text-link">{{ __('Our Story') }}</a>
        </div>
    </div>
</section>

{{-- 2. STORY --}}
<section class="ft-story">
    <img src="{{ $storyPhoto }}" alt="{{ __('Sunday school community') }}" class="ft-story__media" loading="lazy">
    <div class="ft-story__veil" aria-hidden="true"></div>
    <div class="ft-story__content reveal">
        <p class="ft-story__amharic">{{ __('ሰንበት ት/ቤታችን ፍኖተ ጽድቅ ሰንበት ትምህርትቤት በአየርጤና አካባቢ በነበሩ ትጉህ እና መንፈሳዊ ወጣቶች በ1984 ዓ.ም ተመሠረተች።') }}</p>
        <span class="ft-hairline ft-hairline--center" aria-hidden="true"></span>
        <p class="ft-story__english">{{ __('From children to adults, our Sunday school provides spiritual education, fellowship, and community service for all ages.') }}</p>
        <p class="ft-story__years">{{ $yearsOfService }}+ {{ __('Years of Service') }} · {{ $stats['total'] ?? 0 }} {{ __('Members') }}</p>
        <a href="{{ route('about') }}" class="ft-text-link ft-text-link--light mt-6 inline-flex">{{ __('Read our story') }}</a>
    </div>
</section>

{{-- 3. PEOPLE STRIP --}}
<section class="ft-people" aria-label="{{ __('Who We Serve') }}">
    @foreach([
        ['img' => 'children-reading.jpg', 'title' => __('Children'), 'meta' => __('Sunday school & scripture')],
        ['img' => 'team-community.jpg', 'title' => __('Youth'), 'meta' => __('Fellowship and service')],
        ['img' => 'elder-person.jpg', 'title' => __('Families'), 'meta' => __('Parents and elders')],
    ] as $person)
        <div class="ft-people__item reveal">
            <img src="{{ asset('images/unsplash/' . $person['img']) }}" alt="{{ $person['title'] }}" loading="lazy">
            <div class="ft-people__caption">
                <div class="ft-people__title">{{ $person['title'] }}</div>
                <div class="ft-people__meta">{{ $person['meta'] }}</div>
            </div>
        </div>
    @endforeach
</section>

{{-- 4. PROGRAMS — three large tiles --}}
<section class="ft-section">
    <div class="ft-container">
        <x-public.section-heading
            :eyebrow="__('What We Offer')"
            :title="__('Programs')"
            align="center"
            class="mb-10 max-w-xl"
        />
        <div class="ft-programs">
            @foreach([
                ['img' => 'children-reading.jpg', 'title' => __("Children's Program"), 'am' => 'የሕፃናት ፕሮግራም', 'href' => route('about')],
                ['img' => 'team-community.jpg', 'title' => __('Youth Program'), 'am' => 'የወጣቶች ፕሮግራም', 'href' => route('about')],
                ['img' => 'bible-study.jpg', 'title' => __('Spiritual Courses'), 'am' => 'መንፈሳዊ ትምህርቶች', 'href' => route('courses.index')],
            ] as $tile)
                <a href="{{ $tile['href'] }}" class="ft-programs__tile reveal no-underline">
                    <img src="{{ asset('images/unsplash/' . $tile['img']) }}" alt="{{ $tile['title'] }}" loading="lazy">
                    <div class="ft-programs__caption">
                        <div class="ft-programs__am">{{ $tile['am'] }}</div>
                        <div class="ft-programs__en">{{ $tile['title'] }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- 5. GALLERY --}}
<section class="ft-section" style="background: var(--ft-canvas-soft);">
    <div class="ft-container">
        <div class="flex flex-wrap items-end justify-between gap-4 mb-10">
            <x-public.section-heading
                :eyebrow="__('Gallery')"
                :title="__('Moments in Faith')"
            />
            <a href="{{ route('media') }}" class="ft-text-link shrink-0">{{ __('Browse Full Gallery') }}</a>
        </div>
        <div class="ft-gallery reveal">
            @foreach($galleryPhotos->take(6) as $photo)
                <a href="{{ route('media') }}" class="ft-gallery__item {{ $loop->first ? 'featured' : '' }}">
                    <img src="{{ $photo->file_url ?? asset('images/unsplash/worship-music.jpg') }}" alt="{{ $photo->title ?? __('Gallery photo') }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- 6. EVENTS --}}
<section class="ft-section">
    <div class="ft-container">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-10">
            <x-public.section-heading
                :eyebrow="__('Calendar')"
                :title="__('Upcoming Events')"
            />
            <a href="{{ route('news', ['tab' => 'events']) }}" class="ft-text-link">{{ __('View All') }}</a>
        </div>
        <div class="flex flex-col gap-2">
            @forelse($upcomingEvents as $event)
                @php $date = \Carbon\Carbon::parse($event->date_time); @endphp
                <a href="{{ route('events.show', $event) }}" class="ft-event-row reveal no-underline group">
                    <div class="ft-event-row__thumb">
                        <img src="{{ $event->featured_image_url ?? asset('images/unsplash/event-celebration.jpg') }}" alt="{{ $event->name }}" loading="lazy">
                    </div>
                    <div class="flex-1 p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="shrink-0 sm:w-16">
                            <div class="text-2xl font-extrabold ft-ink leading-none">{{ $date->format('d') }}</div>
                            <div class="text-xs font-semibold uppercase tracking-widest mt-1" style="color: var(--ft-ink-muted);">{{ $date->isoFormat('MMM') }}</div>
                        </div>
                        <div class="flex-1">
                            <div class="font-semibold text-lg ft-ink">{{ $event->name }}</div>
                            <div class="text-sm mt-1" style="color: var(--ft-ink-muted);">
                                {{ $date->format('h:i A') }}
                                @if($event->location) · {{ $event->location }} @endif
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <p class="text-center py-12" style="color: var(--ft-ink-muted);">{{ __('Check back soon for upcoming events.') }}</p>
            @endforelse
        </div>
    </div>
</section>

{{-- 7. LEADERSHIP --}}
<section class="ft-section">
    <div class="ft-container">
        <x-public.section-heading
            :eyebrow="__('Leadership')"
            :title="__('Our Leadership Team')"
            align="center"
            class="mb-12 max-w-xl"
        />
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 md:gap-10">
            @foreach([
                ['name' => __('መላከ ኀይል ቄስ ሰሎሞን ሙሉጌታ'), 'en' => 'Melake Hayil Kesis Solomon Mulugeta', 'role' => __('President'), 'img' => 'leadership-team.jpg'],
                ['name' => __('ዲያቆን ዮሴፍ ተፈራ'), 'en' => 'Deacon Yosef Tefera', 'role' => __('Vice President'), 'img' => 'team-community.jpg'],
                ['name' => __('እህት ሕይወት አበራ'), 'en' => 'Sister Hiwot Abera', 'role' => __('General Secretary'), 'img' => 'elder-person.jpg'],
            ] as $leader)
                <figure class="reveal text-center">
                    <div class="aspect-[3/4] overflow-hidden mb-4">
                        <img src="{{ asset('images/unsplash/' . $leader['img']) }}" alt="{{ $leader['en'] }}" class="w-full h-full object-cover" loading="lazy">
                    </div>
                    <figcaption>
                        <div class="text-[0.65rem] font-semibold tracking-[0.16em] uppercase mb-1" style="color: var(--ft-ink-muted);">{{ $leader['role'] }}</div>
                        <div class="font-['Noto_Sans_Ethiopic'] font-bold ft-ink">{{ $leader['name'] }}</div>
                        <div class="text-sm mt-0.5" style="color: var(--ft-ink-muted);">{{ $leader['en'] }}</div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>

{{-- 8. FUNDRAISING --}}
@if($campaigns && $campaigns->isNotEmpty())
<section class="ft-section" style="background: var(--ft-canvas-soft);">
    <div class="ft-container">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
            <div class="reveal overflow-hidden aspect-[4/3]">
                <img src="{{ asset('images/cta-bg.webp') }}" alt="{{ __('Support Our Mission') }}" class="w-full h-full object-cover" loading="lazy">
            </div>
            <div class="reveal">
                <x-public.section-heading
                    :eyebrow="__('Support Our Mission')"
                    :title="__('Fundraising Progress')"
                />
                <div class="mt-8 space-y-6">
                    @foreach($campaigns->take(2) as $campaign)
                        <div>
                            <div class="flex justify-between gap-3 items-baseline mb-2">
                                <h3 class="font-semibold ft-ink">{{ $campaign->campaign_name }}</h3>
                                <span class="text-sm" style="color: var(--ft-ink-muted);">{{ $campaign->progress_percentage }}%</span>
                            </div>
                            <div class="prog-track">
                                <div class="prog-fill" style="width: {{ min(100, $campaign->progress_percentage) }}%"></div>
                            </div>
                            <div class="flex justify-between text-xs mt-2" style="color: var(--ft-ink-muted);">
                                <span>ETB {{ number_format($campaign->total_raised) }}</span>
                                <span>{{ __('Goal: ETB :amount', ['amount' => number_format($campaign->target_amount)]) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('fundraising.index') }}" class="ft-text-link mt-8 inline-flex">{{ __('View Campaigns') }}</a>
            </div>
        </div>
    </div>
</section>
@endif

{{-- 9. FINAL CTA --}}
<section class="ft-cta-full">
    <img src="{{ asset('images/stats-bg.webp') }}" alt="" aria-hidden="true" loading="lazy">
    <div class="ft-cta-full__content reveal">
        <h2 class="ft-cta-full__amharic">ይመዝገቡ</h2>
        <span class="ft-hairline ft-hairline--center" aria-hidden="true"></span>
        <p class="ft-cta-full__en">{{ __('Join Our Community') }}</p>
        <p class="ft-cta-full__sub">{{ __('Join our Sunday school community. Learn, grow, and serve together.') }}</p>
        <div class="flex flex-wrap justify-center items-center gap-6 mt-8">
            <a href="{{ route('contact') }}" class="btn-primary">{{ __('Enroll Your Child') }}</a>
            <a href="{{ route('contact') }}" class="ft-text-link ft-text-link--light">{{ __('Contact Us') }}</a>
        </div>
    </div>
</section>

@endsection
