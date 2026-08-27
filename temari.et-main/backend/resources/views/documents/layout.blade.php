{{-- Shared shell for every official PDF. Deliberately a pixel-faithful
     replica of the frontend article components (receipt-article.tsx,
     letter-article.tsx, …): same fonts (Geist / Outfit / Geist Mono / Noto
     Sans Ethiopic), same design tokens from app/globals.css, same card. If
     the design system changes, change BOTH sides. --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>@yield('title')</title>
<style>
  {!! App\Support\PdfFonts::css() !!}

  @page { size: A4; margin: 0; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  :root {
    /* app/globals.css light tokens */
    --background: oklch(0.988 0.004 95);
    --foreground: oklch(0.215 0.02 160);
    --card: oklch(1 0 0);
    --muted: oklch(0.958 0.006 110);
    --muted-foreground: oklch(0.5 0.018 145);
    --primary: oklch(0.47 0.115 155);
    --destructive: oklch(0.55 0.2 27);
    --border: oklch(0.912 0.008 120);
  }

  body {
    font-family: "Geist", "Noto Sans Ethiopic", ui-sans-serif, system-ui, sans-serif;
    color: var(--foreground);
    background: var(--card);
    font-size: 16px;
    line-height: 1.5;
  }

  .font-display { font-family: "Outfit", "Geist", "Noto Sans Ethiopic", sans-serif; }
  .font-mono { font-family: "Geist Mono", ui-monospace, monospace; }
  .font-ethiopic { font-family: "Noto Sans Ethiopic", sans-serif; }
  .muted { color: var(--muted-foreground); }
  .tnum { font-variant-numeric: tabular-nums; }

  /* Same content as the frontend article card, but PAPER-style: the sheet
     itself is the card — no border, no rounded corners, full width. */
  .card {
    background: var(--card);
    padding: 48px 56px;
  }

  /* header: flex items-start justify-between gap-4 border-b */
  .card-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 16px; border-bottom: 1px solid var(--border);
  }

  /* Logo lockup (components/ui/logo.tsx, size=md) */
  .logo { display: flex; align-items: center; gap: 10px; justify-content: flex-end; }
  .logo .tile {
    width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
    display: block; object-fit: contain;
    /* The PNG already carries the green tile + rounded corners. */
  }
  .logo .word {
    font-family: "Outfit", sans-serif; font-weight: 700; font-size: 18px;
    letter-spacing: -0.025em; line-height: 1;
  }
  .logo .word .et { color: var(--primary); }

  /* label/value rows (receipt) + uppercase micro-labels (letters) */
  .kv-row { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; }
  .kv-row dt { flex-shrink: 0; color: var(--muted-foreground); }
  .kv-row dd { min-width: 0; text-align: right; font-weight: 500; }
  .microlabel {
    font-size: 12px; font-weight: 600; letter-spacing: 0.025em;
    color: var(--muted-foreground); text-transform: uppercase;
  }

  table { border-collapse: collapse; width: 100%; }
</style>
</head>
<body>
  @yield('content')
</body>
</html>
