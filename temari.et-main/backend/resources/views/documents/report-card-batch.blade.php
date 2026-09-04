{{-- A selection's semester report cards as ONE official PDF — the shared
     sheet partial per student. Three layouts, decided by the school's
     report_card_per_page setting:
       · 1 — one relaxed card per A4 page;
       · 2 — two cards stacked vertically, each exactly half the page;
       · 4 — four wallet cards in a 2×2 grid.
     Every sheet is a fixed 296mm grid and every cell clips, so a long
     subject list can never overflow into a neighbouring student. Dashed
     cut lines run between cells. The school logo is inlined ONCE as a
     shared CSS background (renderer budget), like the transcript batch. --}}
@extends('documents.layout')

@section('title', 'Report cards — '.$label)

@section('content')
  <style>
    .sheet { break-after: page; height: 296mm; display: grid; }
    .sheet:last-of-type { break-after: auto; }
    .sheet > .cell { overflow: hidden; min-height: 0; min-width: 0; }
    .sheet.duo { grid-template-rows: 1fr 1fr; }
    .sheet.duo > .cell:first-child { border-bottom: 1px dashed var(--border); }
    .sheet.quad { grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; }
    .sheet.quad > .cell:nth-child(odd) { border-right: 1px dashed var(--border); }
    .sheet.quad > .cell:nth-child(-n+2) { border-bottom: 1px dashed var(--border); }
    @if ($logo !== null)
      .school-logo {
        background-image: url('{{ $logo }}');
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
      }
    @endif
  </style>

  @php
    $perPage = in_array($per_page ?? 1, [1, 2, 4], true) ? $per_page : 1;
    $density = [1 => 'full', 2 => 'half', 4 => 'quarter'][$perPage];
    $layout = [1 => '', 2 => 'duo', 4 => 'quad'][$perPage];
  @endphp

  @foreach (collect($cards)->chunk($perPage) as $chunk)
    <div class="sheet {{ $layout }}">
      @foreach ($chunk as $card)
        <div class="cell">
          @include('documents.partials.report-card-sheet', [
            'card' => $card,
            'qr' => $qr,
            'showSubjectRanks' => $show_subject_ranks ?? false,
            'density' => $density,
            'logoUrl' => null,
            'logoClass' => $logo !== null ? 'school-logo' : null,
          ])
        </div>
      @endforeach
    </div>
  @endforeach

  @include('documents.partials.fit-to-cell')
@endsection
