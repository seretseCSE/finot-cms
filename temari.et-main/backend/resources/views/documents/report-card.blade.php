{{-- The official semester report card (single student). The sheet itself is
     rendered by the shared partial so this print and the class batch are
     pixel-identical; here the school logo travels inline on the card and the
     card owns the whole page. --}}
@extends('documents.layout')

@section('title', 'Report card — '.$card['student']['full_name'])

@section('content')
  <div class="cell" style="height: 296mm; overflow: hidden">
    @include('documents.partials.report-card-sheet', [
      'card' => $card,
      'qr' => $qr,
      'showSubjectRanks' => $show_subject_ranks ?? false,
      'density' => 'full',
      'logoUrl' => $card['school_logo_url'] ?? null,
    ])
  </div>

  @include('documents.partials.fit-to-cell')
@endsection
