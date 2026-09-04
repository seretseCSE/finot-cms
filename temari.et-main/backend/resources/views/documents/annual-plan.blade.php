{{-- The official annual lesson plan — the MoE format: header block (school,
     year, teacher, subject, grade, periods/week, total periods) and the
     semester grid (unit rows with months/weeks derived from each unit's date
     window, allotted periods, pages, objectives, rationale, prerequisite
     knowledge, aids, methodology, assessment). Landscape A4; signature lines
     at the foot mirror the paper ritual. --}}
@extends('documents.layout')

@section('title', 'Annual lesson plan — '.($subject_name ?? '').' '.($grade_name ?? ''))

@php
  $cellBorder = 'border:1px solid var(--border);';
  $th = $cellBorder.' padding:5px 6px; text-align:left; font-weight:600; font-size:10px; background:var(--muted);';
  $td = $cellBorder.' padding:5px 6px; font-size:10px; vertical-align:top;';

  // Group units by semester (term.semester) with un-termed units last.
  $groups = collect($units)->groupBy(fn ($u) => $u['semester'] ?? 0)->sortKeys();
@endphp

@section('content')
  <style>
    @page { size: A4 landscape; margin: 12mm 10mm; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
  </style>

  <article style="padding:0">
    <div class="card-header" style="padding-bottom:14px; margin-bottom:14px">
      <div>
        <h2 class="font-display" style="font-size:20px; font-weight:700">{{ $school_name }}</h2>
        <p class="muted" style="font-size:13px">{{ $branch_name }}</p>
      </div>
      <div style="text-align:right">
        @include('documents.partials.brand')
        <p class="muted" style="margin-top:6px; font-size:12px">Annual lesson plan</p>
      </div>
    </div>

    {{-- General information block --}}
    <table style="font-size:12px; margin-bottom:14px">
      <tr>
        <td style="padding:2px 0; width:34%"><span class="microlabel">Teacher</span><br><strong>{{ $teacher_name }}</strong></td>
        <td style="padding:2px 0; width:22%"><span class="microlabel">Subject</span><br><strong>{{ $subject_name }}</strong></td>
        <td style="padding:2px 0; width:16%"><span class="microlabel">Grade</span><br><strong>{{ $grade_name }}</strong></td>
        <td style="padding:2px 0; width:14%"><span class="microlabel">Academic year</span><br><strong>{{ $year_name }}</strong></td>
        <td style="padding:2px 0; width:14%">
          <span class="microlabel">Periods</span><br>
          <strong>{{ $periods_per_week !== null ? $periods_per_week.'/week' : '—' }}</strong>
          <span class="muted">· {{ $total_periods !== null ? $total_periods.' total' : '—' }}</span>
        </td>
      </tr>
    </table>

    @if (!empty($goals))
      <div style="margin-bottom:12px">
        <span class="microlabel">General objectives</span>
        <div style="font-size:11px">{!! $goals !!}</div>
      </div>
    @endif

    @foreach ($groups as $semester => $rows)
      @if ($groups->count() > 1 || $semester !== 0)
        <h3 class="font-display" style="font-size:13px; font-weight:600; margin:10px 0 6px">
          {{ $semester === 0 ? 'Unscheduled units' : 'Semester '.$semester }}
        </h3>
      @endif

      <table style="margin-bottom:10px">
        <thead>
          <tr>
            <th style="{{ $th }} width:24px">No.</th>
            <th style="{{ $th }} width:90px">Month · weeks</th>
            <th style="{{ $th }} width:150px">Topics &amp; main contents</th>
            <th style="{{ $th }} width:46px">Periods</th>
            <th style="{{ $th }} width:52px">Pages</th>
            <th style="{{ $th }}">General objectives</th>
            <th style="{{ $th }}">Rationale of the unit</th>
            <th style="{{ $th }}">Prerequisite knowledge</th>
            <th style="{{ $th }}">Teaching methodology</th>
            <th style="{{ $th }}">Teaching aids</th>
            <th style="{{ $th }}">Assessment</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($rows as $unit)
            <tr>
              <td style="{{ $td }} text-align:center">{{ $unit['sequence'] }}</td>
              <td style="{{ $td }}">
                {{ $unit['months'] ?? '—' }}
                @if ($unit['weeks'] !== null)
                  <br><span class="muted">{{ $unit['weeks'] }} {{ $unit['weeks'] === 1 ? 'week' : 'weeks' }}</span>
                @endif
              </td>
              <td style="{{ $td }}"><strong>{{ $unit['title'] }}</strong></td>
              <td style="{{ $td }} text-align:center" class="tnum">{{ $unit['planned_periods'] ?: '—' }}</td>
              <td style="{{ $td }} text-align:center" class="tnum">
                @if ($unit['page_from'] !== null)
                  {{ $unit['page_from'] }}@if ($unit['page_to'] !== null && $unit['page_to'] !== $unit['page_from'])–{{ $unit['page_to'] }}@endif
                @else
                  —
                @endif
              </td>
              <td style="{{ $td }}">{{ $unit['objectives'] ?? '—' }}</td>
              <td style="{{ $td }}">{{ $unit['rationale'] ?? '—' }}</td>
              <td style="{{ $td }}">{{ $unit['prerequisite_knowledge'] ?? '—' }}</td>
              <td style="{{ $td }}">{{ $unit['methods'] ?? '—' }}</td>
              <td style="{{ $td }}">{{ $unit['teaching_aids'] ?? '—' }}</td>
              <td style="{{ $td }}">{{ $unit['assessment_techniques'] ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endforeach

    {{-- Signatures --}}
    <table style="margin-top:22px; font-size:11px">
      <tr>
        <td style="padding:4px 12px 4px 0; width:50%">
          <span class="microlabel">Teacher</span><br>
          {{ $teacher_name }} &nbsp; Sign: ______________
        </td>
        <td style="padding:4px 0 4px 12px; width:50%">
          <span class="microlabel">Approved by</span><br>
          @if ($approved_by !== null)
            {{ $approved_by }} <span class="muted">(approved {{ dualDate($approved_at) }})</span>
          @else
            Name: ______________ &nbsp; Sign: ______________
          @endif
        </td>
      </tr>
    </table>

    <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; margin-top:18px; border-top:1px solid var(--border); padding-top:12px">
      <p class="muted" style="font-size:11px">
        Scan the QR code to verify this plan on Temari.et.
      </p>
      <img src="{{ $qr }}" alt="Verification QR code" style="width:72px; height:72px; flex-shrink:0">
    </div>
  </article>
@endsection
