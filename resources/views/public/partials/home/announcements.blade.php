{{-- ═══════════════════════════════════════════════════════
     1.5 ANNOUNCEMENTS — Horizontal cards if they exist
═══════════════════════════════════════════════════════ --}}
@if(count($announcements ?? []) > 0)
<section id="announcements" style="background:var(--bg-950);padding:72px 40px;border-bottom:1px solid rgba(255,255,255,.06);">
    <div style="max-width:1280px;margin:0 auto;">

        {{-- Section header --}}
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:40px;">
            <div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <div style="width:24px;height:1px;background:var(--gold);"></div>
                    <span style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);font-weight:500;">{{ __('News & Updates') }}</span>
                </div>
                <h2 style="font-family:'Playfair Display',serif;font-weight:700;font-size:clamp(1.6rem,3vw,2.4rem);line-height:1.1;color:#fff;letter-spacing:-.02em;margin:0;">
                    {{ __('Latest Announcements') }}
                </h2>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                @if(auth()->check() && auth()->user()->hasRole(['admin', 'superadmin', 'internal_relations_head']))
                    <a href="{{ route('filament.admin.resources.announcements.create') }}" class="btn btn-gold" style="padding:10px 20px;font-size:.85rem;display:flex;align-items:center;gap:6px;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        {{ __('New Announcement') }}
                    </a>
                @endif
                <a href="{{ route('news') }}" class="btn btn-ghost" style="padding:10px 20px;font-size:.85rem;border-color:rgba(255,255,255,.12);color:rgba(255,255,255,.6);">{{ __('View All') }}</a>
            </div>
        </div>

        {{-- Cards grid --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1px;background:rgba(255,255,255,.06);border-radius:16px;overflow:hidden;">
            @foreach($announcements as $ann)
            <a href="{{ route('announcements.show', $ann->id) }}" style="text-decoration:none;display:flex;flex-direction:column;background:var(--bg-950);transition:background .2s;" onmouseover="this.style.background='rgba(255,255,255,.03)'" onmouseout="this.style.background='var(--bg-950)'">

                {{-- Image --}}
                <div style="width:100%;height:180px;overflow:hidden;background:#0d1530;position:relative;flex-shrink:0;">
                    @if($ann->image)
                        <img src="{{ $ann->image_url }}" alt="{{ $ann->title }}" style="width:100%;height:100%;object-fit:cover;opacity:.9;">
                    @else
                        {{-- Ethiopian Orthodox cross placeholder --}}
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                            <img
                                src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/3c/Ethiopian_cross.svg/480px-Ethiopian_cross.svg.png"
                                alt="Ethiopian Orthodox Cross"
                                style="height:90px;width:auto;opacity:.18;filter:invert(1);"
                            >
                        </div>
                    @endif
                    @if($ann->is_urgent)
                        <div style="position:absolute;top:12px;left:12px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:3px 10px;border-radius:99px;font-size:.6rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;">
                            {{ __('Urgent') }}
                        </div>
                    @endif
                </div>

                {{-- Body --}}
                <div style="padding:24px;display:flex;flex-direction:column;gap:8px;flex:1;{{ $ann->is_urgent ? 'border-left:2px solid rgba(239,68,68,.5);' : '' }}">
                    <span style="font-size:10px;color:rgba(255,255,255,.3);font-weight:500;text-transform:uppercase;letter-spacing:.08em;">
                        {{ $ann->published_at ? $ann->published_at->format('M d, Y') : $ann->start_date->format('M d, Y') }}
                    </span>
                    <h3 class="am" style="font-size:.95rem;font-weight:600;color:#fff;line-height:1.45;margin:0;">
                        {{ Str::limit(app()->getLocale() === 'am' ? ($ann->title_am ?? $ann->title) : $ann->title, 60) }}
                    </h3>
                    <p class="am" style="font-size:.82rem;color:rgba(255,255,255,.4);line-height:1.65;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:0;">
                        {!! strip_tags(app()->getLocale() === 'am' ? ($ann->content_am ?? $ann->content) : $ann->content) !!}
                    </p>
                    <div style="margin-top:auto;padding-top:16px;display:flex;align-items:center;gap:6px;color:var(--gold);font-size:.78rem;font-weight:500;">
                        {{ __('Read more') }}
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </div>

            </a>
            @endforeach
        </div>

    </div>
</section>
@endif