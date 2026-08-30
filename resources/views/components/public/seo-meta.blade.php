@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'url' => null,
    'type' => 'website',
])

@php
    $siteName = config('app.name', 'Finote Tsidik');
    $pageTitle = $title ? "$title | $siteName" : $siteName;
    $pageDescription = $description ?? __('Finote Tsidik Sunday School — Faith, service, and fellowship since 1984 E.C.');
    $pageUrl = $url ?? request()->url();
    $pageImage = $image ?? asset('images/hero-bg.jpg');
    $locale = str_replace('_', '-', app()->getLocale());
@endphp

{{-- Open Graph --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url" content="{{ $pageUrl }}">
<meta property="og:image" content="{{ $pageImage }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="{{ $locale }}">

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
<meta name="twitter:image" content="{{ $pageImage }}">

{{-- Canonical URL --}}
<link rel="canonical" href="{{ $pageUrl }}">
