{{-- ONE transcript sheet — mirror of components/grading/transcript-article.tsx.
     Shared by the single transcript PDF and the batch (one sheet per student),
     so a template change lands on both at once.

     Variables:
       $transcript  the transcript payload (StudentReportService::transcript)
       $qr          QR data URI, or null to omit the verification strip
       $qrTitle     the line printed beside the QR
       $qrNote      the small print under it
       $logoClass   CSS class carrying the school logo as a background image
                    (batch sheets share ONE inlined logo instead of repeating
                    the data URI on every page); null = use the payload's
                    logo_url <img>. --}}
@php
  $qr ??= null;
  $qrTitle ??= 'Scan to open this transcript online';
  $qrNote ??= 'The QR always shows the authoritative record — issued through Temari.et.';
  $logoClass ??= null;

  $student = $transcript['student'];
  $issuer = $transcript['issued_by'] ?? null;
  $years = collect($transcript['years']);
  $isPartial = (bool) ($transcript['is_partial'] ?? false);

  // Trim trailing zeros: 84.50 → 84.5, 84.00 → 84.
  $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

  // Years recorded at ANOTHER school than the issuing one carry a footnote —
  // the honest way to present transfer history (ADR-017).
  $footnotes = [];
  foreach ($years as $year) {
      if ($issuer !== null && $year['school_name'] !== null && $year['school_name'] !== $issuer['school_name']) {
          $key = $year['school_name'].'·'.($year['branch_name'] ?? '');
          $footnotes[$key] ??= [
              'n' => count($footnotes) + 1,
              'school' => $year['school_name'],
              'branch' => $year['branch_name'],
          ];
      }
  }
  $footnoteFor = function (array $year) use ($issuer, $footnotes): ?int {
      if ($issuer === null || $year['school_name'] === null || $year['school_name'] === $issuer['school_name']) {
          return null;
      }

      return $footnotes[$year['school_name'].'·'.($year['branch_name'] ?? '')]['n'] ?? null;
  };

  // Chunk years into grid blocks so the sheet never overflows: a year costs
  // its term columns + one Avg column; a block holds at most 12 value columns
  // (4 two-semester years side by side, like the paper transcripts).
  $chunks = [];
  $current = [];
  $cols = 0;
  foreach ($years as $year) {
      $cost = count($year['terms']) + 1;
      if ($current !== [] && $cols + $cost > 12) {
          $chunks[] = $current;
          $current = [];
          $cols = 0;
      }
      $current[] = $year;
      $cols += $cost;
  }
  if ($current !== []) {
      $chunks[] = $current;
  }

  $coveredGrades = $years->pluck('grade_level')->filter()->unique()->implode(', ');

  $cellBorder = 'border:1px solid var(--border);';
@endphp

<article class="card sheet">
  {{-- masthead: school logo · issuing school · student photo --}}
  <header style="display:flex; align-items:center; gap:24px; border-bottom:2px solid var(--foreground); padding-bottom:14px">
    {{-- Side columns share a width so the school name stays centered. --}}
    <div style="width:4cm; flex-shrink:0">
      @if ($logoClass !== null)
        <div class="{{ $logoClass }}" style="width:84px; height:84px"></div>
      @elseif ($issuer !== null && ! empty($issuer['logo_url']))
        <img src="{{ $issuer['logo_url'] }}" alt="" style="width:84px; height:84px; object-fit:contain">
      @endif
    </div>
    <div style="flex:1; min-width:0; text-align:center">
      <h1 class="font-display" style="font-size:20px; font-weight:700">{{ $issuer['school_name'] ?? 'Student transcript' }}</h1>
      @php
        $contactLine = collect([
            $issuer['branch_name'] ?? null,
            $issuer['address'] ?? null,
            ! empty($issuer['phone']) ? '☎ '.$issuer['phone'] : null,
        ])->filter()->implode(' · ');
      @endphp
      @if ($contactLine !== '')
        <p class="muted" style="font-size:12px">{{ $contactLine }}</p>
      @endif
      <p style="margin-top:4px; font-size:12px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase">Official student transcript</p>
      @if ($isPartial)
        <p style="margin-top:2px; font-size:11px; font-weight:600; color:var(--destructive)">
          Partial transcript — {{ $coveredGrades !== '' ? $coveredGrades : 'selected years' }} only
        </p>
      @endif
    </div>
    <div style="width:4cm; flex-shrink:0; display:flex; justify-content:flex-end">
      @if (! empty($student['photo_url']))
        <img src="{{ $student['photo_url'] }}" alt="" style="width:4cm; height:4cm; object-fit:cover; border:1px solid var(--border); border-radius:6px">
      @else
        {{-- 4×4 cm dashed frame so the school can affix a physical photo. --}}
        <div style="width:4cm; height:4cm; border:1.5px dashed var(--border); border-radius:6px; display:flex; align-items:center; justify-content:center">
          <span class="muted" style="font-size:10px; letter-spacing:0.05em; text-transform:uppercase">Photo</span>
        </div>
      @endif
    </div>
  </header>

  {{-- student identity strip --}}
  <dl style="margin-top:14px; display:grid; grid-template-columns:2fr 1fr 1fr 1fr; column-gap:24px; font-size:13px">
    @foreach ([
        ['Student\'s name', $student['full_name']],
        ['Student ID', $student['public_id'] ?? '—'],
        ['Sex', $student['gender'] !== null ? ucfirst((string) $student['gender']) : '—'],
        ['Date of birth', dualDate($student['date_of_birth'] ?? null)],
    ] as [$label, $value])
      <div>
        <dt class="muted" style="font-size:11px">{{ $label }}</dt>
        <dd style="font-weight:600">{{ $value }}</dd>
      </div>
    @endforeach
  </dl>

  @if ($years->isEmpty())
    <p class="muted" style="padding:40px 0; text-align:center; font-size:14px">
      No frozen results yet — the transcript fills in as semesters close.
    </p>
  @endif

  @foreach ($chunks as $chunk)
    @php
      // Subject-union rows for THIS block (insertion = chronological order).
      $subjects = [];
      foreach ($chunk as $year) {
          foreach ($year['terms'] as $term) {
              foreach ($term['subjects'] ?? [] as $line) {
                  $subjects[$line['subject_id']] ??= $line['name'];
              }
          }
      }
    @endphp

    <table style="margin-top:16px; font-size:12px">
      <thead>
        <tr>
          <th rowspan="2" style="{{ $cellBorder }} padding:5px 8px; text-align:left; font-weight:600; width:170px">Subject</th>
          @foreach ($chunk as $year)
            @php $note = $footnoteFor($year); @endphp
            <th colspan="{{ count($year['terms']) + 1 }}" style="{{ $cellBorder }} padding:5px 6px; text-align:center; font-weight:600">
              {{ $year['academic_year'] ?? '—' }} · {{ $year['grade_level'] ?? '—' }}@if ($note !== null)<sup>{{ $note }}</sup>@endif
            </th>
          @endforeach
        </tr>
        <tr class="muted" style="font-size:11px">
          @foreach ($chunk as $year)
            @foreach ($year['terms'] as $term)
              <th style="{{ $cellBorder }} padding:4px 4px; text-align:center; font-weight:500">{{ $term['term_name'] ?? '—' }}</th>
            @endforeach
            <th style="{{ $cellBorder }} padding:4px 4px; text-align:center; font-weight:600">Avg.</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach ($subjects as $subjectId => $name)
          <tr>
            <td style="{{ $cellBorder }} padding:3px 8px">{{ $name }}</td>
            @foreach ($chunk as $year)
              @php $totals = []; @endphp
              @foreach ($year['terms'] as $term)
                @php
                  $line = collect($term['subjects'] ?? [])->firstWhere('subject_id', $subjectId);
                  if ($line !== null && $line['total'] !== null) {
                      $totals[] = (float) $line['total'];
                  }
                  $letters = ($term['grading']['display'] ?? null) === 'letter';
                  $failing = $line !== null && ($line['is_passing'] ?? true) === false;
                @endphp
                <td class="tnum" style="{{ $cellBorder }} padding:3px 4px; text-align:center; {{ $failing ? 'color:var(--destructive)' : '' }}">
                  {{ $line ? ($letters ? ($line['letter'] ?? '—') : $fmt($line['total'] ?? null)) : '—' }}
                </td>
              @endforeach
              <td class="tnum" style="{{ $cellBorder }} padding:3px 4px; text-align:center; font-weight:600">
                {{ $totals === [] ? '—' : $fmt(round(array_sum($totals) / count($totals), 2)) }}
              </td>
            @endforeach
          </tr>
        @endforeach

        {{-- footer rows: the year-summary lines every Ethiopian transcript carries --}}
        <tr style="font-weight:600">
          <td style="{{ $cellBorder }} padding:4px 8px">Total</td>
          @foreach ($chunk as $year)
            @foreach ($year['terms'] as $term)
              <td class="tnum" style="{{ $cellBorder }} padding:4px 4px; text-align:center">{{ $fmt($term['total']) }}</td>
            @endforeach
            <td style="{{ $cellBorder }}"></td>
          @endforeach
        </tr>
        <tr style="font-weight:600">
          <td style="{{ $cellBorder }} padding:4px 8px">Average</td>
          @foreach ($chunk as $year)
            @foreach ($year['terms'] as $term)
              <td class="tnum" style="{{ $cellBorder }} padding:4px 4px; text-align:center">{{ $fmt($term['average']) }}</td>
            @endforeach
            <td class="tnum" style="{{ $cellBorder }} padding:4px 4px; text-align:center">{{ $fmt($year['annual_average']) }}</td>
          @endforeach
        </tr>
        <tr>
          <td style="{{ $cellBorder }} padding:4px 8px; font-weight:600">Rank</td>
          @foreach ($chunk as $year)
            @foreach ($year['terms'] as $term)
              <td class="tnum" style="{{ $cellBorder }} padding:4px 4px; text-align:center">
                {{ $term['rank'] !== null ? $term['rank'].($term['rank_of'] !== null ? ' / '.$term['rank_of'] : '') : '—' }}
              </td>
            @endforeach
            <td style="{{ $cellBorder }}"></td>
          @endforeach
        </tr>
        <tr>
          <td style="{{ $cellBorder }} padding:4px 8px; font-weight:600">Conduct</td>
          @foreach ($chunk as $year)
            @foreach ($year['terms'] as $term)
              <td style="{{ $cellBorder }} padding:4px 4px; text-align:center">{{ $term['conduct'] ?? '—' }}</td>
            @endforeach
            <td style="{{ $cellBorder }}"></td>
          @endforeach
        </tr>
        <tr>
          <td style="{{ $cellBorder }} padding:4px 8px; font-weight:600">Days absent</td>
          @foreach ($chunk as $year)
            @foreach ($year['terms'] as $term)
              <td class="tnum" style="{{ $cellBorder }} padding:4px 4px; text-align:center">{{ $term['absence_days'] ?? '—' }}</td>
            @endforeach
            <td style="{{ $cellBorder }}"></td>
          @endforeach
        </tr>
        {{-- Only rendered when at least one year has a recorded outcome —
             an all-empty Status row is noise, not information. --}}
        @if (collect($chunk)->contains(fn ($year) => ($year['outcome'] ?? null) !== null))
          <tr>
            <td style="{{ $cellBorder }} padding:4px 8px; font-weight:600">Status</td>
            @foreach ($chunk as $year)
              <td colspan="{{ count($year['terms']) + 1 }}" style="{{ $cellBorder }} padding:4px 4px; text-align:center; font-weight:600">
                @if (($year['outcome'] ?? null) !== null)
                  {{ $year['outcome']['decision'] === 'promoted' && $year['outcome']['to_grade_level'] !== null
                      ? 'Promoted to '.$year['outcome']['to_grade_level']
                      : $year['outcome']['label'] }}
                @else
                  —
                @endif
              </td>
            @endforeach
          </tr>
        @endif
      </tbody>
    </table>
  @endforeach

  @if ($footnotes !== [])
    <div style="margin-top:8px; font-size:11px" class="muted">
      @foreach ($footnotes as $note)
        <p><sup>{{ $note['n'] }}</sup> Recorded at {{ $note['school'] }}@if (! empty($note['branch'])) — {{ $note['branch'] }}@endif</p>
      @endforeach
    </div>
  @endif

  {{-- QR (opens the live transcript online) · signatures --}}
  <div style="margin-top:24px; display:flex; align-items:flex-end; justify-content:space-between; gap:32px">
    @if ($qr !== null)
      <div style="display:flex; flex-shrink:0; align-items:center; gap:12px">
        <img src="{{ $qr }}" alt="QR" style="width:84px; height:84px; flex-shrink:0; border-radius:6px; background:#fff">
        <div style="max-width:220px">
          <p style="font-size:11px; font-weight:600">{{ $qrTitle }}</p>
          <p class="muted" style="margin-top:2px; font-size:10.5px">{{ $qrNote }}</p>
        </div>
      </div>
    @else
      <div></div>
    @endif

    <div style="display:flex; flex-shrink:0; gap:40px">
      @foreach (['Prepared by', 'Director'] as $label)
        <div style="width:180px">
          <p class="microlabel" style="font-size:10px">{{ $label }}</p>
          <div style="margin-top:18px; display:flex; align-items:baseline; gap:8px">
            <span class="muted" style="font-size:10px; flex-shrink:0">Name</span>
            <div style="flex:1; border-bottom:1px solid var(--foreground)"></div>
          </div>
          <div style="margin-top:20px; display:flex; align-items:baseline; gap:8px">
            <span class="muted" style="font-size:10px; flex-shrink:0">Signature</span>
            <div style="flex:1; border-bottom:1px solid var(--foreground)"></div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</article>
