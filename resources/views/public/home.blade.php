@extends('layouts.public')

@section('title', config('app.name'))

@section('content')

@include('public.partials.home.hero')
@include('public.partials.home.announcements')
@include('public.partials.home.service-info')
@include('public.partials.home.about-preview')
@include('public.partials.home.stats')
@include('public.partials.home.leadership')
@include('public.partials.home.programs')
@include('public.partials.home.services')
@include('public.partials.home.upcoming-events')
@include('public.partials.home.cta')
@include('public.partials.home.fundraising')
@include('public.partials.home.blog-posts')
@include('public.partials.home.library-resources')
@include('public.partials.home.faq-cta')

@endsection
