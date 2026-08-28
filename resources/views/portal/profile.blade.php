@extends('layouts.public')

@section('title', __('Profile'))

@section('content')
<section class="ft-section py-12">
    <div style="max-width:640px;margin:0 auto;">
        <p><a href="{{ route('portal.home') }}">{{ __('Back') }}</a></p>
        <h1 class="text-3xl font-bold mb-6">{{ __('My profile') }}</h1>
        @if (session('info'))
            <p class="text-sm text-primary-400 mb-4">{{ session('info') }}</p>
        @endif
        <form method="POST" action="{{ route('portal.profile.update') }}" class="space-y-4">
            @csrf
            <div>
                <label>{{ __('Name') }}</label>
                <input name="name" value="{{ old('name', auth()->user()->name) }}" required class="w-full rounded bg-slate-900 border border-slate-700 p-3">
            </div>
            <div>
                <label>{{ __('New password') }}</label>
                <input type="password" name="password" class="w-full rounded bg-slate-900 border border-slate-700 p-3">
            </div>
            <div>
                <label>{{ __('Confirm password') }}</label>
                <input type="password" name="password_confirmation" class="w-full rounded bg-slate-900 border border-slate-700 p-3">
            </div>
            <button class="btn btn-primary" type="submit">{{ __('Save') }}</button>
        </form>
        <button type="button" class="btn btn-ghost mt-6" onclick="window.dispatchEvent(new CustomEvent('product-tour:replay'))">{{ __('Replay tour') }}</button>
    </div>
</section>
<div id="product-tour-root" data-user-role="student" data-panel="portal" data-version="{{ config('product-tour.current_version') }}" hidden></div>
@vite('resources/js/tours/filament-init.js')
@endsection
