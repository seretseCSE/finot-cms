<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 4px; color: #1B4F72; }
        .subtitle { font-size: 11px; color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #1B4F72; color: #fff; font-weight: bold; }
        tr:nth-child(even) td { background: #f4f7fa; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    @if (!empty($subtitle))
        <div class="subtitle">{{ $subtitle }}</div>
    @endif
    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell === '' || $cell === null ? '—' : $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($headings), 1) }}">No rows</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
