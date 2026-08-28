@extends('layouts.public')

@section('title', __('My Attendance'))

@section('content')
<section class="ft-section py-12">
    <div style="max-width:960px;margin:0 auto;">
        <p><a href="{{ route('portal.home') }}">{{ __('Back') }}</a></p>
        <h1 class="text-3xl font-bold mb-6">{{ __('My Attendance') }}</h1>
        @forelse($records as $record)
            <div class="card p-4 mb-3 flex justify-between">
                <span>{{ $record->event_date?->toDateString() }} · {{ $record->event_type }}</span>
                <span>{{ $record->status }}</span>
            </div>
        @empty
            <p>{{ __('No attendance records yet.') }}</p>
        @endforelse
    </div>
</section>
@endsection
