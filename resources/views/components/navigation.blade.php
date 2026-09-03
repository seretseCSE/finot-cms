@props(['currentPage' => ''])

@php
    $resolvedPage = $currentPage;
    if (Request::is('/') || Request::is('')) $resolvedPage = $resolvedPage ?: 'home';
    if (Request::is('announcements*') || Request::is('events*') || Request::is('news*')) $resolvedPage = 'news';
    if (Request::is('blog*')) $resolvedPage = 'blog';
    if (Request::is('courses*') || Request::is('course*') || Request::is('study*') || Request::is('library*')) $resolvedPage = 'learn';
    if (Request::is('media*') || Request::is('songs*') || Request::is('gallery*')) $resolvedPage = 'media';
    if (Request::is('about*')) $resolvedPage = 'about';
    if (Request::is('contact*')) $resolvedPage = 'contact';

    $navLink = function (string $page, string $href, string $label) use ($resolvedPage) {
        $active = $resolvedPage === $page;
        return [
            'href' => $href,
            'label' => $label,
            'page' => $page,
            'class' => $active
                ? 'text-primary-500 dark:text-primary-400 bg-primary-500/10'
                : 'text-slate-700 dark:text-slate-300 hover:text-primary-600 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5',
        ];
    };
@endphp

<nav
    x-data="{
        scrolled: false,
        mobileOpen: false,
        learnOpen: false,
        mobileLearnOpen: false,
        isDark: document.documentElement.classList.contains('dark'),
        init() {
            const hero = document.getElementById('hero');
            if (!hero) { this.scrolled = true; return; }
            const obs = new IntersectionObserver(
                ([entry]) => { this.scrolled = !entry.isIntersecting; },
                { threshold: 0 }
            );
            obs.observe(hero);
            this.$el._heroObserver = obs;
        },
        destroy() {
            if (this.$el._heroObserver) this.$el._heroObserver.disconnect();
        },
        toggleTheme() {
            this.isDark = !this.isDark;
            document.documentElement.classList.toggle('dark', this.isDark);
            localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
            var meta = document.getElementById('theme-color-meta');
            if (meta) meta.content = this.isDark ? '#0A0A0F' : '#1A44F7';
        }
    }"
    :class="scrolled || mobileOpen ? 'ft-nav-glass shadow-sm' : 'bg-transparent ft-nav-over-hero'"
    class="fixed top-0 left-0 right-0 z-50 h-16 transition-all duration-500"
>
    <div class="ft-nav-bar max-w-7xl mx-auto px-4 sm:px-6 h-full flex items-center justify-between gap-3">
        <a href="/" class="flex items-center gap-3 shrink-0 group">
            <img x-show="!scrolled || isDark" src="{{ asset('images/logo2.png') }}" alt="ፍኖተ ጽድቅ" class="h-9 w-auto max-w-[120px] object-contain transition-transform duration-300 group-hover:scale-105" loading="eager" fetchpriority="high">
            <img x-show="scrolled && !isDark" src="{{ asset('images/logow.PNG') }}" alt="ፍኖተ ጽድቅ" class="h-9 w-auto max-w-[120px] object-contain transition-transform duration-300 group-hover:scale-105" loading="eager" fetchpriority="high">
            <div class="hidden lg:block">
                <div class="text-sm font-bold tracking-widest uppercase leading-tight ft-ink">ፍኖተ ጽድቅ</div>
                <div class="text-[0.6rem] font-medium tracking-[0.2em] uppercase leading-tight text-slate-500 dark:text-slate-400">Finote Tsidik</div>
            </div>
        </a>

        <div class="hidden md:flex items-center gap-0.5">
            @foreach([
                $navLink('home', '/', __('Home')),
                $navLink('about', route('about'), __('About')),
                $navLink('news', route('news'), __('News')),
                $navLink('blog', route('blog.index'), __('Blog')),
                $navLink('media', route('media'), __('Gallery')),
            ] as $link)
                <a href="{{ $link['href'] }}" class="relative px-3 py-2 text-xs font-medium tracking-widest uppercase rounded-lg transition-all duration-300 {{ $link['class'] }}" @if($resolvedPage === $link['page']) aria-current="page" @endif>
                    {{ $link['label'] }}
                </a>
            @endforeach

            <div class="relative" @mouseenter="learnOpen = true" @mouseleave="learnOpen = false">
                <span class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium tracking-widest uppercase rounded-lg cursor-default transition-all duration-300
                    {{ $resolvedPage === 'learn' ? 'text-primary-500 dark:text-primary-400 bg-primary-500/10' : 'text-slate-700 dark:text-slate-300 hover:text-primary-600 dark:hover:text-white hover:bg-black/5 dark:hover:bg-white/5' }}">
                    {{ __('Learn') }}
                    <svg :class="learnOpen ? 'rotate-180' : ''" class="transition-transform duration-200" width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </span>
                <div x-show="learnOpen"
                     @click.away="learnOpen = false"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     class="absolute top-full left-1/2 -translate-x-1/2 mt-2 min-w-[180px] ft-nav-glass rounded-xl shadow-xl py-2 z-50 border border-black/5 dark:border-white/10">
                    <a href="{{ route('courses.index') }}" class="block px-5 py-2.5 text-xs font-medium tracking-widest uppercase text-slate-600 dark:text-slate-400 hover:text-primary-500 hover:bg-primary-500/10 transition-all duration-200">{{ __('Courses') }}</a>
                    <a href="{{ route('library') }}" class="block px-5 py-2.5 text-xs font-medium tracking-widest uppercase text-slate-600 dark:text-slate-400 hover:text-primary-500 hover:bg-primary-500/10 transition-all duration-200">{{ __('Library') }}</a>
                </div>
            </div>

            @foreach([
                $navLink('contact', route('contact'), __('Contact')),
            ] as $link)
                <a href="{{ $link['href'] }}" class="relative px-3 py-2 text-xs font-medium tracking-widest uppercase rounded-lg transition-all duration-300 {{ $link['class'] }}" @if($resolvedPage === $link['page']) aria-current="page" @endif>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <div class="flex items-center gap-1">
                @foreach(['en' => 'EN', 'am' => 'አማ'] as $locale => $label)
                    <form method="POST" action="{{ route('language.switch', $locale) }}" class="inline">
                        @csrf
                        <button type="submit"
                            class="px-2 py-1.5 text-xs font-bold tracking-widest uppercase rounded-lg transition-all duration-200
                                {{ app()->getLocale() === $locale
                                    ? 'bg-primary-500/20 text-primary-600 dark:text-primary-400 border border-primary-500/30'
                                    : 'text-slate-500 dark:text-slate-400 border border-transparent hover:text-slate-900 dark:hover:text-white hover:border-black/10 dark:hover:border-white/20' }}"
                        >{{ $label }}</button>
                    </form>
                @endforeach
            </div>

            <button @click="toggleTheme"
                    class="p-2 rounded-lg transition-all duration-300 hover:bg-black/5 dark:hover:bg-white/5 text-slate-600 dark:text-slate-300"
                    aria-label="{{ __('Toggle theme') }}">
                <svg x-show="!isDark" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
                <svg x-show="isDark" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </button>

            @auth
                @if(auth()->user()?->isStaff() || auth()->user()?->hasRole('student'))
                    <a href="{{ url('/admin') }}" class="hidden md:inline-flex items-center gap-2 px-4 py-2 text-xs font-bold tracking-widest uppercase rounded-xl text-white" style="background:linear-gradient(135deg, #1A44F7, #1638D4);">
                        {{ __('Dashboard') }}
                    </a>
                @endif
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="hidden md:inline px-3 py-2 text-xs font-medium tracking-widest uppercase text-slate-600 dark:text-slate-300 hover:text-primary-600 dark:hover:text-white transition-colors">
                        {{ __('Login') }}
                    </a>
                @endif
                <a href="{{ route('contact') }}" class="hidden lg:inline-flex items-center px-4 py-2 text-xs font-bold tracking-widest uppercase rounded-xl text-white" style="background:linear-gradient(135deg, #1A44F7, #1638D4);">
                    {{ __('Enroll') }}
                </a>
            @endauth

            <button @click="mobileOpen = !mobileOpen"
                    class="md:hidden p-2 rounded-lg transition-colors hover:bg-black/5 dark:hover:bg-white/5 text-slate-700 dark:text-white/80"
                    :aria-expanded="mobileOpen"
                    aria-controls="mobile-nav"
                    aria-label="{{ __('Toggle navigation') }}">
                <svg x-show="!mobileOpen" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileOpen" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div id="mobile-nav"
         x-show="mobileOpen"
         @click.away="mobileOpen = false"
         x-transition
         class="md:hidden absolute top-16 left-0 right-0 ft-nav-glass border-b border-black/5 dark:border-white/5 shadow-xl max-h-[calc(100vh-4rem)] overflow-y-auto"
         style="display:none;">
        <div class="px-4 py-5 flex flex-col gap-1">
            @foreach([
                ['href' => '/', 'label' => __('Home'), 'page' => 'home', 'am' => 'መነሻ'],
                ['href' => route('about'), 'label' => __('About'), 'page' => 'about', 'am' => 'ስለ እኛ'],
                ['href' => route('news'), 'label' => __('News'), 'page' => 'news', 'am' => 'ዜና'],
                ['href' => route('blog.index'), 'label' => __('Blog'), 'page' => 'blog', 'am' => 'ብሎግ'],
                ['href' => route('media'), 'label' => __('Gallery'), 'page' => 'media', 'am' => 'ጋለሪ'],
            ] as $item)
                <a href="{{ $item['href'] }}"
                   class="flex items-center justify-between px-4 py-3 text-sm font-medium tracking-widest uppercase rounded-lg transition-all duration-200
                          {{ $resolvedPage === $item['page'] ? 'text-primary-500 bg-primary-500/10' : 'text-slate-700 dark:text-slate-300 hover:bg-black/5 dark:hover:bg-white/5' }}"
                   @if($resolvedPage === $item['page']) aria-current="page" @endif
                   @click="mobileOpen = false">
                    <span>{{ $item['label'] }}</span>
                    <span class="text-[0.6rem] text-slate-500">{{ $item['am'] }}</span>
                </a>
            @endforeach

            <div>
                <button @click="mobileLearnOpen = !mobileLearnOpen"
                    class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium tracking-widest uppercase rounded-lg text-slate-700 dark:text-slate-300 hover:bg-black/5 dark:hover:bg-white/5"
                    :aria-expanded="mobileLearnOpen"
                    aria-controls="mobile-learn-nav">
                    <span>{{ __('Learn') }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-[0.6rem] text-slate-500">ትምህርት</span>
                        <svg :class="mobileLearnOpen ? 'rotate-180' : ''" class="transition-transform duration-200" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div id="mobile-learn-nav" x-show="mobileLearnOpen" class="ml-4 flex flex-col gap-1 pb-1" style="display:none;">
                    <a href="{{ route('courses.index') }}" class="block px-4 py-2.5 text-xs font-medium tracking-widest uppercase text-slate-500 hover:text-primary-500 rounded-lg" @if(($resolvedPage ?? '') === 'courses') aria-current="page" @endif @click="mobileOpen = false">{{ __('Courses') }}</a>
                    <a href="{{ route('library') }}" class="block px-4 py-2.5 text-xs font-medium tracking-widest uppercase text-slate-500 hover:text-primary-500 rounded-lg" @if(($resolvedPage ?? '') === 'library') aria-current="page" @endif @click="mobileOpen = false">{{ __('Library') }}</a>
                </div>
            </div>

            @foreach([
                ['href' => route('contact'), 'label' => __('Contact'), 'page' => 'contact', 'am' => 'ያግኙን'],
            ] as $item)
                <a href="{{ $item['href'] }}"
                   class="flex items-center justify-between px-4 py-3 text-sm font-medium tracking-widest uppercase rounded-lg transition-all duration-200
                          {{ $resolvedPage === $item['page'] ? 'text-primary-500 bg-primary-500/10' : 'text-slate-700 dark:text-slate-300 hover:bg-black/5 dark:hover:bg-white/5' }}"
                   @if($resolvedPage === $item['page']) aria-current="page" @endif
                   @click="mobileOpen = false">
                    <span>{{ $item['label'] }}</span>
                    <span class="text-[0.6rem] text-slate-500">{{ $item['am'] }}</span>
                </a>
            @endforeach

            <div class="mt-4 pt-4 border-t border-black/5 dark:border-white/5 flex flex-col gap-2">
                @auth
                    @if(auth()->user()?->isStaff() || auth()->user()?->hasRole('student'))
                        <a href="{{ url('/admin') }}" class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold tracking-widest uppercase rounded-xl text-white" style="background:linear-gradient(135deg, #1A44F7, #1638D4);" @click="mobileOpen = false">
                            {{ __('Dashboard') }}
                        </a>
                    @endif
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold tracking-widest uppercase border border-black/10 dark:border-white/20 text-slate-700 dark:text-slate-300 rounded-xl" @click="mobileOpen = false">
                            {{ __('Login') }}
                        </a>
                    @endif
                    <a href="{{ route('contact') }}" class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold tracking-widest uppercase rounded-xl text-white" style="background:linear-gradient(135deg, #1A44F7, #1638D4);" @click="mobileOpen = false">
                        {{ __('Enroll') }}
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>

@if(!Request::is('/'))
<div class="h-16"></div>
@endif
