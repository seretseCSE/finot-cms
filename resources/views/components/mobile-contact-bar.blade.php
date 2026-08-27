@php
    $enrollUrl = route('contact');
@endphp

<div class="ft-sticky-cta" id="mobile-contact-bar" role="complementary" aria-label="{{ __('Enroll') }}">
    <a href="{{ $enrollUrl }}" class="ft-sticky-cta__btn">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m-8-8h16"/></svg>
        {{ __('Enroll Your Child') }}
    </a>
</div>

@push('styles')
<style>
    @media (max-width: 767px) {
        body { padding-bottom: 76px; }
    }
</style>
@endpush
