@extends('filament::layouts.app')

@push('head')
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
@endpush

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
<script src="{{ asset('css/tours.css') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const roles = @json(auth()->user()?->getRoleNames()?->values() ?? []);
        if (window.location.hash !== '#tour') return;

        const roleToScript = {
            education_head: 'education_head.js',
            education_monitor: 'education_monitor.js',
            hr_head: 'hr_head.js',
            finance_head: 'finance_head.js',
            charity_head: 'charity_head.js',
            tour_head: 'tour_head.js',
            av_head: 'av_head.js',
            inventory_staff: 'inventory_staff.js',
            admin: 'admin.js',
            superadmin: 'superadmin.js',
            nibret_hisab_head: 'nibret_hisab_head.js',
            worship_monitor: 'worship_monitor.js',
            mezmur_head: 'mezmur_head.js',
            internal_relations_head: 'internal_relations_head.js',
            department_secretary: 'department_secretary.js',
            staff: 'staff.js',
        };

        const role = roles.find((r) => roleToScript[r]);
        const scriptFile = role ? roleToScript[role] : null;
        if (!scriptFile) return;

        const script = document.createElement('script');
        script.src = `{{ asset('js/tours') }}/${scriptFile}`;
        script.onload = () => {
            const starters = {
                education_head: window.startEducationHeadTour,
                education_monitor: window.startEducationMonitorTour,
                hr_head: window.startHrHeadTour,
                finance_head: window.startFinanceHeadTour,
                charity_head: window.startCharityHeadTour,
                tour_head: window.startTourHeadTour,
                av_head: window.startAvHeadTour,
                inventory_staff: window.startInventoryStaffTour,
                admin: window.startAdminTour,
                superadmin: window.startSuperadminTour,
                nibret_hisab_head: window.startNibretHisabHeadTour,
                worship_monitor: window.startWorshipMonitorTour,
                mezmur_head: window.startMezmurHeadTour,
                internal_relations_head: window.startInternalRelationsHeadTour,
                department_secretary: window.startDepartmentSecretaryTour,
                staff: window.startStaffTour,
            };
            starters[role]?.();
        };
        document.head.appendChild(script);
    });
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
