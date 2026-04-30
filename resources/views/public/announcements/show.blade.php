@extends('layouts.public')

@section('title', app()->getLocale() === 'am' ? ($announcement->title_am ?? $announcement->title) : $announcement->title)

@section('content')
<section style="padding:140px 24px 60px;background:var(--dark-950);position:relative;overflow:hidden;border-bottom:1px solid var(--border-subtle);">
    <div style="position:absolute;inset:0;background:url('{{ asset('images/hero-bg.jpg') }}') center/cover no-repeat;filter:brightness(.15) blur(5px);opacity:.5;"></div>
    
    <div style="max-width:800px;margin:0 auto;position:relative;z-index:2;width:100%;">
        <a href="{{ route('news') }}" style="display:inline-flex;align-items:center;gap:8px;color:var(--blue-400);text-decoration:none;font-size:.85rem;font-weight:600;margin-bottom:32px;transition:color .2s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='var(--blue-400)'">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ __('Back to Announcements') }}
        </a>

        @if($announcement->is_urgent)
            <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--red-primary);padding:6px 14px;border-radius:99px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:20px;">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                {{ __('Urgent') }}
            </div>
        @endif

        {{-- Announcement Image --}}
        @if($announcement->image)
            <div style="width:100%;max-height:400px;overflow:hidden;border-radius:16px;margin-bottom:32px;background:var(--dark-800);">
                <img src="{{ $announcement->image_url }}" alt="{{ $announcement->title }}" style="width:100%;height:100%;object-fit:cover;">
            </div>
        @endif

        <h1 class="am" style="font-size:clamp(2rem,5vw,3rem);margin-bottom:24px;color:var(--text-display);line-height:1.2;">
            {{ app()->getLocale() === 'am' ? ($announcement->title_am ?? $announcement->title) : $announcement->title }}
        </h1>

        <div style="display:flex;align-items:center;gap:20px;padding-bottom:32px;border-bottom:1px solid rgba(255,255,255,.05);">
            <div style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:var(--parchment-60);">
                <svg width="16" height="16" fill="none" stroke="var(--gold)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ $announcement->published_at ? $announcement->published_at->format('M d, Y') : $announcement->start_date->format('M d, Y') }}
            </div>
            @if($announcement->createdBy)
                <div style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:var(--parchment-60);">
                    <svg width="16" height="16" fill="none" stroke="var(--gold)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $announcement->createdBy->name }}
                </div>
            @endif
        </div>
    </div>
</section>

<section style="padding:60px 24px 100px;background:var(--dark-900);position:relative;">
    <div class="tilet" style="position:absolute;inset:0;opacity:.2;"></div>
    
    <div style="max-width:800px;margin:0 auto;position:relative;z-index:1;">
        <div class="am announcement-content" style="font-size:1.1rem;color:var(--text-60);line-height:1.8;">
            {!! app()->getLocale() === 'am' ? ($announcement->content_am ?? $announcement->content) : $announcement->content !!}
        </div>

        <div style="margin-top:64px;padding-top:40px;border-top:1px solid var(--border-subtle);display:flex;justify-content:center;">
            <a href="{{ route('news') }}" class="btn btn-primary">
                {{ __('View All Announcements') }}
            </a>
        </div>
    </div>
</section>

<style>
    .announcement-content p { margin-bottom: 24px; }
    .announcement-content h2, .announcement-content h3 { color: var(--text-display); margin: 40px 0 20px; }
    .announcement-content ul, .announcement-content ol { margin: 20px 0 30px; padding-left: 24px; }
    .announcement-content li { margin-bottom: 12px; }
</style>
@endsection
