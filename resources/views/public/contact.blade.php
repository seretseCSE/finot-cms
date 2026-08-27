@extends('layouts.public')

@section('title', __('Contact Us'))

@section('content')

<x-public.page-hero
    :title="__('Get in Touch')"
    :subtitle="__('Have questions about our programs, events, or how to get involved? We are here to listen and guide you.')"
    :eyebrow="__('Reach Out')"
    :image="asset('images/unsplash/volunteer-helping.jpg')"
/>

{{-- ═══════════════════════════════════════════════════════
     2.  CONTACT GRID
     ═══════════════════════════════════════════════════════ --}}
<section class="ft-section">
    <div style="max-width:1200px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:clamp(40px, 8vw, 64px);position:relative;z-index:1;">
        
        {{-- Contact Info --}}
        <div class="sr-l">
            <h2 class="display" style="font-size:2rem;margin-bottom:32px;">{{ __('Contact Information') }}</h2>
            
            <div style="display:grid;gap:32px;">
                @foreach([
                    ['icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z', 'title' => __('Location'), 'val' => 'Addis Ababa, Ayertena', 'am' => 'አዲስ አበባ፣ አየርጤና'],
                    ['icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'title' => __('Email Address'), 'val' => 'info@finottsidik.org', 'am' => 'ኢሜል'],
                    ['icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', 'title' => __('Phone Number'), 'val' => '+251 911 123 456', 'am' => 'ስልክ ቁጥር'],
                    ['icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => __('Service Hours'), 'val' => 'Sun: 2:00-5:00 PM', 'am' => 'የአገልግሎት ሰዓት']
                ] as $item)
                <div style="display:flex;gap:20px;align-items:flex-start;">
                    <div style="width:48px;height:48px;border-radius:12px;background:var(--gold-dim);border:1px solid var(--gold-border);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="20" height="20" fill="none" stroke="var(--gold)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $item['icon'] }}"/></svg>
                    </div>
                    <div>
                        <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;color:var(--text-40);margin-bottom:4px;">{{ $item['title'] }}</div>
                        <div style="font-weight:600;color:var(--text-display);font-size:1.1rem;">{{ $item['val'] }}</div>
                        <div class="am" style="font-size:.85rem;color:var(--text-60);">{{ $item['am'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Form --}}
        <div class="sr-r">
            <div class="card" style="padding:clamp(24px, 5vw, 48px);background:rgba(255,255,255,.02);border-color:rgba(26,68,247,.15);">
                <h3 class="display" style="font-size:clamp(1.5rem, 4vw, 1.8rem);margin-bottom:32px;">{{ __('Send a Message') }}</h3>
                
                <form action="{{ route('contact.store') }}" method="POST" style="display:grid;gap:24px;">
                    @csrf
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;">
                    <div>
                        <label style="display:block;font-size:.8rem;color:var(--text-60);margin-bottom:8px;">{{ __('Full Name') }}</label>
                        <input type="text" name="name" required style="width:100%;background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:8px;padding:12px 16px;color:var(--text-main);outline:none;transition:border-color .2s;font-size:16px;" onfocus="this.style.borderColor='var(--blue-primary)'" onblur="this.style.borderColor='var(--border-subtle)'">
                    </div>
                    <div>
                        <label style="display:block;font-size:.8rem;color:var(--text-60);margin-bottom:8px;">{{ __('Email Address') }}</label>
                        <input type="email" name="email" required style="width:100%;background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:8px;padding:12px 16px;color:var(--text-main);outline:none;transition:border-color .2s;font-size:16px;" onfocus="this.style.borderColor='var(--blue-primary)'" onblur="this.style.borderColor='var(--border-subtle)'">
                    </div>
                </div>
                
                <div>
                    <label style="display:block;font-size:.8rem;color:var(--text-60);margin-bottom:8px;">{{ __('Subject') }}</label>
                    <select name="subject" style="width:100%;background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:8px;padding:12px 16px;color:var(--text-main);outline:none;cursor:pointer;font-size:16px;">
                        <option value="General Inquiry">{{ __('General Inquiry') }}</option>
                        <option value="Registration">{{ __('Program Registration') }}</option>
                        <option value="Volunteer">{{ __('Volunteering') }}</option>
                        <option value="Feedback">{{ __('Feedback') }}</option>
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:.8rem;color:var(--text-60);margin-bottom:8px;">{{ __('Message') }}</label>
                    <textarea name="message" rows="5" required style="width:100%;background:var(--bg-800);border:1px solid var(--border-subtle);border-radius:8px;padding:12px 16px;color:var(--text-main);outline:none;resize:none;transition:border-color .2s;font-size:16px;" onfocus="this.style.borderColor='var(--blue-primary)'" onblur="this.style.borderColor='var(--border-subtle)'"></textarea>
                </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:1rem;">
                        {{ __('Send Message') }}
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </form>
            </div>
        </div>

    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     3.  MAP PLACEHOLDER
     ═══════════════════════════════════════════════════════ --}}
<section style="height:clamp(300px, 50vh, 500px);background:var(--dark-950);position:relative;overflow:hidden;">
    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d563.6919225342599!2d38.690934976793315!3d8.99371953474218!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x164b87a03c2140e5%3A0x53abbfcd9c417317!2sfenote%20sedq%20sunday%20school!5e1!3m2!1sen!2set!4v1777196727633!5m2!1sen!2set" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
</section>

@endsection
