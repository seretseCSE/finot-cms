/**
 * Tiny allowlist HTML sanitizer for question stems (WYSIWYG output).
 * The backend sanitizes on write (QuestionRules::sanitizeStem) — this is the
 * matching render-time defence so nothing script-bearing ever reaches the
 * DOM, even from legacy rows. Dependency-free: uses the browser's parser.
 */

const ALLOWED_TAGS = new Set([
  "P",
  "BR",
  "B",
  "STRONG",
  "I",
  "EM",
  "U",
  "S",
  "STRIKE",
  "DEL",
  "SUB",
  "SUP",
  "H1",
  "H2",
  "H3",
  "H4",
  "BLOCKQUOTE",
  "PRE",
  "CODE",
  "HR",
  "UL",
  "OL",
  "LI",
  "IMG",
  "A",
  "SPAN",
  "DIV",
])

const ALLOWED_ATTRS: Record<string, string[]> = {
  IMG: ["src", "alt", "data-path"],
  A: ["href"],
  // A video embed marker — the only trusted value is `provider:id`, from which
  // the renderer builds a fixed-host iframe. Never a raw iframe or user URL.
  DIV: ["data-video"],
  // A math marker: the attribute holds LaTeX SOURCE that KaTeX renders as
  // text (lib/math.ts) — it can never smuggle markup into the DOM.
  SPAN: ["data-math", "data-display"],
}

/** `youtube:<id>` / `vimeo:<id>` — the only shape a video marker may hold. */
export const VIDEO_MARKER_RE = /^(youtube|vimeo):[A-Za-z0-9_-]{1,32}$/

/**
 * Parse a pasted YouTube/Vimeo URL into a `{ provider, id }` pair, or null.
 * Accepts the common share/watch/embed/shorts forms; the id is constrained to
 * URL-safe characters so it can never carry markup into the embed.
 */
export function parseVideoUrl(raw: string): { provider: "youtube" | "vimeo"; id: string } | null {
  let u: URL
  try {
    u = new URL(/^https?:\/\//i.test(raw.trim()) ? raw.trim() : `https://${raw.trim()}`)
  } catch {
    return null
  }
  const host = u.hostname.replace(/^www\./, "").toLowerCase()
  const ok = (id: string) => /^[A-Za-z0-9_-]{6,32}$/.test(id)

  if (host === "youtu.be") {
    const id = u.pathname.slice(1)
    return ok(id) ? { provider: "youtube", id } : null
  }
  if (host === "youtube.com" || host === "m.youtube.com" || host === "youtube-nocookie.com") {
    let id = u.searchParams.get("v") ?? ""
    if (!id) {
      const m = u.pathname.match(/^\/(?:embed|shorts|v)\/([A-Za-z0-9_-]+)/)
      if (m) id = m[1]
    }
    return ok(id) ? { provider: "youtube", id } : null
  }
  if (host === "vimeo.com" || host === "player.vimeo.com") {
    const m = u.pathname.match(/(\d{6,})/)
    return m ? { provider: "vimeo", id: m[1] } : null
  }
  return null
}

/** Fixed-host embed URL for a validated marker — no user input reaches the src. */
export function videoEmbedUrl(provider: string, id: string): string {
  return provider === "vimeo"
    ? `https://player.vimeo.com/video/${id}`
    : `https://www.youtube-nocookie.com/embed/${id}`
}

function sanitizeNode(node: Element) {
  for (const child of [...node.children]) {
    if (!ALLOWED_TAGS.has(child.tagName)) {
      // Unwrap unknown tags (keep their text) instead of dropping content.
      child.replaceWith(...child.childNodes)
      continue
    }

    const allowed = ALLOWED_ATTRS[child.tagName] ?? []
    for (const attr of [...child.attributes]) {
      if (!allowed.includes(attr.name)) child.removeAttribute(attr.name)
    }

    // Only http(s) URLs survive — never javascript:, data:text, vbscript:.
    for (const urlAttr of ["src", "href"]) {
      const value = child.getAttribute(urlAttr)
      if (value && !/^https?:\/\//i.test(value.trim())) {
        child.removeAttribute(urlAttr)
      }
    }

    // A video marker is trusted only when it matches `provider:id` exactly.
    const marker = child.getAttribute("data-video")
    if (marker !== null && !VIDEO_MARKER_RE.test(marker)) {
      child.removeAttribute("data-video")
    }

    if (child.tagName === "A") {
      child.setAttribute("rel", "noopener noreferrer")
      child.setAttribute("target", "_blank")
    }

    sanitizeNode(child)
  }
}

/** Sanitize an HTML fragment for safe innerHTML rendering. */
export function sanitizeHtml(html: string): string {
  if (typeof window === "undefined") return ""
  const doc = new DOMParser().parseFromString(html, "text/html")
  // <script> bodies must vanish entirely, not unwrap into text.
  doc.body
    .querySelectorAll("script, style, iframe, object, embed, form")
    .forEach((el) => el.remove())
  sanitizeNode(doc.body)
  return doc.body.innerHTML
}

/** Plain text of an HTML fragment — table cells, exports, search keys. */
export function stripHtml(html: string): string {
  if (!/[<&]/.test(html)) return html
  if (typeof window === "undefined") return html.replace(/<[^>]*>/g, " ")
  const doc = new DOMParser().parseFromString(html, "text/html")
  // Math markers hold their LaTeX in the attribute — surface it as text so
  // a formula-only stem never reads as empty in tables/exports.
  doc.body.querySelectorAll("span[data-math]").forEach((el) => {
    el.textContent = el.getAttribute("data-math") ?? ""
  })
  return (doc.body.textContent ?? "").replace(/\s+/g, " ").trim()
}
