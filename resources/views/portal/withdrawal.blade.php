@extends('layouts.public')

@section('title', __('Withdrawal'))

@section('content')
<section class="ft-section py-12">
    <div style="max-width:640px;margin:0 auto;">
        <p><a href="{{ route('portal.home') }}">{{ __('Back') }}</a></p>
        <h1 class="text-3xl font-bold mb-6">{{ __('Request withdrawal') }}</h1>
        @if($existing)
            <p class="mb-4">{{ __('Current status') }}: <strong>{{ $existing->status->value }}</strong>
                <a href="{{ route('portal.withdrawal.print', $existing) }}" class="underline ml-2">{{ __('Print') }}</a>
            </p>
        @endif
        @if($enrollment)
            <form method="POST" action="{{ route('portal.withdrawal.apply') }}" class="space-y-4">
                @csrf
                <div>
                    <label>{{ __('Reason') }}</label>
                    <textarea name="reason" required minlength="10" class="w-full rounded bg-slate-900 border border-slate-700 p-3">{{ old('reason') }}</textarea>
                    @error('reason') <p class="text-red-400 text-sm">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label>{{ __('Destination (optional)') }}</label>
                    <input name="destination" value="{{ old('destination') }}" class="w-full rounded bg-slate-900 border border-slate-700 p-3">
                </div>
                <button class="btn btn-primary" type="submit">{{ __('Submit') }}</button>
            </form>
        @else
            <p>{{ __('You do not have an active enrollment.') }}</p>
        @endif
    </div>
</section>
@endsection
