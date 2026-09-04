/**
 * KaTeX rendering for validated `<span data-math="…latex…">` markers — the
 * math counterpart of the `data-video` marker model in sanitize-html.ts.
 * The LaTeX source lives in the attribute; KaTeX renders it as TEXT, so no
 * user markup ever reaches the DOM through this path.
 *
 * KaTeX (and its CSS) loads on demand only when content actually contains a
 * marker — question lists without math never pay for the bundle (3G rule).
 */

type Katex = typeof import("katex").default

let katexPromise: Promise<Katex> | null = null

// The KaTeX stylesheet is imported once in app/globals.css (tiny, cached);
// its font files download only when a formula actually renders.
function loadKatex(): Promise<Katex> {
  // katex ships CommonJS — normalize the ESM/CJS interop default.
  katexPromise ??= import("katex").then(
    (mod) => (mod as unknown as { default?: Katex }).default ?? (mod as unknown as Katex),
  )
  return katexPromise
}

/** Whether an HTML fragment carries at least one math marker. */
export function hasMath(html: string): boolean {
  return html.includes("data-math")
}

/**
 * Render every `span[data-math]` under `root` in place. Safe to call twice —
 * rendered spans are skipped. A LaTeX typo renders in KaTeX's error color
 * instead of exploding (throwOnError: false).
 */
export async function renderMathIn(root: HTMLElement): Promise<void> {
  const spans = root.querySelectorAll<HTMLElement>("span[data-math]:not([data-math-done])")
  if (spans.length === 0) return
  const katex = await loadKatex()
  spans.forEach((el) => {
    const latex = el.getAttribute("data-math") ?? ""
    el.setAttribute("data-math-done", "1")
    try {
      katex.render(latex, el, {
        throwOnError: false,
        displayMode: el.getAttribute("data-display") === "block",
      })
    } catch {
      el.textContent = latex
    }
  })
}

/** One-off render to an HTML string (the editor's live preview bar). */
export async function renderMathToString(latex: string, display: boolean): Promise<string> {
  const katex = await loadKatex()
  try {
    return katex.renderToString(latex, { throwOnError: false, displayMode: display })
  } catch {
    return latex
  }
}
