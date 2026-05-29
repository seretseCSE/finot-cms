@extends('layouts.sunday-school')

@section('title', 'Finote Tsidik Sunday School')

@section('content')

@include('public.partials.sunday-school.hero')
@include('public.partials.sunday-school.about')
@include('public.partials.sunday-school.programs')
@include('public.partials.sunday-school.stats')
@include('public.partials.sunday-school.leadership')
@include('public.partials.sunday-school.events')
@include('public.partials.sunday-school.cta')

@endsection
