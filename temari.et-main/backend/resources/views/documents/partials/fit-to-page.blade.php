{{-- Fit-to-one-page. The renderer is real Chromium, so each sheet measures
     itself and shrinks (zoom REFLOWS, so nothing clips) until it fits a single
     page. Long histories print smaller, never onto a second page — which in a
     batch would also push every following student out of step.

     Three things a naive "zoom = pageHeight / height" gets wrong, all of them
     observed while printing a five-year transcript:

     1. The measurement must happen in the PRINT layout, not the screen one.
        The renderer's viewport is not A4-wide, and a different width means a
        different height, so the body is pinned to the page width first.
     2. Zooming REFLOWS the sheet, so one open-loop calculation can miss. The
        pass iterates on the painted height (getBoundingClientRect already
        reports it scaled) until the sheet really fits.
     3. Chromium's print layout rounds each row a hair taller than the screen
        layout, and that error accumulates over ~40 grid rows — enough to spill
        a sheet that measured as fitting. SAFETY absorbs it; at 1.5% the
        difference is invisible, and a second page never is.

     Re-runs after fonts settle, because font metrics change the height.

     Variables: $pageWidth / $pageHeight in CSS px (defaults = A4 landscape at
     Chromium's 96 dpi, 1122.5 × 793.7). --}}
@php
  $pageWidth ??= 1122;
  $pageHeight ??= 793;
@endphp
<script>
  (function () {
    var PAGE_W = {{ $pageWidth }};
    var TARGET = {{ $pageHeight }} * 0.985;
    var PASSES = 6;

    function fit() {
      // Lay out exactly as the printer will before measuring anything.
      document.body.style.width = PAGE_W + 'px';

      Array.prototype.forEach.call(document.querySelectorAll('.sheet'), function (sheet) {
        // Re-fitting must start from the unscaled sheet, never compound.
        sheet.style.zoom = '';

        var zoom = 1;

        for (var pass = 0; pass < PASSES; pass++) {
          var height = sheet.getBoundingClientRect().height;

          if (height <= TARGET) {
            break;
          }

          zoom = zoom * TARGET / height;
          sheet.style.zoom = zoom.toFixed(4);
        }
      });
    }

    fit();
    window.addEventListener('load', fit);

    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(fit);
    }
  })();
</script>
