@props(['currentPage' => ''])

<nav id="main-nav" style="
    position:fixed;top:0;left:0;right:0;z-index:900;
    padding:0 24px;
    background:rgba(5,10,28,0.7);
    backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border-subtle);
    transition:background .4s,box-shadow .4s;
">
    <div style="max-width:1280px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;height:68px;">

        {{-- Logo --}}
        <a href="/" style="display:flex;align-items:center;gap:10px;text-decoration:none;flex-shrink:0;" id="nav-logo">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" style="height:38px;width:auto;">
            <div id="nav-logo-text">
                <div class="am" style="font-size:1rem;font-weight:700;color:var(--text-display);line-height:1.2;">ፍኖተ ጽድቅ</div>
                <div style="font-size:.62rem;letter-spacing:.08em;text-transform:uppercase;color:var(--gold);line-height:1;opacity:.85;">Sunday School</div>
            </div>
        </a>

        {{-- Desktop Links (hidden/shown via JS) --}}
        <div id="nav-links" style="display:flex;align-items:center;gap:4px;">
            @foreach([
                ['href' => '/',                          'label' => __('Home'),        'page' => 'home'],
                ['href' => route('about'),               'label' => __('About'),       'page' => 'about'],
                                ['href' => route('events'),              'label' => __('Events'),      'page' => 'events'],
                ['href' => route('announcements.index'), 'label' => __('News'),        'page' => 'announcements'],
                ['href' => route('songs.index'),         'label' => __('Songs'),       'page' => 'songs'],
                ['href' => route('library'),             'label' => __('Library'),     'page' => 'library'],
                ['href' => route('tours.index'),         'label' => __('Tours'),       'page' => 'tours'],
                ['href' => route('blog.index'),          'label' => __('Blog'),        'page' => 'blog'],
                ['href' => route('media'),               'label' => __('Media'),       'page' => 'media'],
                ['href' => route('fundraising.index'),   'label' => __('Fundraising'), 'page' => 'fundraising'],
                ['href' => route('contact'),             'label' => __('Contact'),     'page' => 'contact'],
            ] as $link)
                <a href="{{ $link['href'] }}"
                   class="nav-link {{ $currentPage === $link['page'] ? 'nav-active' : '' }}"
                   style="
                       padding:8px 12px;border-radius:6px;font-size:.82rem;font-weight:500;
                       color:{{ $currentPage === $link['page'] ? 'var(--text-display)' : 'var(--text-40)' }};
                       text-decoration:none;transition:color .2s,background .2s;position:relative;
                       {{ $currentPage === $link['page'] ? 'background:rgba(26,68,247,.1);' : '' }}
                   "
                >{{ $link['label'] }}</a>
            @endforeach

            {{-- Desktop Language Switcher --}}
            <div style="display:flex;align-items:center;gap:2px;margin-left:4px;">
                <form method="POST" action="{{ route('language.switch', ['locale' => 'en']) }}" style="display:inline;">
                    @csrf
                    <button type="submit" title="English" style="
                        padding:6px 10px;border-radius:6px;font-size:.75rem;font-weight:700;
                        background:{{ app()->getLocale() === 'en' ? 'rgba(26,68,247,.2)' : 'transparent' }};
                        border:1px solid {{ app()->getLocale() === 'en' ? 'var(--blue-primary)' : 'var(--border-subtle)' }};
                        color:{{ app()->getLocale() === 'en' ? 'var(--text-display)' : 'var(--text-40)' }};
                        cursor:pointer;transition:all .2s;
                    ">EN</button>
                </form>
                <form method="POST" action="{{ route('language.switch', ['locale' => 'am']) }}" style="display:inline;">
                    @csrf
                    <button type="submit" title="አማርኛ" style="
                        padding:6px 10px;border-radius:6px;font-size:.75rem;font-weight:700;
                        background:{{ app()->getLocale() === 'am' ? 'rgba(26,68,247,.2)' : 'transparent' }};
                        border:1px solid {{ app()->getLocale() === 'am' ? 'var(--blue-primary)' : 'var(--border-subtle)' }};
                        color:{{ app()->getLocale() === 'am' ? 'var(--text-display)' : 'var(--text-40)' }};
                        cursor:pointer;transition:all .2s;
                    ">አማ</button>
                </form>
            </div>

            {{-- Desktop Theme Button --}}
            <button id="theme-btn-desktop" title="Toggle Mode" style="
                width:36px;height:36px;border-radius:10px;flex-shrink:0;
                background:var(--glass);border:1px solid var(--border-subtle);
                color:var(--gold);cursor:pointer;display:flex;align-items:center;justify-content:center;
                transition:background .2s,transform .2s;margin:0 4px 0 4px;
            ">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path id="theme-path-d" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d=""/>
                </svg>
            </button>

            @auth
                <a href="{{ url('/admin') }}" class="nav-cta">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    {{ __('Dashboard') }}
                </a>
            @else
                <a href="{{ route('login') }}" class="nav-cta">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    {{ __('Login') }}
                </a>
            @endauth
        </div>

        {{-- Mobile Actions: Language + Theme + Burger (hidden/shown via JS) --}}
        <div id="mobile-actions" style="display:none;align-items:center;gap:8px;">

            <div style="display:flex;align-items:center;gap:2px;">
                <form method="POST" action="{{ route('language.switch', ['locale' => 'en']) }}" style="display:inline;">
                    @csrf
                    <button type="submit" title="English" style="
                        padding:6px 8px;border-radius:6px;font-size:.7rem;font-weight:700;
                        background:{{ app()->getLocale() === 'en' ? 'rgba(26,68,247,.2)' : 'transparent' }};
                        border:1px solid {{ app()->getLocale() === 'en' ? 'var(--blue-primary)' : 'var(--border-subtle)' }};
                        color:{{ app()->getLocale() === 'en' ? 'var(--text-display)' : 'var(--text-40)' }};
                        cursor:pointer;transition:all .2s;
                    ">EN</button>
                </form>
                <form method="POST" action="{{ route('language.switch', ['locale' => 'am']) }}" style="display:inline;">
                    @csrf
                    <button type="submit" title="አማርኛ" style="
                        padding:6px 8px;border-radius:6px;font-size:.7rem;font-weight:700;
                        background:{{ app()->getLocale() === 'am' ? 'rgba(26,68,247,.2)' : 'transparent' }};
                        border:1px solid {{ app()->getLocale() === 'am' ? 'var(--blue-primary)' : 'var(--border-subtle)' }};
                        color:{{ app()->getLocale() === 'am' ? 'var(--text-display)' : 'var(--text-40)' }};
                        cursor:pointer;transition:all .2s;
                    ">አማ</button>
                </form>
            </div>

            <button id="theme-btn-mobile" title="Toggle Mode" style="
                width:38px;height:38px;border-radius:10px;flex-shrink:0;
                background:var(--glass);border:1px solid var(--border-subtle);
                color:var(--gold);cursor:pointer;display:flex;align-items:center;justify-content:center;
                transition:background .2s;
            ">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path id="theme-path-m" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d=""/>
                </svg>
            </button>

            <button id="mobile-toggle" style="
                width:38px;height:38px;border-radius:8px;flex-shrink:0;
                background:var(--glass);border:1px solid var(--border-subtle);
                color:var(--text-display);cursor:pointer;
                display:flex;align-items:center;justify-content:center;
                transition:background .2s;
            ">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path id="burger-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div id="mobile-menu" style="
        max-height:0;overflow:hidden;opacity:0;
        transition:max-height .35s cubic-bezier(.22,1,.36,1),opacity .25s ease;
        background:rgba(5,10,28,0.98);
        border-top:0px solid var(--border-subtle);
    ">
        <div style="padding:12px 8px 20px;display:flex;flex-direction:column;gap:2px;">
            @foreach([
                ['href' => '/',                          'label' => __('Home'),        'page' => 'home'],
                ['href' => route('about'),               'label' => __('About'),       'page' => 'about'],
                                ['href' => route('events'),              'label' => __('Events'),      'page' => 'events'],
                ['href' => route('announcements.index'),   'label' => __('News'),        'page' => 'announcements'],
                ['href' => route('songs.index'),         'label' => __('Songs'),       'page' => 'songs'],
                ['href' => route('library'),             'label' => __('Library'),     'page' => 'library'],
                ['href' => route('tours.index'),         'label' => __('Tours'),       'page' => 'tours'],
                ['href' => route('blog.index'),          'label' => __('Blog'),        'page' => 'blog'],
                ['href' => route('media'),               'label' => __('Media'),       'page' => 'media'],
                ['href' => route('fundraising.index'),   'label' => __('Fundraising'), 'page' => 'fundraising'],
                ['href' => route('contact'),             'label' => __('Contact'),     'page' => 'contact'],
            ] as $link)
                <a href="{{ $link['href'] }}" class="mobile-nav-link" style="
                    display:block;padding:12px 14px;border-radius:10px;
                    color:{{ $currentPage === $link['page'] ? 'var(--text-display)' : 'var(--text-60)' }};
                    text-decoration:none;font-size:.95rem;font-weight:500;
                    transition:background .2s,color .2s;
                    {{ $currentPage === $link['page'] ? 'background:rgba(26,68,247,.12);' : '' }}
                ">{{ $link['label'] }}</a>
            @endforeach

            <div style="margin-top:10px;padding-top:12px;border-top:1px solid var(--border-subtle);">
                @auth
                    <a href="{{ url('/admin') }}" style="display:flex;align-items:center;justify-content:center;padding:13px 20px;border-radius:10px;background:linear-gradient(135deg,var(--blue-primary),#3D5EFF);color:#fff;text-decoration:none;font-weight:600;font-size:.95rem;">
                        {{ __('Dashboard') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" style="display:flex;align-items:center;justify-content:center;padding:13px 20px;border-radius:10px;background:linear-gradient(135deg,var(--blue-primary),#3D5EFF);color:#fff;text-decoration:none;font-weight:600;font-size:.95rem;">
                        {{ __('Login') }}
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>

<div style="height:68px;" id="nav-spacer"></div>

@push('styles')
<style>
    .nav-scrolled {
        background: rgba(5,10,28,0.97) !important;
        box-shadow: 0 4px 30px rgba(0,0,0,.25) !important;
    }
    .nav-link:hover { color: var(--text-display) !important; background: var(--glass-hover) !important; }
    .nav-active { color: var(--text-display) !important; }
    .nav-active::after {
        content:''; position:absolute; bottom:2px; left:50%; transform:translateX(-50%);
        width:16px; height:2px; border-radius:2px;
        background:linear-gradient(90deg, var(--blue-primary), var(--gold));
    }
    .nav-cta {
        display:inline-flex; align-items:center; gap:6px;
        padding:9px 18px; border-radius:8px; font-size:.82rem; font-weight:600;
        background:linear-gradient(135deg,var(--blue-primary),#3D5EFF);
        color:#fff; text-decoration:none; margin-left:8px;
        box-shadow:0 4px 18px rgba(26,68,247,.35);
        transition:transform .2s,box-shadow .2s; white-space:nowrap;
    }
    .nav-cta:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(26,68,247,.45); }
    .mobile-nav-link:hover { background:rgba(255,255,255,.07) !important; color:var(--text-display) !important; }

    @media (max-width: 480px) {
        #main-nav { padding: 0 12px !important; }
        #nav-logo img { height: 30px !important; }
    }
    @media (max-width: 360px) {
        #nav-logo-text { display: none; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const BREAKPOINT = 1024;

    const nav          = document.getElementById('main-nav');
    const navLinks     = document.getElementById('nav-links');
    const mobileActs   = document.getElementById('mobile-actions');
    const mobileMenu   = document.getElementById('mobile-menu');
    const toggleBtn    = document.getElementById('mobile-toggle');
    const burgerPath   = document.getElementById('burger-path');
    const themeBtnD    = document.getElementById('theme-btn-desktop');
    const themeBtnM    = document.getElementById('theme-btn-mobile');
    const themePathD   = document.getElementById('theme-path-d');
    const themePathM   = document.getElementById('theme-path-m');

    const BURGER = 'M4 6h16M4 12h16M4 18h16';
    const CLOSE  = 'M6 18L18 6M6 6l12 12';
    const SUN    = 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z';
    const MOON   = 'M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z';

    let menuOpen = false;

    // ── Responsive: JS controls show/hide to avoid CSS specificity battles ──
    function applyLayout() {
        const isMobile = window.innerWidth < BREAKPOINT;
        navLinks.style.display   = isMobile ? 'none'  : 'flex';
        mobileActs.style.display = isMobile ? 'flex'  : 'none';
        if (!isMobile && menuOpen) closeMenu();
    }

    window.addEventListener('resize', applyLayout);
    applyLayout(); // run immediately on load

    // ── Menu open / close ───────────────────────────────────────────────────
    function openMenu() {
        menuOpen = true;
        mobileMenu.style.maxHeight      = mobileMenu.scrollHeight + 'px';
        mobileMenu.style.opacity        = '1';
        mobileMenu.style.borderTopWidth = '1px';
        burgerPath.setAttribute('d', CLOSE);
    }

    function closeMenu() {
        menuOpen = false;
        mobileMenu.style.maxHeight      = '0';
        mobileMenu.style.opacity        = '0';
        mobileMenu.style.borderTopWidth = '0px';
        burgerPath.setAttribute('d', BURGER);
    }

    toggleBtn.addEventListener('click', () => menuOpen ? closeMenu() : openMenu());

    // Close on link tap
    mobileMenu.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));

    // Close on outside click
    document.addEventListener('click', e => {
        if (menuOpen && !nav.contains(e.target)) closeMenu();
    });

    // ── Scroll ──────────────────────────────────────────────────────────────
    window.addEventListener('scroll', () => {
        nav.classList.toggle('nav-scrolled', window.scrollY > 20);
    }, { passive: true });

    // ── Theme ────────────────────────────────────────────────────────────────
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        const p = theme === 'dark' ? SUN : MOON;
        if (themePathD) themePathD.setAttribute('d', p);
        if (themePathM) themePathM.setAttribute('d', p);
    }

    function toggleTheme() {
        const cur = document.documentElement.getAttribute('data-theme') || 'dark';
        applyTheme(cur === 'dark' ? 'light' : 'dark');
    }

    applyTheme(localStorage.getItem('theme') || document.documentElement.getAttribute('data-theme') || 'dark');
    themeBtnD.addEventListener('click', toggleTheme);
    themeBtnM.addEventListener('click', toggleTheme);
})();
</script>
@endpush