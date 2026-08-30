<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') | {{ config('app.name') }}@else{{ config('app.name') }}@endif</title>
    <meta name="description" content="@yield('meta_description', __('Finote Tsidik Sunday School — Faith, service, and fellowship since 1984 E.C.'))">

    <link rel="icon" type="image/png" href="{{ asset('images/logo2.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/logo2.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">

    <script>
    (function() {
        var theme = localStorage.getItem('theme');
        if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="min-h-screen ft-page transition-colors duration-300">

    @if(session('success'))
        <div class="fixed top-5 right-5 z-[800] px-5 py-3 bg-green-500/10 backdrop-blur-xl border border-green-500/20 text-green-300 rounded-xl text-sm shadow-lg" role="alert">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="fixed top-5 right-5 z-[800] px-5 py-3 bg-red-500/10 backdrop-blur-xl border border-red-500/20 text-red-300 rounded-xl text-sm shadow-lg" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <main id="main-content" class="min-h-screen" tabindex="-1">@yield('content')</main>

    @stack('scripts')
</body>
</html>
