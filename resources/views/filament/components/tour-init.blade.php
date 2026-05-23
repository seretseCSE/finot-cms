<div id="product-tour-root"
     data-user-role="{{ auth()->user()?->roles->first()?->name ?? '' }}"
     data-panel="admin"
     data-version="{{ config('product-tour.current_version', '1.0.0') }}"
     aria-hidden="true"
     style="display:none"></div>

@php
    $tourEnabled = config('product-tour.enabled') && \App\Services\ProductTour\ProductTourService::isAvailableStatic();
@endphp

@if ($tourEnabled)
    @include('filament.components.whats-new')
    @vite('resources/js/tours/filament-init.js')
@endif
