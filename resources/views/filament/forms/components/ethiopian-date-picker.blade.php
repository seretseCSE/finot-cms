@php
use Illuminate\Support\Carbon;
@endphp

<x-text-input
    type="date"
    {{ $attributes }}
    @if($getValue())
        value="{{ $getValue() }}"
    @else
        value="{{ Carbon::now()->format('Y-m-d') }}"
    @endif
/>
