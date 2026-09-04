{{-- Payslip in the standard document-card language (mirrors the receipt's
     header/amount-panel/footer and the payroll run detail's line items). --}}
@extends('documents.layout')

@section('title', 'Payslip '.$slip['reference'])

@php
  $etb = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').' ETB';
  $totalDeductions = (float) $slip['income_tax'] + (float) $slip['pension_employee'] + (float) $slip['deductions_total'];
@endphp

@section('content')
  <article class="card">
    {{-- header --}}
    <div class="card-header" style="padding-bottom:20px">
      <div>
        <h2 class="font-display" style="font-size:20px; font-weight:600">{{ $slip['school'] }}</h2>
        <p class="muted" style="font-size:14px">{{ $slip['branch'] }}</p>
      </div>
      <div style="text-align:right">
        @include('documents.partials.brand')
        <p class="font-mono" style="margin-top:6px; font-size:14px; font-weight:600">{{ $slip['reference'] }}</p>
      </div>
    </div>

    <h3 style="margin-top:20px; text-align:center; font-size:14px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase">
      Payslip
    </h3>

    <dl style="margin-top:20px; font-size:14px">
      @foreach ([
          ['Employee', $slip['employee']],
          ['Employee ID', $slip['employee_id']],
          ['Pay period', $slip['run_name']],
          ['Dates', dualDate($slip['period_start']).' — '.dualDate($slip['period_end'])],
      ] as $i => [$label, $value])
        <div class="kv-row" @if($i > 0) style="margin-top:10px" @endif>
          <dt>{{ $label }}</dt>
          <dd>{{ $value }}</dd>
        </div>
      @endforeach
    </dl>

    {{-- earnings --}}
    <p class="microlabel" style="margin-top:24px">Earnings</p>
    <dl style="margin-top:8px; font-size:14px">
      @foreach ($slip['breakdown']['positions'] ?? [] as $i => $position)
        <div class="kv-row" @if($i > 0) style="margin-top:8px" @endif>
          <dt>{{ str_replace('_', ' ', ucfirst($position['job_title'])) }}</dt>
          <dd class="tnum">{{ $etb($position['salary']) }}</dd>
        </div>
      @endforeach
      @foreach ($slip['breakdown']['allowances'] ?? [] as $allowance)
        <div class="kv-row" style="margin-top:8px">
          <dt>{{ $allowance['name'] }}</dt>
          <dd class="tnum">+{{ $etb($allowance['amount']) }}</dd>
        </div>
      @endforeach
      <div class="kv-row" style="margin-top:10px; border-top:1px solid var(--border); padding-top:8px; font-weight:600">
        <dt style="color:var(--foreground)">Gross pay</dt>
        <dd class="tnum">{{ $etb($slip['gross_pay']) }}</dd>
      </div>
    </dl>

    {{-- deductions --}}
    <p class="microlabel" style="margin-top:24px">Deductions</p>
    <dl style="margin-top:8px; font-size:14px">
      <div class="kv-row">
        <dt>Income tax</dt>
        <dd class="tnum">−{{ $etb($slip['income_tax']) }}</dd>
      </div>
      <div class="kv-row" style="margin-top:8px">
        <dt>Pension (7%)</dt>
        <dd class="tnum">−{{ $etb($slip['pension_employee']) }}</dd>
      </div>
      @foreach ($slip['breakdown']['deductions'] ?? [] as $deduction)
        <div class="kv-row" style="margin-top:8px">
          <dt>{{ $deduction['name'] }}</dt>
          <dd class="tnum">−{{ $etb($deduction['amount']) }}</dd>
        </div>
      @endforeach
      <div class="kv-row" style="margin-top:10px; border-top:1px solid var(--border); padding-top:8px; font-weight:600">
        <dt style="color:var(--foreground)">Total deductions</dt>
        <dd class="tnum">−{{ $etb($totalDeductions) }}</dd>
      </div>
    </dl>

    {{-- net pay panel (mirrors the receipt's amount panel) --}}
    <div style="margin-top:20px; border-radius:12px; background:oklch(0.958 0.006 110 / 0.4); padding:12px 16px; text-align:center">
      <p class="muted" style="font-size:12px; text-transform:uppercase; letter-spacing:0.025em">Net pay</p>
      <p class="font-display tnum" style="margin-top:2px; font-size:24px; font-weight:700">{{ $etb($slip['net_pay']) }}</p>
      <p class="muted" style="margin-top:4px; font-size:12px">
        Employer pension (11%): {{ $etb($slip['pension_employer']) }} — paid by the school, not deducted
      </p>
    </div>

    {{-- footer: verify hint + QR --}}
    <div style="margin-top:24px; display:flex; align-items:flex-end; justify-content:space-between; gap:16px; border-top:1px solid var(--border); padding-top:20px">
      <p class="muted" style="max-width:384px; font-size:11px; line-height:1.625">
        Scan the QR code to verify this payslip against the school's records on
        Temari.et. If the details differ from this paper, trust the online copy.
      </p>
      <img src="{{ $qr }}" alt="Verification QR code" style="width:96px; height:96px; flex-shrink:0">
    </div>
  </article>
@endsection
