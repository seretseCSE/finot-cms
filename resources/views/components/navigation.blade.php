@props(['currentPage' => ''])

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

<nav
    x-data="{
        scrolled: false,
        mobileOpen: false,
        resourcesOpen: false,
        mobileResourcesOpen: false,
        init() {
            window.addEventListener('scroll', () => {
                this.scrolled = window.scrollY > 20;
            }, { passive: true });
        }
    }"
    :class="scrolled ? 'bg-base/85 backdrop-blur-xl shadow-lg border-b border-white/5' : 'bg-transparent border-b border-transparent'"
    class="fixed top-0 left-0 right-0 z-50 h-16 transition-all duration-500"
>
    <div class="max-w-7xl mx-auto px-6 h-full flex items-center justify-between">
        {{-- Logo --}}
        <a href="/" class="flex items-center gap-3 shrink-0">
            <img src="{{ asset('images/logo.png') }}" alt="ፍኖተ ጽድቅ" class="w-8 h-8 object-contain">
            <div class="hidden sm:block">
                <div class="text-sm font-bold tracking-widest text-white uppercase leading-tight font-amharic">ፍኖተ ጽድቅ</div>
                <div class="text-[0.6rem] font-medium tracking-[0.2em] text-gray-400 uppercase leading-tight">Finote Tsidik</div>
            </div>
        </a>

        {{-- Desktop Nav --}}
        <div class="hidden md:flex items-center gap-1">
            @php
                $links = [
                    ['href' => '/',                          'label' => __('Home'),      'page' => 'home',      'am' => 'መነሻ'],
                    ['href' => route('about'),               'label' => __('About'),     'page' => 'about',     'am' => 'ስለ እኛ'],
                    ['href' => route('courses.index'),        'label' => __('Courses'),   'page' => 'courses',   'am' => 'ኮርሶች'],
                    ['href' => '#',                          'label' => __('Resources'), 'page' => 'resources', 'am' => 'ሀብቶች', 'dropdown' => true],
                    ['href' => route('media'),               'label' => __('Media'),     'page' => 'media',     'am' => 'ሚድያ'],
                    ['href' => route('contact'),             'label' => __('Contact'),   'page' => 'contact',   'am' => 'ያግኙን'],
                ];
            @endphp

            @foreach($links as $link)
                @if(!empty($link['dropdown']))
                    <div
                        class="relative"
                        @mouseenter="resourcesOpen = true"
                        @mouseleave="resourcesOpen = false"
                    >
                        <span class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium tracking-widest uppercase rounded cursor-default transition-colors
                            {{ $resolvedPage === 'resources' ? 'text-white' : 'text-gray-300 hover:text-white' }}">
                            {{ $link['label'] }}
                            <svg :class="resourcesOpen ? 'rotate-180' : ''" class="transition-transform duration-200" width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                        <div
                            x-show="resourcesOpen"
                            @click.away="resourcesOpen = false"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            class="absolute top-full left-0 mt-1 min-w-[180px] bg-base/98 backdrop-blur-xl border border-white/5 rounded-xl shadow-2xl py-2 z-50"
                        >
                            @foreach([
                                ['href' => route('songs.index'),   'label' => __('Songs')],
                                ['href' => route('library'),       'label' => __('Library')],
                                ['href' => route('media'),         'label' => __('Media')],
                                ['href' => route('blog.index'),    'label' => __('Blog')],
                            ] as $item)
                                <a href="{{ $item['href'] }}" class="block px-5 py-2.5 text-xs font-medium tracking-widest uppercase text-gray-400 hover:text-white hover:bg-white/5 transition-colors">
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ $link['href'] }}"
                       class="px-3 py-2 text-xs font-medium tracking-widest uppercase rounded transition-colors
                              {{ $resolvedPage === $link['page'] ? 'text-white' : 'text-gray-300 hover:text-white' }} link-underline"
                    >{{ $link['label'] }}</a>
                @endif
            @endforeach
        </div>

        {{-- Right side --}}
        <div class="flex items-center gap-3">
            @php $isEn = app()->getLocale() === 'en'; @endphp

            {{-- Desktop language toggle (simplified) --}}
            <div class="hidden md:flex items-center gap-1">
                @foreach(['en' => 'EN', 'am' => 'አማ'] as $locale => $label)
                    <form method="POST" action="{{ route('language.switch', $locale) }}" class="inline">
                        @csrf
                        <button type="submit"
                            class="px-2 py-1.5 text-xs font-bold tracking-widest uppercase rounded transition-colors
                                {{ app()->getLocale() === $locale ? 'bg-accent/20 text-accent border border-accent/30' : 'text-gray-500 border border-transparent hover:text-white' }}"
                        >{{ $label }}</button>
                    </form>
                @endforeach
            </div>

            @auth
                <a href="{{ url('/admin') }}" class="hidden md:inline-flex items-center gap-2 px-4 py-2 text-xs font-bold tracking-widest uppercase bg-accent text-white rounded hover:bg-accent-hover transition-all glow-blue-sm">
                    {{ __('Dashboard') }}
                </a>
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="hidden md:inline text-xs font-medium tracking-widest uppercase text-gray-400 hover:text-white transition-colors">{{ __('Login') }}</a>
                @endif
            @endauth

            {{-- Mobile: lang + hamburger --}}
            <div class="md:hidden flex items-center gap-2">
                @foreach(['en' => 'EN', 'am' => 'አማ'] as $locale => $label)
                    <form method="POST" action="{{ route('language.switch', $locale) }}" class="inline">
                        @csrf
                        <button type="submit"
                            class="px-1.5 py-1 text-[0.6rem] font-bold tracking-widest uppercase rounded transition-colors
                                {{ app()->getLocale() === $locale ? 'bg-accent/20 text-accent border border-accent/30' : 'text-gray-500 border border-transparent' }}"
                        >{{ $label }}</button>
                    </form>
                @endforeach

                <button @click="mobileOpen = !mobileOpen" class="p-2 text-white" aria-label="Toggle navigation">
                    <svg x-show="!mobileOpen" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileOpen" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div
        x-show="mobileOpen"
        @click.away="mobileOpen = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="md:hidden absolute top-16 left-0 right-0 bg-base/98 backdrop-blur-xl border-b border-white/5 shadow-xl max-h-[calc(100vh-4rem)] overflow-y-auto"
    >
        <div class="px-4 py-5 flex flex-col gap-1">
            @php
                $mobileLinks = [
                    ['href' => '/',                          'label' => __('Home'),      'page' => 'home',     'am' => 'መነሻ'],
                    ['href' => route('about'),               'label' => __('About'),     'page' => 'about',    'am' => 'ስለ እኛ'],
                    ['href' => route('courses.index'),        'label' => __('Courses'),   'page' => 'courses',  'am' => 'ኮርሶች'],
                ];
            @endphp

            @foreach($mobileLinks as $link)
                <a href="{{ $link['href'] }}"
                   class="flex items-center justify-between px-4 py-3 text-sm font-medium tracking-widest uppercase rounded transition-colors
                          {{ $resolvedPage === $link['page'] ? 'text-white bg-accent/10' : 'text-gray-300 hover:text-white hover:bg-white/5' }}"
                   @click="mobileOpen = false"
                >
                    <span>{{ $link['label'] }}</span>
                    <span class="text-[0.6rem] text-gray-500 font-amharic">{{ $link['am'] }}</span>
                </a>
            @endforeach

            {{-- Mobile Resources --}}
            <div>
                <button @click="mobileResourcesOpen = !mobileResourcesOpen"
                    class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium tracking-widest uppercase rounded transition-colors text-gray-300 hover:text-white hover:bg-white/5"
                >
                    <span>{{ __('Resources') }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-[0.6rem] text-gray-500 font-amharic">ሀብቶች</span>
                        <svg :class="mobileResourcesOpen ? 'rotate-180' : ''" class="transition-transform duration-200" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>
                <div x-show="mobileResourcesOpen" class="ml-4 flex flex-col gap-1 pb-1">
                    @foreach([
                        ['href' => route('songs.index'),   'label' => __('Songs')],
                        ['href' => route('library'),       'label' => __('Library')],
                        ['href' => route('media'),         'label' => __('Media')],
                        ['href' => route('blog.index'),    'label' => __('Blog')],
                    ] as $item)
                        <a href="{{ $item['href'] }}" class="block px-4 py-2.5 text-xs font-medium tracking-widest uppercase text-gray-400 hover:text-white hover:bg-white/5 rounded transition-colors" @click="mobileOpen = false">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('media') }}"
               class="flex items-center justify-between px-4 py-3 text-sm font-medium tracking-widest uppercase rounded transition-colors
                      {{ $resolvedPage === 'media' ? 'text-white bg-accent/10' : 'text-gray-300 hover:text-white hover:bg-white/5' }}"
               @click="mobileOpen = false"
            >
                <span>{{ __('Media') }}</span>
                <span class="text-[0.6rem] text-gray-500 font-amharic">ሚድያ</span>
            </a>
            <a href="{{ route('contact') }}"
               class="flex items-center justify-between px-4 py-3 text-sm font-medium tracking-widest uppercase rounded transition-colors
                      {{ $resolvedPage === 'contact' ? 'text-white bg-accent/10' : 'text-gray-300 hover:text-white hover:bg-white/5' }}"
               @click="mobileOpen = false"
            >
                <span>{{ __('Contact') }}</span>
                <span class="text-[0.6rem] text-gray-500 font-amharic">ያግኙን</span>
            </a>

            {{-- Mobile CTA --}}
            <div class="mt-4 pt-4 border-t border-white/5">
                @auth
                    <a href="{{ url('/admin') }}" class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold tracking-widest uppercase bg-accent text-white rounded-lg hover:bg-accent-hover transition-all glow-blue-sm" @click="mobileOpen = false">
                        {{ __('Dashboard') }}
                    </a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-bold tracking-widest uppercase bg-accent text-white rounded-lg hover:bg-accent-hover transition-all glow-blue-sm" @click="mobileOpen = false">
                            {{ __('Login') }}
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</nav>

{{-- Nav spacer --}}
<div class="h-16"></div>

@push('styles')
<style>
    .link-underline {
        position: relative;
        text-decoration: none;
    }
    .link-underline::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 0;
        height: 1.5px;
        background: #1A44F7;
        transform: translateX(-50%);
        transition: width 0.25s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .link-underline:hover::after {
        width: 16px;
    }
</style>
@endpush
