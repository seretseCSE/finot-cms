@props([
    'type' => 'events',
    'title' => '',
    'message' => '',
    'ctaText' => '',
    'ctaUrl' => '#',
    'icon' => null,
])

@php
    $isEvents = $type === 'events';

    $defaults = $isEvents
        ? [
            'title'   => __('No Upcoming Events'),
            'message' => __('Services and gatherings are being planned! Stay tuned for updates — something wonderful is on the way.'),
            'ctaText' => __('Notify Me'),
            'ctaUrl'  => route('contact'),
        ]
        : [
            'title'   => __('No Resources Found'),
            'message' => __('Our library is growing! New resources are being thoughtfully curated for you. Check back soon.'),
            'ctaText' => __('Suggest a Topic'),
            'ctaUrl'  => route('contact'),
        ];

    $title   = $title ?: $defaults['title'];
    $message = $message ?: $defaults['message'];
    $ctaText = $ctaText ?: $defaults['ctaText'];
    $ctaUrl  = $ctaUrl ?: $defaults['ctaUrl'];
@endphp

<div class="empty-state-card">
    {{-- Illustration --}}
    <div class="empty-state-illustration">
        @if ($icon)
            {{ $icon }}
        @elseif ($isEvents)
            {{-- Calendar with question mark --}}
            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="8" y="14" width="64" height="56" rx="10" stroke="currentColor" stroke-width="3" fill="none" class="empty-state-icon-bg"/>
                <rect x="8" y="28" width="64" height="10" rx="2" fill="currentColor" opacity="0.08"/>
                <line x1="26" y1="8" x2="26" y2="20" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <line x1="54" y1="8" x2="54" y2="20" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <circle cx="40" cy="46" r="12" fill="currentColor" opacity="0.06"/>
                <text x="40" y="52" text-anchor="middle" font-size="20" font-weight="700" fill="currentColor" opacity="0.5">?</text>
                <circle cx="23" cy="42" r="2.5" fill="currentColor" opacity="0.3"/>
                <circle cx="23" cy="52" r="2.5" fill="currentColor" opacity="0.3"/>
                <circle cx="57" cy="42" r="2.5" fill="currentColor" opacity="0.3"/>
                <circle cx="57" cy="52" r="2.5" fill="currentColor" opacity="0.3"/>
                <circle cx="40" cy="60" r="2.5" fill="currentColor" opacity="0.3"/>
                <line x1="12" y1="62" x2="68" y2="62" stroke="currentColor" stroke-width="1" opacity="0.08"/>
            </svg>
        @else
            {{-- Open book with question mark --}}
            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M8 16C8 16 24 8 40 16C56 8 72 16 72 16V68C72 68 56 60 40 68C24 60 8 68 8 68V16Z" stroke="currentColor" stroke-width="3" fill="none" class="empty-state-icon-bg"/>
                <line x1="40" y1="16" x2="40" y2="64" stroke="currentColor" stroke-width="3" opacity="0.2"/>
                <circle cx="40" cy="36" r="10" fill="currentColor" opacity="0.06"/>
                <text x="40" y="42" text-anchor="middle" font-size="18" font-weight="700" fill="currentColor" opacity="0.5">?</text>
                <line x1="20" y1="30" x2="28" y2="30" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.2"/>
                <line x1="20" y1="38" x2="26" y2="38" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.2"/>
                <line x1="52" y1="30" x2="60" y2="30" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.2"/>
                <line x1="54" y1="38" x2="60" y2="38" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.2"/>
                <rect x="62" y="48" width="4" height="6" rx="1" fill="currentColor" opacity="0.12"/>
                <rect x="14" y="48" width="4" height="6" rx="1" fill="currentColor" opacity="0.12"/>
            </svg>
        @endif
    </div>

    {{-- Title --}}
    <h3 class="empty-state-title">{{ $title }}</h3>

    {{-- Message --}}
    <p class="empty-state-message">{{ $message }}</p>

    {{-- CTA --}}
    <a href="{{ $ctaUrl }}" class="empty-state-cta">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            @if ($isEvents)
                <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            @else
                <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/>
            @endif
        </svg>
        <span>{{ $ctaText }}</span>
    </a>
</div>

@once
    <style>
        .empty-state-card {
            background: var(--bg-900, #0A1230);
            border: 1px solid var(--gold-border, rgba(243,186,21,0.18));
            border-radius: var(--r-lg, 20px);
            padding: 48px 40px;
            text-align: center;
            max-width: 480px;
            margin: 0 auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2), 0 2px 8px rgba(243, 186, 21, 0.06);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .empty-state-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--gold, #F3BA15), transparent);
            opacity: 0.6;
        }

        .empty-state-card:hover {
            border-color: var(--gold-border, rgba(243,186,21,0.3));
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25), 0 4px 16px rgba(243, 186, 21, 0.1);
        }

        .empty-state-illustration {
            margin-bottom: 24px;
            display: flex;
            justify-content: center;
            color: var(--gold, #F3BA15);
            opacity: 0.7;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .empty-state-card:hover .empty-state-illustration {
            opacity: 0.9;
            transform: scale(1.04);
        }

        .empty-state-icon-bg {
            opacity: 0.15;
            transition: opacity 0.3s ease;
        }

        .empty-state-card:hover .empty-state-icon-bg {
            opacity: 0.25;
        }

        .empty-state-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-display, #FFFFFF);
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .empty-state-message {
            font-size: 0.9rem;
            color: var(--text-60, rgba(232,228,220,0.6));
            line-height: 1.7;
            margin-bottom: 28px;
            max-width: 380px;
            margin-left: auto;
            margin-right: auto;
        }

        .empty-state-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            cursor: pointer;
            background: linear-gradient(135deg, var(--gold, #F3BA15), var(--gold-light, #FFCF42));
            color: var(--bg-950, #050A1C);
            box-shadow: 0 4px 20px rgba(243, 186, 21, 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            animation: empty-state-pulse 2.5s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }

        .empty-state-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(243, 186, 21, 0.4);
            animation: none;
        }

        .empty-state-cta:active {
            transform: translateY(0);
        }

        @keyframes empty-state-pulse {
            0%, 100% {
                box-shadow: 0 4px 20px rgba(243, 186, 21, 0.25);
            }
            50% {
                box-shadow: 0 4px 28px rgba(243, 186, 21, 0.5), 0 0 0 4px rgba(243, 186, 21, 0.1);
            }
        }

        @media (max-width: 768px) {
            .empty-state-card {
                padding: 36px 24px;
            }
            .empty-state-illustration svg {
                width: 64px;
                height: 64px;
            }
            .empty-state-title {
                font-size: 1.2rem;
            }
            .empty-state-message {
                font-size: 0.85rem;
            }
            .empty-state-cta {
                padding: 10px 22px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .empty-state-card {
                padding: 28px 20px;
                border-radius: 16px;
            }
            .empty-state-illustration svg {
                width: 56px;
                height: 56px;
            }
            .empty-state-title {
                font-size: 1.1rem;
            }
            .empty-state-message {
                font-size: 0.8rem;
                margin-bottom: 22px;
            }
        }
    </style>
@endonce
