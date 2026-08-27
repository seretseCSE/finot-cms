{{-- The Temari brand lockup for official PDFs: the real app-icon logo
     (public/images/logo/web-app-manifest-512x512.png — the sprouting
     seedling on the green tile) plus the wordmark. Embedded as a base64
     data URI because the PDF renderer (Cloudflare Browser Rendering) gets
     fully self-contained HTML and can't fetch local file paths. NEVER the
     ተ text glyph (the PDF font can't render it and falls back to a stray
     dagger). One mark, every document. --}}
@php
  $temariLogo = 'data:image/png;base64,'.base64_encode(
    file_get_contents(public_path('images/logo/web-app-manifest-512x512.png'))
  );
@endphp
<span class="logo">
  <img class="tile" src="{{ $temariLogo }}" alt="Temari.et logo">
  <span class="word">Temari<span class="et">.et</span></span>
</span>
