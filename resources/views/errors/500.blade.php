<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Something went wrong' }} | {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; background: #F7F4EC; color: #111; }
        .box { max-width: 28rem; padding: 2rem; text-align: center; }
        a { color: #1A44F7; }
        h1 { font-size: 1.5rem; margin-bottom: 0.75rem; }
        p { color: #555; line-height: 1.5; }
    </style>
</head>
<body>
    <main class="box" role="main">
        <h1>{{ $heading ?? 'Something went wrong' }}</h1>
        <p>{{ $message ?? 'We could not complete that request. Please try again, or return home.' }}</p>
        <p><a href="{{ url('/') }}">Back to home</a></p>
    </main>
</body>
</html>
