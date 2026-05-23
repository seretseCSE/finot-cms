vs@props(['currentPage' => ''])

@php
    $resolvedPage = $currentPage;
    if (Request::is('announcements/*')) $resolvedPage = 'news';
    if (Request::is('events/*')) $resolvedPage = 'news';
    if (Request::is('songs*')) $resolvedPage = 'resources';
    if (Request::is('library*')) $resolvedPage = 'resources';
    if (Request::is('media*')) $resolvedPage = 'resources';
    if (Request::is('blog*')) $resolvedPage = 'resources';
    if (Request::is('shop*')) $resolvedPage = 'tours';
    if (Request::is('tours*')) $resolvedPage = 'tours';
    if (Request::is('courses*') || Request::is('course*')) $resolvedPage = 'courses';
    if (Request::is('study*')) $resolvedPage = 'courses';
@endphp

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
                ['href' => '/',                          'label' => __('Home'),          'page' => 'home'],
                ['href' => route('about'),               'label' => __('About'),         'page' => 'about'],
                ['href' => route('news'),                'label' => __('News & Events'), 'page' => 'news'],
                ['href' => route('courses.index'),        'label' => __('Courses'),       'page' => 'courses'],
            ] as $link)
                <a href="{{ $link['href'] }}"
                   class="nav-link {{ $resolvedPage === $link['page'] ? 'nav-active' : '' }}"
                   style="
                       padding:8px 12px;border-radius:6px;font-size:.82rem;font-weight:500;
                       color:{{ $resolvedPage === $link['page'] ? 'var(--text-display)' : 'var(--text-40)' }};
                       text-decoration:none;transition:color .2s,background .2s;position:relative;
                       {{ $resolvedPage === $link['page'] ? 'background:rgba(26,68,247,.1);' : '' }}
                   "
                >{{ $link['label'] }}</a>
            @endforeach

            {{-- Resources Dropdown --}}
            <div class="nav-dropdown" style="position:relative;" id="resources-dropdown">
                <a href="#" class="nav-link {{ $resolvedPage === 'resources' ? 'nav-active' : '' }}" style="
                    padding:8px 12px;border-radius:6px;font-size:.82rem;font-weight:500;
                    color:{{ $resolvedPage === 'resources' ? 'var(--text-display)' : 'var(--text-40)' }};
                    text-decoration:none;transition:color .2s,background .2s;position:relative;display:flex;align-items:center;gap:4px;
                    {{ $resolvedPage === 'resources' ? 'background:rgba(26,68,247,.1);' : '' }}
                " onclick="event.preventDefault()">
                    {{ __('Resources') }}
                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="dropdown-arrow" style="transition:transform .2s;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </a>
                <div class="dropdown-menu" id="dropdown-menu" style="
                    position:absolute;top:100%;left:0;min-width:180px;padding:8px 0;
                    background:var(--overlay-98);border:1px solid var(--border-subtle);
                    border-radius:10px;box-shadow:0 12px 40px rgba(0,0,0,.35);
                    opacity:0;visibility:hidden;transform:translateY(8px);
                    transition:opacity .2s,transform .2s,visibility .2s;z-index:100;
                ">
                    @foreach([
                        ['href' => route('songs.index'),   'label' => __('Songs')],
                        ['href' => route('library'),       'label' => __('Library')],
                        ['href' => route('media'),         'label' => __('Media')],
                        ['href' => route('blog.index'),    'label' => __('Blog')],
                    ] as $item)
                        <a href="{{ $item['href'] }}" class="dropdown-item" style="
                            display:block;padding:10px 20px;font-size:.82rem;color:var(--text-40);
                            text-decoration:none;transition:background .15s,color .15s;
                        ">{{ $item['label'] }}</a>
                    @endforeach
                </div>
            </div>

            @foreach([
                ['href' => route('tours.index'),         'label' => __('Tours & Shop'),  'page' => 'tours'],
                ['href' => route('fundraising.index'),   'label' => __('Fundraising'),   'page' => 'fundraising'],
                ['href' => route('contact'),             'label' => __('Contact'),       'page' => 'contact'],
            ] as $link)
                <a href="{{ $link['href'] }}"
                   class="nav-link {{ $resolvedPage === $link['page'] ? 'nav-active' : '' }}"
                   style="
                       padding:8px 12px;border-radius:6px;font-size:.82rem;font-weight:500;
                       color:{{ $resolvedPage === $link['page'] ? 'var(--text-display)' : 'var(--text-40)' }};
                       text-decoration:none;transition:color .2s,background .2s;position:relative;
                       {{ $resolvedPage === $link['page'] ? 'background:rgba(26,68,247,.1);' : '' }}
                   "
                >{{ $link['label'] }}</a>
            @endforeach

            {{-- Desktop Language Toggle --}}
            <x-language-toggle />

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
                <a href="{{ url('/admin') }}" class="nav-cta" id="nav-cta-btn">
                    <span class="nav-cta-inner">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        {{ __('Dashboard') }}
                        <svg class="nav-cta-arrow" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </span>
                </a>
            @else
                <a href="{{ route('login') }}" class="nav-cta" id="nav-cta-btn">
                    <span class="nav-cta-inner">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-3l3-3m0 0l-3-3m3 3H9"/></svg>
                        {{ __('Login') }}
                        <svg class="nav-cta-arrow" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </span>
                </a>
            @endauth
        </div>

        {{-- Mobile Actions: Language + Theme + Burger (hidden/shown via JS) --}}
        <div id="mobile-actions" style="display:none;align-items:center;gap:8px;">

            {{-- Mobile Language Toggle --}}
            <x-language-toggle />

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
                ['href' => '/',                          'label' => __('Home'),          'page' => 'home'],
                ['href' => route('about'),               'label' => __('About'),         'page' => 'about'],
                ['href' => route('news'),                'label' => __('News & Events'), 'page' => 'news'],
                ['href' => route('courses.index'),        'label' => __('Courses'),       'page' => 'courses'],
                ['href' => '#',                          'label' => __('Resources'),     'page' => 'resources', 'sub' => [
                    ['href' => route('songs.index'),   'label' => __('Songs')],
                    ['href' => route('library'),       'label' => __('Library')],
                    ['href' => route('media'),         'label' => __('Media')],
                    ['href' => route('blog.index'),    'label' => __('Blog')],
                ]],
                ['href' => route('tours.index'),         'label' => __('Tours & Shop'),  'page' => 'tours'],
                ['href' => route('fundraising.index'),   'label' => __('Fundraising'),   'page' => 'fundraising'],
                ['href' => route('contact'),             'label' => __('Contact'),       'page' => 'contact'],
            ] as $link)
                @if(isset($link['sub']))
                    <div>
                        <button class="mobile-nav-link mobile-sub-toggle" style="
                            display:block;width:100%;text-align:left;padding:12px 14px;border-radius:10px;
                            border:none;background:{{ $resolvedPage === $link['page'] ? 'rgba(26,68,247,.12)' : 'transparent' }};
                            color:{{ $resolvedPage === $link['page'] ? 'var(--text-display)' : 'var(--text-60)' }};
                            font-size:.95rem;font-weight:500;cursor:pointer;font-family:inherit;
                            transition:background .2s,color .2s;display:flex;justify-content:space-between;align-items:center;
                        ">
                            {{ $link['label'] }}
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="transition:transform .2s;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="mobile-sub-links" style="max-height:0;overflow:hidden;transition:max-height .3s ease;">
                            @foreach($link['sub'] as $sub)
                                <a href="{{ $sub['href'] }}" class="mobile-nav-link" style="
                                    display:block;padding:10px 14px 10px 32px;border-radius:10px;
                                    color:var(--text-40);text-decoration:none;font-size:.88rem;font-weight:400;
                                    transition:background .2s,color .2s;
                                ">{{ $sub['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ $link['href'] }}" class="mobile-nav-link" style="
                        display:block;padding:12px 14px;border-radius:10px;
                        color:{{ $resolvedPage === $link['page'] ? 'var(--text-display)' : 'var(--text-60)' }};
                        text-decoration:none;font-size:.95rem;font-weight:500;
                        transition:background .2s,color .2s;
                        {{ $resolvedPage === $link['page'] ? 'background:rgba(26,68,247,.12);' : '' }}
                    ">{{ $link['label'] }}</a>
                @endif
            @endforeach

            <div style="margin-top:10px;padding-top:12px;border-top:1px solid var(--border-subtle);">
                @auth
                    <a href="{{ url('/admin') }}" class="mobile-cta-btn">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        {{ __('Dashboard') }}
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-left:auto;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="mobile-cta-btn">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-3l3-3m0 0l-3-3m3 3H9"/></svg>
                        {{ __('Login') }}
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-left:auto;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>

<div style="height:68px;" id="nav-spacer"></div>

@push('styles')
<style>
    /* ── Scrolled state ── */
    .nav-scrolled {
        background: var(--overlay-98) !important;
        box-shadow: 0 1px 0 var(--border-subtle), 0 8px 32px rgba(0,0,0,.2) !important;
    }

    /* ── Nav links — animated underline on hover ── */
    .nav-link {
        position: relative;
    }
    .nav-link::after {
        content: ''; position: absolute; bottom: 2px; left: 50%; width: 0; height: 2px;
        border-radius: 2px; background: linear-gradient(90deg, var(--blue-primary), var(--gold));
        transform: translateX(-50%); transition: width .25s cubic-bezier(.22,1,.36,1);
    }
    .nav-link:hover { color: var(--text-display) !important; background: transparent !important; }
    .nav-link:hover::after { width: 20px; }
    .nav-active { color: var(--text-display) !important; }
    .nav-active::after { width: 20px; }

    /* ── CTA Button — gradient border + glow ── */
    @keyframes cta-shimmer {
        0%   { background-position: 0% 50%; }
        100% { background-position: 200% 50%; }
    }
    .nav-cta {
        position: relative; display: inline-flex; align-items: center;
        padding: 1.5px; border-radius: 50px; margin-left: 10px;
        background: linear-gradient(135deg, var(--blue-primary), var(--gold), var(--blue-500), var(--gold-light));
        background-size: 200% 200%;
        text-decoration: none; white-space: nowrap;
        transition: transform .25s cubic-bezier(.22,1,.36,1), box-shadow .25s;
        box-shadow: 0 0 16px rgba(26,68,247,.2), 0 0 6px rgba(243,186,21,.1);
        animation: cta-shimmer 4s linear infinite;
    }
    .nav-cta-inner {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 20px; border-radius: 50px;
        background: var(--bg-900);
        color: var(--text-display); font-size: .8rem; font-weight: 600;
        transition: background .25s, color .25s;
    }
    .nav-cta-arrow {
        opacity: 0; transform: translateX(-4px);
        transition: opacity .2s, transform .2s;
        flex-shrink: 0;
    }
    .nav-cta:hover {
        transform: translateY(-2px) scale(1.03);
        box-shadow: 0 0 28px rgba(26,68,247,.35), 0 0 12px rgba(243,186,21,.18);
    }
    .nav-cta:hover .nav-cta-inner {
        background: linear-gradient(135deg, var(--blue-primary), #2952FF);
        color: #fff;
    }
    .nav-cta:hover .nav-cta-arrow {
        opacity: 1; transform: translateX(0);
    }

    /* ── Mobile CTA ── */
    .mobile-cta-btn {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 22px; border-radius: 14px;
        background: linear-gradient(135deg, var(--blue-primary) 0%, #2952FF 100%);
        color: #fff; text-decoration: none; font-weight: 600; font-size: .95rem;
        box-shadow: 0 4px 24px rgba(26,68,247,.3);
        transition: transform .2s, box-shadow .2s;
        position: relative; overflow: hidden;
    }
    .mobile-cta-btn::before {
        content: ''; position: absolute; inset: 0;
        background: linear-gradient(135deg, transparent 40%, rgba(243,186,21,.15) 100%);
        opacity: 0; transition: opacity .3s;
    }
    .mobile-cta-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 32px rgba(26,68,247,.4); }
    .mobile-cta-btn:hover::before { opacity: 1; }

    /* ── Dropdown ── */
    .dropdown-menu { opacity:0; visibility:hidden; transform:translateY(8px); pointer-events:none; }
    .nav-dropdown:hover .dropdown-menu { opacity:1; visibility:visible; transform:translateY(0); pointer-events:auto; }
    .nav-dropdown:hover > a svg.dropdown-arrow { transform:rotate(180deg); }
    .dropdown-item:hover { background:var(--glass-hover); color:var(--text-display) !important; }

    /* ── Mobile ── */
    .mobile-nav-link:hover { background:rgba(255,255,255,.07) !important; color:var(--text-display) !important; }
    .mobile-sub-toggle.open svg { transform:rotate(180deg); }

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

    // ── Mobile sub-toggle (Resources dropdown) ───────────────────────────
    document.querySelectorAll('.mobile-sub-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const subLinks = this.nextElementSibling;
            const isOpen = this.classList.contains('open');
            if (isOpen) {
                this.classList.remove('open');
                subLinks.style.maxHeight = '0';
            } else {
                this.classList.add('open');
                subLinks.style.maxHeight = subLinks.scrollHeight + 'px';
            }
        });
    });

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

    // ── Resources Dropdown (hover) ──────────────────────────────────────────
    var dd = document.getElementById('resources-dropdown');
    var ddMenu = document.getElementById('dropdown-menu');
    if (dd && ddMenu) {
        var ddArrow = dd.querySelector('.dropdown-arrow');
        dd.addEventListener('mouseenter', function() {
            ddMenu.style.opacity = '1';
            ddMenu.style.visibility = 'visible';
            ddMenu.style.transform = 'translateY(0)';
            ddMenu.style.pointerEvents = 'auto';
            if (ddArrow) ddArrow.style.transform = 'rotate(180deg)';
        });
        dd.addEventListener('mouseleave', function() {
            ddMenu.style.opacity = '0';
            ddMenu.style.visibility = 'hidden';
            ddMenu.style.transform = 'translateY(8px)';
            ddMenu.style.pointerEvents = 'none';
            if (ddArrow) ddArrow.style.transform = 'rotate(0deg)';
        });
    }
})();
</script>
@endpush
