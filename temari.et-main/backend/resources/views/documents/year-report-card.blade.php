{{-- The yearly progress report card, one student per A4 landscape sheet.
     Serves the single print AND the batch (same view, 1..60 cards) as a
     duplex booklet:

       side=inside — marks grid (subject × term with year averages,
         per-term totals/averages/ranks/absences/conduct, semester
         sub-averages when the school runs quarters) + the behavioral skill
         panel when one is configured + the optional grading-criteria
         legend + the final-result band.

       side=cover — the outer sheet: left half is the per-term remarks
         page, right half the front cover (masthead, student identity,
         photo frame).

       side=both — cover then inside per student, in duplex feed order:
         print double-sided and every sheet folds into one finished booklet.

     Variables: $side, $terms, $cards, $skills, $masthead, $logo,
                $show_grading_criteria, $grading_criteria, $qr. --}}
@extends('documents.layout')

@section('title', 'Yearly report cards')

@php
  $quarterGroups = collect($terms)->where('is_quarter', true)->whereNotNull('semester')->groupBy('semester');
  $hasGroups = $quarterGroups->isNotEmpty();
  $termList = collect($terms);
  // Column order must match the grouped header: quarters by semester first,
  // then non-quarter terms.
  $orderedTerms = $hasGroups
      ? $quarterGroups->flatten(1)->concat($termList->reject(fn ($t) => $t['is_quarter'] && $t['semester'] !== null))
      : $termList;
  $ratingLegend = 'E = Excellent · VG = Very Good · S = Satisfactory · NI = Needs Improvement';
  $sides = ($side ?? 'inside') === 'both' ? ['cover', 'inside'] : [$side ?? 'inside'];
@endphp

@section('content')
  <style>
    @page { size: A4 landscape; }
    /* min-height (not height) so fit-to-page can measure a long sheet and
       shrink it onto one page instead of clipping marks. */
    .sheet {
      break-after: page; min-height: 209mm;
      padding: 24px 30px; position: relative;
      display: flex; flex-direction: column;
    }
    .sheet:last-of-type { break-after: auto; }
    .sheet::before {
      content: ""; position: absolute; inset: 0 0 auto 0; height: 5px;
      background: var(--primary);
    }
    @if ($logo !== null)
      .school-logo {
        background-image: url('{{ $logo }}');
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
      }
    @endif

    /* ── Shared header ── */
    .yr-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 2px solid var(--foreground); padding-bottom: 9px; }
    .yr-brand { display: flex; align-items: center; gap: 11px; min-width: 0; }
    .yr-brand .logo { width: 38px; height: 38px; flex-shrink: 0; }
    .yr-brand h1 { font-size: 15px; font-weight: 700; letter-spacing: -0.01em; line-height: 1.2; }
    .yr-brand p { font-size: 10px; color: var(--muted-foreground); margin-top: 1px; }
    .yr-doc { text-align: right; flex-shrink: 0; }
    .yr-doc .kind { font-size: 9px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--primary); }
    .yr-doc .when { font-size: 11px; font-weight: 600; margin-top: 1px; }

    .yr-who { margin-top: 9px; display: flex; align-items: center; gap: 12px; background: var(--muted); border-radius: 10px; padding: 7px 12px; }
    .yr-who .name { min-width: 0; flex: 1; }
    .yr-who .name p:first-child { font-size: 13px; font-weight: 700; }
    .yr-who .name p:last-child { font-size: 9px; color: var(--muted-foreground); }
    .yr-who .fact { text-align: center; flex-shrink: 0; }
    .yr-who .fact p:first-child { font-size: 7.5px; color: var(--muted-foreground); text-transform: uppercase; letter-spacing: 0.06em; }
    .yr-who .fact p:last-child { font-size: 10.5px; font-weight: 700; }

    .yr-cols { display: flex; gap: 20px; margin-top: 10px; align-items: stretch; flex: 1; min-height: 0; }
    .yr-main { flex: 1 1 58%; min-width: 0; }
    .yr-side { flex: 1 1 42%; min-width: 0; display: flex; flex-direction: column; }

    /* ── Marks grid ── */
    table.grid { font-size: 10px; }
    table.grid th, table.grid td { border: 1px solid var(--border); padding: 3px 6px; }
    table.grid thead th { background: var(--muted); font-weight: 700; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid var(--primary); }
    table.grid .sub { text-align: left; font-weight: 500; }
    table.grid .num { text-align: center; font-variant-numeric: tabular-nums; }
    table.grid tbody tr:nth-child(even) td { background: var(--background); }
    table.grid .year-col { font-weight: 800; background: var(--muted); }
    table.grid tr.strip td { font-weight: 700; background: var(--muted); font-size: 9px; }

    /* ── Panels ── */
    .panel { border: 1px solid var(--border); border-radius: 10px; padding: 9px 11px; }
    .panel h3 { font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--primary); }
    .panel .hint { font-size: 8.5px; color: var(--muted-foreground); margin-top: 2px; }
    table.skills { font-size: 9px; margin-top: 7px; }
    table.skills th, table.skills td { border: 1px solid var(--border); padding: 2.5px 5px; }
    table.skills thead th { background: var(--muted); font-size: 7.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
    table.skills .num { text-align: center; font-weight: 600; }
    .legend { font-size: 8px; color: var(--muted-foreground); margin-top: 5px; }

    .final { margin-top: auto; padding-top: 9px; display: flex; gap: 7px; }
    /* No skills panel → nothing above the band; read from the top instead. */
    .final.start { margin-top: 0; padding-top: 0; }
    .final .tile { flex: 1; border: 1px solid var(--border); border-radius: 9px; padding: 6px 9px; }
    .final .tile p:first-child { font-size: 8px; color: var(--muted-foreground); text-transform: uppercase; letter-spacing: 0.05em; }
    .final .tile p:last-child { font-size: 13px; font-weight: 800; margin-top: 1px; }
    .final .tile.lead { background: var(--primary); border-color: var(--primary); }
    .final .tile.lead p { color: #fff; }
    .final .tile.lead p:first-child { opacity: 0.85; }

    .yr-signs { margin-top: 9px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; padding-top: 15px; }
    .yr-signs div { text-align: center; }
    .yr-signs .line { border-bottom: 1px solid var(--foreground); height: 15px; }
    .yr-signs p { margin-top: 3px; font-size: 8.5px; color: var(--muted-foreground); }

    .yr-qr { margin-top: 8px; display: flex; align-items: center; gap: 8px; border-top: 1px dashed var(--border); padding-top: 7px; }
    .yr-qr img { width: 60px; height: 60px; border-radius: 5px; background: #fff; }
    .yr-qr p { font-size: 8px; color: var(--muted-foreground); }

    /* ── Cover side ── */
    .cover-body { display: flex; flex: 1; min-height: 0; }
    .cover-body > * { width: 50%; flex: 0 0 50%; min-width: 0; }
    .cover-body .remarks { padding-right: 24px; border-right: 1px dashed var(--border); display: flex; flex-direction: column; }
    .cover-body .face { padding-left: 24px; text-align: center; display: flex; flex-direction: column; }
    .remarks h3 { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--primary); }
    .remarks .block { margin-top: 8px; border: 1px solid var(--border); border-radius: 9px; padding: 7px 10px; flex: 1; display: flex; flex-direction: column; }
    .remarks .block h4 { font-size: 9px; font-weight: 700; }
    .remarks .lines { border-bottom: 1px solid var(--border); flex: 1; min-height: 11px; }
    .remarks .sig { display: flex; justify-content: flex-end; gap: 5px; font-size: 8px; color: var(--muted-foreground); margin-top: 3px; align-items: baseline; }
    .remarks .sig span.line { display: inline-block; width: 95px; border-bottom: 1px solid var(--border); height: 10px; }
    .face .logo-lg { width: 72px; height: 72px; margin: 8px auto 8px; }
    .face h1 { font-size: 21px; font-weight: 800; letter-spacing: 0.01em; }
    .face .contact { margin-top: 6px; font-size: 9px; color: var(--muted-foreground); line-height: 1.55; }
    .face .doc {
      margin: 14px auto 0; padding: 7px 0; font-size: 12px; font-weight: 800;
      letter-spacing: 0.14em; text-transform: uppercase; width: 82%;
      border-top: 2px solid var(--primary); border-bottom: 2px solid var(--primary);
      color: var(--primary);
    }
    .face dl { margin: 12px auto 0; width: 84%; text-align: left; font-size: 11px; }
    .face dl .row { display: flex; gap: 8px; align-items: baseline; margin-top: 8px; }
    .face dl dt { font-weight: 700; flex-shrink: 0; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted-foreground); align-self: flex-end; }
    .face dl dd { flex: 1; border-bottom: 1px solid var(--foreground); padding: 0 4px 1px; min-height: 15px; font-weight: 600; }
    .face .photo { margin: 14px auto 0; width: 84px; height: 106px; border: 1px dashed var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 8px; color: var(--muted-foreground); }
    .face .qr-strip { margin-top: auto; display: flex; align-items: center; justify-content: center; gap: 8px; padding-top: 8px; }
  </style>

  @foreach ($cards as $card)
    @foreach ($sides as $sheetSide)
      @if ($sheetSide === 'cover')
        <div class="sheet">
          <div class="cover-body">
            {{-- Left half: the remarks page (inside-left when folded). --}}
            <div class="remarks">
              <h3>Remarks</h3>
              @foreach ($termList as $term)
                <div class="block">
                  <h4>{{ $term['name'] }} — Teacher's comment</h4>
                  <div class="lines"></div>
                  <p class="sig">Sig. <span class="line"></span></p>
                  <h4 style="margin-top:4px">Parent's comment</h4>
                  <div class="lines"></div>
                  <p class="sig">Sig. <span class="line"></span></p>
                </div>
              @endforeach
            </div>

            {{-- Right half: the front cover. --}}
            <div class="face">
              @if ($logo !== null)
                <div class="logo-lg school-logo"></div>
              @endif
              <h1 class="font-display">{{ $masthead['school_name'] }}</h1>
              @if ($masthead['branch_name'])
                <p style="font-size:10px; color:var(--muted-foreground); margin-top:2px">{{ $masthead['branch_name'] }}</p>
              @endif
              <p class="contact">
                @if ($masthead['phone']) Tel. {{ $masthead['phone'] }}<br> @endif
                @if ($masthead['address']) {{ $masthead['address'] }}<br> @endif
                Ethiopia
              </p>
              <p class="doc">Student progress report card</p>
              <dl>
                <div class="row"><dt>Name</dt><dd>{{ $card['student']['full_name'] }}</dd></div>
                <div class="row">
                  <dt>Sex</dt><dd style="flex:0 0 52px">{{ $card['student']['gender'] !== null ? strtoupper(substr($card['student']['gender'], 0, 1)) : '' }}</dd>
                  <dt>Student ID</dt><dd>{{ $card['student']['public_id'] }}</dd>
                </div>
                <div class="row"><dt>Academic year</dt><dd>{{ $card['academic_year'] }}</dd></div>
                <div class="row">
                  <dt>Grade</dt><dd>{{ $card['grade_level'] }}</dd>
                  <dt>Section</dt><dd style="flex:0 0 62px">{{ $card['section_name'] }}</dd>
                </div>
              </dl>
              <div class="photo">Student's photo</div>
              <div class="qr-strip">
                <img src="{{ $qr }}" alt="QR" style="width:64px; height:64px; border-radius:6px; background:#fff">
                <p style="font-size:8px; color:var(--muted-foreground); text-align:left">Scan to verify this report card —<br>issued through Temari.et.</p>
              </div>
            </div>
          </div>
        </div>
      @else
        <div class="sheet">
          <header class="yr-head">
            <div class="yr-brand">
              @if ($logo !== null)
                <div class="logo school-logo"></div>
              @endif
              <div style="min-width:0">
                <h1 class="font-display">{{ $masthead['school_name'] }}</h1>
                @if ($masthead['branch_name'])
                  <p>{{ $masthead['branch_name'] }}</p>
                @endif
              </div>
            </div>
            <div class="yr-doc">
              <p class="kind">Yearly progress report</p>
              <p class="when">{{ $card['academic_year'] }}</p>
            </div>
          </header>

          <div class="yr-who">
            <div class="name">
              <p>{{ $card['student']['full_name'] }}</p>
              <p>ID {{ $card['student']['public_id'] ?? '—' }}</p>
            </div>
            <div class="fact"><p>Grade</p><p>{{ $card['grade_level'] ?? '—' }}</p></div>
            <div class="fact"><p>Section</p><p>{{ $card['section_name'] ?? '—' }}</p></div>
          </div>

          <div class="yr-cols">
            <div class="yr-main">
              <table class="grid">
                <thead>
                  @if ($hasGroups)
                    <tr>
                      <th rowspan="2" class="sub" style="text-align:left">Subject</th>
                      @foreach ($quarterGroups as $semester => $group)
                        <th colspan="{{ count($group) }}">Semester {{ $semester }}</th>
                      @endforeach
                      @foreach ($termList->reject(fn ($t) => $t['is_quarter'] && $t['semester'] !== null) as $term)
                        <th rowspan="2">{{ $term['name'] }}</th>
                      @endforeach
                      <th rowspan="2" class="year-col">Year avg</th>
                    </tr>
                    <tr>
                      @foreach ($quarterGroups as $group)
                        @foreach ($group as $term)
                          <th>{{ $term['name'] }}</th>
                        @endforeach
                      @endforeach
                    </tr>
                  @else
                    <tr>
                      <th class="sub" style="text-align:left">Subject</th>
                      @foreach ($termList as $term)
                        <th>{{ $term['name'] }}</th>
                      @endforeach
                      <th class="year-col">Year avg</th>
                    </tr>
                  @endif
                </thead>
                <tbody>
                  @foreach ($card['subjects'] as $subject)
                    <tr>
                      <td class="sub">{{ $subject['name'] }}</td>
                      @foreach ($orderedTerms as $term)
                        @php $cell = $subject['per_term'][$term['id']] ?? null; @endphp
                        <td class="num" style="{{ ($cell['is_passing'] ?? true) === false ? 'color:var(--destructive)' : '' }}">
                          {{ $cell['total'] ?? '—' }}
                        </td>
                      @endforeach
                      <td class="num year-col">{{ $subject['year_avg'] ?? '—' }}</td>
                    </tr>
                  @endforeach
                  @foreach ([
                    ['Total', 'totals', null],
                    ['Average', 'averages', $card['year']['average']],
                    ['Rank', 'ranks', $card['year']['rank'] !== null && $card['year']['rank_of'] !== null ? $card['year']['rank'].' / '.$card['year']['rank_of'] : '—'],
                    ['Absent days', 'absences', null],
                    ['Conduct', 'conducts', null],
                  ] as [$label, $key, $yearCell])
                    <tr class="strip">
                      <td class="sub">{{ $label }}</td>
                      @foreach ($orderedTerms as $term)
                        <td class="num">{{ $card[$key][$term['id']] ?? '—' }}</td>
                      @endforeach
                      <td class="num year-col">{{ $yearCell ?? '—' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>

              @if ($show_grading_criteria && $grading_criteria !== [])
                <div class="panel" style="margin-top:9px">
                  <h3>Grading criteria</h3>
                  <p class="legend" style="margin-top:4px">
                    @foreach ($grading_criteria as $band)
                      <span style="display:inline-block; margin-right:11px">
                        <strong>{{ $band['letter'] ?? $band['label'] }}</strong>
                        = {{ rtrim(rtrim(number_format($band['min'], 2), '0'), '.') }}–{{ rtrim(rtrim(number_format($band['max'], 2), '0'), '.') }}
                        @if ($band['label']) ({{ $band['label'] }}) @endif
                      </span>
                    @endforeach
                  </p>
                </div>
              @endif
            </div>

            <div class="yr-side">
              @if (count($skills) > 0)
                <div class="panel">
                  <h3>Academic &amp; behavioral assessment</h3>
                  <p class="hint">A skill checklist to show your child's progress. Each skill is a goal the school uses to promote intellectual, social, emotional and physical growth.</p>
                  @foreach (collect($skills)->groupBy('group') as $group => $groupSkills)
                    <table class="skills">
                      <thead>
                        <tr>
                          <th style="text-align:left; width:52%">{{ $group === 'character' ? 'Character development & work habits' : 'Study & work habits' }}</th>
                          @foreach ($termList as $term)
                            <th>{{ $term['name'] }}</th>
                          @endforeach
                        </tr>
                      </thead>
                      <tbody>
                        @foreach ($groupSkills as $skill)
                          <tr>
                            <td style="text-align:left">
                              {{ $skill['label']['en'] }}
                              @if (! empty($skill['label']['am']))
                                <span class="muted font-ethiopic" style="font-size:0.9em"> · {{ $skill['label']['am'] }}</span>
                              @endif
                            </td>
                            @foreach ($termList as $term)
                              <td class="num">{{ $card['skill_ratings'][$term['id']][$skill['key']] ?? '' }}</td>
                            @endforeach
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  @endforeach
                  <p class="legend">{{ $ratingLegend }}</p>
                </div>
              @endif

              <div class="final {{ count($skills) === 0 ? 'start' : '' }}">
                <div class="tile lead">
                  <p>Year average</p>
                  <p class="tnum">{{ $card['year']['average'] ?? '—' }}</p>
                </div>
                <div class="tile">
                  <p>Year rank</p>
                  <p class="tnum">{{ $card['year']['rank'] !== null ? $card['year']['rank'].' / '.$card['year']['rank_of'] : '—' }}</p>
                </div>
                <div class="tile">
                  <p>Final result</p>
                  <p style="font-size:10.5px">
                    @if ($card['outcome'] !== null)
                      {{ $card['outcome']['label'] }}{{ $card['outcome']['to_grade_level'] ? ' — '.$card['outcome']['to_grade_level'] : '' }}
                    @else
                      —
                    @endif
                  </p>
                </div>
              </div>

              <div class="yr-signs">
                @foreach (['Homeroom teacher', 'Director', 'Parent / Guardian'] as $label)
                  <div>
                    <div class="line"></div>
                    <p>{{ $label }}</p>
                  </div>
                @endforeach
              </div>

              <div class="yr-qr">
                <img src="{{ $qr }}" alt="QR">
                <p>Scan to verify this report card online — issued through Temari.et; the QR always shows the authoritative record.</p>
              </div>
            </div>
          </div>
        </div>
      @endif
    @endforeach
  @endforeach

  @include('documents.partials.fit-to-page')
@endsection
