@extends('filament-panels::page')

@push('scripts')
<script src="{{ asset('js/offline/attendance.js') }}" defer></script>
<script>
    window.authUserId = {{ auth()->id() }};
</script>
@endpush

@section('header')
    <h2 class="filament-page-heading">
        {{ __('Welcome, :name', ['name' => filament()->auth()->user()->name]) }}
    </h2>
@endsection

@section('content')
    <div class="space-y-6">
        {{ $this->getWidgets() }}
    </div>
@endsection
