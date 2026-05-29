@extends('layouts.public')

@section('title', __('Register for Tour') . ' - ' . $tour->place)

@section('content')

{{-- ═══════════════════════════════════════════════════════
     1.  HERO — Registration Header
     ═══════════════════════════════════════════════════════ --}}
<section style="position:relative;padding:140px 24px 80px;background:var(--dark-950);overflow:hidden;">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(5,10,28,.98) 0%,rgba(26,68,247,.1) 50%,rgba(5,10,28,.98) 100%);"></div>
    <div class="tilet" style="position:absolute;inset:0;opacity:.4;"></div>

    <div style="position:relative;z-index:2;max-width:1280px;margin:0 auto;text-align:center;">
        <div class="sec-label sr" style="justify-content:center;">{{ __('Tour Registration') }}</div>
        <h1 class="display sr" style="font-size:clamp(2rem,4vw,3.2rem);margin-bottom:24px;line-height:1.2;">
            {{ __('Join Us at') }}
            <span style="color:var(--gold);">{{ $tour->place }}</span>
        </h1>
        <div class="sr" style="display:flex;align-items:center;justify-content:center;gap:24px;font-size:.9rem;color:var(--parchment-40);">
            <div style="display:flex;align-items:center;gap:8px;"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> {{ $tour->ethiopian_date }}</div>
            <div style="display:flex;align-items:center;gap:8px;"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> {{ $tour->start_time }}</div>
            <div style="display:flex;align-items:center;gap:8px;"><svg width="14" height="14" fill="none" stroke="var(--gold)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg> <span style="color:var(--gold);font-weight:700;">{{ $tour->formatted_cost }}</span></div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════
     2.  REGISTRATION FORM
     ═══════════════════════════════════════════════════════ --}}
<section style="padding:40px 24px 100px;background:var(--dark-900);">
    <div style="max-width:800px;margin:0 auto;">
        
        <div class="card sr" style="padding:48px;border-color:rgba(255,255,255,.05);">
            <form action="{{ route('tour.register.submit', $tour->id) }}" method="POST" enctype="multipart/form-data" id="registrationForm">
                @csrf

                @if(session('success'))
                    <div style="padding:16px 24px;background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.2);border-radius:10px;color:#86efac;margin-bottom:32px;font-size:.9rem;display:flex;align-items:center;gap:12px;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div style="padding:16px 24px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.2);border-radius:10px;color:#fca5a5;margin-bottom:32px;font-size:.9rem;">
                        <div style="font-weight:700;margin-bottom:8px;">{{ __('Please fix the following errors:') }}</div>
                        <ul style="list-style:disc;margin-left:20px;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div style="display:grid;gap:24px;">
                    {{-- Primary Name --}}
                    <div>
                        <label for="full_name" style="display:block;font-size:.85rem;color:var(--parchment-40);margin-bottom:8px;">{{ __('Primary Contact Name') }} <span style="color:var(--gold);">*</span></label>
                        <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required style="width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:12px 16px;color:#fff;outline:none;" placeholder="{{ __('Enter your full name') }}">
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label for="phone" style="display:block;font-size:.85rem;color:var(--parchment-40);margin-bottom:8px;">{{ __('Phone Number') }} <span style="color:var(--gold);">*</span></label>
                        <div style="display:flex;">
                            <span style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-right:none;border-radius:10px 0 0 10px;padding:12px 16px;color:var(--parchment-40);font-size:.9rem;">+251</span>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required pattern="[0-9]{9}" maxlength="9" style="flex:1;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);border-radius:0 10px 10px 0;padding:12px 16px;color:#fff;outline:none;" placeholder="912345678">
                        </div>
                        <p style="font-size:.7rem;color:var(--parchment-40);margin-top:6px;">{{ __('Enter 9 digits after +251') }}</p>
                    </div>

                    {{-- Passenger Count --}}
                    <div>
                        <label for="passenger_count" style="display:block;font-size:.85rem;color:var(--parchment-40);margin-bottom:8px;">{{ __('Number of Passengers') }} <span style="color:var(--gold);">*</span></label>
                        <input type="number" id="passenger_count" name="passenger_count" value="{{ old('passenger_count', 1) }}" required min="1" max="20" style="width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:12px 16px;color:#fff;outline:none;">
                    </div>

                    {{-- Additional Passengers --}}
                    <div id="additionalPassengers" style="display:grid;gap:16px;"></div>

                    {{-- Receipt --}}
                    <div>
                        <label for="receipt_image" style="display:block;font-size:.85rem;color:var(--parchment-40);margin-bottom:8px;">{{ __('Receipt Upload') }} ({{ __('Optional') }})</label>
                        <input type="file" id="receipt_image" name="receipt_image" accept=".pdf,.jpg,.jpeg,.png" style="width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:12px 16px;color:var(--parchment-40);outline:none;">
                        <p style="font-size:.7rem;color:var(--parchment-40);margin-top:6px;">{{ __('PDF, JPG, PNG files only (Max 5MB)') }}</p>
                    </div>

                    <div style="position:absolute;left:-9999px;visibility:hidden;" aria-hidden="true">
                        <input type="text" name="honeypot" value="{{ old('honeypot') }}" tabindex="-1" autocomplete="off">
                    </div>

                    <div style="margin-top:20px;display:flex;justify-content:space-between;align-items:center;">
                        <a href="{{ route('tours.index') }}" class="btn btn-ghost" style="padding:0;font-size:.85rem;color:var(--parchment-60);">← {{ __('Back to Tours') }}</a>
                        <button type="submit" class="btn btn-primary" style="padding:14px 40px;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('Submit Registration') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Important Info --}}
        <div class="sr" style="margin-top:40px;padding:32px;background:rgba(26,68,247,.05);border:1px solid rgba(26,68,247,.1);border-radius:15px;">
            <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--blue-400);margin-bottom:16px;">{{ __('Important Information') }}</h3>
            <ul style="display:grid;gap:12px;">
                @foreach([
                    __('Your registration will be reviewed and confirmed by the tour coordinator.'),
                    __('You will receive a confirmation reference number after submission.'),
                    __('Phone numbers can only be registered once per tour.')
                ] as $info)
                    <li style="display:flex;align-items:flex-start;gap:12px;font-size:.85rem;color:var(--parchment-60);">
                        <svg width="16" height="16" fill="var(--blue-400)" viewBox="0 0 20 20" style="margin-top:2px;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        {{ $info }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const passengerCountInput = document.getElementById('passenger_count');
        const additionalPassengersDiv = document.getElementById('additionalPassengers');

        function updateAdditionalPassengers() {
            const count = parseInt(passengerCountInput.value) || 1;
            const currentFields = additionalPassengersDiv.querySelectorAll('.additional-passenger').length;

            // Remove excess fields
            if (currentFields > count - 1) {
                const fields = additionalPassengersDiv.querySelectorAll('.additional-passenger');
                for (let i = count - 1; i < currentFields; i++) {
                    if (fields[i]) fields[i].remove();
                }
            }

            // Add needed fields
            for (let i = currentFields + 1; i < count; i++) {
                const div = document.createElement('div');
                div.className = 'additional-passenger';
                div.innerHTML = `
                    <label style="display:block;font-size:.85rem;color:var(--parchment-40);margin-bottom:8px;margin-top:8px;">{{ __('Passenger') }} ${i + 1} {{ __('Name') }} <span style="color:var(--gold);">*</span></label>
                    <input type="text" name="passenger_names[]" required style="width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:12px 16px;color:#fff;outline:none;" placeholder="{{ __('Enter passenger name') }}">
                    
                    <label style="display:block;font-size:.85rem;color:var(--parchment-40);margin-bottom:8px;margin-top:8px;">{{ __('Passenger') }} ${i + 1} {{ __('Phone') }} ({{ __('Optional') }})</label>
                    <div style="display:flex;">
                        <span style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-right:none;border-radius:10px 0 0 10px;padding:12px 16px;color:var(--parchment-40);font-size:.9rem;">+251</span>
                        <input type="tel" name="passenger_phones[]" pattern="[0-9]{9}" maxlength="9" style="flex:1;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);border-radius:0 10px 10px 0;padding:12px 16px;color:#fff;outline:none;" placeholder="912345678">
                    </div>
                    <p style="font-size:.7rem;color:var(--parchment-40);margin-top:6px;">{{ __('Enter 9 digits after +251 (optional)') }}</p>
                `;
                additionalPassengersDiv.appendChild(div);
            }
        }

        passengerCountInput.addEventListener('input', updateAdditionalPassengers);
        updateAdditionalPassengers();
    });
</script>

@endsection
