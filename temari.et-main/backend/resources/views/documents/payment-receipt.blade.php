{{-- Mirror of components/fees/receipt-article.tsx — keep the two in step. --}}
@extends('documents.layout')

@php
  // JS Number.toLocaleString parity: whole numbers drop the decimals.
  $etb = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.').' ETB';

  $rows = array_filter([
      ['Student', $receipt['student']['full_name']],
      ['Student ID', $receipt['student']['public_id']],
      ['Paid for', ($receipt['invoice_title'] ?? '—').' ('.$receipt['invoice_number'].')'],
      ['Method', $receipt['method_label']],
      ['Reference', $receipt['reference']],
      ['Received into', $receipt['bank_account']
          ? trim(($receipt['bank_account']['bank_name'] ?? '').' '.$receipt['bank_account']['account_number'])
          : null],
      ['Paid on', dualDate($receipt['paid_at'])],
      ['Recorded by', $receipt['recorded_by']],
  ], fn ($row) => filled($row[1]));
@endphp

@section('title', 'Receipt '.$receipt['receipt_number'])

@section('content')
  <article class="card">
    {{-- header --}}
    <div class="card-header" style="padding-bottom:20px">
      <div>
        <h2 class="font-display" style="font-size:20px; font-weight:600">{{ $receipt['school'] }}</h2>
        <p class="muted" style="font-size:14px">{{ $receipt['branch'] }}</p>
      </div>
      <div style="text-align:right">
        @include('documents.partials.brand')
        <p class="font-mono" style="margin-top:6px; font-size:14px; font-weight:600">{{ $receipt['receipt_number'] }}</p>
      </div>
    </div>

    <h3 style="margin-top:20px; text-align:center; font-size:14px; font-weight:600; letter-spacing:0.1em; text-transform:uppercase">
      Payment receipt
    </h3>

    <dl style="margin-top:20px; font-size:14px">
      @foreach ($rows as $i => [$label, $value])
        <div class="kv-row" @if($i > 0) style="margin-top:10px" @endif>
          <dt>{{ $label }}</dt>
          <dd>{{ $value }}</dd>
        </div>
      @endforeach
    </dl>

    {{-- amount panel: rounded-xl bg-muted/40 px-4 py-3 --}}
    <div style="margin-top:20px; border-radius:12px; background:oklch(0.958 0.006 110 / 0.4); padding:12px 16px; text-align:center">
      <p class="muted" style="font-size:12px; text-transform:uppercase; letter-spacing:0.025em">Amount paid</p>
      <p class="font-display tnum" style="margin-top:2px; font-size:24px; font-weight:700">{{ $etb($receipt['amount']) }}</p>
      @if ($receipt['invoice_total_due'] !== null)
        <p class="muted" style="margin-top:4px; font-size:12px">
          {{ $etb($receipt['invoice_amount_paid']) }} of {{ $etb($receipt['invoice_total_due']) }} settled on this invoice
        </p>
      @endif
    </div>

    {{-- footer: verify hint + QR --}}
    <div style="margin-top:24px; display:flex; align-items:flex-end; justify-content:space-between; gap:16px; border-top:1px solid var(--border); padding-top:20px">
      <p class="muted" style="max-width:384px; font-size:11px; line-height:1.625">
        Scan the QR code to verify this receipt against the school's records on
        Temari.et. If the details differ from this paper, trust the online copy.
      </p>
      <img src="{{ $qr }}" alt="Verification QR code" style="width:96px; height:96px; flex-shrink:0">
    </div>
  </article>
@endsection
