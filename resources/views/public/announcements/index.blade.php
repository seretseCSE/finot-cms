@extends('layouts.public')

@section('title', __('Announcements'))

@section('content')
<section style="position:relative;padding:120px 24px 80px;background:var(--dark-950);overflow:hidden;">
    <div style="position:absolute;inset:0;background:url('{{ asset('images/hero-bg.jpg') }}') center/cover no-repeat;filter:brightness(.2);"></div>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--overlay-90),rgba(26,68,247,.2));"></div>
    
    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:2;width:100%;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;margin-bottom:20px;">
            <span class="am">መግለጫዎች እና ማስታወቂያዎች</span>
        </div>
        <h1 class="display sr" style="font-size:clamp(2.6rem,6vw,4rem);margin-bottom:16px;">
            {{ __('Announcements') }}
        </h1>
        <p class="sr" style="color:var(--text-60);max-width:600px;margin:0 auto;font-size:1.1rem;line-height:1.7;">
            {{ __('Stay informed with the latest news, updates, and urgent messages from our Sunday school.') }}
        </p>
    </div>
</section>

<section style="padding:80px 24px;background:var(--dark-900);position:relative;">
    <div class="tilet" style="position:absolute;inset:0;opacity:.2;"></div>
    
    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:1;">
        @if($announcements->count() > 0)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:32px;">
                @foreach($announcements as $announcement)
                    <div class="card sr" style="padding:0;overflow:hidden;display:flex;flex-direction:column;border-radius:var(--r-lg);">
                        {{-- Urgent Ribbon --}}
                        @if($announcement->is_urgent)
                            <div style="background:var(--red-primary);color:#fff;padding:6px 16px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;display:flex;align-items:center;gap:8px;">
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                {{ __('Urgent Announcement') }}
                            </div>
                        @endif

                        {{-- Announcement Image --}}
                        @if($announcement->image)
                            <div style="width:100%;height:200px;overflow:hidden;background:var(--dark-800);">
                                <img src="{{ $announcement->image_url }}" alt="{{ $announcement->title }}" style="width:100%;height:100%;object-fit:cover;">
                            </div>
                        @endif

                        <div style="padding:32px;">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                                <div style="width:40px;height:40px;border-radius:10px;background:rgba(26,68,247,.1);border:1px solid rgba(26,68,247,.2);display:flex;align-items:center;justify-content:center;color:var(--blue-400);">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                                </div>
                                <div style="font-size:.8rem;color:var(--parchment-40);font-weight:500;">
                                    {{ $announcement->published_at ? $announcement->published_at->format('M d, Y') : $announcement->start_date->format('M d, Y') }}
                                </div>
                            </div>

                            <h3 class="am" style="font-size:1.4rem;font-weight:700;color:var(--text-display);margin-bottom:12px;line-height:1.3;">
                                {{ app()->getLocale() === 'am' ? ($announcement->title_am ?? $announcement->title) : $announcement->title }}
                            </h3>
                            
                            <div class="am" style="font-size:.95rem;color:var(--text-60);line-height:1.7;margin-bottom:24px;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;">
                                {!! strip_tags(app()->getLocale() === 'am' ? ($announcement->content_am ?? $announcement->content) : $announcement->content) !!}
                            </div>

                            <div style="margin-top:auto;display:flex;align-items:center;justify-content:space-between;padding-top:20px;border-top:1px solid var(--border-subtle);">
                                <a href="{{ route('announcements.show', $announcement->id) }}" class="btn btn-ghost" style="padding:8px 16px;font-size:.85rem;">
                                    {{ __('Read More') }}
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:60px;">
                {{ $announcements->links() }}
            </div>
        @else
            <div class="card sr" style="padding:80px 24px;text-align:center;">
                <div style="font-size:3rem;margin-bottom:20px;">📢</div>
                <h2 class="display" style="font-size:1.8rem;margin-bottom:12px;">{{ __('No Announcements') }}</h2>
                <p style="color:var(--text-60);max-width:500px;margin:0 auto;">{{ __('There are no active announcements at this time. Please check back later.') }}</p>
                <a href="{{ url('/') }}" class="btn btn-primary" style="margin-top:32px;">{{ __('Back to Home') }}</a>
            </div>
        @endif
    </div>
</section>
@endsection
