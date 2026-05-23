@extends('filament::layouts.app')

@push('scripts')
@vite(['resources/js/app.js'])
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
                console.log('Service Worker registered:', registration);
            })
            .catch(error => {
                console.error('Service Worker registration failed:', error);
            });
    }
</script>

@endpush

@push('styles')
<style>
    #offline-banner {
        z-index: 9999;
    }
</style>
@endpush

{{ $slot }}

<x-pwa-install-prompt />
