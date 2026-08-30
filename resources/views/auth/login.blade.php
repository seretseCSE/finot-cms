@extends('layouts.auth')

@section('title', __('Login'))

@push('styles')
<style>
    .login-split { display: grid; grid-template-columns: 1fr; min-height: 100vh; min-height: 100dvh; }
    @media (min-width: 768px) { .login-split { grid-template-columns: 1fr 1fr; } }
    @media (min-width: 1280px) { .login-split { grid-template-columns: 1.15fr 0.85fr; } }

    .login-brand {
        position: relative; overflow: hidden; display: none;
        background: linear-gradient(135deg, #0A0A0F 0%, #111827 100%);
    }
    @media (min-width: 768px) { .login-brand { display: flex; align-items: center; justify-content: center; } }

    .login-brand__img {
        position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;
        opacity: 0.45; animation: loginZoom 25s ease-in-out infinite alternate;
    }
    @keyframes loginZoom { from { transform: scale(1); } to { transform: scale(1.08); } }

    .login-brand__overlay {
        position: absolute; inset: 0;
        background: linear-gradient(160deg, rgba(26,68,247,0.35) 0%, rgba(10,10,15,0.7) 50%, rgba(243,186,21,0.15) 100%);
    }

    .login-brand__text { position: relative; z-index: 2; text-align: center; padding: 2rem; }

    .login-form-side {
        display: flex; align-items: center; justify-content: center; padding: 2rem 1.5rem;
        background: var(--ft-canvas);
    }
    @media (min-width: 768px) { .login-form-side { padding: 3rem 2.5rem; } }

    .login-card {
        width: 100%; max-width: 400px;
        animation: loginFadeUp 0.5s ease-out both;
    }
    @keyframes loginFadeUp {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .login-input-group {
        display: flex; border-radius: 0.75rem; overflow: hidden;
        border: 1px solid var(--ft-border); background: var(--ft-canvas);
        transition: box-shadow 0.2s, border-color 0.2s;
    }
    .login-input-group:focus-within {
        border-color: var(--ft-blue);
        box-shadow: 0 0 0 3px rgba(26,68,247,0.12);
    }

    .login-role-badges { display: flex; gap: 0.5rem; }
    .login-role-badge {
        flex: 1; display: flex; align-items: center; gap: 0.5rem;
        padding: 0.625rem 0.75rem; border-radius: 0.625rem;
        background: var(--ft-canvas-soft); border: 1px solid var(--ft-border);
        font-size: 0.75rem; font-weight: 600; color: var(--ft-ink);
        transition: border-color 0.2s, background 0.2s;
    }
    .login-role-badge:hover {
        border-color: var(--ft-blue);
        background: rgba(26,68,247,0.04);
    }
    .login-role-badge svg { width: 1rem; height: 1rem; flex-shrink: 0; }

    .login-btn {
        width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
        padding: 0.875rem 1.5rem; border-radius: 0.75rem; border: none; cursor: pointer;
        font-size: 0.9375rem; font-weight: 600; color: #fff;
        background: linear-gradient(135deg, var(--ft-blue) 0%, var(--ft-blue-dark) 100%);
        box-shadow: 0 2px 8px rgba(26,68,247,0.25);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .login-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,68,247,0.3); }
    .login-btn:active { transform: translateY(0); }

    .login-divider {
        display: flex; align-items: center; gap: 1rem; margin: 1.5rem 0;
    }
    .login-divider::before, .login-divider::after {
        content: ''; flex: 1; height: 1px; background: var(--ft-border);
    }
</style>
@endpush

@section('content')
<div class="login-split">
    {{-- Left: Brand panel --}}
    <div class="login-brand">
        <img src="{{ asset('images/hero-bg.webp') }}" alt="" class="login-brand__img" loading="eager">
        <div class="login-brand__overlay"></div>
        <div class="login-brand__text">
            <div class="font-['Noto_Sans_Ethiopic'] text-5xl font-bold text-white/90 leading-tight mb-3">ፍኖተ ጽድቅ</div>
            <div class="w-12 h-px bg-white/30 mx-auto mb-3"></div>
            <div class="text-white/80 text-lg font-light tracking-wide">Finote Tsidik Sunday School</div>
            <p class="text-white/50 text-sm mt-4 max-w-xs mx-auto leading-relaxed">{{ __('Spiritual education, fellowship, and community service since 1984 E.C.') }}</p>
        </div>
    </div>

    {{-- Right: Form --}}
    <div class="login-form-side" x-data="{ showPassword: false }">
        <div class="login-card">
            {{-- Logo --}}
            <div class="flex flex-col items-center mb-8">
                <a href="{{ url('/') }}" class="mb-3" aria-label="{{ __('Go to homepage') }}">
                    <img src="{{ asset('images/logo2.png') }}" alt="Finote Tsidik" class="h-14 w-auto dark:block hidden" loading="eager">
                    <img src="{{ asset('images/logow.PNG') }}" alt="Finote Tsidik" class="h-14 w-auto dark:hidden block" loading="eager">
                </a>
                <h1 class="text-xl font-bold ft-ink">{{ __('Welcome Back') }}</h1>
                <p class="text-sm mt-1" style="color: var(--ft-ink-muted);">{{ __('Sign in to your account') }}</p>
            </div>

            {{-- Role badges --}}
            <div class="login-role-badges mb-6">
                <div class="login-role-badge">
                    <svg class="text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <div>
                        <div>{{ __('Staff') }}</div>
                        <div class="font-normal text-[0.65rem]" style="color: var(--ft-ink-muted);">{{ __('Admin dashboard') }}</div>
                    </div>
                </div>
                <div class="login-role-badge">
                    <svg class="text-secondary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                    <div>
                        <div>{{ __('Students') }}</div>
                        <div class="font-normal text-[0.65rem]" style="color: var(--ft-ink-muted);">{{ __('Student portal') }}</div>
                    </div>
                </div>
            </div>

            {{-- Flash messages --}}
            @if (session('info'))
                <div class="mb-5 rounded-xl border border-primary-500/25 bg-primary-500/8 px-4 py-3 text-sm text-primary-600 dark:text-primary-300" role="status">
                    {{ session('info') }}
                </div>
            @endif
            @if (session('error') || session('session_expired'))
                <div class="mb-5 rounded-xl border border-red-500/30 bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-300" role="alert">
                    {{ session('error') ?: session('session_expired') }}
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('login.submit', absolute: false) }}" class="space-y-5">
                @csrf

                <div>
                    <label for="login-phone" class="block text-xs font-semibold tracking-wide uppercase mb-2 ft-ink">{{ __('Phone Number') }}</label>
                    <div class="login-input-group">
                        <span class="inline-flex items-center px-3.5 text-sm font-medium shrink-0 border-r" style="color: var(--ft-ink-muted); border-color: var(--ft-border); background: var(--ft-canvas-soft);">{{ config('finot.phone_prefix', '+251') }}</span>
                        <input
                            name="phone"
                            id="login-phone"
                            value="{{ old('phone') }}"
                            required
                            class="flex-1 min-w-0 px-3.5 py-3 text-sm bg-transparent border-0 outline-none ft-ink placeholder:opacity-40"
                            placeholder="911234567"
                            inputmode="numeric"
                            autocomplete="tel"
                        >
                    </div>
                    <p class="text-xs mt-1.5" style="color: var(--ft-ink-muted);">{{ \App\Services\PhoneFormattingService::helperText() }}</p>
                    @error('phone')
                        <p class="text-red-500 dark:text-red-400 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="login-password" class="block text-xs font-semibold tracking-wide uppercase mb-2 ft-ink">{{ __('Password') }}</label>
                    <div class="login-input-group">
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            id="login-password"
                            required
                            class="flex-1 min-w-0 px-3.5 py-3 text-sm bg-transparent border-0 outline-none ft-ink placeholder:opacity-40"
                            autocomplete="current-password"
                        >
                        <button
                            type="button"
                            class="inline-flex items-center px-3 transition-colors hover:opacity-70"
                            style="color: var(--ft-ink-muted);"
                            @click="showPassword = !showPassword"
                            :aria-label="showPassword ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                        >
                            <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    <p class="text-xs mt-1.5" style="color: var(--ft-ink-muted);">{{ __('New students: first password is those 9 digits, then change it.') }}</p>
                    @error('password')
                        <p class="text-red-500 dark:text-red-400 text-sm mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <button class="login-btn" type="submit">
                    {{ __('Sign in') }}
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>

            <p class="text-center text-xs mt-8" style="color: var(--ft-ink-muted);">
                {{ __('Not enrolled yet?') }}
                <a href="{{ route('contact') }}" class="ft-text-link !text-xs !normal-case !tracking-normal">{{ __('Contact us') }}</a>
            </p>
        </div>
    </div>
</div>
@endsection
