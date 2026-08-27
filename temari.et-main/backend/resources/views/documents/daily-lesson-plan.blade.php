{{-- The official daily lesson plan — the MoE daily format: header block
     (teacher, subject, grade, period, E.C. date, unit & topic, subtopic,
     rationale, prerequisite knowledge, objectives), the three-stage table
     (learning contents · page · teacher's activity · student's activity ·
     assessment techniques · teaching aids · remark), the special-need
     learner supports, and the signature chain. Landscape A4. --}}
@extends('documents.layout')

@section('title', 'Daily lesson plan — '.($subject_name ?? '').' '.($teaches_on ?? ''))

@php
  $cellBorder = 'border:1px solid var(--border);';
  $th = $cellBorder.' padding:5px 6px; text-align:left; font-weight:600; font-size:10px; background:var(--muted);';
  $td = $cellBorder.' padding:6px; font-size:10.5px; vertical-align:top;';

  $periodLabels = collect($sittings)
      ->map(fn ($s) => trim(($s['section'] ?? '').($s['period_number'] !== null ? ' · P'.$s['period_number'] : '')))
      ->filter()->implode(', ');
@endphp

@section('content')
  <style>
    @page { size: A4 landscape; margin: 12mm 10mm; }
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
        <p class="muted" style="margin-top:6px; font-size:12px">Daily lesson plan</p>
      </div>
    </div>

    {{-- Header block --}}
    <table style="font-size:12px; margin-bottom:10px">
      <tr>
        <td style="padding:2px 0; width:30%"><span class="microlabel">Teacher</span><br><strong>{{ $teacher_name }}</strong></td>
        <td style="padding:2px 0; width:18%"><span class="microlabel">Subject</span><br><strong>{{ $subject_name }}</strong></td>
        <td style="padding:2px 0; width:14%"><span class="microlabel">Grade</span><br><strong>{{ $grade_name }}</strong></td>
        <td style="padding:2px 0; width:20%"><span class="microlabel">Section · period</span><br><strong>{{ $periodLabels !== '' ? $periodLabels : '—' }}</strong></td>
        <td style="padding:2px 0; width:18%"><span class="microlabel">Day / date</span><br><strong>{{ $ec_date }}</strong> <span class="muted">({{ $teaches_on }})</span></td>
      </tr>
    </table>

    <table style="font-size:12px; margin-bottom:12px">
      <tr>
        <td style="padding:2px 0; width:50%">
          <span class="microlabel">Unit &amp; topic</span><br>
          <strong>{{ $unit_title !== null ? $unit_title.' — ' : '' }}{{ $topic }}</strong>
          @if ($subtopic !== null)<br><span class="muted">Subtopic: {{ $subtopic }}</span>@endif
        </td>
        <td style="padding:2px 0 2px 16px; width:25%">
          <span class="microlabel">Rationale of the topic</span><br>{{ $rationale ?? '—' }}
        </td>
        <td style="padding:2px 0 2px 16px; width:25%">
          <span class="microlabel">Prerequisite knowledge</span><br>{{ $prerequisite_knowledge ?? '—' }}
        </td>
      </tr>
    </table>

    <div style="margin-bottom:12px">
      <span class="microlabel">Objective — at the end of this lesson the students will be able to:</span>
      <p style="font-size:11.5px">{{ $objectives ?? '—' }}</p>
    </div>

    {{-- Stage table --}}
    <table style="margin-bottom:12px">
      <thead>
        <tr>
          <th style="{{ $th }} width:92px">Stage</th>
          <th style="{{ $th }}">Learning contents</th>
          <th style="{{ $th }} width:44px">Page</th>
          <th style="{{ $th }}">Teacher's activity</th>
          <th style="{{ $th }}">Student's activity</th>
          <th style="{{ $th }} width:110px">Assessment techniques</th>
          <th style="{{ $th }} width:100px">Teaching aids</th>
          <th style="{{ $th }} width:80px">Remark</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($stages as $stage)
          <tr>
            <td style="{{ $td }} font-weight:600">{{ $stage['label'] }}</td>
            <td style="{{ $td }}">{{ $stage['learning_contents'] ?? '—' }}</td>
            <td style="{{ $td }} text-align:center" class="tnum">{{ $stage['page'] ?? '—' }}</td>
            <td style="{{ $td }}">{{ $stage['teacher_activity'] ?? '—' }}</td>
            <td style="{{ $td }}">{{ $stage['student_activity'] ?? '—' }}</td>
            <td style="{{ $td }}">{{ $stage['assessment_techniques'] ?? '—' }}</td>
            <td style="{{ $td }}">{{ $stage['teaching_aids'] ?? '—' }}</td>
            <td style="{{ $td }}">{{ $stage['remark'] ?? '—' }}</td>
          </tr>
        @empty
          <tr><td style="{{ $td }} text-align:center" colspan="8" class="muted">No stages recorded.</td></tr>
        @endforelse
      </tbody>
    </table>

    {{-- Special-need learner supports --}}
    <table style="margin-bottom:14px">
      <tr><td style="{{ $td }} width:160px; font-weight:600">Support for learners of special need</td><td style="{{ $td }}"></td></tr>
      <tr><td style="{{ $td }}">Slow learners</td><td style="{{ $td }}">{{ $support_slow ?? '—' }}</td></tr>
      <tr><td style="{{ $td }}">Medium learners</td><td style="{{ $td }}">{{ $support_medium ?? '—' }}</td></tr>
      <tr><td style="{{ $td }}">Fast learners</td><td style="{{ $td }}">{{ $support_fast ?? '—' }}</td></tr>
      @if ($homework !== null)
        <tr><td style="{{ $td }}">Homework</td><td style="{{ $td }}">{{ $homework }}</td></tr>
      @endif
    </table>

    {{-- Signatures --}}
    <table style="font-size:11px">
      <tr>
        <td style="padding:4px 12px 4px 0; width:50%">
          <span class="microlabel">Teacher</span><br>
          {{ $teacher_name }} &nbsp; Sign: ______________
        </td>
        <td style="padding:4px 0 4px 12px; width:50%">
          <span class="microlabel">Approved by</span><br>
          @if ($approved_by !== null)
            {{ $approved_by }} <span class="muted">(approved)</span>
          @else
            Name: ______________ &nbsp; Sign: ______________
          @endif
        </td>
      </tr>
    </table>

    <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; margin-top:16px; border-top:1px solid var(--border); padding-top:12px">
      <p class="muted" style="font-size:11px">
        Scan the QR code to verify this plan on Temari.et.
      </p>
      <img src="{{ $qr }}" alt="Verification QR code" style="width:72px; height:72px; flex-shrink:0">
    </div>
  </article>
@endsection
