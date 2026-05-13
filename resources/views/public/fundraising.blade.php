@extends('layouts.public')

@section('title', __('Support Our Mission'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Fundraising Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    {{-- Parallax background image --}}
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/stats-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90) 0%,rgba(26,68,247,.1) 50%,var(--overlay-95) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Giving') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;color:var(--text-hero);">
            {{ __('Fundraising') }}
            <span style="color:var(--gold);">{{ __('Progress') }}</span>
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ __('Support our mission and community through various fundraising campaigns. Your generous contributions help us make a difference.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  OVERALL SUMMARY
     ═══════════════════════════════════════════════════════ --}}
@if($campaigns->count() > 0)
<section style="padding:40px 24px;background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        <div class="card sr" style="padding:40px;background:linear-gradient(135deg,rgba(26,68,247,.1),var(--overlay-40));border-color:rgba(26,68,247,.2);">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:40px;text-align:center;">
                <div>
                    <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;color:var(--text-40);margin-bottom:8px;">{{ __('Total Raised') }}</div>
                    <div style="font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:700;color:var(--text-display);">ETB {{ number_format($totalRaised, 2) }}</div>
                </div>
                <div>
                    <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;color:var(--parchment-40);margin-bottom:12px;">{{ __('Overall Progress') }}</div>
                    <div style="height:8px;background:rgba(255,255,255,.05);border-radius:99px;overflow:hidden;margin-bottom:12px;">
                        <div style="width:{{ min(100, $overallProgress) }}%;height:100%;background:linear-gradient(90deg, var(--blue-primary), var(--gold));"></div>
                    </div>
                    <div style="font-size:1.2rem;font-weight:700;color:var(--gold);">{{ number_format($overallProgress, 1) }}%</div>
                </div>
                <div>
                    <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;color:var(--text-40);margin-bottom:8px;">{{ __('Active Campaigns') }}</div>
                    <div style="font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:700;color:var(--text-display);">{{ $campaigns->where('status', 'Active')->count() }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ═══════════════════════════════════════════════════════
     3.  CAMPAIGNS GRID
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:60px 24px 100px;background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        
        @if($campaigns->isEmpty())
            <div class="card sr" style="padding:80px;text-align:center;max-width:600px;margin:0 auto;">
                <div style="font-size:3rem;margin-bottom:24px;">💰</div>
                <h3 class="display" style="font-size:1.8rem;margin-bottom:12px;">{{ __('No Active Campaigns') }}</h3>
                <p style="color:var(--text-60);">{{ __('We don\'t have any active campaigns at the moment. Please check back later.') }}</p>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:32px;">
                @foreach($campaigns as $campaign)
                    <div class="card sr" style="padding:0;overflow:hidden;display:flex;flex-direction:column;" data-delay="{{ $loop->index * 60 }}">
                        {{-- Header Image/Status --}}
                        <div style="height:140px;background:linear-gradient(135deg,rgba(26,68,247,.3),var(--overlay-80));position:relative;overflow:hidden;">
                            @if($campaign->featured_image)
                                <img src="{{ $campaign->featured_image_url }}" alt="{{ $campaign->campaign_name }}" style="width:100%;height:100%;object-fit:cover;opacity:.6;">
                            @endif
                            <div style="position:absolute;top:20px;left:20px;">
                                <span style="font-size:.6rem;padding:3px 10px;border-radius:99px;background:{{ $campaign->status === 'Active' ? 'rgba(74,222,128,.15)' : 'rgba(255,255,255,.1)' }};border:1px solid {{ $campaign->status === 'Active' ? 'rgba(74,222,128,.3)' : 'rgba(255,255,255,.2)' }};color:{{ $campaign->status === 'Active' ? '#86efac' : '#fff' }};text-transform:uppercase;letter-spacing:.1em;font-weight:700;">{{ $campaign->status }}</span>
                            </div>
                        </div>

                        <div style="padding:32px;flex:1;display:flex;flex-direction:column;gap:20px;">
                            <div>
                                <h3 style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:600;color:var(--text-display);margin-bottom:8px;">{{ $campaign->campaign_name }}</h3>
                                @if($campaign->campaign_category)
                                    <span style="font-size:.65rem;color:var(--gold);text-transform:uppercase;letter-spacing:.05em;font-weight:600;">{{ $campaign->campaign_category }}</span>
                                @endif
                            </div>

                            @if($campaign->description)
                                <p style="color:var(--parchment-60);font-size:.85rem;line-height:1.7;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                                    {{ $campaign->description }}
                                </p>
                            @endif

                            <div style="margin-top:auto;">
                                <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:10px;">
                                    <div style="font-size:.75rem;color:var(--parchment-40);">{{ __('Raised') }}: <strong style="color:#fff;">ETB {{ number_format($campaign->total_raised, 0) }}</strong></div>
                                    <div style="font-size:.9rem;font-weight:800;color:var(--gold);">{{ number_format($campaign->progress_percentage, 0) }}%</div>
                                </div>
                                <div style="height:6px;background:rgba(255,255,255,.05);border-radius:99px;overflow:hidden;">
                                    <div style="width:{{ min(100, $campaign->progress_percentage) }}%;height:100%;background:linear-gradient(90deg, var(--blue-primary), var(--gold));"></div>
                                </div>
                                <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.65rem;color:var(--parchment-40);">
                                    <span>{{ __('Goal') }}: ETB {{ number_format($campaign->target_amount, 0) }}</span>
                                    @if($campaign->days_remaining !== null)
                                        <span>{{ $campaign->days_remaining }} {{ __('days left') }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Donor Stats --}}
                            @if($campaign->donations && $campaign->donations->count() > 0)
                                <div style="padding:16px;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.05);border-radius:10px;">
                                    <div style="font-size:.7rem;text-transform:uppercase;color:var(--parchment-40);margin-bottom:6px;font-weight:700;">{{ __('Donors') }}: {{ $campaign->donations->count() }}</div>
                                    @php
                                        $topDonors = $campaign->donations()
                                            ->where('donor_name', '!=', 'Campaign Update')
                                            ->whereNotNull('donor_name')
                                            ->groupBy('donor_name')
                                            ->selectRaw('donor_name, SUM(amount) as total')
                                            ->orderByDesc('total')
                                            ->limit(3)
                                            ->get();
                                    @endphp
                                    @if($topDonors->count() > 0)
                                        <div style="font-size:.75rem;color:var(--text-60);margin-bottom:8px;">{{ __('Top Contributors') }}:</div>
                                        @foreach($topDonors as $donor)
                                            <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--parchment-60);margin-bottom:2px;">
                                                <span>{{ $donor->donor_name }}</span>
                                                <span style="color:var(--gold);font-weight:600;">ETB {{ number_format($donor->total, 0) }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endif

                            @if($campaign->bank_account_info)
                                <div style="padding:16px;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.05);border-radius:10px;">
                                    <div style="font-size:.7rem;text-transform:uppercase;color:var(--parchment-40);margin-bottom:6px;font-weight:700;">{{ __('Donation Info') }}</div>
                                    <div style="font-size:.75rem;color:var(--text-60);line-height:1.5;white-space:pre-line;">{{ $campaign->bank_account_info }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     4.  HELP SECTION
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:80px 24px;background:var(--dark-950);text-align:center;">
    <div class="card sr" style="max-width:800px;margin:0 auto;padding:48px;border-color:var(--blue-glow);">
        <h2 class="display" style="font-size:1.8rem;margin-bottom:16px;">{{ __('How to Support Us') }}</h2>
        <p style="color:var(--text-60);font-size:.95rem;line-height:1.75;margin-bottom:32px;">
            {{ __('All donations are processed offline through bank transfers or in-person contributions. Please use the bank account information provided in each campaign. For any questions, feel free to contact us.') }}
        </p>
        <div style="display:flex;justify-content:center;gap:16px;">
            <a href="{{ route('contact') }}" class="btn btn-primary">{{ __('Contact for Inquiry') }}</a>
            <a href="{{ route('about') }}" class="btn btn-ghost">{{ __('Our Mission') }}</a>
        </div>
    </div>
</section>

@endsection
