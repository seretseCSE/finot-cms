@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'image' => null,
])

@php
    $image = $image ?: asset('images/hero-bg.webp');
@endphp

<section {{ $attributes->merge(['class' => 'ft-page-hero']) }}>
    <div class="ft-page-hero__media" aria-hidden="true">
        <img src="{{ $image }}" alt="" loading="eager" fetchpriority="high">
    </div>
    <div class="ft-page-hero__overlay" aria-hidden="true"></div>
    <div class="ft-page-hero__content">
        @if($eyebrow)
            <div class="ft-eyebrow text-primary-300 !text-primary-200 mb-4">{{ $eyebrow }}</div>
        @endif
        <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-tight text-white leading-tight max-w-4xl">
            {{ $title }}
        </h1>
        @if($subtitle)
            <p class="mt-4 text-lg text-white/75 max-w-2xl leading-relaxed">{{ $subtitle }}</p>
        @endif
        @if(isset($slot) && trim($slot) !== '')
            <div class="mt-8">{{ $slot }}</div>
        @endif
    </div>
</section>
