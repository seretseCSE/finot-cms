@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
    'align' => 'left',
])

@php
    $alignClass = $align === 'center' ? 'text-center mx-auto items-center' : '';
    $eyebrowClass = $align === 'center' ? 'justify-center' : '';
@endphp

<div {{ $attributes->merge(['class' => "reveal {$alignClass}"]) }}>
    @if($eyebrow)
        <div class="ft-eyebrow {{ $eyebrowClass }}">{{ $eyebrow }}</div>
    @endif
    <h2 class="ft-title {{ $align === 'center' ? 'mx-auto' : '' }}">{{ $title }}</h2>
    @if($subtitle)
        <p class="ft-subtitle {{ $align === 'center' ? 'mx-auto' : '' }}">{{ $subtitle }}</p>
    @endif
</div>
