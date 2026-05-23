{{-- ═══════════════════════════════════════════════════════
     10.  FUNDRAISING — Skeleton → API loaded
═══════════════════════════════════════════════════════ --}}
<section id="fundraising" style="padding:80px 24px;background:var(--dark-950);position:relative;overflow:hidden;">
    <div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:600px;height:300px;border-radius:50%;background:var(--gold);filter:blur(130px);opacity:.03;pointer-events:none;"></div>

    <div style="max-width:1280px;margin:0 auto;position:relative;z-index:1;">
        <div style="text-align:center;margin-bottom:48px;">
            <div class="sec-label sr" style="justify-content:center;">{{ __('Support Our Mission') }}</div>
            <h2 class="display sr" style="font-size:clamp(1.8rem,3vw,2.8rem);margin-bottom:12px;">{{ __('Fundraising Progress') }}</h2>
        </div>

        <div id="fundraising-progress" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;">
            @for($i = 0; $i < 3; $i++)
            <div class="card" style="padding:24px;">
                <div class="skel" style="height:100px;margin-bottom:16px;"></div>
                <div class="skel" style="height:18px;width:70%;margin-bottom:10px;"></div>
                <div class="skel" style="height:6px;margin-bottom:8px;"></div>
                <div style="display:flex;justify-content:space-between;"><div class="skel" style="height:12px;width:40%;"></div><div class="skel" style="height:12px;width:20%;"></div></div>
            </div>
            @endfor
        </div>

        <div style="text-align:center;margin-top:36px;" class="sr">
            <a href="{{ route('fundraising.index') }}" class="btn btn-primary">{{ __('View All Campaigns') }}</a>
        </div>
    </div>
</section>

@push('scripts')
<script>
async function loadFundraising(){
    try {
        const res = await fetch('{{ route('fundraising.api') }}');
        const data = await res.json();
        const container = document.getElementById('fundraising-progress');

        if(!data.campaigns.length){
            container.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:48px 24px;">
                <svg class="tour-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px;" aria-hidden="true"><use href="#icon-giving" /></svg>
                <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--text-display);margin-bottom:8px;">{{ __('No Active Campaigns') }}</h3>
                <p style="color:var(--parchment-60);font-size:.85rem;">{{ __('Check back later for new fundraising campaigns.') }}</p>
            </div>`;
            return;
        }

        container.innerHTML = data.campaigns.slice(0,3).map(c => `
            <div class="card" style="overflow:hidden;padding:0;">
                <div style="height:80px;background:linear-gradient(135deg,rgba(26,68,247,.6),var(--overlay-80));display:flex;align-items:flex-end;padding:14px;position:relative;">
                    <span style="position:relative;font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;padding:4px 10px;border-radius:99px;background:rgba(243,186,21,.15);border:1px solid rgba(243,186,21,.25);color:var(--gold);">${c.status}</span>
                </div>
                <div style="padding:20px;">
                    <h3 style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:600;color:var(--text-display);margin-bottom:12px;">${c.campaign_name}</h3>
                    <div style="margin-bottom:6px;display:flex;justify-content:space-between;font-size:.8rem;">
                        <span style="color:var(--text-display);font-weight:600;">ETB ${Number(c.total_raised).toLocaleString()}</span>
                        <span style="color:var(--gold);">${c.progress_percentage}%</span>
                    </div>
                    <div class="prog-track"><div class="prog-fill" id="pf-${c.id}" style="width:0%;"></div></div>
                    <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--parchment-40);margin-top:6px;">
                        <span>Goal: ETB ${Number(c.target_amount).toLocaleString()}</span>
                        ${c.days_remaining!==null ? `<span>${c.days_remaining} days left</span>` : ''}
                    </div>
                </div>
            </div>`).join('');

        requestAnimationFrame(()=>{
            data.campaigns.slice(0,3).forEach(c=>{
                const el = document.getElementById('pf-'+c.id);
                if(el) setTimeout(()=>{ el.style.width = Math.min(100,c.progress_percentage)+'%'; }, 300);
            });
        });
    } catch(e) {
        document.getElementById('fundraising-progress').innerHTML = `
            <div style="grid-column:1/-1;text-align:center;padding:32px;color:var(--parchment-60);font-size:.85rem;">
                {{ __('Unable to load fundraising data.') }}
            </div>`;
    }
}
document.addEventListener('DOMContentLoaded', loadFundraising);
</script>
@endpush