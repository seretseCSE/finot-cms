{{-- Mirror of components/transfers/letter-article.tsx — keep the two in step. --}}
@extends('documents.layout')

@section('title', 'Transfer letter '.$letter['reference'])

@section('content')
  @php
    $dl = '';
    $block = function (string $label, string $value): string {
        return '<div><dt class="microlabel">'.e($label).'</dt>'
            .'<dd style="margin-top:4px; font-weight:500">'.e($value).'</dd></div>';
    };
    $dl .= $block('Last attended',
        $letter['last_grade']
        .($letter['last_section'] ? ' — '.$letter['last_section'] : '')
        .' · '.$letter['last_academic_year']);
    $dl .= $block('Transferred to', $letter['to_school'].' — '.$letter['to_branch']);
    $dl .= $block('Placed into', $letter['new_grade'].' · '.$letter['new_academic_year']);
    if ($letter['reason']) {
        $dl .= '<div><dt class="microlabel">Reason</dt>'
            .'<dd style="margin-top:4px">'.e($letter['reason']).'</dd></div>';
    }
  @endphp

  @include('documents.partials.letter-shell', [
      'school' => $letter['from_school'],
      'branch' => $letter['from_branch'],
      'reference' => $letter['reference'],
      'letterTitle' => 'Student transfer letter',
      'student' => $letter['student'],
      'slotDl' => $dl,
      'signLabel' => 'Approved by',
      'signName' => $letter['approved_by'],
      'signDate' => dualDate($letter['approved_at']),
      'qr' => $qr,
  ])
@endsection
