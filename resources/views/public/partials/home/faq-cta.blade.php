{{-- ═══════════════════════════════════════════════════════
     13.  FAQ + CTA SPLIT
═══════════════════════════════════════════════════════ --}}
<section id="faq" style="padding:80px 24px;background:linear-gradient(180deg,var(--dark-900),var(--dark-950));">
    <div class="faq-cta-grid" style="max-width:1280px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:start;">

        {{-- FAQ --}}
        <div class="sr-l">
            <div class="sec-label">{{ __('Questions') }}</div>
            <h2 class="display" style="font-size:clamp(1.8rem,3vw,2.6rem);margin-bottom:32px;">{{ __('FAQs') }}</h2>

            @if($faqs->count() > 0)
                @foreach($faqs as $faq)
            <div class="faq-item">
                <button class="faq-btn">
                    <span>{{ app()->getLocale() === 'am' ? ($faq->question_am ?? $faq->question) : $faq->question }}</span>
                    <div class="faq-icon">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/></svg>
                    </div>
                </button>
                <div class="faq-body"><p>{!! app()->getLocale() === 'am' ? ($faq->answer_am ?? $faq->answer) : $faq->answer !!}</p></div>
            </div>
                @endforeach
            @else
                {{-- Fallback FAQs if none exist in database --}}
                @foreach([
                    ['q' => __('Where are you located?'), 'a' => __('We are located in Addis Ababa, Ayertena area. See the Contact page for exact directions.')],
                    ['q' => __('How can I volunteer?'), 'a' => __('Send us a message via Contact and we will respond with available opportunities.')],
                    ['q' => __('Who can join the programs?'), 'a' => __('Our programs are open to all ages — from children to adults. Visit Programs for details.')],
                    ['q' => __('How can I become a member?'), 'a' => __('Contact our Internal Relations department or visit us during service hours for membership information.')],
                ] as $faq)
            <div class="faq-item">
                <button class="faq-btn">
                    <span>{{ $faq['q'] }}</span>
                    <div class="faq-icon">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/></svg>
                    </div>
                </button>
                <div class="faq-body"><p>{{ $faq['a'] }}</p></div>
            </div>
                @endforeach
            @endif
        </div>

        {{-- CTA --}}
        <div class="sr-r">
            <div style="background:linear-gradient(135deg,var(--dark-800),var(--dark-700));border:1px solid rgba(26,68,247,.15);border-radius:var(--r-lg);padding:44px;position:relative;overflow:hidden;">
                <div style="position:absolute;bottom:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:var(--blue-primary);filter:blur(70px);opacity:.15;pointer-events:none;"></div>
                <svg style="position:absolute;top:-20px;right:-20px;width:120px;opacity:.04;pointer-events:none;" viewBox="0 0 100 100" fill="none">
                    <rect x="43" y="6" width="14" height="88" rx="2" fill="#F3BA15"/>
                    <rect x="6" y="43" width="88" height="14" rx="2" fill="#F3BA15"/>
                </svg>

                <div class="sec-label">{{ __('Stay Connected') }}</div>
                <h3 class="display" style="font-size:clamp(1.5rem,2.5vw,2.2rem);margin-bottom:14px;">{{ __('Join Our Community') }}</h3>
                <p style="color:var(--parchment-60);font-size:.9rem;margin-bottom:28px;line-height:1.7;">{{ __('Get updates about events, programs, and announcements. Be part of something meaningful.') }}</p>

                <div style="display:flex;flex-direction:column;gap:10px;">
                    <a href="{{ route('contact') }}" class="btn btn-primary" style="justify-content:center;">{{ __('Contact Us') }}</a>
                    <a href="{{ route('library') }}" class="btn btn-ghost" style="justify-content:center;">{{ __('Explore Programs') }}</a>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:28px;padding-top:24px;border-top:1px solid rgba(255,255,255,.06);">
                    @foreach([
                        ['am' => 'ህጻናት', 'en' => __('Children'), 'count' => \App\Models\Member::where('member_type', 'Kids')->count()],
                        ['am' => 'አዳጊ',   'en' => __('Youth'),    'count' => \App\Models\Member::where('member_type', 'Youth')->count()],
                        ['am' => 'ወጣት',  'en' => __('Adults'),   'count' => \App\Models\Member::where('member_type', 'Adult')->count()],
                        ['am' => 'ጠቅላ',  'en' => __('Total'),    'count' => \App\Models\Member::count()],
                    ] as $prog)
                    <div style="padding:10px 12px;border-radius:8px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);text-align:center;">
                        <div class="am" style="font-size:.85rem;color:var(--gold);font-weight:600;">{{ $prog['am'] }}</div>
                        <div style="font-size:.72rem;color:var(--parchment-40);margin-top:2px;">{{ $prog['en'] }}: {{ $prog['count'] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</section>

@push('styles')
<style>
    @media(max-width:768px) {
        .faq-cta-grid { grid-template-columns: 1fr !important; gap: 36px !important; }
    }
</style>
@endpush