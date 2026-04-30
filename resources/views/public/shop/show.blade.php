@extends('layouts.public')

@section('title', $product->name)

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Product Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:140px 24px 80px;background:var(--dark-950);overflow:hidden;">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(5,10,28,.98) 0%,rgba(26,68,247,.1) 50%,rgba(5,10,28,.98) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:900px;margin:0 auto;text-align:center;">
        @if($product->category)
            <div class="sec-label sr" style="justify-content:center;">{{ $product->category }}</div>
        @endif
        <h1 class="display sr" style="font-size:clamp(2rem,4vw,3.2rem);margin-bottom:24px;line-height:1.2;">
            {{ $product->name }}
        </h1>
        <div class="sr" style="font-family:'Playfair Display',serif;font-size:1.6rem;color:var(--blue-400);">
            {{ $product->formatted_price }}
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  PRODUCT DETAIL
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:60px 24px 100px;background:var(--dark-900);position:relative;">
    <div style="max-width:1000px;margin:0 auto;position:relative;z-index:1;">
        <div class="card sr" style="padding:0;overflow:hidden;border-color:rgba(255,255,255,.05);display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));">
            <div style="min-height:350px;overflow:hidden;background:var(--dark-950);">
                @if($product->image_url)
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" style="width:100%;height:100%;object-fit:cover;">
                @else
                    <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--dark-800),var(--blue-primary));display:flex;align-items:center;justify-content:center;">
                        <svg width="64" height="64" fill="none" stroke="var(--blue-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                @endif
            </div>

            <div style="padding:clamp(32px,5vw,48px);display:flex;flex-direction:column;gap:20px;">
                <div>
                    <h2 style="font-size:1.4rem;font-weight:600;color:var(--text-display);margin-bottom:12px;">{{ __('Description') }}</h2>
                    @if($product->description)
                        <p style="color:var(--text-60);font-size:1rem;line-height:1.8;">{{ $product->description }}</p>
                    @else
                        <p style="color:var(--text-40);font-size:1rem;">{{ __('No description available.') }}</p>
                    @endif
                </div>

                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
                    <div class="card" style="padding:16px;text-align:center;border-color:rgba(255,255,255,.05);">
                        <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--parchment-40);margin-bottom:4px;">{{ __('Price') }}</div>
                        <div style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;color:var(--blue-400);">{{ $product->formatted_price }}</div>
                    </div>
                    <div class="card" style="padding:16px;text-align:center;border-color:rgba(255,255,255,.05);">
                        <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--parchment-40);margin-bottom:4px;">{{ __('Stock') }}</div>
                        <div style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;color:{{ $product->isInStock() ? '#4ade80' : 'var(--text-40)' }};">
                            {{ $product->isInStock() ? $product->stock_quantity : __('Out of Stock') }}
                        </div>
                    </div>
                </div>

                <div style="padding-top:16px;border-top:1px solid rgba(255,255,255,.05);">
                    <div style="display:flex;align-items:center;gap:8px;color:var(--text-40);font-size:.8rem;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('Added') }} {{ $product->created_at?->diffForHumans() ?? __('Recently') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="sr" style="margin-top:40px;display:flex;justify-content:center;">
            <a href="{{ route('shop.index') }}" class="btn btn-ghost">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Back to Shop') }}
            </a>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  RELATED PRODUCTS
     ═══════════════════════════════════════════════════════ --}}
@if($relatedProducts->isNotEmpty())
<section style="padding:80px 24px;background:var(--dark-950);">
    <div style="max-width:1200px;margin:0 auto;">
        <h2 class="display sr" style="font-size:1.8rem;margin-bottom:32px;text-align:center;">{{ __('Related Products') }}</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;">
            @foreach($relatedProducts as $related)
                <a href="{{ route('shop.show', $related->slug) }}" class="card sr" style="padding:0;overflow:hidden;text-decoration:none;display:flex;flex-direction:column;">
                    <div style="height:160px;overflow:hidden;">
                        @if($related->image_url)
                            <img src="{{ $related->image_url }}" alt="{{ $related->name }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--dark-800),var(--blue-primary));"></div>
                        @endif
                    </div>
                    <div style="padding:20px;display:flex;flex-direction:column;gap:8px;flex:1;">
                        @if($related->category)
                            <div style="font-size:.7rem;color:var(--gold);text-transform:uppercase;letter-spacing:.05em;">{{ $related->category }}</div>
                        @endif
                        <h4 style="font-size:.95rem;font-weight:600;color:#fff;margin-bottom:4px;line-height:1.4;">{{ $related->name }}</h4>
                        <div style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--blue-400);margin-top:auto;">{{ $related->formatted_price }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection
