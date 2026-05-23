@extends('layouts.public')

@section('title', __('Shop'))

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Shop Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    <div class="hero-parallax" style="position:absolute;inset:-10% 0;background:url('{{ asset('images/page-title-bg.jpg') }}') center/cover no-repeat;filter:brightness(.25) saturate(.8);will-change:transform;"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90) 0%,rgba(26,68,247,.2) 50%,var(--overlay-95) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Our Store') }}</div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;color:var(--text-hero);">
            {{ __('Shop') }}
            <span style="color:var(--gold);">{{ __('Products') }}</span>
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ __('Browse our collection of books, apparel, and spiritual resources available for purchase.') }}
        </p>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  STATS & FILTERS
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:clamp(40px,8vw,60px) clamp(12px,4vw,24px) clamp(15px,3vw,20px);background:var(--dark-900);">
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
            <form action="{{ route('shop.index') }}" method="GET" style="display:flex;flex-direction:column;gap:clamp(12px,3vw,16px);" id="shop-filter-form">
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
                    <a href="{{ route('shop.index') }}" class="btn btn-ghost" style="padding:12px 20px;width:100%;text-align:center;">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  PRODUCTS GRID
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:0 clamp(12px,4vw,24px) clamp(60px,12vw,100px);background:var(--dark-900);">
    <div style="max-width:1200px;margin:0 auto;">
        @if($products->count() > 0)
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
                <x-tour-icon name="giving" size="48" class="" aria-hidden="true" />
                <h3 class="display" style="font-size:1.8rem;margin-bottom:12px;">{{ __('No Products Found') }}</h3>
                <p style="color:var(--text-60);">{{ __('Try adjusting your filters or check back later for new items.') }}</p>
            </div>
        @endif
    </div>
</section>

@endsection
