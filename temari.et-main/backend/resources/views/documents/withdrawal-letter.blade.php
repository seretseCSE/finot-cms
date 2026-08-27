{{-- Mirror of components/transfers/withdrawal-letter-article.tsx — keep in step. --}}
@extends('documents.layout')

@section('title', 'Withdrawal letter '.$letter['reference'])

@section('content')
  @php
    $block = function (string $label, string $value): string {
        return '<div><dt class="microlabel">'.e($label).'</dt>'
            .'<dd style="margin-top:4px; font-weight:500">'.e($value).'</dd></div>';
    };

    $dl = $block('Last attended',
        $letter['last_grade']
        .($letter['last_section'] ? ' — '.$letter['last_section'] : '')
        .' · '.$letter['last_academic_year']);
    $dl .= $block('Withdrawal date', dualDate($letter['withdrawn_on']));
    if ($letter['destination']) {
        $dl .= $block('Destination', $letter['destination']);
    }
    $dl .= '<div><dt class="microlabel">Reason</dt>'
        .'<dd style="margin-top:4px">'.e($letter['reason'] ?? '—').'</dd></div>';

    // The RTE-style rule: never withheld over fees — the debt is noted instead.
    $outstanding = (float) $letter['outstanding_amount'];
    $note = $outstanding > 0
        ? '<p style="margin-top:24px; border:1px dashed var(--border); border-radius:12px;'
            .' background:oklch(0.958 0.006 110 / 0.4); padding:12px; font-size:14px">'
            .'Outstanding balance at withdrawal: '.e(number_format($outstanding)).' ETB — recorded by the school; this letter remains valid.'
            .'</p>'
        : '';
  @endphp

  @include('documents.partials.letter-shell', [
      'school' => $letter['school'],
      'branch' => $letter['branch'],
      'reference' => $letter['reference'],
      'letterTitle' => 'Withdrawal & clearance letter',
      'student' => $letter['student'],
      'slotDl' => $dl,
      'noteHtml' => $note,
      'signLabel' => 'Issued by',
      'signName' => $letter['issued_by'],
      'signDate' => dualDate($letter['withdrawn_on']),
      'qr' => $qr,
  ])
@endsection
