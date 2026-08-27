{{-- Fit-to-cell: the per-cell sibling of fit-to-page. The N-up report-card
     sheets are fixed grids (each student owns exactly a half or quarter of
     the page), so overflow can't push a neighbour — but a pathological
     subject list could CLIP inside its cell. This pass measures each card at
     its natural height and zooms it down until it fits its cell, so every
     mark always prints. Iterates on the painted height (zoom reflows) and
     re-runs once fonts settle, exactly like fit-to-page.

     Variables: $pageWidth in CSS px (default = A4 portrait at 96 dpi). --}}
@php
  $pageWidth ??= 793;
@endphp
<script>
  (function () {
    var PAGE_W = {{ $pageWidth }};
    var PASSES = 4;

    function fit() {
      // Lay out exactly as the printer will before measuring anything.
      document.body.style.width = PAGE_W + 'px';

      Array.prototype.forEach.call(document.querySelectorAll('.cell'), function (cell) {
        var card = cell.firstElementChild;

        if (!card) {
          return;
        }

        card.style.zoom = '';
        var zoom = 1;

        for (var pass = 0; pass < PASSES; pass++) {
          // Natural height: the 100%-height flex card always matches its
          // cell, so measure with height released, then restore.
          card.style.height = 'auto';
          var height = card.getBoundingClientRect().height;
          card.style.height = '';

          var target = cell.getBoundingClientRect().height * 0.99;

          if (height <= target) {
            break;
          }

          zoom = zoom * target / height;
          card.style.zoom = zoom.toFixed(4);
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
