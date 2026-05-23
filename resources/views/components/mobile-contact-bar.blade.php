@php
    $phoneRaw = '+251911123456';
    $phoneDisplay = '+251 911 123 456';
    $mapsUrl = 'https://www.google.com/maps/dir/?api=1&destination=Fenote+Sedq+Sunday+School+Addis+Ababa+Ethiopia&destination_place_id=ChIJ5UADPKGHpRYRIXHDnP2rq1M';
    $contactUrl = route('contact');
@endphp

<div id="mobile-contact-bar" role="complementary" aria-label="{{ __('Quick contact') }}" style="
    display:none;position:fixed;bottom:0;left:0;right:0;z-index:800;
    background:rgba(255,255,255,.72);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
    border-top:1px solid rgba(0,0,0,.06);
    padding:8px 12px calc(8px + env(safe-area-inset-bottom, 0px));
    min-height:44px;
    box-shadow:0 -4px 24px rgba(0,0,0,.06);
    transition:transform .35s cubic-bezier(.22,1,.36,1),opacity .35s ease;
">

    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;max-width:600px;margin:0 auto;">

        {{-- Phone: tap-to-call --}}
        <a href="tel:{{ $phoneRaw }}"
           class="mcb-btn"
           data-mcb-action="phone"
           aria-label="{{ __('Call') }} {{ $phoneDisplay }}"
           style="
                display:flex;align-items:center;gap:6px;text-decoration:none;
                padding:8px 10px;border-radius:10px;min-height:44px;min-width:44px;
                flex:1;justify-content:center;
           "
        >
            <svg width="18" height="18" fill="none" stroke="#1A44F7" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
            <span style="font-size:.78rem;font-weight:600;color:#1A1A2E;font-family:'Inter',sans-serif;white-space:nowrap;">{{ $phoneDisplay }}</span>
        </a>

        {{-- Divider --}}
        <span style="width:1px;height:24px;background:rgba(0,0,0,.08);flex-shrink:0;"></span>

        {{-- Directions --}}
        <a href="{{ $mapsUrl }}"
           target="_blank"
           rel="noopener"
           class="mcb-btn"
           data-mcb-action="directions"
           aria-label="{{ __('Get directions to our location') }}"
           style="
                display:flex;align-items:center;gap:6px;text-decoration:none;
                padding:8px 10px;border-radius:10px;min-height:44px;min-width:44px;
                flex:1;justify-content:center;
           "
        >
            <svg width="18" height="18" fill="none" stroke="#1A44F7" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span style="font-size:.75rem;font-weight:500;color:#1A1A2E;font-family:'Inter',sans-serif;white-space:nowrap;">{{ __('Directions') }}</span>
        </a>
    </div>

    {{-- Floating "Message Us" FAB --}}
    <a href="{{ $contactUrl }}"
       id="mcb-fab"
       class="mcb-btn"
       data-mcb-action="message"
       aria-label="{{ __('Message us') }}"
       style="
            position:absolute;top:-24px;right:16px;
            width:52px;height:52px;border-radius:50%;
            background:linear-gradient(135deg,#FFD700,#FFB300);
            border:none;box-shadow:0 4px 20px rgba(255,179,0,.45);
            display:flex;align-items:center;justify-content:center;
            cursor:pointer;transition:transform .2s,box-shadow .2s;
            text-decoration:none;
       "
    >
        <svg width="22" height="22" fill="none" stroke="#1A1A2E" viewBox="0 0 24 24" style="stroke-width:2;">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
    </a>
</div>

@push('styles')
<style>
    .mcb-btn {
        background: transparent;
        transition: background .2s, transform .2s;
        -webkit-tap-highlight-color: transparent;
        user-select: none;
    }
    .mcb-btn:active {
        background: rgba(26,68,247,.08);
        transform: scale(.96);
    }
    .mcb-btn:focus-visible {
        outline: 2px solid #1A44F7;
        outline-offset: 2px;
        border-radius: 10px;
    }
    #mcb-fab:active {
        transform: scale(.9);
        box-shadow: 0 2px 12px rgba(255,179,0,.3);
    }
    #mcb-fab:focus-visible {
        outline: 2px solid #FFB300;
        outline-offset: 3px;
    }

    /* Hidden state for dismiss */
    #mobile-contact-bar.mcb-hidden {
        transform: translateY(100%);
        opacity: 0;
        pointer-events: none;
    }

    @media (max-width: 1023px) {
        #mobile-contact-bar {
            display: block !important;
        }
        /* Push page content above the bar */
        body {
            padding-bottom: 68px;
        }
        body.mcb-hidden {
            padding-bottom: 0;
        }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var bar = document.getElementById('mobile-contact-bar');
    var fab = document.getElementById('mcb-fab');
    if (!bar) return;

    // Check if already dismissed
    if (localStorage.getItem('mcb_dismissed') === '1') {
        bar.classList.add('mcb-hidden');
        document.body.classList.add('mcb-hidden');
        return;
    }

    // Haptic + dismiss on any interaction
    function handleTap(e) {
        var target = e.currentTarget || e.target.closest('.mcb-btn');
        if (!target) return;

        // Simulate haptic feedback
        if (navigator.vibrate) {
            navigator.vibrate(12);
        }

        // Dismiss bar after first interaction
        localStorage.setItem('mcb_dismissed', '1');
        bar.classList.add('mcb-hidden');
        document.body.classList.add('mcb-hidden');

        // Cleanup listeners
        bar.querySelectorAll('.mcb-btn').forEach(function (btn) {
            btn.removeEventListener('click', handleTap);
        });
    }

    bar.querySelectorAll('.mcb-btn').forEach(function (btn) {
        btn.addEventListener('click', handleTap);
    });
})();
</script>
@endpush