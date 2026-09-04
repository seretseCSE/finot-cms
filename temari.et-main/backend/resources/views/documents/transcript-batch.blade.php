{{-- A class's transcripts as ONE official PDF: the same sheet as the
     single-student transcript, one per student, one per page. Bulk printing
     is how schools actually issue transcripts (a section at a time), so this
     must be the identical document — never a browser print of the web page.

     Two deliberate differences from the single sheet, both to keep the render
     inside Cloudflare Browser Rendering's one-shot budget:
       · the school logo is inlined ONCE as a CSS background instead of
         repeating its data URI on every page;
       · student photos are omitted (each would be its own inlined image) —
         the sheets carry the 4×4 cm frame for a physical photo, exactly like
         a transcript printed for a student without a photo on file. --}}
@extends('documents.layout')

@section('title', 'Transcripts — '.$label)

@section('content')
  <style>
    @page { size: A4 landscape; }
    /* One student per page; the last sheet must not emit a trailing blank. */
    .sheet { break-after: page; }
    .sheet:last-of-type { break-after: auto; }
    @if ($logo !== null)
      .school-logo {
        background-image: url('{{ $logo }}');
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
      }
    @endif
  </style>

  @foreach ($transcripts as $transcript)
    @include('documents.partials.transcript-sheet', [
      'transcript' => $transcript,
      'qr' => $qr,
      'logoClass' => $logo !== null ? 'school-logo' : null,
      'qrTitle' => 'Scan to verify this document',
      'qrNote' => 'Confirms the school and date this transcript was issued — issued through Temari.et.',
    ])
  @endforeach

  @include('documents.partials.fit-to-page')
@endsection
