@extends('layouts.public')

@section('title', __('Login'))

@section('content')
<section class="ft-section py-16">
    <div style="max-width:420px;margin:0 auto;" class="card p-8">
        <h1 class="text-2xl font-bold mb-2">{{ __('Sign in') }}</h1>
        <p class="text-sm text-slate-400 mb-6">{{ __('Staff open the admin dashboard. Students open the portal.') }}</p>
        @if (session('info'))
            <p class="text-sm text-primary-400 mb-4">{{ session('info') }}</p>
        @endif
        <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label class="text-sm">{{ __('Phone') }}</label>
                <div class="flex">
                    <span class="px-3 py-2 bg-slate-800 rounded-l">{{ config('finot.phone_prefix', '+251') }}</span>
                    <input name="phone" value="{{ old('phone') }}" required class="flex-1 px-3 py-2 rounded-r bg-slate-900 border border-slate-700" placeholder="911234567" inputmode="numeric" autocomplete="tel">
                </div>
                <p class="text-xs text-slate-500 mt-1">{{ \App\Services\PhoneFormattingService::helperText() }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ __('New students: first password is those 9 digits, then change it.') }}</p>
                @error('phone') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm">{{ __('Password') }}</label>
                <input type="password" name="password" required class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" autocomplete="current-password">
                @error('password') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <button class="btn btn-primary w-full" type="submit">{{ __('Sign in') }}</button>
        </form>
    </div>
</section>
@endsection
