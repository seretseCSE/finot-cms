@extends('layouts.public')

@section('title', 'Finote Tsidik Sunday School')
@section('seo_title', 'Finote Tsidik Sunday School')
@section('seo_description', __('Finote Tsidik Sunday School — Spiritual education, fellowship, and community service for children, youth, and adults in Addis Ababa since 1984 E.C.'))

@php
    $yearsOfService = (int) date('Y') - 1992;
    $img = fn (string $path) => asset('images/' . $path);
    $galleryPhotos = ($recentPhotos && $recentPhotos->isNotEmpty())
        ? $recentPhotos
        : collect([
            (object)['file_url' => $img('masonry-portfolio/masonry-portfolio-4.jpg'), 'title' => __('Worship'), 'tags' => null],
            (object)['file_url' => $img('masonry-portfolio/masonry-portfolio-5.jpg'), 'title' => __('Children'), 'tags' => null],
            (object)['file_url' => $img('masonry-portfolio/masonry-portfolio-1.jpg'), 'title' => __('Community'), 'tags' => null],
            (object)['file_url' => $img('masonry-portfolio/masonry-portfolio-8.jpg'), 'title' => __('Learning'), 'tags' => null],
            (object)['file_url' => $img('masonry-portfolio/masonry-portfolio-10.jpg'), 'title' => __('Events'), 'tags' => null],
            (object)['file_url' => $img('masonry-portfolio/masonry-portfolio-7.jpg'), 'title' => __('Youth'), 'tags' => null],
        ]);
    $storyPhoto = $galleryPhotos->first()->file_url ?? $img('page-title-bg.jpg');
@endphp

@section('content')

{{-- 1. HERO --}}
<section id="hero" class="ft-hero">
    <picture class="ft-hero__picture">
        <source srcset="{{ $img('hero-bg.webp') }}" type="image/webp">
        <img src="{{ $img('hero-bg.jpg') }}" alt="" class="ft-hero__media" width="1920" height="1080" loading="eager" fetchpriority="high">
    </picture>
    <div class="ft-hero__overlay ft-hero__overlay--mesh" aria-hidden="true"></div>

    <div class="ft-hero__content ft-hero__content--centered">
        <span class="ft-hero__watermark reveal" aria-hidden="true">ፍኖተ ጽድቅ</span>
        <h1 class="reveal">
            <span class="ft-hero__english ft-hero__english--large">{{ __('Finote Tsidik') }}</span>
            <span class="ft-hero__english ft-hero__english--sub">{{ __('Sunday School') }}</span>
        </h1>
        <p class="ft-hero__tagline reveal" data-delay="0.12">{{ __('A Place to Belong') }}</p>
        <div class="ft-hero__actions ft-hero__actions--centered reveal" data-delay="0.22">
            <a href="{{ route('contact') }}" class="btn-primary btn-primary--lg">{{ __('Enroll Your Child') }}</a>
            <a href="{{ route('about') }}" class="ft-ghost-btn">{{ __('Our Story') }}</a>
        </div>
        <div class="ft-hero__scroll reveal" data-delay="0.4" aria-hidden="true">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
        </div>
    </div>
</section>

{{-- 2. STORY — two-column --}}
<section class="ft-section ft-section--lg">
    <div class="ft-container">
        <div class="ft-story-grid">
            <div class="ft-story-grid__media reveal">
                <img src="{{ $storyPhoto }}" alt="{{ __('Sunday school community') }}" loading="lazy">
            </div>
            <div class="ft-story-grid__text reveal" data-delay="0.1">
                <span class="ft-eyebrow">{{ __('Our Story') }}</span>
                <h2 class="ft-title mt-2">{{ __('Founded on Faith') }}</h2>
                <p class="font-['Noto_Sans_Ethiopic'] text-base leading-relaxed mt-4" style="color: var(--ft-ink-muted);">{{ __('ሰንበት ት/ቤታችን ፍኖተ ጽድቅ ሰንበት ትምህርትቤት በአየርጤና አካባቢ በነበሩ ትጉህ እና መንፈሳዊ ወጣቶች በ1984 ዓ.ም ተመሠረተች።') }}</p>
                <div class="ft-hairline my-5" aria-hidden="true"></div>
                <p class="text-base leading-relaxed" style="color: var(--ft-ink-muted);">{{ __('From children to adults, our Sunday school provides spiritual education, fellowship, and community service for all ages.') }}</p>
                <a href="{{ route('about') }}" class="ft-text-link mt-6 inline-flex">{{ __('Read Our Full Story') }}</a>

                {{-- Stat counters --}}
                <div class="ft-stat-row mt-8" x-data="statCounter()" x-init="$nextTick(() => { const el = $el; const obs = new IntersectionObserver((entries) => { if (entries[0].isIntersecting) { start(); obs.disconnect(); } }, { threshold: 0.3 }); obs.observe(el); })">
                    <div class="ft-stat">
                        <span class="ft-stat__number" x-text="display(years, {{ $yearsOfService }})">{{ $yearsOfService }}+</span>
                        <span class="ft-stat__label">{{ __('Years') }}</span>
                    </div>
                    <div class="ft-stat">
                        <span class="ft-stat__number" x-text="display(members, {{ $stats['total'] ?? 0 }})">{{ $stats['total'] ?? 0 }}</span>
                        <span class="ft-stat__label">{{ __('Members') }}</span>
                    </div>
                    <div class="ft-stat">
                        <span class="ft-stat__number" x-text="display(depts, {{ $stats['departments'] ?? 5 }})">{{ $stats['departments'] ?? 5 }}</span>
                        <span class="ft-stat__label">{{ __('Departments') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 3. PEOPLE STRIP --}}
<section class="ft-people-v2" aria-label="{{ __('Who We Serve') }}">
    @foreach([
        ['img' => 'masonry-portfolio/masonry-portfolio-5.jpg', 'title' => __('Children'), 'meta' => __('Sunday school & scripture')],
        ['img' => 'masonry-portfolio/masonry-portfolio-7.jpg', 'title' => __('Youth'), 'meta' => __('Fellowship and service')],
        ['img' => 'masonry-portfolio/masonry-portfolio-4.jpg', 'title' => __('Families'), 'meta' => __('Parents and elders')],
    ] as $i => $person)
        <div class="ft-people-v2__card reveal" data-delay="{{ $i * 0.08 }}" style="--card-offset: {{ $i * 1.2 }}rem;">
            <img src="{{ $img($person['img']) }}" alt="{{ $person['title'] }}" loading="lazy">
            <div class="ft-people-v2__overlay"></div>
            <div class="ft-people-v2__caption">
                <div class="ft-people-v2__title">{{ $person['title'] }}</div>
                <div class="ft-people-v2__meta">{{ $person['meta'] }}</div>
            </div>
        </div>
    @endforeach
</section>

{{-- 4. PROGRAMS — bento grid --}}
<section class="ft-section">
    <div class="ft-container">
        <x-public.section-heading
            :eyebrow="__('What We Offer')"
            :title="__('Programs')"
            align="center"
            class="mb-10 max-w-xl"
        />
        <div class="ft-bento-programs">
            @foreach([
                ['img' => 'masonry-portfolio/masonry-portfolio-8.jpg', 'title' => __("Children's Program"), 'am' => 'የሕፃናት ፕሮግራም', 'href' => route('about'), 'span' => 'large'],
                ['img' => 'masonry-portfolio/masonry-portfolio-1.jpg', 'title' => __('Youth Program'), 'am' => 'የወጣቶች ፕሮግራም', 'href' => route('about'), 'span' => 'small'],
                ['img' => 'cta-bg.webp', 'title' => __('Spiritual Courses'), 'am' => 'መንፈሳዊ ትምህርቶች', 'href' => route('courses.index'), 'span' => 'small'],
            ] as $i => $tile)
                <a href="{{ $tile['href'] }}" class="ft-bento-programs__tile {{ $tile['span'] === 'large' ? 'ft-bento-programs__tile--lg' : '' }} reveal no-underline" data-delay="{{ $i * 0.08 }}">
                    <img src="{{ $img($tile['img']) }}" alt="{{ $tile['title'] }}" loading="lazy">
                    <div class="ft-bento-programs__overlay"></div>
                    <div class="ft-bento-programs__caption">
                        <span class="ft-bento-programs__am">{{ $tile['am'] }}</span>
                        <span class="ft-bento-programs__en">{{ $tile['title'] }}</span>
                    </div>
                    <div class="ft-bento-programs__arrow" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
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
        <div class="ft-gallery-v2">
            @foreach($galleryPhotos->take(6) as $i => $photo)
                <a href="{{ route('media') }}" class="ft-gallery-v2__item {{ $loop->first ? 'ft-gallery-v2__item--featured' : '' }} reveal" data-delay="{{ $i * 0.06 }}">
                    <img src="{{ $photo->file_url ?? $img('masonry-portfolio/masonry-portfolio-4.jpg') }}" alt="{{ $photo->title ?? __('Gallery photo') }}" loading="lazy">
                    <div class="ft-gallery-v2__hover">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- 6. EVENTS — horizontal cards --}}
<section class="ft-section">
    <div class="ft-container">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-10">
            <x-public.section-heading
                :eyebrow="__('Calendar')"
                :title="__('Upcoming Events')"
            />
            <a href="{{ route('news', ['tab' => 'events']) }}" class="ft-text-link">{{ __('View All') }}</a>
        </div>
        @if($upcomingEvents->isNotEmpty())
            <div class="ft-events-scroll">
                @foreach($upcomingEvents->take(4) as $i => $event)
                    @php $date = \Carbon\Carbon::parse($event->date_time); @endphp
                    <a href="{{ route('events.show', $event) }}" class="ft-event-card reveal no-underline" data-delay="{{ $i * 0.08 }}">
                        <div class="ft-event-card__img">
                            <img src="{{ $event->featured_image_url ?? $img('hero-bg.webp') }}" alt="{{ $event->name }}" loading="lazy">
                        </div>
                        <div class="ft-event-card__badge">
                            <span class="ft-event-card__day">{{ $date->format('d') }}</span>
                            <span class="ft-event-card__month">{{ $date->isoFormat('MMM') }}</span>
                        </div>
                        <div class="ft-event-card__body">
                            <h3 class="ft-event-card__title">{{ $event->name }}</h3>
                            <p class="ft-event-card__meta">
                                {{ $date->format('h:i A') }}
                                @if($event->location) &middot; {{ $event->location }} @endif
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="text-center py-16">
                <svg class="w-12 h-12 mx-auto mb-4" style="color: var(--ft-ink-muted); opacity: 0.4;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p style="color: var(--ft-ink-muted);">{{ __('Check back soon for upcoming events.') }}</p>
            </div>
        @endif
    </div>
</section>

{{-- 7. LEADERSHIP --}}
<section class="ft-section" style="background: var(--ft-canvas-soft);">
    <div class="ft-container">
        <x-public.section-heading
            :eyebrow="__('Leadership')"
            :title="__('Our Leadership Team')"
            align="center"
            class="mb-12 max-w-xl"
        />
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 md:gap-12">
            @foreach([
                ['name' => __('መላከ ኀይል ቄስ ሰሎሞን ሙሉጌታ'), 'en' => 'Melake Hayil Kesis Solomon Mulugeta', 'role' => __('President'), 'img' => 'cta-bg.webp'],
                ['name' => __('ዲያቆን ዮሴፍ ተፈራ'), 'en' => 'Deacon Yosef Tefera', 'role' => __('Vice President'), 'img' => 'masonry-portfolio/masonry-portfolio-4.jpg'],
                ['name' => __('እህት ሕይወት አበራ'), 'en' => 'Sister Hiwot Abera', 'role' => __('General Secretary'), 'img' => 'hero-bg.webp'],
            ] as $i => $leader)
                <figure class="reveal text-center" data-delay="{{ $i * 0.1 }}">
                    <div class="ft-leader-photo">
                        <img src="{{ $img($leader['img']) }}" alt="{{ $leader['en'] }}" loading="lazy">
                    </div>
                    <figcaption class="mt-5">
                        <div class="text-[0.65rem] font-semibold tracking-[0.18em] uppercase mb-1.5" style="color: var(--ft-blue);">{{ $leader['role'] }}</div>
                        <div class="font-['Noto_Sans_Ethiopic'] font-bold ft-ink text-lg">{{ $leader['name'] }}</div>
                        <div class="text-sm mt-0.5" style="color: var(--ft-ink-muted);">{{ $leader['en'] }}</div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>

{{-- 8. FUNDRAISING --}}
@if($campaigns && $campaigns->isNotEmpty())
    <section class="ft-section">
        <div class="ft-container">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center">
                <div class="reveal overflow-hidden rounded-2xl aspect-[4/3]">
                    <img src="{{ $img('cta-bg.webp') }}" alt="{{ __('Support Our Mission') }}" class="w-full h-full object-cover" loading="lazy">
                </div>
                <div class="reveal" data-delay="0.1">
                    <x-public.section-heading
                        :eyebrow="__('Support Our Mission')"
                        :title="__('Fundraising Progress')"
                    />
                    <div class="mt-8 space-y-6">
                        @foreach($campaigns->take(2) as $campaign)
                            <div>
                                <div class="flex justify-between gap-3 items-baseline mb-2">
                                    <h3 class="font-semibold ft-ink">{{ $campaign->campaign_name }}</h3>
                                    <span class="text-sm font-semibold" style="color: var(--ft-blue);">{{ $campaign->progress_percentage }}%</span>
                                </div>
                                <div class="prog-track prog-track--animated">
                                    <div class="prog-fill prog-fill--gradient" style="width: {{ min(100, $campaign->progress_percentage) }}%"></div>
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

{{-- 8b. FAQ --}}
@if(isset($faqs) && $faqs->isNotEmpty())
    <section class="ft-section" style="background: var(--ft-canvas-soft);">
        <div class="ft-container max-w-3xl">
            <x-public.section-heading
                :eyebrow="__('Common Questions')"
                :title="__('Frequently Asked Questions')"
                align="center"
                class="mb-10"
            />
            <div class="space-y-3">
                @foreach($faqs as $faq)
                    <div class="border rounded-xl overflow-hidden" style="border-color: var(--ft-border); background: var(--ft-canvas);">
                        <button type="button"
                            class="faq-btn w-full flex items-center justify-between gap-4 px-6 py-4 text-left font-semibold ft-ink transition-colors hover:opacity-80"
                            aria-expanded="false"
                            aria-controls="faq-{{ $loop->index }}"
                        >
                            <span>{{ app()->getLocale() === 'am' ? ($faq->question_am ?? $faq->question) : $faq->question }}</span>
                            <svg class="w-5 h-5 shrink-0 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div id="faq-{{ $loop->index }}" class="faq-body px-6 pb-0 overflow-hidden max-h-0 transition-all duration-300" role="region">
                            <div class="pb-4 text-sm leading-relaxed" style="color: var(--ft-ink-muted);">
                                {{ app()->getLocale() === 'am' ? ($faq->answer_am ?? $faq->answer) : $faq->answer }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @push('structured-data')
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            @foreach($faqs as $faq)
            {
                "@type": "Question",
                "name": @json($faq->question),
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": @json(strip_tags($faq->answer))
                }
            }@if(!$loop->last),@endif
            @endforeach
        ]
    }
    </script>
    @endpush
@endif

{{-- 9. FINAL CTA --}}
<section class="ft-cta-gradient">
    <div class="ft-cta-gradient__content reveal">
        <h2 class="font-['Noto_Sans_Ethiopic'] text-white/90 leading-none" style="font-size: clamp(2.5rem, 7vw, 5rem); font-weight: 900;">ይመዝገቡ</h2>
        <div class="w-12 h-px bg-white/25 mx-auto my-4"></div>
        <p class="text-white/75 text-lg tracking-wide uppercase">{{ __('Join Our Community') }}</p>
        <p class="text-white/50 mt-2 max-w-md mx-auto leading-relaxed">{{ __('Join our Sunday school community. Learn, grow, and serve together.') }}</p>
        <div class="flex flex-wrap justify-center items-center gap-5 mt-8">
            <a href="{{ route('contact') }}" class="btn-primary btn-primary--lg">{{ __('Enroll Your Child') }}</a>
            <a href="{{ route('contact') }}" class="ft-ghost-btn">{{ __('Contact Us') }}</a>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
function statCounter() {
    return {
        years: 0, members: 0, depts: 0, running: false,
        start() {
            if (this.running) return;
            this.running = true;
            this.animate('years', {{ $yearsOfService }}, 1200);
            this.animate('members', {{ $stats['total'] ?? 0 }}, 1600);
            this.animate('depts', {{ $stats['departments'] ?? 5 }}, 800);
        },
        animate(prop, target, duration) {
            const start = performance.now();
            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                this[prop] = Math.round(eased * target);
                if (progress < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        },
        display(val, target) {
            return val >= target ? val + '+' : val.toString();
        }
    };
}
</script>
@endpush
