<footer class="relative overflow-hidden border-t mt-auto" style="border-color: var(--ft-border); background: var(--ft-canvas-soft);">
    <div class="absolute inset-0 bg-grid-pattern pointer-events-none opacity-40"></div>
    <div class="absolute -top-40 -right-40 w-[420px] h-[420px] rounded-full opacity-30 pointer-events-none" style="background:radial-gradient(circle,rgba(26,68,247,0.12),transparent 70%);filter:blur(80px);"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-20 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-12 mb-12">
            <div>
                <a href="/" class="flex items-center gap-3 no-underline mb-5 group">
                    <img src="{{ asset('images/logo2.png') }}" alt="{{ config('app.name') }}" class="h-11 w-auto dark:block hidden transition-transform duration-300 group-hover:scale-105">
                    <img src="{{ asset('images/logow.PNG') }}" alt="{{ config('app.name') }}" class="h-11 w-auto dark:hidden block transition-transform duration-300 group-hover:scale-105">
                    <div>
                        <div class="text-lg font-bold ft-ink leading-tight font-['Noto_Sans_Ethiopic']">ፍኖተ ጽድቅ</div>
                        <div class="text-[0.6rem] font-semibold tracking-widest uppercase text-secondary-500">ሰንበት ትምህርት ቤት</div>
                    </div>
                </a>
                <p class="text-sm leading-relaxed mb-6" style="color: var(--ft-ink-muted);">
                    {{ __('Faith, service, and fellowship — building a stronger community through the light of the Gospel since 1984 E.C.') }}
                </p>
                <div class="flex gap-2.5 flex-wrap">
                    @foreach([
                        ['icon' => '<path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>', 'href' => 'https://t.me/Finote1619', 'label' => 'Telegram'],
                        ['icon' => '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>', 'href' => 'https://web.facebook.com/FinoteTsidkeSundaySchool', 'label' => 'Facebook'],
                        ['icon' => '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.405a1.44 1.44 0 112.881.001 1.44 1.44 0 01-2.881-.001z"/>', 'href' => 'https://www.instagram.com/finote16_19?utm_source=qr&igsh=MW90eHZvOGlnZndwOA%3D', 'label' => 'Instagram'],
                        ['icon' => '<path d="M12.525.02c1.31-.02 2.61.01 3.91.03 0 0 .34 2.16.73 3.39.65 2.03 1.82 2.77 3.15 3.36.73.32 1.48.53 2.25.64v3.73c-1.27-.13-2.49-.52-3.54-1.12-.56-.32-1.09-.69-1.57-1.1 0 2.39.01 4.78 0 7.17-.03 1.54-.5 3.07-1.37 4.32-1.4 2.01-3.75 3.26-6.19 3.3-1.55.03-3.1-.46-4.36-1.33-2.1-1.44-3.44-3.91-3.46-6.47-.01-.54.03-1.08.12-1.61.4-2.34 1.87-4.41 3.92-5.57 1.23-.69 2.64-1.06 4.06-1.07.14 0 .28 0 .42.01v3.84c-.15-.02-.3-.04-.45-.04-1.34-.01-2.62.72-3.27 1.87-.3.53-.43 1.14-.41 1.74.06 1.49 1.12 2.81 2.55 3.18.56.14 1.15.16 1.72.06.89-.16 1.67-.66 2.19-1.37.27-.36.44-.79.52-1.23.1-.56.09-1.13.09-1.7V.02h3.28z"/>', 'href' => 'https://www.tiktok.com/@finote1619_?_t=8oiZzAgbsXu&_r=1', 'label' => 'TikTok'],
                        ['icon' => '<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>', 'href' => 'https://youtube.com/@finote1619?si=HDw0RDGj0I1kSAKI', 'label' => 'YouTube'],
                    ] as $social)
                        <a href="{{ $social['href'] }}" target="_blank" rel="noopener" aria-label="{{ $social['label'] }}"
                           class="flex items-center justify-center w-9 h-9 rounded-lg ft-surface text-slate-500 hover:text-primary-500 hover:-translate-y-1 transition-all duration-300">
                            <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24">{!! $social['icon'] !!}</svg>
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <h4 class="text-xs font-semibold tracking-widest uppercase text-secondary-500 mb-5">{{ __('About') }}</h4>
                <div class="flex flex-col gap-3">
                    @foreach([
                        ['href' => '/', 'label' => __('Home')],
                        ['href' => route('about'), 'label' => __('About Us')],
                        ['href' => route('news'), 'label' => __('News')],
                        ['href' => route('blog.index'), 'label' => __('Blog')],
                        ['href' => route('contact'), 'label' => __('Contact')],
                    ] as $link)
                        <a href="{{ $link['href'] }}" class="text-sm transition-colors no-underline hover:text-primary-500" style="color: var(--ft-ink-muted);">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>

            <div>
                <h4 class="text-xs font-semibold tracking-widest uppercase text-secondary-500 mb-5">{{ __('Learn') }} &amp; {{ __('Media') }}</h4>
                <div class="flex flex-col gap-3">
                    @foreach([
                        ['href' => route('courses.index'), 'label' => __('Courses')],
                        ['href' => route('library'), 'label' => __('Library')],
                        ['href' => route('media'), 'label' => __('Gallery')],
                        ['href' => route('media', ['tab' => 'songs']), 'label' => __('Songs')],
                    ] as $link)
                        <a href="{{ $link['href'] }}" class="text-sm transition-colors no-underline hover:text-primary-500" style="color: var(--ft-ink-muted);">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>

            <div>
                <h4 class="text-xs font-semibold tracking-widest uppercase text-secondary-500 mb-5">{{ __('Get Involved') }}</h4>
                <div class="flex flex-col gap-3 mb-6">
                    @foreach([
                        ['href' => route('tours.index'), 'label' => __('Tours')],
                        ['href' => route('fundraising.index'), 'label' => __('Fundraising')],
                        ['href' => route('contact'), 'label' => __('Contact Us')],
                    ] as $link)
                        <a href="{{ $link['href'] }}" class="text-sm transition-colors no-underline hover:text-primary-500" style="color: var(--ft-ink-muted);">{{ $link['label'] }}</a>
                    @endforeach
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-primary-500/10 border border-primary-500/20 flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" stroke="currentColor" class="text-primary-500" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium ft-ink">{{ __('Addis Ababa, Ayertena') }}</div>
                        <div class="text-xs mt-0.5 font-['Noto_Sans_Ethiopic']" style="color: var(--ft-ink-muted);">አዲስ አበባ፣ አየርጤና</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t pt-6 flex flex-col sm:flex-row justify-between items-center gap-3" style="border-color: var(--ft-border);">
            <p class="text-xs m-0" style="color: var(--ft-ink-muted);">
                &copy; {{ date('Y') }} <strong class="ft-ink font-semibold">{{ config('app.name') }}</strong> &mdash; {{ __('All Rights Reserved') }}
            </p>
            <p class="text-xs m-0" style="color: var(--ft-ink-muted);">
                {{ __('Designed by') }} <span class="text-secondary-500">AudioVisual</span>
            </p>
        </div>
    </div>
</footer>
