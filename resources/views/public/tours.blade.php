@extends('layouts.public')

@section('title', __('Spiritual Tours'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Tours Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/stats-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-95) 0%,rgba(26,68,247,.1) 50%,var(--overlay-98) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Journeys of Faith') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;color:var(--text-hero);">
            {{ __('Upcoming') }}
            <span style="color:var(--gold);">{{ __('Tours') }}</span>
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ __('Explore sacred sites, monasteries, and historical landmarks. Join our spiritual pilgrimages across the holy lands of Ethiopia.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  TOURS GRID
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:100px 24px;background:var(--dark-900);position:relative;">
    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:1;">
        
        @if($tours->isEmpty())
            <div class="card sr" style="padding:80px;text-align:center;max-width:600px;margin:0 auto;">
                <div style="font-size:3.5rem;margin-bottom:24px;">⛪</div>
                <h3 class="display" style="font-size:1.8rem;margin-bottom:12px;">{{ __('No Tours Scheduled') }}</h3>
                <p style="color:var(--text-60);">{{ __('We are currently planning our next spiritual journeys. Please check back later.') }}</p>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:32px;">
                @foreach($tours as $tour)
                    <div class="card sr" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
                        {{-- Image Header --}}
                        <div style="height:220px;position:relative;overflow:hidden;">
                            @if($tour->image)
                                <img src="{{ asset('storage/' . $tour->image) }}" alt="{{ $tour->place }}" style="width:100%;height:100%;object-fit:cover;transition:transform .5s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            @else
                                <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--dark-800),var(--blue-primary));display:flex;align-items:center;justify-content:center;">
                                    <svg width="60" height="60" fill="none" stroke="rgba(255,255,255,.1)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                </div>
                            @endif
                            
                            {{-- Price Badge --}}
                            <div style="position:absolute;top:20px;right:20px;padding:8px 16px;background:var(--gold);color:var(--dark-950);border-radius:99px;font-weight:800;font-size:.85rem;box-shadow:0 4px 15px rgba(243,186,21,.3);">
                                {{ $tour->formatted_cost }}
                            </div>

                            @if(!$tour->is_registration_open)
                                <div style="position:absolute;inset:0;background:var(--overlay-80);backdrop-filter:grayscale(1) blur(2px);display:flex;align-items:center;justify-content:center;">
                                    <span style="padding:10px 20px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);color:#fca5a5;border-radius:8px;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;">
                                        {{ $tour->is_full ? __('Tour Full') : __('Closed') }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div style="padding:32px;flex:1;display:flex;flex-direction:column;gap:20px;">
                            <div>
                                <h3 style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:600;color:var(--text-display);margin-bottom:8px;">{{ $tour->place }}</h3>
                                <p style="color:var(--text-60);font-size:.9rem;line-height:1.7;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                                    {{ $tour->description }}
                                </p>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:20px 0;border-top:1px solid rgba(255,255,255,.05);border-bottom:1px solid rgba(255,255,255,.05);">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="width:32px;height:32px;border-radius:8px;background:rgba(26,68,247,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <svg width="14" height="14" fill="none" stroke="var(--blue-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div style="font-size:.78rem;color:var(--text-40);">{{ $tour->ethiopian_date }}</div>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="width:32px;height:32px;border-radius:8px;background:rgba(243,186,21,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <svg width="14" height="14" fill="none" stroke="var(--gold)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div style="font-size:.78rem;color:var(--text-40);">{{ $tour->start_time }}</div>
                                </div>
                            </div>

                            @if($tour->is_registration_open)
                                <a href="{{ route('tour.register', $tour->id) }}" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;">
                                    {{ __('Register Now') }}
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                </a>
                            @else
                                <button disabled class="btn btn-ghost" style="width:100%;justify-content:center;padding:14px;opacity:.5;cursor:not-allowed;">
                                    {{ __('Registration Closed') }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  PAST TOURS / GALLERY CTA
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:80px 24px;background:var(--dark-950);text-align:center;">
    <div class="sr">
        <h3 class="display" style="font-size:1.8rem;margin-bottom:16px;">{{ __('Memories of Spiritual Journeys') }}</h3>
        <p style="color:var(--text-60);max-width:500px;margin:0 auto 32px;">{{ __('Explore the beauty and grace of our previous pilgrimages through our media gallery.') }}</p>
        <a href="{{ route('media') }}" class="btn btn-ghost">
            {{ __('View Gallery') }}
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </a>
    </div>
</section>

@endsection
