{{-- Mirror of the Statement card on /finance (app/(app)/finance/page.tsx),
     wrapped in the standard document card with the school header. --}}
@extends('documents.layout')

@section('title', 'Income & expense statement')

@php
  $etb = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').' ETB';
  $net = (float) $statement['net'];
@endphp

@section('content')
  <article class="card" style="padding:0; overflow:hidden">
    {{-- school header --}}
    <div class="card-header" style="padding:48px 56px 20px">
      <div>
        <h2 class="font-display" style="font-size:20px; font-weight:600">{{ $school_name }}</h2>
        <p class="muted" style="font-size:14px">{{ $branch_name ?? 'All branches' }}</p>
      </div>
      <div style="text-align:right">
        @include('documents.partials.brand')
        <p class="muted tnum" style="margin-top:6px; font-size:12px">{{ $from }} — {{ $to }}</p>
      </div>
    </div>

    <div style="padding:0 56px">
      <h3 style="margin-top:20px; text-align:center; font-size:14px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase">
        Income & expense statement
      </h3>
    </div>

    {{-- income --}}
    <div style="padding:16px 56px 16px">
      <p style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; letter-spacing:0.025em; text-transform:uppercase; color:oklch(0.55 0.13 152)">
        Income
      </p>
      <ul style="margin-top:8px; list-style:none; font-size:14px">
        <li style="display:flex; align-items:baseline; justify-content:space-between; gap:12px; padding:3px 0">
          <span>School fees</span>
          <span class="tnum" style="font-weight:500">{{ $etb($statement['income']['school_fees']) }}</span>
        </li>
        @foreach ($statement['income']['other'] as $row)
          <li style="display:flex; align-items:baseline; justify-content:space-between; gap:12px; padding:3px 0">
            <span style="min-width:0">{{ $row['category'] }}</span>
            <span class="tnum" style="font-weight:500">{{ $etb($row['amount']) }}</span>
          </li>
        @endforeach
      </ul>
      <div style="margin-top:10px; display:flex; align-items:baseline; justify-content:space-between; border-top:1px solid var(--border); padding-top:8px; font-size:14px; font-weight:600">
        <span>Total income</span>
        <span class="tnum">{{ $etb($statement['income']['total']) }}</span>
      </div>
    </div>

    {{-- expenses --}}
    <div style="border-top:1px solid var(--border); padding:16px 56px">
      <p style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; letter-spacing:0.025em; text-transform:uppercase; color:var(--destructive)">
        Expenses
      </p>
      <ul style="margin-top:8px; list-style:none; font-size:14px">
        <li style="display:flex; align-items:baseline; justify-content:space-between; gap:12px; padding:3px 0">
          <span>Payroll</span>
          <span class="tnum" style="font-weight:500">{{ $etb($statement['expenses']['payroll']) }}</span>
        </li>
        @foreach ($statement['expenses']['categories'] as $row)
          <li style="display:flex; align-items:baseline; justify-content:space-between; gap:12px; padding:3px 0">
            <span style="min-width:0">{{ $row['category'] }}</span>
            <span class="tnum" style="font-weight:500">{{ $etb($row['amount']) }}</span>
          </li>
        @endforeach
      </ul>
      <div style="margin-top:10px; display:flex; align-items:baseline; justify-content:space-between; border-top:1px solid var(--border); padding-top:8px; font-size:14px; font-weight:600">
        <span>Total expenses</span>
        <span class="tnum">{{ $etb($statement['expenses']['total']) }}</span>
      </div>
    </div>

    {{-- net --}}
    <div style="display:flex; align-items:baseline; justify-content:space-between; border-top:1px solid var(--border); padding:16px 56px; background:{{ $net >= 0 ? 'oklch(0.55 0.13 152 / 0.05)' : 'oklch(0.55 0.2 27 / 0.05)' }}">
      <p style="font-size:14px; font-weight:600">Net</p>
      <p class="font-display tnum" style="font-size:20px; font-weight:700; color:{{ $net >= 0 ? 'oklch(0.55 0.13 152)' : 'var(--destructive)' }}">
        {{ $etb($statement['net']) }}
      </p>
    </div>

    {{-- verification footer --}}
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; border-top:1px solid var(--border); padding:20px 56px 48px">
      <p class="muted" style="max-width:384px; font-size:11px; line-height:1.625">
        Money in counts recorded fee payments and other income; money out counts
        approved expenses and approved/paid payroll runs. Scan the QR code to
        verify this statement on Temari.et.
      </p>
      <img src="{{ $qr }}" alt="Verification QR code" style="width:96px; height:96px; flex-shrink:0">
    </div>
  </article>
@endsection
