@props(['sections' => [
    ['id' => 'hero-section',  'label' => __('Hero')],
    ['id' => 'story',         'label' => __('Story')],
    ['id' => 'stats-section', 'label' => __('Stats')],
    ['id' => 'leadership',    'label' => __('Leadership')],
    ['id' => 'programs',      'label' => __('Programs')],
    ['id' => 'services',      'label' => __('Services')],
    ['id' => 'cta',           'label' => __('Join')],
    ['id' => 'fundraising',   'label' => __('Fund')],
    ['id' => 'blog',          'label' => __('Blog')],
    ['id' => 'faq',           'label' => __('FAQ')],
]])

{{-- Desktop sidebar --}}
<nav id="quick-nav" aria-label="{{ __('Section navigation') }}" style="
    position:fixed;right:20px;top:50%;transform:translateY(-50%);z-index:700;
    display:flex;flex-direction:column;align-items:center;gap:4px;
    padding:10px 6px;border-radius:24px;
    background:rgba(5,10,28,.55);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
    border:1px solid rgba(255,255,255,.06);
    transition:opacity .3s,transform .3s;
    max-height:90vh;overflow-y:auto;-ms-overflow-style:none;scrollbar-width:none;
">
">
    @foreach($sections as $s)
    <a href="#{{ $s['id'] }}"
       class="qn-btn"
       data-section="{{ $s['id'] }}"
       aria-label="{{ $s['label'] }}"
       title="{{ $s['label'] }}"
       style="
           display:flex;align-items:center;justify-content:center;
           width:36px;height:36px;border-radius:50%;position:relative;
           text-decoration:none;transition:all .25s;
           background:transparent;border:none;cursor:pointer;
       "
    >
        <span style="
            display:block;width:10px;height:10px;border-radius:50%;
            background:rgba(255,255,255,.25);
            transition:all .3s cubic-bezier(.22,1,.36,1);
        "></span>
        <span class="qn-tooltip" style="
            position:absolute;right:calc(100% + 12px);top:50%;transform:translateY(-50%);
            white-space:nowrap;padding:4px 10px;border-radius:6px;
            background:rgba(5,10,28,.85);backdrop-filter:blur(8px);
            border:1px solid rgba(255,255,255,.08);
            font-size:.72rem;color:var(--text-main);font-weight:500;
            opacity:0;pointer-events:none;transition:opacity .2s;
        ">{{ $s['label'] }}</span>
    </a>
    @endforeach

    {{-- Divider --}}
    <div style="width:16px;height:1px;background:rgba(255,255,255,.08);margin:4px 0;"></div>

    {{-- Back to Top --}}
    <button id="qn-top"
       aria-label="{{ __('Back to top') }}"
       title="{{ __('Back to top') }}"
       style="
           display:flex;align-items:center;justify-content:center;
           width:36px;height:36px;border-radius:50%;position:relative;
           background:transparent;border:none;cursor:pointer;
           opacity:0;pointer-events:none;transition:all .3s;
       "
    >
        <svg width="14" height="14" fill="none" stroke="rgba(255,255,255,.4)" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
        </svg>
        <span class="qn-tooltip" style="
            position:absolute;right:calc(100% + 12px);top:50%;transform:translateY(-50%);
            white-space:nowrap;padding:4px 10px;border-radius:6px;
            background:rgba(5,10,28,.85);backdrop-filter:blur(8px);
            border:1px solid rgba(255,255,255,.08);
            font-size:.72rem;color:var(--text-main);font-weight:500;
            opacity:0;pointer-events:none;transition:opacity .2s;
        ">{{ __('Top') }}</span>
    </button>
</nav>

{{-- Mobile bottom bar --}}
<nav id="quick-nav-mobile" aria-label="{{ __('Section navigation') }}" style="
    position:fixed;bottom:0;left:0;right:0;z-index:700;
    display:none;justify-content:space-around;align-items:center;
    padding:8px 12px;padding-bottom:calc(8px + env(safe-area-inset-bottom, 0px));
    background:rgba(5,10,28,.88);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
    border-top:1px solid rgba(255,255,255,.06);
">
    @foreach($sections as $s)
    <a href="#{{ $s['id'] }}"
       class="qnm-btn"
       data-section="{{ $s['id'] }}"
       aria-label="{{ $s['label'] }}"
       style="
           display:flex;flex-direction:column;align-items:center;gap:2px;
           text-decoration:none;border:none;background:transparent;cursor:pointer;
           padding:6px 4px;min-width:44px;min-height:44px;justify-content:center;
           transition:opacity .2s;
       "
    >
        <span style="
            display:block;width:8px;height:8px;border-radius:50%;
            background:rgba(255,255,255,.25);transition:all .3s;
        "></span>
        <span style="font-size:.58rem;color:rgba(255,255,255,.4);font-weight:500;letter-spacing:.02em;transition:color .3s;">
            {{ $s['label'] }}
        </span>
    </a>
    @endforeach

    {{-- Mobile Back to Top --}}
    <button id="qnm-top"
       aria-label="{{ __('Back to top') }}"
       style="
           display:flex;flex-direction:column;align-items:center;gap:2px;
           border:none;background:transparent;cursor:pointer;
           padding:6px 4px;min-width:44px;min-height:44px;justify-content:center;
           transition:opacity .2s;
           opacity:0;pointer-events:none;
       "
    >
        <svg width="14" height="14" fill="none" stroke="rgba(255,255,255,.4)" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
        </svg>
        <span style="font-size:.58rem;color:rgba(255,255,255,.4);font-weight:500;letter-spacing:.02em;">{{ __('Top') }}</span>
    </button>
</nav>

@push('styles')
<style>
    /* ── Desktop hover tooltip ── */
    .qn-btn:hover span:first-child { background: #D4AF37; box-shadow: 0 0 12px rgba(212,175,55,.3); transform: scale(1.25); }
    .qn-btn:hover .qn-tooltip { opacity: 1; }
    .qn-btn.qn-active span:first-child { background: #D4AF37; box-shadow: 0 0 14px rgba(212,175,55,.35); transform: scale(1.3); }

    /* ── Back to Top hover ── */
    #qn-top.qn-visible { opacity: 1 !important; pointer-events: auto !important; }
    #qn-top:hover svg { stroke: #D4AF37; }
    #qn-top:hover .qn-tooltip { opacity: 1; }
    #qnm-top.qnm-visible { opacity: 1 !important; pointer-events: auto !important; }

    /* ── Mobile active ── */
    .qnm-btn.qnm-active span:first-child { background: #D4AF37; box-shadow: 0 0 10px rgba(212,175,55,.3); }
    .qnm-btn.qnm-active span:last-child { color: #D4AF37 !important; }

    /* ── Hide on mobile, show mobile bar ── */
    @media (max-width: 768px) {
        #quick-nav { display: none !important; }
        #quick-nav-mobile { display: flex !important; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const sections = @json($sections);
    const sectionIds = sections.map(s => s.id);

    const qnBtns = document.querySelectorAll('.qn-btn');
    const qnmBtns = document.querySelectorAll('.qnm-btn');
    const qnTop = document.getElementById('qn-top');
    const qnmTop = document.getElementById('qnm-top');

    let currentIndex = -1;

    function scrollToSection(id) {
        const el = document.getElementById(id);
        if (!el) return;
        const offset = 80;
        const top = el.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top, behavior: 'smooth' });
    }

    // Button clicks
    qnBtns.forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            scrollToSection(btn.dataset.section);
        });
    });
    qnmBtns.forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            scrollToSection(btn.dataset.section);
        });
    });
    qnTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    qnmTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    // Active section detection on scroll
    function updateActive() {
        const scrollY = window.scrollY;
        const maxScroll = document.documentElement.scrollHeight - window.innerHeight;

        // Back to Top visibility
        const showTop = scrollY > 300;
        qnTop.classList.toggle('qn-visible', showTop);
        qnmTop.classList.toggle('qnm-visible', showTop);

        // Find current section
        let active = -1;
        for (let i = sectionIds.length - 1; i >= 0; i--) {
            const el = document.getElementById(sectionIds[i]);
            if (el && el.offsetTop - 120 <= scrollY) {
                active = i;
                break;
            }
        }

        if (scrollY + window.innerHeight >= maxScroll - 10) {
            active = sectionIds.length - 1;
        }

        if (active === currentIndex) return;
        currentIndex = active;

        // Desktop
        qnBtns.forEach((btn, i) => {
            btn.classList.toggle('qn-active', i === active);
        });

        // Mobile
        qnmBtns.forEach((btn, i) => {
            btn.classList.toggle('qnm-active', i === active);
        });
    }

    window.addEventListener('scroll', updateActive, { passive: true });
    window.addEventListener('resize', updateActive, { passive: true });
    updateActive();
})();
</script>
@endpush