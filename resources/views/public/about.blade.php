@extends('layouts.public')

@section('title', $page?->title ?? __('About'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — About Page Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    {{-- Parallax background image --}}
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/features-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    
    {{-- Gradient overlay --}}
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90) 0%,rgba(26,68,247,.2) 50%,var(--overlay-95) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Our Identity') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;color:var(--text-hero);">
            {{ $page?->title ?? __('About') }}
            <span style="color:var(--gold);">{{ __('Us') }}</span>
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:clamp(0.95rem, 2vw, 1.1rem);line-height:1.7;">
            {{ __('Discover the journey, mission, and community of Finot-Tsidik Sunday School — a legacy of faith since 1984 E.C.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  CONTENT — Dynamic Page Content
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:100px 24px;background:var(--dark-900);position:relative;">
    <div style="max-width:1000px;margin:0 auto;position:relative;z-index:1;">
        
        @if($page && $page->content)
            @php
                // Split content by h2 headings to create separate cards
                $contentSections = preg_split('/<h2[^>]*>(.*?)<\/h2>/', $page->content, -1, PREG_SPLIT_DELIM_CAPTURE);
                $icons = [
                    'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', // Book (Mission)
                    'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', // Lightbulb (Vision)
                    'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z', // Sparkles (Values)
                    'M4.318 9.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', // Heart/Diamond
                    'M13 10V3L4 14h7v7l9-11h-7z', // Lightning
                    'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z' // Device
                ];
                $bgGradients = [
                    'linear-gradient(135deg, rgba(59,130,246,0.2), rgba(37,99,235,0.1))',
                    'linear-gradient(135deg, rgba(168,85,247,0.2), rgba(147,51,234,0.1))', 
                    'linear-gradient(135deg, rgba(243,186,21,0.2), rgba(217,119,6,0.1))',
                    'linear-gradient(135deg, rgba(168,85,247,0.2), rgba(147,51,234,0.1))',
                    'linear-gradient(135deg, rgba(239,68,68,0.2), rgba(220,38,38,0.1))',
                    'linear-gradient(135deg, rgba(59,130,246,0.2), rgba(37,99,235,0.1))'
                ];
                $iconColors = [
                    '#60A5FA', // Blue
                    '#C084FC', // Purple
                    'var(--gold)', // Gold
                    '#C084FC', // Purple
                    '#F87171', // Red
                    '#60A5FA'  // Blue
                ];
                $glowColors = [
                    'rgba(59, 130, 246, 0.15)', // Blue
                    'rgba(168, 85, 247, 0.15)', // Purple
                    'rgba(243, 186, 21, 0.15)', // Gold
                    'rgba(168, 85, 247, 0.15)', // Purple
                    'rgba(239, 68, 68, 0.15)',  // Red
                    'rgba(59, 130, 246, 0.15)'  // Blue
                ];
            @endphp
        @endif

<style>
    .mvv-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: clamp(24px, 4vw, 40px);
        margin-bottom: 80px;
    }
    .premium-card {
        position: relative;
        padding: clamp(32px, 5vw, 48px);
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        flex-direction: column;
        gap: 28px;
        overflow: hidden;
        transition: all 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
        z-index: 1;
        backdrop-filter: blur(10px);
        height: 100%;
    }
    .premium-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; height: 100%;
        background: radial-gradient(circle at top right, var(--glow-color, rgba(255,255,255,0.1)), transparent 60%);
        opacity: 0;
        transition: opacity 0.5s ease;
        z-index: -1;
        pointer-events: none;
    }
    .premium-card:hover {
        transform: translateY(-8px);
        background: rgba(255, 255, 255, 0.04);
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 24px 48px -12px rgba(0,0,0,0.5);
    }
    .premium-card:hover::before {
        opacity: 1;
    }
    .premium-icon-wrap {
        width: 68px;
        height: 68px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        transition: transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.1);
    }
    .premium-card:hover .premium-icon-wrap {
        transform: scale(1.1) translateY(-4px);
    }
    .premium-icon-wrap::after {
        content: '';
        position: absolute;
        inset: -10px;
        border-radius: inherit;
        background: inherit;
        filter: blur(20px);
        opacity: 0;
        transition: opacity 0.5s ease;
        z-index: -1;
    }
    .premium-card:hover .premium-icon-wrap::after {
        opacity: 0.5;
    }
    .premium-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-display);
        margin-bottom: 6px;
        letter-spacing: 0.5px;
    }
    .premium-subtitle {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        font-weight: 600;
    }
    .premium-content {
        color: var(--text-60);
        line-height: 1.8;
        font-size: 1.05rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .premium-value-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .premium-value-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 16px;
        border-radius: 12px;
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.03);
        transition: all 0.3s ease;
        font-size: 0.95rem;
        color: var(--text-70);
    }
    .premium-value-item:hover {
        background: rgba(255,255,255,0.05);
        border-color: rgba(255,255,255,0.1);
        transform: translateX(6px);
        color: var(--text-display);
    }
</style>

        @if($page && $page->content)
            <div class="mvv-grid">
                @for($i = 0; $i < count($contentSections); $i += 2)
                    @if(isset($contentSections[$i + 1]) && !empty(trim($contentSections[$i + 1])))
                        @php
                            $title = strip_tags($contentSections[$i]);
                            $content = $contentSections[$i + 1];
                            $iconIndex = floor($i / 2) % count($icons);
                        @endphp
                        
                        <div class="premium-card sr" style="--glow-color: {{ $glowColors[$iconIndex] }};">
                            <div class="premium-icon-wrap" style="background: {{ $bgGradients[$iconIndex] }}; color: {{ $iconColors[$iconIndex] }};">
                                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icons[$iconIndex] }}"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="premium-title am">{{ $title }}</h3>
                                <div class="premium-subtitle" style="color: {{ $iconColors[$iconIndex] }};">{{ __('About Section') }}</div>
                            </div>
                            <div class="premium-content">
                                <div class="prose prose-invert max-w-none">
                                    {!! $content !!}
                                </div>
                            </div>
                        </div>
                    @endif
                @endfor
            </div>
        @else
            <div class="mvv-grid">
                {{-- Mission Card --}}
                <div class="premium-card sr" style="--glow-color: rgba(59, 130, 246, 0.15);">
                    <div class="premium-icon-wrap" style="background: linear-gradient(135deg, rgba(59,130,246,0.2), rgba(37,99,235,0.1)); color: #60A5FA;">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <div>
                        <h3 class="premium-title am">{{ __('Our Mission') }}</h3>
                        <div class="premium-subtitle" style="color: #60A5FA;">{{ __('Nurturing Faith, Building Community') }}</div>
                    </div>
                    <div class="premium-content">
                        <p>{{ __('Our mission is to provide spiritual guidance, education, and support to our community members, fostering growth and development in all aspects of life.') }}</p>
                        <p>{{ __('We believe in the power of the Gospel to transform lives and the importance of preserving the rich traditions of the Ethiopian Orthodox Tewahedo Church.') }}</p>
                    </div>
                </div>

                {{-- Vision Card --}}
                <div class="premium-card sr" style="--glow-color: rgba(168, 85, 247, 0.15);">
                    <div class="premium-icon-wrap" style="background: linear-gradient(135deg, rgba(168,85,247,0.2), rgba(147,51,234,0.1)); color: #C084FC;">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    </div>
                    <div>
                        <h3 class="premium-title am">{{ __('Our Vision') }}</h3>
                        <div class="premium-subtitle" style="color: #C084FC;">{{ __('A Lighthouse of Spiritual Wisdom') }}</div>
                    </div>
                    <div class="premium-content">
                        <p>{{ __('We envision a community where every individual has the opportunity to grow spiritually, intellectually, and socially, contributing to the betterment of society.') }}</p>
                        <p>{{ __('Through steadfast faith and community, we strive to be a beacon of hope and a center for Orthodox teachings for generations to come.') }}</p>
                    </div>
                </div>

                {{-- Values Card --}}
                <div class="premium-card sr" style="--glow-color: rgba(243, 186, 21, 0.15);">
                    <div class="premium-icon-wrap" style="background: linear-gradient(135deg, rgba(243,186,21,0.2), rgba(217,119,6,0.1)); color: var(--gold);">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    </div>
                    <div>
                        <h3 class="premium-title am">{{ __('Our Values') }}</h3>
                        <div class="premium-subtitle" style="color: var(--gold);">{{ __('Core Principles & Beliefs') }}</div>
                    </div>
                    <div class="premium-content">
                        <ul class="premium-value-list">
                            @foreach([
                                __('Faith and spiritual growth'), 
                                __('Education and continuous learning'), 
                                __('Community service and outreach'), 
                                __('Integrity and transparency'), 
                                __('Respect and inclusivity')
                            ] as $val)
                            <li class="premium-value-item">
                                <div style="color: var(--gold); display: flex; align-items: center; justify-content: center;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span>{{ $val }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif 

        @if($page && $page->content_am)
            <div class="card sr" style="padding:clamp(24px, 5vw, 60px) clamp(20px, 4vw, 48px);margin-top:40px;line-height:1.8;border-color:var(--gold-border);">
                @if($page->title_am)
                    <h2 class="am display" style="font-size:clamp(1.5rem, 4vw, 2rem);color:var(--gold);margin-bottom:30px;text-align:center;">{{ $page->title_am }}</h2>
                @endif
                <div class="am prose prose-invert max-w-none" style="color:var(--text-60);font-size:clamp(0.9rem, 2vw, 1.05rem);">
                    {!! $page->content_am !!}
                </div>
            </div>
        @endif

    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  STATS — Reusing from Home but focused
     ═══════════════════════════════════════════════════════ --}}
<section id="stats-section" style="padding:80px 24px;background:var(--dark-950);position:relative;">
    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:1;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;">
            @php
                $statIcons = ['community', 'education', 'faith', 'events'];
            @endphp
            @foreach([
                ['count' => $stats['kids'],   'label' => __('Children'),     'icon' => 'community', 'color' => '#4ade80'],
                ['count' => $stats['youth'],  'label' => __('Youth'),        'icon' => 'education', 'color' => '#60a5fa'],
                ['count' => $stats['adults'], 'label' => __('Young Adults'), 'icon' => 'leadership', 'color' => 'var(--gold)'],
                ['count' => $stats['total'],  'label' => __('Total Members'), 'icon' => 'faith', 'color' => '#fff'],
            ] as $stat)
            <div class="card sr" style="padding:32px 24px;text-align:center;">
                <x-tour-icon :name="$stat['icon']" size="28" class="" style="color:{{ $stat['color'] }}" aria-hidden="true" />
                <div data-count="{{ $stat['count'] }}" style="font-family:'Playfair Display',serif;font-size:2.5rem;font-weight:700;color:{{ $stat['color'] }};line-height:1;">0</div>
                <div style="font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;color:var(--parchment-40);margin-top:8px;">{{ $stat['label'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     4.  CTA
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:100px 24px;background:linear-gradient(180deg,var(--dark-950),var(--dark-900));text-align:center;">
    <div class="sr" style="max-width:700px;margin:0 auto;">
        <h2 class="display" style="font-size:clamp(2rem, 5vw, 2.6rem);margin-bottom:20px;">{{ __('Be Part of Our Journey') }}</h2>
        <p style="color:var(--text-60);max-width:600px;margin:0 auto 36px;">
            {{ __('Whether you want to learn, volunteer, or support us, there is a place for you here at Finot-Tsidik.') }}
        </p>
        <div style="display:flex;justify-content:center;gap:16px;flex-wrap:wrap;">
            <a href="{{ route('contact') }}" class="btn btn-primary btn-mobile-full">{{ __('Contact Us') }}</a> 
        </div>
    </div>
</section>

@endsection
