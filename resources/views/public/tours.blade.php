@extends('layouts.public')

@section('title', __('Tours & Shop'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Adapts based on active tab
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/stats-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-95) 0%,rgba(26,68,247,.1) 50%,var(--overlay-98) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Journeys & Store') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;color:var(--text-hero);">
            {{ __('Tours') }}
            <span style="color:var(--gold);">&</span>
            {{ __('Shop') }}
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ __('Explore sacred pilgrimages across Ethiopia and browse our collection of spiritual resources.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  TAB BUTTONS
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:40px 24px 0;background:var(--dark-900);">
    <div style="max-width:1280px;margin:0 auto;">
        <div class="sr" style="display:flex;gap:4px;margin-bottom:0;">
            <button class="tours-tab active" data-tab="tours" style="
                padding:12px 28px;border-radius:10px 10px 0 0;font-family:'Inter',sans-serif;font-size:.9rem;font-weight:600;
                background:var(--bg-900);border:1px solid var(--border-subtle);border-bottom:none;
                color:var(--text-display);cursor:pointer;transition:all .2s;
            ">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;display:inline;vertical-align:-2px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                {{ __('Tours') }}
            </button>
            <button class="tours-tab" data-tab="shop" style="
                padding:12px 28px;border-radius:10px 10px 0 0;font-family:'Inter',sans-serif;font-size:.9rem;font-weight:600;
                background:transparent;border:1px solid transparent;border-bottom:none;
                color:var(--text-40);cursor:pointer;transition:all .2s;
            ">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;display:inline;vertical-align:-2px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                {{ __('Shop') }}
            </button>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  TOURS GRID (shown when Tours tab active)
     ═══════════════════════════════════════════════════════ --}}
<section id="tab-tours" class="tours-tab-content" style="padding:100px 24px;background:var(--dark-900);position:relative;display:{{ $activeTab === 'tours' ? 'block' : 'none' }};">
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

                            @if($tour->ethiopian_registration_deadline)
                                <div style="display:flex;align-items:center;gap:10px;padding:12px 0 0;">
                                    <div style="width:28px;height:28px;border-radius:6px;background:rgba(239,68,68,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <svg width="12" height="12" fill="none" stroke="var(--red-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div style="font-size:.75rem;color:var(--text-40);">
                                        <span style="color:var(--red-400);">{{ __('Register by') }}:</span> {{ $tour->ethiopian_registration_deadline }}
                                    </div>
                                </div>
                            @endif

                            {{-- Days Left Display --}}
                            @if($tour->days_left !== null)
                                <div style="display:flex;align-items:center;gap:10px;padding:12px 0 0;">
                                    @if($tour->days_left < 0)
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(239,68,68,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <svg width="12" height="12" fill="none" stroke="var(--red-400)" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M3 12a9 9 0 1118 0 9 9 0 01-18 0z"/>
                                            </svg>
                                        </div>
                                        <div style="font-size:.75rem;color:var(--red-400);">
                                            {{ abs($tour->days_left) }} {{ __('days ago') }}
                                        </div>
                                    @elseif($tour->days_left <= 3)
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(249,115,22,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <svg width="12" height="12" fill="none" stroke="var(--orange-400)" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div style="font-size:.75rem;color:var(--orange-400);">
                                            {{ $tour->days_left == 0 ? __('Today') : ($tour->days_left == 1 ? __('Tomorrow') : $tour->days_left . ' ' . __('days left')) }}
                                        </div>
                                    @elseif($tour->days_left <= 7)
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(245,158,11,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <svg width="12" height="12" fill="none" stroke="var(--yellow-400)" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div style="font-size:.75rem;color:var(--yellow-400);">
                                            {{ $tour->days_left }} {{ __('days left') }}
                                        </div>
                                    @else
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(34,197,94,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <svg width="12" height="12" fill="none" stroke="var(--green-400)" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div style="font-size:.75rem;color:var(--green-400);">
                                            {{ $tour->days_left }} {{ __('days left') }}
                                        </div>
                                    @endif
                                </div>
                            @endif

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
     4.  SHOP SECTION (shown when Shop tab active)
     ═══════════════════════════════════════════════════════ --}}
<section id="tab-shop" class="tours-tab-content" style="padding:clamp(40px,8vw,60px) clamp(12px,4vw,24px) clamp(15px,3vw,20px);background:var(--dark-900);display:{{ $activeTab === 'shop' ? 'block' : 'none' }};">
    <div style="max-width:1200px;margin:0 auto;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:clamp(12px,3vw,20px);margin-bottom:clamp(24px,5vw,40px);">
            @foreach([
                ['val' => $totalProducts, 'label' => __('Total Items'), 'color' => 'var(--blue-400)'],
                ['val' => $inStockProducts, 'label' => __('In Stock'), 'color' => '#4ade80'],
                ['val' => $categories->count(), 'label' => __('Categories'), 'color' => 'var(--gold)'],
            ] as $stat)
            <div class="card sr" style="padding:20px;text-align:center;border-color:rgba(255,255,255,.05);">
                <div style="font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:{{ $stat['color'] }};">{{ $stat['val'] }}</div>
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--parchment-40);margin-top:4px;">{{ $stat['label'] }}</div>
            </div>
            @endforeach
        </div>

        <div class="card sr" style="padding:clamp(16px,4vw,24px);margin-bottom:clamp(24px,5vw,40px);">
            <form action="{{ route('tours.index') }}" method="GET" style="display:flex;flex-direction:column;gap:clamp(12px,3vw,16px);">
                <input type="hidden" name="tab" value="shop">
                <div style="position:relative;">
                    <svg style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-40);" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search products...') }}" style="width:100%;background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 16px 12px 42px;color:var(--text-main);outline:none;font-size:16px;">
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                    <select name="category" style="background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:10px;padding:12px 16px;color:var(--text-main);outline:none;cursor:pointer;flex:1;min-width:140px;font-size:16px;" onchange="this.form.submit()">
                        <option value="" style="background:var(--bg-800);">{{ __('All Categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" {{ request('category') == $category ? 'selected' : '' }} style="background:var(--bg-800);">{{ $category }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-primary" style="padding:12px 24px;flex:1;min-width:100px;">{{ __('Filter') }}</button>
                </div>

                @if(request('search') || request('category'))
                    <a href="{{ route('tours.index', ['tab' => 'shop']) }}" class="btn btn-ghost" style="padding:12px 20px;width:100%;text-align:center;">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>
    </div>
</section>

<section style="padding:0 clamp(12px,4vw,24px) clamp(60px,12vw,100px);background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        @if($products && $products->count() > 0)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:clamp(16px,4vw,24px);">
                @foreach($products as $product)
                    <a href="{{ route('shop.show', $product->slug) }}" class="card sr" style="padding:0;overflow:hidden;text-decoration:none;display:flex;flex-direction:column;" data-delay="{{ $loop->index * 40 }}">
                        <div style="height:200px;overflow:hidden;position:relative;">
                            @if($product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" style="width:100%;height:100%;object-fit:cover;transition:transform .5s;">
                            @else
                                <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--dark-800),var(--blue-primary));display:flex;align-items:center;justify-content:center;">
                                    <svg width="48" height="48" fill="none" stroke="var(--blue-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                </div>
                            @endif
                            @if(!$product->isInStock())
                                <div style="position:absolute;top:12px;right:12px;padding:4px 12px;border-radius:99px;background:rgba(239,68,68,.9);color:#fff;font-size:.7rem;font-weight:600;text-transform:uppercase;">{{ __('Out of Stock') }}</div>
                            @elseif($product->stock_quantity < 5)
                                <div style="position:absolute;top:12px;right:12px;padding:4px 12px;border-radius:99px;background:rgba(243,186,21,.9);color:var(--bg-950);font-size:.7rem;font-weight:600;text-transform:uppercase;">{{ __('Low Stock') }}</div>
                            @endif
                        </div>

                        <div style="padding:clamp(20px,5vw,28px);display:flex;flex-direction:column;gap:10px;flex:1;">
                            @if($product->category)
                                <div style="font-size:.7rem;color:var(--gold);text-transform:uppercase;letter-spacing:.05em;">{{ $product->category }}</div>
                            @endif
                            <h3 style="font-size:1.05rem;font-weight:600;color:var(--text-display);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $product->name }}</h3>
                            @if($product->description)
                                <p style="color:var(--text-60);font-size:.85rem;line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;flex:1;">
                                    {{ $product->description }}
                                </p>
                            @endif
                            <div style="padding-top:14px;border-top:1px solid rgba(255,255,255,.05);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <span style="font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;color:var(--blue-400);">{{ $product->formatted_price }}</span>
                                <span style="font-size:.8rem;color:var(--text-40);display:flex;align-items:center;gap:4px;">
                                    {{ __('View Details') }}
                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div style="margin-top:60px;display:flex;justify-content:center;">
                {{ $products->links() }}
            </div>
        @else
            <div class="card sr" style="padding:80px;text-align:center;max-width:600px;margin:0 auto;">
                <div style="font-size:3rem;margin-bottom:24px;">🛒</div>
                <h3 class="display" style="font-size:1.8rem;margin-bottom:12px;">{{ __('No Products Found') }}</h3>
                <p style="color:var(--text-60);">{{ __('Try adjusting your filters or check back later for new items.') }}</p>
            </div>
        @endif
    </div>
</section>

@push('scripts')
<script>
(function() {
    var tabs = document.querySelectorAll('.tours-tab');
    var contents = document.querySelectorAll('.tours-tab-content');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var target = this.getAttribute('data-tab');

            tabs.forEach(function(t) {
                t.classList.remove('active');
                t.style.background = 'transparent';
                t.style.border = '1px solid transparent';
                t.style.color = 'var(--text-40)';
            });

            this.classList.add('active');
            this.style.background = 'var(--bg-900)';
            this.style.border = '1px solid var(--border-subtle)';
            this.style.borderBottom = 'none';
            this.style.color = 'var(--text-display)';

            contents.forEach(function(c) {
                c.style.display = c.id === 'tab-' + target ? 'block' : 'none';
            });
        });
    });

    var activeTab = '{{ $activeTab }}';
    if (activeTab === 'shop') {
        var shopTab = document.querySelector('[data-tab="shop"]');
        if (shopTab) shopTab.click();
    }
})();
</script>
@endpush

@endsection
