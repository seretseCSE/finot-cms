<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Withdrawal record</title>
    <style>
        body { font-family: Georgia, serif; margin: 2rem; color: #111; }
        h1 { margin-bottom: 0.25rem; }
        .meta { color: #444; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.4rem 0; border-bottom: 1px solid #ddd; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <p class="noprint"><button onclick="window.print()">Print</button></p>
    <h1>Withdrawal record</h1>
    <p class="meta">FINOTE TSIDIK · never deleted · {{ $withdrawal->created_at?->toDayDateTimeString() }}</p>
    <table>
        <tr><th>Student</th><td>{{ $withdrawal->member?->full_name }}</td></tr>
        <tr><th>Class</th><td>{{ $withdrawal->class?->name }}</td></tr>
        <tr><th>Reason</th><td>{{ $withdrawal->reason }}</td></tr>
        <tr><th>Destination</th><td>{{ $withdrawal->destination ?: '—' }}</td></tr>
        <tr><th>Status</th><td>{{ $withdrawal->status->value }}</td></tr>
        <tr><th>Requested by</th><td>{{ $withdrawal->requestedBy?->name }} · {{ $withdrawal->requested_at }}</td></tr>
        <tr><th>Education decision</th><td>{{ $withdrawal->educationDecidedBy?->name }} · {{ $withdrawal->education_decided_at }}</td></tr>
        <tr><th>Finalized</th><td>{{ $withdrawal->finalizedBy?->name }} · {{ $withdrawal->finalized_at }}</td></tr>
        <tr><th>Effective date</th><td>{{ $withdrawal->effective_date }}</td></tr>
        <tr><th>Enrollment removed_at</th><td>{{ $withdrawal->enrollment?->removed_at }}</td></tr>
    </table>
</body>
</html>
