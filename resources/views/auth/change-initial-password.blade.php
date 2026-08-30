@extends('layouts.auth')

@section('title', __('Change Password'))

@push('styles')
<style>
    .pw-split { display: grid; grid-template-columns: 1fr; min-height: 100vh; min-height: 100dvh; }
    @media (min-width: 768px) { .pw-split { grid-template-columns: 1fr 1fr; } }
    @media (min-width: 1280px) { .pw-split { grid-template-columns: 1.15fr 0.85fr; } }

    .pw-brand {
        position: relative; overflow: hidden; display: none;
        background: linear-gradient(135deg, #0A0A0F 0%, #111827 100%);
    }
    @media (min-width: 768px) { .pw-brand { display: flex; align-items: center; justify-content: center; } }

    .pw-brand__img {
        position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;
        opacity: 0.4; animation: pwZoom 25s ease-in-out infinite alternate;
    }
    @keyframes pwZoom { from { transform: scale(1); } to { transform: scale(1.06); } }

    .pw-brand__overlay {
        position: absolute; inset: 0;
        background: linear-gradient(160deg, rgba(26,68,247,0.3) 0%, rgba(10,10,15,0.75) 50%, rgba(243,186,21,0.1) 100%);
    }

    .pw-brand__text { position: relative; z-index: 2; text-align: center; padding: 2rem; }

    .pw-form-side {
        display: flex; align-items: center; justify-content: center; padding: 2rem 1.5rem;
        background: var(--ft-canvas);
    }
    @media (min-width: 768px) { .pw-form-side { padding: 3rem 2.5rem; } }

    .pw-card {
        width: 100%; max-width: 400px;
        animation: pwFadeUp 0.5s ease-out both;
    }
    @keyframes pwFadeUp {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .pw-input-group {
        display: flex; border-radius: 0.75rem; overflow: hidden;
        border: 1px solid var(--ft-border); background: var(--ft-canvas);
        transition: box-shadow 0.2s, border-color 0.2s;
    }
    .pw-input-group:focus-within {
        border-color: var(--ft-blue);
        box-shadow: 0 0 0 3px rgba(26,68,247,0.12);
    }

    .pw-btn {
        width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
        padding: 0.875rem 1.5rem; border-radius: 0.75rem; border: none; cursor: pointer;
        font-size: 0.9375rem; font-weight: 600; color: #fff;
        background: linear-gradient(135deg, var(--ft-blue) 0%, var(--ft-blue-dark) 100%);
        box-shadow: 0 2px 8px rgba(26,68,247,0.25);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .pw-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,68,247,0.3); }
    .pw-btn:active { transform: translateY(0); }

    .pw-req {
        display: inline-flex; align-items: center; gap: 0.25rem;
        padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600;
        border: 1px solid var(--ft-border); color: var(--ft-ink-muted);
        transition: all 0.25s;
    }
    .pw-req.met { border-color: #1E8449; color: #1E8449; background: rgba(30,132,73,0.08); }
    .pw-req svg { width: 0.75rem; height: 0.75rem; }
</style>
@endpush

@section('content')
<div class="pw-split" x-data="{
    pw: '',
    showCurrent: false,
    showNew: false,
    showConfirm: false,
    get hasLength() { return this.pw.length >= 8 },
    get hasUpper() { return /[A-Z]/.test(this.pw) },
    get hasLower() { return /[a-z]/.test(this.pw) },
    get hasNumber() { return /[0-9]/.test(this.pw) },
}">
    {{-- Left: Brand panel --}}
    <div class="pw-brand">
        <img src="{{ asset('images/hero-bg.webp') }}" alt="" class="pw-brand__img" loading="eager">
        <div class="pw-brand__overlay"></div>
        <div class="pw-brand__text">
            <div class="font-['Noto_Sans_Ethiopic'] text-5xl font-bold text-white/90 leading-tight mb-3">ፍኖተ ጽድቅ</div>
            <div class="w-12 h-px bg-white/30 mx-auto mb-3"></div>
            <div class="text-white/80 text-lg font-light tracking-wide">Finote Tsidik Sunday School</div>
            <div class="flex items-center justify-center gap-2 mt-6">
                <svg class="w-5 h-5 text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span class="text-white/50 text-sm">{{ __('Secure your account') }}</span>
            </div>
        </div>
    </div>

    {{-- Right: Form --}}
    <div class="pw-form-side">
        <div class="pw-card">
            {{-- Logo --}}
            <div class="flex flex-col items-center mb-8">
                <a href="{{ url('/') }}" class="mb-3" aria-label="{{ __('Go to homepage') }}">
                    <img src="{{ asset('images/logo2.png') }}" alt="Finote Tsidik" class="h-14 w-auto dark:block hidden" loading="eager">
                    <img src="{{ asset('images/logow.PNG') }}" alt="Finote Tsidik" class="h-14 w-auto dark:hidden block" loading="eager">
                </a>
                <h1 class="text-xl font-bold ft-ink">{{ __('Change Your Password') }}</h1>
                <p class="text-sm mt-1 text-center max-w-xs" style="color: var(--ft-ink-muted);">{{ __('For security, replace the temporary password before accessing the panel.') }}</p>
            </div>

            {{-- Errors --}}
            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-400/30 bg-red-500/8 px-4 py-3 text-sm text-red-600 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ __('Please fix the following:') }}</p>
                    <ul class="list-disc list-inside space-y-0.5 text-xs">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-5 rounded-xl border border-green-400/30 bg-green-500/8 px-4 py-3 text-sm text-green-600 dark:text-green-400">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('change-initial-password.submit', absolute: false) }}" class="space-y-5">
                @csrf

                <div>
                    <label for="current_password" class="block text-xs font-semibold tracking-wide uppercase mb-2 ft-ink">{{ __('Current Password') }}</label>
                    <div class="pw-input-group">
                        <input
                            :type="showCurrent ? 'text' : 'password'"
                            name="current_password"
                            id="current_password"
                            required
                            class="flex-1 min-w-0 px-3.5 py-3 text-sm bg-transparent border-0 outline-none ft-ink placeholder:opacity-40"
                            autocomplete="current-password"
                        >
                        <button type="button" class="inline-flex items-center px-3 transition-colors hover:opacity-70" style="color: var(--ft-ink-muted);" @click="showCurrent = !showCurrent" :aria-label="showCurrent ? '{{ __('Hide') }}' : '{{ __('Show') }}'">
                            <svg x-show="!showCurrent" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showCurrent" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    @error('current_password') <p class="text-red-500 text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold tracking-wide uppercase mb-2 ft-ink">{{ __('New Password') }}</label>
                    <div class="pw-input-group">
                        <input
                            :type="showNew ? 'text' : 'password'"
                            name="password"
                            id="password"
                            required
                            x-model="pw"
                            class="flex-1 min-w-0 px-3.5 py-3 text-sm bg-transparent border-0 outline-none ft-ink placeholder:opacity-40"
                            autocomplete="new-password"
                        >
                        <button type="button" class="inline-flex items-center px-3 transition-colors hover:opacity-70" style="color: var(--ft-ink-muted);" @click="showNew = !showNew">
                            <svg x-show="!showNew" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showNew" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <span class="pw-req" :class="{ 'met': hasLength }">
                            <svg x-show="hasLength" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            8+ chars
                        </span>
                        <span class="pw-req" :class="{ 'met': hasUpper }">
                            <svg x-show="hasUpper" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            A-Z
                        </span>
                        <span class="pw-req" :class="{ 'met': hasLower }">
                            <svg x-show="hasLower" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            a-z
                        </span>
                        <span class="pw-req" :class="{ 'met': hasNumber }">
                            <svg x-show="hasNumber" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            0-9
                        </span>
                    </div>
                    @error('password') <p class="text-red-500 text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold tracking-wide uppercase mb-2 ft-ink">{{ __('Confirm New Password') }}</label>
                    <div class="pw-input-group">
                        <input
                            :type="showConfirm ? 'text' : 'password'"
                            name="password_confirmation"
                            id="password_confirmation"
                            required
                            class="flex-1 min-w-0 px-3.5 py-3 text-sm bg-transparent border-0 outline-none ft-ink placeholder:opacity-40"
                            autocomplete="new-password"
                        >
                        <button type="button" class="inline-flex items-center px-3 transition-colors hover:opacity-70" style="color: var(--ft-ink-muted);" @click="showConfirm = !showConfirm">
                            <svg x-show="!showConfirm" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showConfirm" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    @error('password_confirmation') <p class="text-red-500 text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>

                <button class="pw-btn" type="submit">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    {{ __('Change Password') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
