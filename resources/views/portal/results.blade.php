@extends('layouts.public')

@section('title', __('My Results'))

@section('content')
<section class="ft-section py-12">
    <div style="max-width:960px;margin:0 auto;">
        <p><a href="{{ route('portal.home') }}">{{ __('Back') }}</a></p>
        <h1 class="text-3xl font-bold mb-6">{{ __('My Results') }}</h1>
        @forelse($items as $item)
            <div class="card p-4 mb-3">
                <div class="font-semibold">{{ $item->marklist?->subject?->name }} · {{ $item->marklist?->term?->name }}</div>
                <div class="text-sm text-slate-400">{{ __('Conduct') }}: {{ $item->conduct?->value ?? '—' }}
                    · {{ __('Memorization') }}: {{ $item->memorization?->value ?? '—' }}
                    · {{ __('Participation') }}: {{ $item->participation?->value ?? '—' }}</div>
            </div>
        @empty
            <p>{{ __('No approved results yet.') }}</p>
        @endforelse
    </div>
</section>
@endsection
