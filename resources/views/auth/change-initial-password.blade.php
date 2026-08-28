@extends('layouts.public')

@section('title', __('Change Password'))

@section('content')
<section class="ft-section py-16">
    <div style="max-width:420px;margin:0 auto;" class="card p-8">
        <h1 class="text-2xl font-bold mb-2">{{ __('Change your password') }}</h1>
        <p class="text-sm text-slate-400 mb-6">{{ __('For security, you must replace the temporary password before opening the admin panel.') }}</p>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-400/40 bg-red-500/10 p-3 text-sm text-red-500">
                <p class="font-semibold mb-1">{{ __('Please fix the following:') }}</p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <p class="text-sm text-green-400 mb-4">{{ session('success') }}</p>
        @endif

        <form method="POST" action="{{ route('change-initial-password.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label class="text-sm" for="current_password">{{ __('Current password') }}</label>
                <input id="current_password" type="password" name="current_password" required class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" autocomplete="current-password">
                @error('current_password') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm" for="password">{{ __('New password') }}</label>
                <input id="password" type="password" name="password" required class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" autocomplete="new-password">
                <p class="text-xs text-slate-500 mt-1">{{ __('At least 8 characters, with uppercase, lowercase, and a number. Must be different from the current password.') }}</p>
                @error('password') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm" for="password_confirmation">{{ __('Confirm new password') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" autocomplete="new-password">
                @error('password_confirmation') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <button class="btn btn-primary w-full" type="submit">{{ __('Change password') }}</button>
        </form>
    </div>
</section>
@endsection
