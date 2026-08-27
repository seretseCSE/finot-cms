{{-- Print-ready A4 exam paper (ADR-016). Two variants share one template:
     the QUESTION PAPER handed to students, and the teacher's MARKING KEY
     (correct options highlighted + key lines). Typeset like a real national
     paper: candidate box, general instructions, numbered parts, questions
     that never split across a page break. --}}
@extends('documents.layout')

@section('title', $paper['title'])

@section('content')
@php
  $pts = fn ($v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
  $roman = function (int $n): string {
      $map = [1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC',
              50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
      $out = '';
      foreach ($map as $value => $token) {
          while ($n >= $value) { $out .= $token; $n -= $value; }
      }
      return $out;
  };
  $isKey = $paper['variant'] === 'answer_key';
  $meta = collect([
      $paper['grade_level_name'],
      $paper['subject_name'],
      $paper['section_names'] !== [] ? implode(', ', $paper['section_names']) : null,
      $paper['exam_year_ec'] ? $paper['exam_year_ec'].' E.C.' : null,
  ])->filter()->implode(' · ');
  $partIndex = 0;
@endphp

<style>
  /* Real page margins on EVERY page — the paper flows over many pages. */
  @page { size: A4; margin: 15mm 14mm 16mm; }

  .paper { font-size: 12.5px; line-height: 1.5; }
  .paper .rich p { margin: 0 0 4px; }
  .paper .rich p:last-child { margin-bottom: 0; }
  .paper .rich img { display: block; max-width: 100%; max-height: 210px; margin: 6px 0; }
  .paper .rich ul, .paper .rich ol { margin: 2px 0 4px; padding-left: 18px; }
  .paper .rich pre, .paper .rich code {
    font-family: "Geist Mono", ui-monospace, monospace; font-size: 11px;
    background: var(--muted); border-radius: 4px;
  }
  .paper .rich pre { padding: 6px 8px; margin: 4px 0; white-space: pre-wrap; }
  .paper .rich code { padding: 1px 4px; }
  .paper .rich blockquote { border-left: 2px solid var(--border); padding-left: 8px; color: var(--muted-foreground); }

  /* ── masthead ── */
  .masthead { text-align: center; padding-bottom: 10px; border-bottom: 2px solid var(--foreground); }
  .masthead .school { font-family: "Outfit", "Noto Sans Ethiopic", sans-serif; font-size: 15px; font-weight: 700; letter-spacing: 0.02em; }
  .masthead .school img { height: 34px; vertical-align: middle; margin-right: 8px; }
  .masthead .exam { font-family: "Outfit", "Noto Sans Ethiopic", sans-serif; font-size: 19px; font-weight: 700; margin-top: 3px; }
  .masthead .meta { font-size: 11.5px; color: var(--muted-foreground); margin-top: 2px; }
  .key-banner {
    margin-top: 8px; padding: 5px 10px; border: 1.5px solid var(--destructive);
    color: var(--destructive); font-weight: 700; font-size: 11px;
    letter-spacing: 0.14em; text-transform: uppercase; display: inline-block; border-radius: 4px;
  }

  /* ── facts strip + candidate box ── */
  .strip { display: flex; justify-content: space-between; gap: 12px; padding: 7px 2px; border-bottom: 1px solid var(--border); font-size: 11.5px; }
  .strip b { font-weight: 600; }
  .candidate { display: flex; gap: 18px; padding: 9px 2px 11px; border-bottom: 1px solid var(--border); font-size: 11.5px; break-inside: avoid; page-break-inside: avoid; }
  .candidate .field { display: flex; align-items: flex-end; gap: 5px; flex: 1; }
  .candidate .line { flex: 1; border-bottom: 1px solid var(--muted-foreground); min-width: 40px; height: 14px; }

  /* ── general instructions ── */
  .instr { margin: 12px 0 2px; padding: 9px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--muted); break-inside: avoid; page-break-inside: avoid; }
  .instr .microlabel { margin-bottom: 3px; }

  /* ── parts ── */
  .part-head {
    display: flex; align-items: baseline; gap: 10px; margin: 16px 0 4px;
    padding: 5px 9px; background: var(--foreground); color: #fff; border-radius: 4px;
    break-after: avoid; page-break-after: avoid;
  }
  .part-head .name { font-family: "Outfit", "Noto Sans Ethiopic", sans-serif; font-weight: 700; font-size: 12.5px; letter-spacing: 0.04em; text-transform: uppercase; }
  .part-head .marks { margin-left: auto; font-size: 10.5px; opacity: 0.85; white-space: nowrap; }
  .part-instr { margin: 2px 0 6px; padding: 0 2px; font-style: italic; color: var(--muted-foreground); font-size: 11.5px; break-after: avoid; page-break-after: avoid; }

  /* ── questions ── */
  .q { display: flex; gap: 7px; padding: 7px 2px; break-inside: avoid; page-break-inside: avoid; }
  .q + .q { border-top: 1px dotted var(--border); }
  .q-no { min-width: 20px; font-weight: 700; }
  .q-body { flex: 1; min-width: 0; }
  .q-head { display: flex; align-items: baseline; gap: 8px; }
  .q-stem { flex: 1; min-width: 0; }
  .q-marks { white-space: nowrap; color: var(--muted-foreground); font-size: 10px; }

  .opts { display: flex; flex-wrap: wrap; margin-top: 4px; }
  .opt { display: flex; gap: 6px; width: 100%; padding: 1.5px 0; }
  .opts.grid .opt { width: 50%; padding-right: 10px; box-sizing: border-box; }
  .opt .letter { font-weight: 600; }
  .opt.correct { font-weight: 700; }
  .opt.correct .letter::after { content: " ✓"; }

  .ansline { display: flex; align-items: flex-end; gap: 6px; margin-top: 7px; font-size: 11px; color: var(--muted-foreground); }
  .ansline .line { flex: 1; max-width: 320px; border-bottom: 1px solid var(--muted-foreground); height: 15px; }
  .essay-lines { margin-top: 8px; }
  .essay-lines .line { border-bottom: 1px solid var(--border); height: 21px; }
  .blank { display: inline-flex; align-items: flex-end; gap: 4px; margin: 6px 14px 0 0; font-size: 11px; color: var(--muted-foreground); }
  .blank .line { display: inline-block; width: 110px; border-bottom: 1px solid var(--muted-foreground); height: 14px; }

  .match { width: 100%; margin-top: 5px; border-collapse: collapse; }
  .match th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted-foreground); padding: 2px 6px; border-bottom: 1px solid var(--border); }
  .match td { vertical-align: top; padding: 3px 6px; width: 50%; }
  .match .tag { font-weight: 600; margin-right: 5px; }
  .match-answers { margin-top: 6px; }

  .passage { margin: 8px 0 2px; padding: 8px 11px; border: 1px solid var(--border); border-left: 3px solid var(--primary); border-radius: 6px; background: var(--muted); break-after: avoid; page-break-after: avoid; }
  .passage .microlabel { margin-bottom: 3px; }

  .keyline { margin-top: 5px; padding: 4px 9px; border-left: 3px solid var(--primary); background: var(--muted); border-radius: 0 4px 4px 0; font-size: 11.5px; }
  .keyline b { color: var(--primary); }
  .keyline .expl { color: var(--muted-foreground); margin-top: 1px; }

  .endmark { margin-top: 18px; text-align: center; font-size: 10.5px; letter-spacing: 0.2em; color: var(--muted-foreground); text-transform: uppercase; }
  .colophon { margin-top: 6px; text-align: center; font-size: 9.5px; color: var(--muted-foreground); }
</style>

<div class="paper">
  {{-- masthead --}}
  <div class="masthead">
    <div class="school">
      @if ($paper['school_logo'])<img src="{{ $paper['school_logo'] }}" alt="">@endif
      {{ $paper['school_name'] ?? 'Temari.et' }}
    </div>
    <div class="exam">{{ $paper['title'] }}</div>
    @if ($meta !== '')<div class="meta">{{ $meta }}</div>@endif
    @if ($isKey)<span class="key-banner">Marking key — confidential</span>@endif
  </div>

  {{-- facts strip --}}
  <div class="strip">
    <span><b>Time allowed:</b>
      {{ $paper['duration_minutes'] > 0 ? $paper['duration_minutes'].' minutes' : '—' }}</span>
    <span><b>Questions:</b> {{ $paper['question_count'] }}</span>
    <span><b>Total marks:</b> {{ $pts($paper['total_points']) }}</span>
  </div>

  {{-- candidate box (question paper only) --}}
  @unless ($isKey)
    <div class="candidate">
      <div class="field" style="flex: 2;"><span>Name</span><span class="line"></span></div>
      <div class="field"><span>{{ $paper['section_names'] !== [] ? 'Section' : 'Grade' }}</span><span class="line"></span></div>
      <div class="field"><span>ID No.</span><span class="line"></span></div>
      <div class="field"><span>Date</span><span class="line"></span></div>
    </div>
  @endunless

  {{-- general instructions --}}
  @if ($paper['instructions'])
    <div class="instr">
      <div class="microlabel">General instructions</div>
      <div class="rich">{!! $paper['instructions'] !!}</div>
    </div>
  @endif

  {{-- parts --}}
  @foreach ($paper['parts'] as $part)
    @if ($part['title'] !== null)
      @php $partIndex++; @endphp
      <div class="part-head">
        <span class="name">Part {{ $roman($partIndex) }}{{ $part['title'] !== '' ? ' — '.$part['title'] : '' }}</span>
        <span class="marks">{{ $pts($part['points']) }} marks</span>
      </div>
      @if ($part['instructions'])
        <div class="part-instr rich">{!! $part['instructions'] !!}</div>
      @endif
    @elseif (count($paper['parts']) > 1)
      <div class="part-head">
        <span class="name">Other questions</span>
        <span class="marks">{{ $pts($part['points']) }} marks</span>
      </div>
    @endif

    @foreach ($part['questions'] as $q)
      @if (!empty($q['passage']))
        <div class="passage">
          <div class="microlabel">Read the following and answer the questions below</div>
          <div class="rich">{!! $q['passage'] !!}</div>
        </div>
      @endif
      <div class="q">
        <div class="q-no">{{ $q['number'] }}.</div>
        <div class="q-body">
          <div class="q-head">
            <div class="q-stem rich">{!! $q['stem'] !!}</div>
            <span class="q-marks">({{ $pts($q['points']) }})</span>
          </div>

          {{-- choices --}}
          @if ($q['options'] !== [])
            <div class="opts {{ $q['options_grid'] ? 'grid' : '' }}">
              @foreach ($q['options'] as $opt)
                <div class="opt {{ $isKey && in_array($opt['id'], $q['correct_ids'] ?? [], true) ? 'correct' : '' }}">
                  <span class="letter">{{ $opt['letter'] }}.</span>
                  {{-- pdfInline(): sanitized rich HTML, or entity-escaped plain text --}}
                  <span class="rich">{!! $opt['text'] !!}</span>
                </div>
              @endforeach
            </div>
          @endif

          {{-- matching columns --}}
          @if ($q['left'] !== null && $q['right'] !== null)
            <table class="match">
              <tr><th>Column A</th><th>Column B</th></tr>
              @for ($i = 0; $i < max(count($q['left']), count($q['right'])); $i++)
                <tr>
                  <td>@isset($q['left'][$i])<span class="tag">{{ $q['left'][$i]['letter'] }}.</span><span class="rich">{!! $q['left'][$i]['text'] !!}</span>@endisset</td>
                  <td>@isset($q['right'][$i])<span class="tag">{{ $q['right'][$i]['letter'] }}.</span><span class="rich">{!! $q['right'][$i]['text'] !!}</span>@endisset</td>
                </tr>
              @endfor
            </table>
            @unless ($isKey)
              <div class="match-answers">
                @foreach ($q['left'] as $item)
                  <span class="blank"><span>{{ $item['letter'] }} →</span><span class="line" style="width: 46px;"></span></span>
                @endforeach
              </div>
            @endunless
          @endif

          {{-- write-in areas (question paper only) --}}
          @unless ($isKey)
            @if (in_array($q['type'], ['true_false', 'short_answer', 'numeric'], true))
              <div class="ansline"><span>Answer:</span><span class="line"></span></div>
            @elseif ($q['type'] === 'fill_blank')
              <div>
                @for ($i = 1; $i <= max(1, $q['blanks_count']); $i++)
                  <span class="blank"><span>{{ $i }}.</span><span class="line"></span></span>
                @endfor
              </div>
            @elseif ($q['type'] === 'essay')
              <div class="essay-lines">
                @for ($i = 0; $i < 6; $i++)<div class="line"></div>@endfor
              </div>
            @endif
          @endunless

          {{-- the key --}}
          @if ($isKey)
            <div class="keyline">
              <b>Answer:</b> {{ $q['answer'] !== '' ? $q['answer'] : '—' }}
              @if (!empty($q['explanation']))<div class="expl">{{ $q['explanation'] }}</div>@endif
            </div>
          @endif
        </div>
      </div>
    @endforeach
  @endforeach

  <div class="endmark">— End of {{ $isKey ? 'marking key' : 'examination' }} —</div>
  <div class="colophon">Generated on {{ dualDate($generated_at) }} · Temari.et</div>
</div>

@if (!empty($paper['has_math']))
  {{-- KaTeX renders <span data-math="…"> markers before Cloudflare snapshots
       the page (the renderer waits for networkidle0, which covers the CDN
       fetch + font loads). throwOnError:false keeps a typo from killing the
       whole paper — the raw LaTeX prints instead. --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
  <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"
    onload="document.querySelectorAll('span[data-math]').forEach(function (el) {
      try {
        katex.render(el.getAttribute('data-math'), el, {
          throwOnError: false,
          displayMode: el.getAttribute('data-display') === 'block',
        });
      } catch (e) { el.textContent = el.getAttribute('data-math'); }
    })"></script>
@endif
@endsection
