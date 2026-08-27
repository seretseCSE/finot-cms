"use client"

import { useEffect, useState } from "react"

import { useTranslation } from "@/lib/i18n"
import { renderMathToString } from "@/lib/math"
import { cn } from "@/lib/utils"

/**
 * The Word/Docs-style equation picker inside the editor's formula bar:
 * structure templates (fraction, root, integral…) and symbol pads (Greek,
 * operators, arrows & sets). Every button INSERTS LaTeX at the caret of the
 * formula input — teachers click their way to a formula and never have to
 * know LaTeX, while the underlying storage stays the same `data-math`
 * marker.
 *
 * `caret` is where the input caret should land inside the inserted snippet
 * (e.g. inside the first `{}` of a fraction) so the next click/typing fills
 * the right slot.
 */

interface Template {
  /** Rendered (KaTeX) preview shown on the button. */
  preview: string
  insert: string
  caret: number
}

const TEMPLATES: Template[] = [
  { preview: "\\frac{a}{b}", insert: "\\frac{}{}", caret: 6 },
  { preview: "x^{n}", insert: "^{}", caret: 2 },
  { preview: "x_{n}", insert: "_{}", caret: 2 },
  { preview: "\\sqrt{x}", insert: "\\sqrt{}", caret: 6 },
  { preview: "\\sqrt[n]{x}", insert: "\\sqrt[3]{}", caret: 9 },
  { preview: "\\int_{a}^{b}", insert: "\\int_{}^{}", caret: 6 },
  { preview: "\\sum_{i=1}^{n}", insert: "\\sum_{}^{}", caret: 6 },
  { preview: "\\lim_{x\\to 0}", insert: "\\lim_{x\\to }", caret: 11 },
  { preview: "\\vec{a}", insert: "\\vec{}", caret: 5 },
  { preview: "\\overline{x}", insert: "\\overline{}", caret: 10 },
  { preview: "\\binom{n}{k}", insert: "\\binom{}{}", caret: 7 },
  { preview: "\\left(x\\right)", insert: "\\left(\\right)", caret: 6 },
]

/** [glyph shown on the button, LaTeX inserted] */
type Sym = [string, string]

const GREEK: Sym[] = [
  ["α", "\\alpha"], ["β", "\\beta"], ["γ", "\\gamma"], ["δ", "\\delta"],
  ["ε", "\\varepsilon"], ["θ", "\\theta"], ["λ", "\\lambda"], ["μ", "\\mu"],
  ["π", "\\pi"], ["ρ", "\\rho"], ["σ", "\\sigma"], ["φ", "\\phi"],
  ["ω", "\\omega"], ["Δ", "\\Delta"], ["Σ", "\\Sigma"], ["Ω", "\\Omega"],
]

const OPERATORS: Sym[] = [
  ["±", "\\pm"], ["×", "\\times"], ["÷", "\\div"], ["·", "\\cdot"],
  ["≤", "\\le"], ["≥", "\\ge"], ["≠", "\\ne"], ["≈", "\\approx"],
  ["≡", "\\equiv"], ["∞", "\\infty"], ["∝", "\\propto"], ["°", "^\\circ"],
  ["∴", "\\therefore"], ["%", "\\%"], ["!", "!"], ["√", "\\sqrt{}"],
]

const ARROWS_SETS: Sym[] = [
  ["→", "\\rightarrow"], ["←", "\\leftarrow"], ["↔", "\\leftrightarrow"], ["⇒", "\\Rightarrow"],
  ["⇌", "\\rightleftharpoons"], ["∈", "\\in"], ["∉", "\\notin"], ["⊂", "\\subset"],
  ["∪", "\\cup"], ["∩", "\\cap"], ["∅", "\\varnothing"], ["∠", "\\angle"],
  ["⊥", "\\perp"], ["∥", "\\parallel"], ["∀", "\\forall"], ["∃", "\\exists"],
]

type Category = "templates" | "greek" | "operators" | "arrows"

const CATEGORIES: Category[] = ["templates", "greek", "operators", "arrows"]

// KaTeX previews for the template buttons, rendered once per session.
let templatePreviewCache: Promise<string[]> | null = null

function templatePreviews(): Promise<string[]> {
  templatePreviewCache ??= Promise.all(
    TEMPLATES.map((tpl) => renderMathToString(tpl.preview, false)),
  )
  return templatePreviewCache
}

export function MathPalette({
  onInsert,
  className,
}: {
  /** Insert a LaTeX snippet at the formula input's caret. */
  onInsert: (snippet: string, caret?: number) => void
  className?: string
}) {
  const { t } = useTranslation("common")
  const [category, setCategory] = useState<Category>("templates")
  const [previews, setPreviews] = useState<string[] | null>(null)

  useEffect(() => {
    let cancelled = false
    void templatePreviews().then((rendered) => {
      if (!cancelled) setPreviews(rendered)
    })
    return () => {
      cancelled = true
    }
  }, [])

  const symbols: Sym[] | null =
    category === "greek" ? GREEK : category === "operators" ? OPERATORS : category === "arrows" ? ARROWS_SETS : null

  return (
    <div className={cn("w-full", className)}>
      {/* category pills */}
      <div className="flex flex-wrap gap-1">
        {CATEGORIES.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setCategory(key)}
            className={cn(
              "rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
              category === key
                ? "border-primary/40 bg-primary/10 text-primary"
                : "text-muted-foreground hover:bg-muted/60",
            )}
          >
            {t(`richText.mathCat.${key}`)}
          </button>
        ))}
      </div>

      {/* pads */}
      <div className="mt-2 max-h-44 overflow-y-auto">
        {category === "templates" ? (
          <div className="grid grid-cols-4 gap-1 sm:grid-cols-6">
            {TEMPLATES.map((tpl, index) => (
              <button
                key={tpl.insert}
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => onInsert(tpl.insert, tpl.caret)}
                className="flex h-11 items-center justify-center rounded-lg border bg-background text-sm transition-colors hover:border-primary/40 hover:bg-primary/5"
                aria-label={tpl.insert}
              >
                {previews ? (
                  // KaTeX output rendered from OUR OWN template strings.
                  <span dangerouslySetInnerHTML={{ __html: previews[index] }} />
                ) : (
                  <span className="font-mono text-[10px] text-muted-foreground">…</span>
                )}
              </button>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-8 gap-1">
            {(symbols ?? []).map(([glyph, latex]) => (
              <button
                key={latex}
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() =>
                  latex.endsWith("{}") ? onInsert(latex, latex.length - 1) : onInsert(`${latex} `)
                }
                className="flex h-10 items-center justify-center rounded-lg border bg-background text-base transition-colors hover:border-primary/40 hover:bg-primary/5"
                aria-label={latex}
              >
                {glyph}
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
