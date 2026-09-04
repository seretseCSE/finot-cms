{{-- The official single-student transcript. The sheet itself lives in
     partials/transcript-sheet.blade.php — shared with the batch print, so the
     one-student PDF and a whole class's stack are always the same document. --}}
@extends('documents.layout')

@section('title', 'Transcript — '.$transcript['student']['full_name'])

@section('content')
  <style>@page { size: A4 landscape; }</style>

  @include('documents.partials.transcript-sheet', ['transcript' => $transcript, 'qr' => $qr])

  @include('documents.partials.fit-to-page')
@endsection
