{{-- One semester report card, shared by the single print (report-card) and
     the batch (report-card-batch). Three densities, one design:

       · full    — one relaxed card per A4 page;
       · half    — two cards stacked vertically, each EXACTLY half the page;
       · quarter — four cards in a 2×2 grid, each exactly a quarter page.

     Every density is a flex column inside a fixed-height cell: the summary
     band, signatures and footer pin to the bottom (margin-top:auto) so a
     half/quarter card fills its slot edge to edge and can never overflow
     into its neighbour (the cell clips).

     Inputs: $card, $qr, $showSubjectRanks (bool),
             $density ('full' | 'half' | 'quarter'),
             $logoUrl (data URI, single) OR $logoClass (shared CSS class, batch). --}}
@once
  <style>
    .rc {
      display: flex; flex-direction: column; height: 100%;
      padding: 34px 40px; font-size: 13px; position: relative;
    }
    /* The brand accent: a slim primary band along the top edge. */
    .rc::before {
      content: ""; position: absolute; inset: 0 0 auto 0; height: 5px;
      background: var(--primary);
    }

    /* ── Header: school identity left, document identity right ── */
    .rc-head {
      display: flex; align-items: center; justify-content: space-between;
      gap: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--foreground);
    }
    .rc-school { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .rc-logo { width: 44px; height: 44px; object-fit: contain; flex-shrink: 0; }
    .rc-school h2 { font-size: 17px; font-weight: 700; letter-spacing: -0.01em; line-height: 1.2; }
    .rc-school p { font-size: 11px; color: var(--muted-foreground); margin-top: 1px; }
    .rc-doc { text-align: right; flex-shrink: 0; }
    .rc-doc .kind {
      font-size: 10px; font-weight: 700; letter-spacing: 0.16em;
      text-transform: uppercase; color: var(--primary);
    }
    .rc-doc .when { font-size: 12px; font-weight: 600; margin-top: 2px; }

    /* ── Identity band: the student front and centre ── */
    .rc-who {
      margin-top: 12px; display: flex; align-items: center; gap: 14px;
      background: var(--muted); border-radius: 12px; padding: 10px 14px;
    }
    .rc-who .name { min-width: 0; flex: 1; }
    .rc-who .name p:first-child { font-size: 15px; font-weight: 700; line-height: 1.25; }
    .rc-who .name p:last-child { font-size: 10.5px; color: var(--muted-foreground); margin-top: 1px; }
    .rc-who .fact { text-align: center; flex-shrink: 0; }
    .rc-who .fact p:first-child { font-size: 9.5px; color: var(--muted-foreground); text-transform: uppercase; letter-spacing: 0.06em; }
    .rc-who .fact p:last-child { font-size: 12.5px; font-weight: 700; margin-top: 1px; }

    /* ── Subject table ── */
    .rc-table { margin-top: 12px; }
    .rc-table th {
      padding: 6px 8px; text-align: left; font-size: 10px; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.07em;
      color: var(--muted-foreground); border-bottom: 2px solid var(--primary);
    }
    .rc-table td { padding: 5px 8px; border-bottom: 1px solid var(--border); }
    .rc-table tbody tr:nth-child(even) td { background: var(--background); }
    .rc-table .mark { text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; }
    .rc-table .ctr { text-align: center; }
    .rc-table .remark { text-align: right; font-size: 0.88em; }

    /* ── Summary band: right below the marks, average leads ── */
    .rc-tiles { margin-top: 12px; display: flex; gap: 8px; }
    .rc-tile { flex: 1; border: 1px solid var(--border); border-radius: 10px; padding: 7px 10px; }
    .rc-tile p:first-child { font-size: 9.5px; color: var(--muted-foreground); text-transform: uppercase; letter-spacing: 0.06em; }
    .rc-tile p:last-child { font-size: 16px; font-weight: 800; margin-top: 1px; font-variant-numeric: tabular-nums; }
    .rc-tile.lead { background: var(--primary); border-color: var(--primary); }
    .rc-tile.lead p { color: #fff; }
    .rc-tile.lead p:first-child { opacity: 0.85; }

    /* ── Comment boxes: they FLEX to soak up whatever space is left, so no
          density ever prints a dead gap — the classic paper card's teacher
          and parent boxes. The stored homeroom comment prints inside the
          teacher box; the parent box stays blank for handwriting. ── */
    .rc-box {
      margin-top: 10px; border: 1px solid var(--border); border-radius: 10px;
      padding: 8px 12px; flex: 1; min-height: 0;
      display: flex; flex-direction: column;
    }
    .rc-box h4 { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted-foreground); }
    .rc-box .body { flex: 1; font-size: 12px; padding-top: 4px; }
    .rc-box .sig { display: flex; justify-content: flex-end; align-items: baseline; gap: 6px; font-size: 9.5px; color: var(--muted-foreground); padding-top: 4px; }
    .rc-box .sig span.line { display: inline-block; width: 120px; border-bottom: 1px solid var(--border); height: 12px; }

    /* ── Footer: QR authenticity chip, director signature, computed date ── */
    .rc-foot {
      margin-top: 10px; display: flex; align-items: center; gap: 12px;
      border-top: 1px dashed var(--border); padding-top: 8px;
    }
    .rc-foot img { width: 84px; height: 84px; flex-shrink: 0; border-radius: 6px; background: #fff; }
    .rc-foot .note { min-width: 0; flex: 1; }
    .rc-foot .note p:first-child { font-size: 10.5px; font-weight: 700; }
    .rc-foot .note p:last-child { font-size: 9.5px; color: var(--muted-foreground); margin-top: 1px; }
    .rc-foot .director { flex-shrink: 0; text-align: center; }
    .rc-foot .director .line { border-bottom: 1px solid var(--foreground); height: 18px; width: 130px; }
    .rc-foot .director p { margin-top: 3px; font-size: 9.5px; color: var(--muted-foreground); }
    .rc-foot .date { text-align: right; font-size: 9px; color: var(--muted-foreground); white-space: nowrap; }

    /* ── Half density (2 per page): one notch tighter ── */
    .rc-half { padding: 18px 26px; font-size: 11px; }
    .rc-half::before { height: 4px; }
    .rc-half .rc-head { padding-bottom: 8px; }
    .rc-half .rc-logo { width: 32px; height: 32px; }
    .rc-half .rc-school h2 { font-size: 14px; }
    .rc-half .rc-school p { font-size: 9.5px; }
    .rc-half .rc-doc .kind { font-size: 8.5px; }
    .rc-half .rc-doc .when { font-size: 10.5px; }
    .rc-half .rc-who { margin-top: 8px; padding: 7px 11px; gap: 10px; border-radius: 10px; }
    .rc-half .rc-who .name p:first-child { font-size: 12.5px; }
    .rc-half .rc-who .name p:last-child { font-size: 9px; }
    .rc-half .rc-who .fact p:first-child { font-size: 8px; }
    .rc-half .rc-who .fact p:last-child { font-size: 10.5px; }
    .rc-half .rc-table { margin-top: 8px; }
    .rc-half .rc-table th { padding: 4px 6px; font-size: 8.5px; }
    .rc-half .rc-table td { padding: 2.5px 6px; }
    .rc-half .rc-tiles { margin-top: 8px; gap: 6px; }
    .rc-half .rc-tile { padding: 5px 8px; border-radius: 8px; }
    .rc-half .rc-tile p:first-child { font-size: 8px; }
    .rc-half .rc-tile p:last-child { font-size: 12.5px; }
    .rc-half .rc-box { margin-top: 7px; padding: 6px 9px; border-radius: 8px; }
    .rc-half .rc-box h4 { font-size: 8px; }
    .rc-half .rc-box .body { font-size: 10px; padding-top: 3px; }
    .rc-half .rc-box .sig { font-size: 8px; padding-top: 3px; }
    .rc-half .rc-box .sig span.line { width: 95px; height: 10px; }
    .rc-half .rc-foot { margin-top: 7px; padding-top: 6px; gap: 9px; }
    .rc-half .rc-foot img { width: 62px; height: 62px; }
    .rc-half .rc-foot .note p:first-child { font-size: 9px; }
    .rc-half .rc-foot .note p:last-child { font-size: 8px; }
    .rc-half .rc-foot .director .line { height: 13px; width: 100px; }
    .rc-half .rc-foot .director p { font-size: 8px; }
    .rc-half .rc-foot .date { font-size: 7.5px; }

    /* ── Quarter density (4 per page): the wallet card ── */
    .rc-quarter { padding: 12px 15px; font-size: 8.5px; }
    .rc-quarter::before { height: 3px; }
    .rc-quarter .rc-head { padding-bottom: 5px; border-bottom-width: 1.5px; gap: 8px; }
    .rc-quarter .rc-logo { width: 22px; height: 22px; }
    .rc-quarter .rc-school { gap: 7px; }
    .rc-quarter .rc-school h2 { font-size: 10.5px; }
    .rc-quarter .rc-school p { font-size: 7px; }
    .rc-quarter .rc-doc .kind { font-size: 6.5px; letter-spacing: 0.1em; }
    .rc-quarter .rc-doc .when { font-size: 8px; }
    .rc-quarter .rc-who { margin-top: 5px; padding: 4px 8px; gap: 7px; border-radius: 7px; }
    .rc-quarter .rc-who .name p:first-child { font-size: 9.5px; }
    .rc-quarter .rc-who .name p:last-child { font-size: 7px; }
    .rc-quarter .rc-who .fact p:first-child { font-size: 6px; }
    .rc-quarter .rc-who .fact p:last-child { font-size: 8px; }
    .rc-quarter .rc-table { margin-top: 5px; }
    .rc-quarter .rc-table th { padding: 2.5px 5px; font-size: 6.5px; border-bottom-width: 1.5px; }
    .rc-quarter .rc-table td { padding: 1.5px 5px; }
    .rc-quarter .rc-tiles { margin-top: 5px; gap: 4px; }
    .rc-quarter .rc-tile { padding: 3px 6px; border-radius: 6px; }
    .rc-quarter .rc-tile p:first-child { font-size: 6px; }
    .rc-quarter .rc-tile p:last-child { font-size: 9.5px; }
    .rc-quarter .rc-box { margin-top: 4px; padding: 4px 7px; border-radius: 6px; }
    .rc-quarter .rc-box h4 { font-size: 6px; }
    .rc-quarter .rc-box .body { font-size: 7.5px; padding-top: 2px; }
    .rc-quarter .rc-box .sig { font-size: 6px; padding-top: 2px; }
    .rc-quarter .rc-box .sig span.line { width: 65px; height: 7px; }
    .rc-quarter .rc-foot { margin-top: 4px; padding-top: 3px; gap: 6px; }
    .rc-quarter .rc-foot img { width: 48px; height: 48px; }
    .rc-quarter .rc-foot .note p:first-child { font-size: 6.5px; }
    .rc-quarter .rc-foot .note p:last-child { font-size: 6px; }
    .rc-quarter .rc-foot .director .line { height: 8px; width: 70px; }
    .rc-quarter .rc-foot .director p { font-size: 6px; margin-top: 1px; }
    .rc-quarter .rc-foot .date { font-size: 5.5px; }
  </style>
@endonce

@php
  $density = $density ?? 'full';
  $display = $card['grading']['display'] ?? 'numeric';
  $showNumbers = $display !== 'letter';
  $showLetters = $display !== 'numeric';
  // Ranks exist only on rows frozen after the per-subject-rank release.
  $hasSubjectRanks = ($showSubjectRanks ?? false)
      && collect($card['subjects'] ?? [])->contains(fn ($line) => ($line['rank'] ?? null) !== null);
  // The wallet card keeps only the essentials — remarks go, marks stay.
  $showRemark = $density !== 'quarter';

  $tiles = [];
  if ($showNumbers) $tiles[] = ['Average', $card['average'] ?? '—', true];
  if ($showLetters) $tiles[] = ['Grade', $card['grading']['overall']['letter'] ?? $card['grading']['overall']['label'] ?? '—', ! $showNumbers];
  $tiles[] = ['Rank', $card['rank'] !== null && $card['rank_of'] !== null ? $card['rank'].' / '.$card['rank_of'] : '—', false];
  $tiles[] = ['Conduct', $card['conduct'] ?? '—', false];
  $tiles[] = ['Days absent', $card['absence_days'] ?? '—', false];
@endphp

<article class="rc {{ $density === 'half' ? 'rc-half' : '' }} {{ $density === 'quarter' ? 'rc-quarter' : '' }}">
  <header class="rc-head">
    <div class="rc-school">
      @if (! empty($logoUrl))
        <img src="{{ $logoUrl }}" alt="" class="rc-logo">
      @elseif (! empty($logoClass))
        <div class="rc-logo {{ $logoClass }}"></div>
      @endif
      <div style="min-width:0">
        <h2 class="font-display">{{ $card['school_name'] }}</h2>
        @if ($card['branch_name'])
          <p>{{ $card['branch_name'] }}</p>
        @endif
      </div>
    </div>
    <div class="rc-doc">
      <p class="kind">Student report card</p>
      <p class="when">{{ $card['term_name'] }} · {{ $card['academic_year'] }}</p>
    </div>
  </header>

  <div class="rc-who">
    <div class="name">
      <p>{{ $card['student']['full_name'] }}</p>
      <p>ID {{ $card['student']['public_id'] ?? '—' }}</p>
    </div>
    <div class="fact"><p>Grade</p><p>{{ $card['grade_level'] ?? '—' }}</p></div>
    <div class="fact"><p>Section</p><p>{{ $card['section_name'] ?? '—' }}</p></div>
  </div>

  <table class="rc-table">
    <thead>
      <tr>
        <th>Subject</th>
        @if ($showNumbers)
          <th class="mark" style="width:14%">Mark</th>
        @endif
        @if ($hasSubjectRanks)
          <th class="ctr" style="width:11%">Rank</th>
        @endif
        @if ($showLetters)
          <th class="ctr" style="width:11%">Grade</th>
        @endif
        @if ($showRemark)
          <th class="remark" style="width:22%">Remark</th>
        @endif
      </tr>
    </thead>
    <tbody>
      @foreach ($card['subjects'] ?? [] as $line)
        <tr>
          <td>{{ $line['name'] }}</td>
          @if ($showNumbers)
            <td class="mark" style="{{ ($line['is_passing'] ?? true) === false ? 'color:var(--destructive)' : '' }}">
              {{ $line['total'] ?? '—' }}
            </td>
          @endif
          @if ($hasSubjectRanks)
            <td class="ctr tnum">{{ $line['rank'] ?? '—' }}</td>
          @endif
          @if ($showLetters)
            <td class="ctr" style="font-weight:700">{{ $line['letter'] ?? '—' }}</td>
          @endif
          @if ($showRemark)
            <td class="remark" style="color:{{ ($line['is_passing'] ?? true) === false ? 'var(--destructive)' : 'var(--muted-foreground)' }}">
              {{ $line['band_label'] ?? '—' }}
            </td>
          @endif
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="rc-tiles">
    @foreach ($tiles as [$label, $value, $lead])
      <div class="rc-tile {{ $lead ? 'lead' : '' }}">
        <p>{{ $label }}</p>
        <p>{{ $value }}</p>
      </div>
    @endforeach
  </div>

  {{-- The comment boxes flex to fill every remaining millimetre of the
       card's slot — no dead space at any density. --}}
  <div class="rc-box">
    <h4>Teacher's comment</h4>
    <div class="body">{{ $card['comment'] ?? '' }}</div>
    <p class="sig">Name <span class="line"></span> Sig. <span class="line"></span></p>
  </div>

  <div class="rc-box">
    <h4>Parent's comment</h4>
    <div class="body"></div>
    <p class="sig">Name <span class="line"></span> Sig. <span class="line"></span></p>
  </div>

  <div class="rc-foot">
    <img src="{{ $qr }}" alt="QR">
    <div class="note">
      <p>Scan to verify this report card online</p>
      <p>
        Issued through Temari.et — the QR always shows the authoritative record.
        @if ($card['computed_at'])
          <span class="date">Computed {{ dualDate(\Carbon\CarbonImmutable::parse($card['computed_at'])) }}</span>
        @endif
      </p>
    </div>
    <div class="director">
      <div class="line"></div>
      <p>Director</p>
    </div>
  </div>
</article>
