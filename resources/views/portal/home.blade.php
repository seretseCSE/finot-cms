@extends('layouts.public')

@section('title', __('Student portal'))

@section('content')
<section class="ft-section py-12">
    <div style="max-width:960px;margin:0 auto;">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold" data-tour="portal-home">{{ __('Hello, :name', ['name' => auth()->user()->name]) }}</h1>
            <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="btn btn-ghost">{{ __('Log out') }}</button></form>
        </div>
        <div class="grid gap-4 sm:grid-cols-2" data-tour="portal-tiles">
            <a href="{{ route('portal.results') }}" class="card p-6" data-tour="tile-results">
                <h2 class="text-xl font-semibold">{{ __('My Results') }}</h2>
                <p class="text-sm text-slate-400 mt-2">{{ __('Approved marklists for your class.') }}</p>
            </a>
            <a href="{{ route('portal.attendance') }}" class="card p-6" data-tour="tile-attendance">
                <h2 class="text-xl font-semibold">{{ __('My Attendance') }}</h2>
                <p class="text-sm text-slate-400 mt-2">{{ __('Your recent attendance record.') }}</p>
            </a>
            <a href="{{ route('library') }}" class="card p-6" data-tour="tile-library">
                <h2 class="text-xl font-semibold">{{ __('Library') }}</h2>
                <p class="text-sm text-slate-400 mt-2">{{ __('Books, worksheets, and documents.') }}</p>
            </a>
            <a href="{{ route('library') }}" class="card p-6" data-tour="tile-worksheets">
                <h2 class="text-xl font-semibold">{{ __('Worksheets') }}</h2>
                <p class="text-sm text-slate-400 mt-2">{{ __('Opens the existing library.') }}</p>
            </a>
        </div>
        <p class="mt-8"><a href="{{ route('portal.profile') }}" class="text-sm underline">{{ __('Profile & tour replay') }}</a>
            · <a href="{{ route('portal.withdrawal') }}" class="text-sm underline">{{ __('Request withdrawal') }}</a></p>
    </div>
</section>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!('caches' in window)) return;
    fetch(@json(route('portal.offline-snapshot')), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.clone().text().then(function (t) { return { r: r, t: t }; }) : null; })
        .then(function (payload) {
            if (!payload) return;
            caches.open('finot-media-opt-in').then(function (cache) {
                cache.put(payload.r.url, new Response(payload.t, { headers: { 'Content-Type': 'application/json' } }));
            });
        }).catch(function () {});
});
</script>
@endpush
@endsection
