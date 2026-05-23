@props(['position' => 'inline'])

@php
    $currentLocale = app()->getLocale();
    $isEn = $currentLocale === 'en';
@endphp

<div
    class="lang-toggle"
    role="group"
    aria-label="{{ __('Language selection') }}"
    style="display:flex;align-items:center;gap:2px;position:relative;"
>
    {{-- Screen reader announcer --}}
    <div aria-live="polite" aria-atomic="true" class="lang-sr-announcer" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;"></div>

    {{-- EN button --}}
    <form method="POST" action="{{ route('language.switch', ['locale' => 'en']) }}" class="lang-form" style="display:inline;">
        @csrf
        <button type="submit"
            class="lang-btn {{ $isEn ? 'lang-active' : '' }}"
            data-locale="en"
            aria-label="{{ __('Switch to English') }}"
            aria-pressed="{{ $isEn ? 'true' : 'false' }}"
            title="{{ __('Switch language / ቋንቋ ቀይር') }}"
            style="
                min-width:44px;min-height:44px;padding:6px 10px;border-radius:8px;
                font-size:.78rem;font-weight:700;font-family:'Inter',sans-serif;
                background:{{ $isEn ? 'rgba(212,175,55,.18)' : 'transparent' }};
                border:1px solid {{ $isEn ? '#D4AF37' : 'rgba(255,255,255,.1)' }};
                color:{{ $isEn ? '#fff' : 'rgba(255,255,255,.45)' }};
                cursor:pointer;transition:all .25s;display:flex;align-items:center;justify-content:center;gap:4px;
                position:relative;line-height:1;
            "
        >EN</button>
    </form>

    {{-- Separator --}}
    <span style="color:rgba(255,255,255,.15);font-size:.7rem;font-weight:300;user-select:none;line-height:1;">|</span>

    {{-- አማ button --}}
    <form method="POST" action="{{ route('language.switch', ['locale' => 'am']) }}" class="lang-form" style="display:inline;">
        @csrf
        <button type="submit"
            class="lang-btn {{ !$isEn ? 'lang-active' : '' }}"
            data-locale="am"
            aria-label="{{ __('Switch to Amharic') }}"
            aria-pressed="{{ !$isEn ? 'true' : 'false' }}"
            title="{{ __('Switch language / ቋንቋ ቀይር') }}"
            style="
                min-width:44px;min-height:44px;padding:6px 10px;border-radius:8px;
                font-size:.82rem;font-weight:700;font-family:'Noto Sans Ethiopic','Nyala',sans-serif;
                background:{{ !$isEn ? 'rgba(212,175,55,.18)' : 'transparent' }};
                border:1px solid {{ !$isEn ? '#D4AF37' : 'rgba(255,255,255,.1)' }};
                color:{{ !$isEn ? '#fff' : 'rgba(255,255,255,.45)' }};
                cursor:pointer;transition:all .25s;display:flex;align-items:center;justify-content:center;
                position:relative;line-height:1.6;
            "
        >አማ</button>
    </form>

    {{-- Tooltip --}}
    <div class="lang-tooltip" role="tooltip" style="
        position:absolute;top:calc(100% + 8px);right:0;
        white-space:nowrap;padding:6px 12px;border-radius:8px;
        background:rgba(5,10,28,.92);backdrop-filter:blur(12px);
        border:1px solid rgba(255,255,255,.08);
        font-size:.7rem;color:rgba(255,255,255,.7);font-weight:500;font-family:'Inter',sans-serif;
        opacity:0;pointer-events:none;transition:opacity .2s;
        z-index:10;
    ">
        <span class="am" style="font-family:'Noto Sans Ethiopic','Nyala',sans-serif;font-size:.72rem;line-height:1.5;">ቋንቋ ቀይር</span>
        <span style="margin:0 4px;color:rgba(255,255,255,.3);">/</span>
        <span>Switch language</span>
    </div>
</div>

@push('styles')
<style>
    .lang-btn:hover {
        background: rgba(212,175,55,.12) !important;
        color: #fff !important;
        transform: translateY(-1px);
    }
    .lang-btn.lang-active {
        box-shadow: 0 0 14px rgba(212,175,55,.2);
    }
    .lang-btn:focus-visible {
        outline: 2px solid #D4AF37;
        outline-offset: 2px;
    }
    .lang-toggle:hover .lang-tooltip {
        opacity: 1;
    }
    .lang-btn:focus + .lang-tooltip,
    .lang-btn:focus-visible ~ .lang-tooltip {
        opacity: 1;
    }

    {{-- Transition overlay for language switch --}}
    .lang-switching::before {
        content: '';
        position: fixed; inset: 0; z-index: 9999;
        background: var(--bg-950);
        opacity: 0;
        pointer-events: none;
        transition: opacity .25s ease;
    }
    .lang-switching.is-switching::before {
        opacity: 1;
        pointer-events: auto;
    }
    body.lang-switching {
        transition: opacity .25s ease;
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const forms = document.querySelectorAll('.lang-form');
    const announcer = document.querySelector('.lang-sr-announcer');

    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const btn = this.querySelector('.lang-btn');
            const locale = btn.dataset.locale;
            const label = locale === 'en' ? 'English' : 'አማርኛ';

            // Announce to screen readers
            if (announcer) {
                announcer.textContent = '{{ __('Loading') }} ' + label + '...';
            }

            // Add switching overlay
            document.body.classList.add('lang-switching');

            // Brief delay for overlay to render
            requestAnimationFrame(() => {
                document.body.classList.add('is-switching');

                setTimeout(() => {
                    // Submit the form naturally (page will reload)
                    this.submit();
                }, 200);
            });
        });
    });
})();
</script>
@endpush