{{-- The official roster sheet — mirror of components/grading/roster-matrix.tsx
     (semester) and yearly-roster.tsx (yearly). Frozen student_term_results laid
     out as the classic Ethiopian grid: students × subjects with total, average
     and rank. Wraps in the standard document card with the school header and a
     verification QR. Landscape A4; flows across pages for large registers. --}}
@extends('documents.layout')

@section('title', ($scope === 'year' ? 'Yearly roster' : 'Semester roster').' — '.($scope_label ?? ''))

@php
  $columns = $roster['data']['columns'];
  $computedAt = $roster['meta']['computed_at'] ?? null;

  // Trim trailing zeros: 84.50 → 84.5, 84.00 → 84.
  $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

  $cellBorder = 'border:1px solid var(--border);';
  $th = $cellBorder.' padding:5px 6px; text-align:center; font-weight:600; font-size:11px;';
  $td = $cellBorder.' padding:4px 6px; text-align:center;';

  $rankText = fn ($rank, $rankOf) => $rank === null
      ? '—'
      : $rank.($rankOf !== null ? ' / '.$rankOf : '');

  // Flatten a frozen score map ({subject_id => {total, letter, …}}) to plain
  // per-subject totals ({subject_id => number|null}) — the uniform shape every
  // roster line carries, so a term row and a computed mean row render alike.
  $flatScores = function (array $scores) use ($columns): array {
      $out = [];
      foreach ($columns as $col) {
          $key = (string) $col['subject_id'];
          $out[$key] = isset($scores[$key]['total']) ? (float) $scores[$key]['total'] : null;
      }

      return $out;
  };

  // Per-subject mean of the available totals across a set of flat score maps —
  // mirrors meanScores() in yearly-roster.tsx (blank when a subject is absent).
  $meanScores = function (array $flatMaps) use ($columns): array {
      $out = [];
      foreach ($columns as $col) {
          $key = (string) $col['subject_id'];
          $totals = array_values(array_filter(
              array_map(fn ($m) => $m[$key] ?? null, $flatMaps),
              fn ($v) => $v !== null,
          ));
          $out[$key] = $totals === [] ? null : round(array_sum($totals) / count($totals), 2);
      }

      return $out;
  };
@endphp

@section('content')
  <style>
    @page { size: A4 landscape; margin: 14mm 12mm; }
    /* Repeat the header on every printed page; never split a student row. */
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
  </style>

  <article style="padding:0">
    {{-- school header --}}
    <div class="card-header" style="padding-bottom:16px; margin-bottom:16px">
      <div>
        <h2 class="font-display" style="font-size:20px; font-weight:700">{{ $school_name ?? 'Roster' }}</h2>
        <p class="muted" style="font-size:13px">{{ $branch_name ?? 'All branches' }}</p>
      </div>
      <div style="text-align:right">
        @include('documents.partials.brand')
        <p class="muted" style="margin-top:6px; font-size:12px">
          {{ $scope === 'year' ? 'Yearly roster' : 'Semester roster' }}
        </p>
      </div>
    </div>

    <div style="display:flex; align-items:baseline; justify-content:space-between; gap:16px; margin-bottom:12px">
      <h3 class="font-display" style="font-size:15px; font-weight:600">
        {{ $scope_label ?? 'All grades' }}
      </h3>
      <p class="muted" style="font-size:12px">{{ $period_label }}</p>
    </div>

    @if ($scope === 'year')
      @php $students = $roster['data']['students']; @endphp

      @if ($students === [])
        <p class="muted" style="padding:40px 0; text-align:center; font-size:14px">No frozen results for this selection.</p>
      @else
        @php
          $terms = collect($roster['meta']['terms']);
          $hasSemesterGroups = (bool) ($roster['meta']['has_semester_groups'] ?? false);
          // Quarter terms grouped by their semester tag (last quarter closes it).
          $groups = $terms->filter(fn ($t) => $t['is_quarter'] && $t['semester'] !== null)->groupBy('semester');
        @endphp

        <table style="font-size:11px">
          <thead>
            <tr>
              <th style="{{ $th }} text-align:left; width:180px">Student</th>
              <th style="{{ $th }} text-align:left; width:96px">Period</th>
              @foreach ($columns as $col)
                <th style="{{ $th }}" title="{{ $col['name'] }}">{{ $col['code'] ?? $col['name'] }}</th>
              @endforeach
              <th style="{{ $th }}">Total</th>
              <th style="{{ $th }}">Avg.</th>
              <th style="{{ $th }}">Rank</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($students as $student)
              @php
                // Flat per-term totals keyed by term id — every line is scalar.
                $flatByTerm = collect($student['terms'])
                    ->mapWithKeys(fn ($line) => [$line['term_id'] => $flatScores($line['scores'])]);
                $byTerm = collect($student['terms'])->keyBy('term_id');
                $lines = [];
                foreach ($terms as $t) {
                    $line = $byTerm->get($t['id']);
                    if ($line !== null) {
                        $lines[] = ['kind' => 'term', 'label' => $t['name'], 'scores' => $flatByTerm[$t['id']],
                            'total' => $line['total'], 'average' => $line['average'], 'rank' => $line['rank'], 'rank_of' => $line['rank_of']];
                    }
                    // Close the semester after its last present quarter.
                    if ($hasSemesterGroups && $t['is_quarter'] && $t['semester'] !== null) {
                        $group = $groups->get($t['semester']) ?? collect();
                        if ($group->isNotEmpty() && $group->last()['id'] === $t['id']) {
                            $present = $group->map(fn ($g) => $flatByTerm->get($g['id']))->filter()->values()->all();
                            if ($present !== []) {
                                $avg = collect($student['semesters'])->firstWhere('semester', $t['semester'])['average'] ?? null;
                                $lines[] = ['kind' => 'semester', 'label' => 'Semester '.$t['semester'].' avg.',
                                    'scores' => $meanScores($present), 'total' => null, 'average' => $avg, 'rank' => null, 'rank_of' => null];
                            }
                        }
                    }
                }
                $lines[] = ['kind' => 'year', 'label' => 'Year avg.', 'scores' => $meanScores($flatByTerm->values()->all()),
                    'total' => null, 'average' => $student['year']['average'], 'rank' => $student['year']['rank'], 'rank_of' => $student['year']['rank_of']];
                $rowCount = count($lines);
              @endphp

              @foreach ($lines as $i => $line)
                @php
                  $tint = $line['kind'] === 'year' ? 'background:var(--muted);'
                      : ($line['kind'] === 'semester' ? 'background:oklch(0.958 0.006 110 / 0.55);' : '');
                  $weight = $line['kind'] === 'term' ? '' : 'font-weight:600;';
                @endphp
                <tr style="{{ $tint }}">
                  @if ($i === 0)
                    <td rowspan="{{ $rowCount }}" style="{{ $cellBorder }} padding:4px 8px; text-align:left; vertical-align:top">
                      <div style="font-weight:600">{{ $student['full_name'] ?? '—' }}</div>
                      <div class="muted" style="font-size:10px">
                        {{ collect([$show_section ? ($student['section_name'] ?? null) : null, $student['public_id'] ?? null])->filter()->implode(' · ') }}
                      </div>
                    </td>
                  @endif
                  <td style="{{ $cellBorder }} padding:4px 6px; text-align:left; {{ $line['kind'] === 'term' ? 'color:var(--muted-foreground);' : $weight }}">{{ $line['label'] }}</td>
                  @foreach ($columns as $col)
                    <td class="tnum" style="{{ $td }} {{ $weight }}">{{ $fmt($line['scores'][(string) $col['subject_id']] ?? null) }}</td>
                  @endforeach
                  <td class="tnum" style="{{ $td }}">{{ $fmt($line['total']) }}</td>
                  <td class="tnum" style="{{ $td }} {{ $line['kind'] === 'term' ? '' : 'font-weight:700;' }}">{{ $fmt($line['average']) }}</td>
                  <td class="tnum" style="{{ $td }}">{{ $rankText($line['rank'], $line['rank_of']) }}</td>
                </tr>
              @endforeach
            @endforeach
          </tbody>
        </table>
      @endif
    @else
      @php $rows = $roster['data']['rows']; @endphp

      @if ($rows === [])
        <p class="muted" style="padding:40px 0; text-align:center; font-size:14px">No frozen results for this selection.</p>
      @else
        <table style="font-size:11px">
          <thead>
            <tr>
              <th style="{{ $th }}; width:28px">#</th>
              <th style="{{ $th }} text-align:left">Student</th>
              <th style="{{ $th }} text-align:left; width:96px">ID</th>
              @if ($show_section)
                <th style="{{ $th }} text-align:left; width:96px">Section</th>
              @endif
              @foreach ($columns as $col)
                <th style="{{ $th }}" title="{{ $col['name'] }}">{{ $col['code'] ?? $col['name'] }}</th>
              @endforeach
              <th style="{{ $th }}">Total</th>
              <th style="{{ $th }}">Avg.</th>
              <th style="{{ $th }}">Rank</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $i => $row)
              <tr>
                <td class="tnum" style="{{ $td }}">{{ $i + 1 }}</td>
                <td style="{{ $cellBorder }} padding:4px 6px; text-align:left; font-weight:500">{{ $row['full_name'] ?? '—' }}</td>
                <td class="tnum" style="{{ $cellBorder }} padding:4px 6px; text-align:left">{{ $row['public_id'] ?? '—' }}</td>
                @if ($show_section)
                  <td style="{{ $cellBorder }} padding:4px 6px; text-align:left">{{ $row['section_name'] ?? '—' }}</td>
                @endif
                @foreach ($columns as $col)
                  @php $cell = $row['scores'][(string) $col['subject_id']] ?? null; @endphp
                  <td class="tnum" style="{{ $td }} {{ $cell !== null && ($cell['is_passing'] ?? true) === false ? 'color:var(--destructive);' : '' }}">
                    {{ $cell === null ? '—' : $fmt($cell['total'] ?? null) }}
                  </td>
                @endforeach
                <td class="tnum" style="{{ $td }}">{{ $fmt($row['total']) }}</td>
                <td class="tnum" style="{{ $td }} font-weight:600">{{ $fmt($row['average']) }}</td>
                <td class="tnum" style="{{ $td }}">{{ $rankText($row['rank'], $row['rank_of'] ?? null) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    @endif

    {{-- verification footer --}}
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; border-top:1px solid var(--border); margin-top:24px; padding-top:16px">
      <p class="muted" style="max-width:520px; font-size:11px; line-height:1.6">
        Marks are read from the frozen term results — they always match the issued report cards.
        @if ($computedAt)
          Frozen {{ dualDate($computedAt) }}.
        @endif
        Scan the QR code to verify this roster on Temari.et.
      </p>
      <img src="{{ $qr }}" alt="Verification QR code" style="width:88px; height:88px; flex-shrink:0">
    </div>
  </article>
@endsection
