<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Finote Tsidik Sunday School')</title>
    <meta name="description" content="@yield('meta_description', 'Finote Tsidik Sunday School — Faith, service, and fellowship since 1984 E.C.')">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/sunday-school.css', 'resources/js/sunday-school.js'])
    @stack('styles')
</head>
<body>
    <div id="scroll-progress"></div>

    <nav id="ss-nav" class="ss-nav">
        <a href="/sunday-school" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
            <img src="{{ asset('images/logo.png') }}" alt="Finote Tsidik" style="height:30px;width:auto;">
            <span style="font-weight:700;font-size:0.9rem;color:#fff;letter-spacing:-0.02em;">ፍኖተ ጽድቅ</span>
        </a>
        <div class="nav-links">
            <a href="#hero">Home</a>
            <a href="#about">About</a>
            <a href="#programs">Programs</a>
            <a href="#stats">Impact</a>
            <a href="#events">Events</a>
            <a href="#cta" class="nav-cta">Enroll</a>
            <a href="/" class="nav-back">Main Site</a>
        </div>
        <button class="nav-hamburger" aria-label="Toggle navigation">☰</button>
    </nav>

    <div class="snap-container" id="snap-container">
        @yield('content')
        <footer style="padding:40px 32px;text-align:center;color:rgba(255,255,255,0.25);font-size:0.7rem;letter-spacing:0.05em;border-top:1px solid rgba(255,255,255,0.04);background:#050505;">
            <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:12px;">
                <img src="{{ asset('images/logo.png') }}" alt="" style="height:24px;width:auto;opacity:0.4;">
                <span style="font-weight:600;color:rgba(255,255,255,0.3);">ፍኖተ ጽድቅ</span>
            </div>
            <p>&copy; {{ date('Y') }} Finote Tsidik Sunday School. All rights reserved.</p>
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
