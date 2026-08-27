@props([
    'href' => null,
    'image',
    'title',
    'meta' => null,
    'alt' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'ft-photo-card']) }}
>
    <img src="{{ $image }}" alt="{{ $alt ?? $title }}" loading="lazy">
    <div class="ft-photo-card__overlay">
        <div class="ft-photo-card__title font-['Noto_Sans_Ethiopic']">{{ $title }}</div>
        @if($meta)
            <div class="ft-photo-card__meta">{{ $meta }}</div>
        @endif
    </div>
</{{ $tag }}>
