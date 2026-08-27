"use client"

import {
  Bold,
  Braces,
  Check,
  Clapperboard,
  Code,
  Heading2,
  Heading3,
  ImagePlus,
  Italic,
  Link2,
  Link2Off,
  List,
  ListOrdered,
  Loader2,
  Quote,
  RemoveFormatting,
  Sigma,
  Strikethrough,
  Subscript,
  Superscript,
  Underline,
  X,
} from "lucide-react"
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react"

import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { MathPalette } from "@/components/ui/math-palette"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { useTranslation } from "@/lib/i18n"
import { renderMathIn, renderMathToString } from "@/lib/math"
import { parseVideoUrl, sanitizeHtml } from "@/lib/sanitize-html"
import { cn } from "@/lib/utils"

const IMAGE_ACCEPT = "image/jpeg,image/png,image/webp,image/gif"

interface Props {
  value: string
  onChange: (html: string) => void
  placeholder?: string
  className?: string
  /** Upload handler for inline images; omit to hide the image button. */
  onUploadImage?: (file: File) => Promise<{ url: string; path: string }>
  /** Fires while an inline image uploads — hosts disable Save so an in-flight image can't be lost. */
  onUploadingChange?: (uploading: boolean) => void
  /**
   * Single-value mode for short rich fields (MCQ options, matching items):
   * lower minimum height and a trimmed toolbar (inline marks + math + image)
   * — block tools (headings, lists, video…) make no sense in one line.
   */
  compact?: boolean
}

/** Escape a LaTeX source for safe placement inside a double-quoted attribute. */
function escapeAttr(value: string): string {
  return value.replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
}

/** Normalize a user-typed URL to an http(s) link the sanitizer will keep. */
function normalizeUrl(raw: string): string | null {
  const trimmed = raw.trim()
  if (!trimmed) return null
  const withScheme = /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`
  try {
    return new URL(withScheme).href
  } catch {
    return null
  }
}

/** Walk up from the current selection to the enclosing <a>, if any. */
function anchorInSelection(root: HTMLElement): HTMLAnchorElement | null {
  const sel = window.getSelection()
  if (!sel || sel.rangeCount === 0) return null
  let node: Node | null = sel.anchorNode
  while (node && node !== root) {
    if (node instanceof HTMLAnchorElement) return node
    node = node.parentNode
  }
  return null
}

/**
 * Dependency-free WYSIWYG for question stems, course materials and exam
 * instructions. Formatting (headings, quote, code, sub/sup, links, lists,
 * inline images) rides on the browser's own editing engine — no heavyweight
 * editor library on 3G (DESIGN.md). Emits sanitized HTML matching the
 * allowlist in `sanitize-html.ts` / `QuestionRules::sanitizeStem`; images
 * uploaded through `onUploadImage` carry `data-path` so the backend can
 * re-sign their URLs forever.
 */
export function RichTextEditor({
  value,
  onChange,
  placeholder,
  className,
  onUploadImage,
  onUploadingChange,
  compact = false,
}: Props) {
  const { t } = useTranslation("common")
  const ref = useRef<HTMLDivElement>(null)
  const fileRef = useRef<HTMLInputElement>(null)
  const linkInputRef = useRef<HTMLInputElement>(null)
  const savedRange = useRef<Range | null>(null)
  const lastEmitted = useRef<string>("")
  // The math chip currently being edited via the bar (click-to-edit).
  const editingMath = useRef<HTMLElement | null>(null)
  const [uploading, setUploading] = useState(false)
  const [empty, setEmpty] = useState(value.trim() === "")
  const [barMode, setBarMode] = useState<"link" | "video" | "math" | null>(null)
  const [barUrl, setBarUrl] = useState("")
  const [mathDisplay, setMathDisplay] = useState(false)
  const [mathPreview, setMathPreview] = useState("")
  const [active, setActive] = useState<Record<string, boolean>>({})

  // Turn bare `<div data-video>` markers into non-editable preview cards for
  // authoring; `cleanHtml` reverses this back to markers on every emit.
  const hydrateVideos = useCallback(
    (root: HTMLElement) => {
      root.querySelectorAll<HTMLElement>("div[data-video]").forEach((el) => {
        const [provider, id] = (el.getAttribute("data-video") ?? "").split(":")
        if (!provider || !id) return
        el.contentEditable = "false"
        el.className =
          "temari-video relative my-2 select-none overflow-hidden rounded-xl border bg-muted"
        const thumb =
          provider === "youtube" ? `https://img.youtube.com/vi/${id}/hqdefault.jpg` : ""
        el.innerHTML = `${
          thumb
            ? `<img src="${thumb}" alt="" class="pointer-events-none block h-44 w-full object-cover" />`
            : `<div class="h-44 w-full bg-muted"></div>`
        }<div class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex size-12 items-center justify-center rounded-full bg-black/60 text-lg text-white">▶</span></div><div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-3 py-2 text-xs font-medium text-white">${t("richText.videoLabel", { provider: provider === "youtube" ? "YouTube" : "Vimeo" })}</div><button type="button" contenteditable="false" data-video-remove aria-label="${t("richText.unlink")}" class="absolute right-2 top-2 flex size-7 items-center justify-center rounded-full bg-black/60 text-lg leading-none text-white hover:bg-black/80">×</button>`
      })
    },
    [t],
  )

  /**
   * Render `<span data-math>` markers as non-editable KaTeX chips for
   * authoring; click edits, backspace deletes the chip whole. `cleanHtml`
   * collapses chips back to bare markers on every emit.
   */
  const hydrateMath = useCallback((root: HTMLElement) => {
    root.querySelectorAll<HTMLElement>("span[data-math]").forEach((el) => {
      if (el.contentEditable === "false") return
      el.contentEditable = "false"
      el.classList.add("temari-math")
    })
    void renderMathIn(root)
  }, [])

  /** DOM back to storage markers: video cards collapse to `<div data-video>`. */
  function cleanHtml(root: HTMLElement): string {
    const clone = root.cloneNode(true) as HTMLElement
    clone.querySelectorAll("[data-video]").forEach((el) => {
      const marker = document.createElement("div")
      const v = el.getAttribute("data-video")
      if (v) marker.setAttribute("data-video", v)
      el.replaceWith(marker)
    })
    clone.querySelectorAll("span[data-math]").forEach((el) => {
      const marker = document.createElement("span")
      marker.setAttribute("data-math", el.getAttribute("data-math") ?? "")
      if (el.getAttribute("data-display") === "block") marker.setAttribute("data-display", "block")
      el.replaceWith(marker)
    })
    return clone.innerHTML
  }

  // Paint external value changes (open/reset) without stealing the caret
  // while the user is typing their own edits.
  useEffect(() => {
    if (!ref.current || value === lastEmitted.current) return
    ref.current.innerHTML = sanitizeHtml(value)
    hydrateVideos(ref.current)
    hydrateMath(ref.current)
    lastEmitted.current = value
    setEmpty(ref.current.textContent?.trim() === "" && !ref.current.querySelector("img, [data-video], [data-math]"))
  }, [value, hydrateVideos, hydrateMath])

  const refreshActive = useCallback(() => {
    if (typeof document === "undefined" || !ref.current) return
    const sel = window.getSelection()
    if (!sel || sel.rangeCount === 0 || !ref.current.contains(sel.anchorNode)) return
    const block = (document.queryCommandValue("formatBlock") || "").toLowerCase()
    setActive({
      bold: document.queryCommandState("bold"),
      italic: document.queryCommandState("italic"),
      underline: document.queryCommandState("underline"),
      strikeThrough: document.queryCommandState("strikeThrough"),
      subscript: document.queryCommandState("subscript"),
      superscript: document.queryCommandState("superscript"),
      insertUnorderedList: document.queryCommandState("insertUnorderedList"),
      insertOrderedList: document.queryCommandState("insertOrderedList"),
      h2: block === "h2",
      h3: block === "h3",
      blockquote: block === "blockquote",
      pre: block === "pre",
      link: anchorInSelection(ref.current) !== null,
    })
  }, [])

  useEffect(() => {
    document.addEventListener("selectionchange", refreshActive)
    return () => document.removeEventListener("selectionchange", refreshActive)
  }, [refreshActive])

  function emit() {
    if (!ref.current) return
    const html = cleanHtml(ref.current)
    lastEmitted.current = html
    setEmpty(ref.current.textContent?.trim() === "" && !ref.current.querySelector("img, [data-video], [data-math]"))
    onChange(html)
  }

  function exec(command: string, value?: string) {
    ref.current?.focus()
    document.execCommand(command, false, value)
    emit()
    refreshActive()
  }

  /** Toggle a block wrapper (heading / quote / code block) on and off. */
  function toggleBlock(tag: string) {
    ref.current?.focus()
    const current = (document.queryCommandValue("formatBlock") || "").toLowerCase()
    document.execCommand("formatBlock", false, current === tag ? "p" : tag)
    emit()
    refreshActive()
  }

  /** Wrap the current selection in inline <code>. */
  function toggleInlineCode() {
    const root = ref.current
    if (!root) return
    root.focus()
    const sel = window.getSelection()
    if (!sel || sel.rangeCount === 0) return
    const range = sel.getRangeAt(0)
    if (range.collapsed) return
    const text = range.toString()
    if (!text) return
    document.execCommand(
      "insertHTML",
      false,
      `<code>${text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")}</code>`,
    )
    emit()
    refreshActive()
  }

  /** Re-apply the selection the toolbar click stole when the bar opened. */
  function restoreRange() {
    const sel = window.getSelection()
    if (savedRange.current && sel) {
      sel.removeAllRanges()
      sel.addRange(savedRange.current)
    }
    return sel
  }

  function openBar(mode: "link" | "video" | "math", mathEl: HTMLElement | null = null) {
    const root = ref.current
    if (!root) return
    const sel = window.getSelection()
    if (sel && sel.rangeCount > 0 && root.contains(sel.anchorNode)) {
      savedRange.current = sel.getRangeAt(0).cloneRange()
    }
    const existing = mode === "link" ? anchorInSelection(root) : null
    editingMath.current = mode === "math" ? mathEl : null
    if (mode === "math") {
      setBarUrl(mathEl?.getAttribute("data-math") ?? "")
      setMathDisplay(mathEl?.getAttribute("data-display") === "block")
    } else {
      setBarUrl(existing?.getAttribute("href") ?? "")
    }
    setBarMode(mode)
    // Focus the URL field after it renders.
    requestAnimationFrame(() => linkInputRef.current?.focus())
  }

  function applyBar() {
    if (barMode === "video") return applyVideo()
    if (barMode === "math") return applyMath()
    applyLink()
  }

  /** Live formula preview while typing in the math bar. */
  useEffect(() => {
    if (barMode !== "math") return
    let cancelled = false
    const latex = barUrl.trim()
    void (latex === "" ? Promise.resolve("") : renderMathToString(latex, mathDisplay)).then(
      (html) => {
        if (!cancelled) setMathPreview(html)
      },
    )
    return () => {
      cancelled = true
    }
  }, [barMode, barUrl, mathDisplay])

  /**
   * Palette click → LaTeX lands at the formula input's caret; `caret` puts
   * the cursor inside the snippet (a fraction's first slot, a root's body…)
   * so the teacher keeps clicking or typing without repositioning.
   */
  function insertMathSnippet(snippet: string, caret?: number) {
    const input = linkInputRef.current
    const start = input?.selectionStart ?? barUrl.length
    const end = input?.selectionEnd ?? start
    setBarUrl(barUrl.slice(0, start) + snippet + barUrl.slice(end))
    const pos = start + (caret ?? snippet.length)
    requestAnimationFrame(() => {
      input?.focus()
      input?.setSelectionRange(pos, pos)
    })
  }

  function applyMath() {
    const root = ref.current
    const latex = barUrl.trim()
    if (!root || latex === "") {
      setBarMode(null)
      return
    }

    if (editingMath.current && root.contains(editingMath.current)) {
      // Click-to-edit: re-point the existing chip and re-render it.
      const el = editingMath.current
      el.setAttribute("data-math", latex)
      if (mathDisplay) el.setAttribute("data-display", "block")
      else el.removeAttribute("data-display")
      el.removeAttribute("data-math-done")
      el.textContent = ""
      hydrateMath(root)
    } else {
      root.focus()
      restoreRange()
      document.execCommand(
        "insertHTML",
        false,
        `<span data-math="${escapeAttr(latex)}"${mathDisplay ? ' data-display="block"' : ""}></span>&nbsp;`,
      )
      hydrateMath(root)
    }

    editingMath.current = null
    setBarMode(null)
    setBarUrl("")
    emit()
    refreshActive()
  }

  function applyLink() {
    const root = ref.current
    const href = normalizeUrl(barUrl)
    if (!root || !href) {
      setBarMode(null)
      return
    }
    root.focus()
    const sel = restoreRange()
    const existing = anchorInSelection(root)
    if (existing) {
      // Editing a link already there: select the whole anchor, re-point it.
      const range = document.createRange()
      range.selectNode(existing)
      sel?.removeAllRanges()
      sel?.addRange(range)
      document.execCommand("unlink")
    }
    if (sel && sel.isCollapsed) {
      // No text selected — drop the URL in as its own linked text.
      document.execCommand("insertHTML", false, `<a href="${href}">${href}</a>`)
    } else {
      document.execCommand("createLink", false, href)
    }
    setBarMode(null)
    setBarUrl("")
    emit()
    refreshActive()
  }

  function applyVideo() {
    const root = ref.current
    const parsed = parseVideoUrl(barUrl)
    if (!root || !parsed) {
      setBarMode(null)
      return
    }
    root.focus()
    restoreRange()
    document.execCommand(
      "insertHTML",
      false,
      `<div data-video="${parsed.provider}:${parsed.id}"></div><p><br></p>`,
    )
    hydrateVideos(root)
    setBarMode(null)
    setBarUrl("")
    emit()
    refreshActive()
  }

  function removeLink() {
    const root = ref.current
    if (!root) return
    root.focus()
    const sel = restoreRange()
    const existing = anchorInSelection(root)
    if (existing) {
      const range = document.createRange()
      range.selectNode(existing)
      sel?.removeAllRanges()
      sel?.addRange(range)
    }
    document.execCommand("unlink")
    setBarMode(null)
    setBarUrl("")
    emit()
    refreshActive()
  }

  async function insertImage(file: File) {
    if (!onUploadImage) return
    setUploading(true)
    onUploadingChange?.(true)
    try {
      const { url, path } = await onUploadImage(file)
      ref.current?.focus()
      const inserted = document.execCommand(
        "insertHTML",
        false,
        `<img src="${url}" data-path="${path}" alt="">`,
      )
      // Focus/selection can be lost after the native file dialog (dialogs,
      // focus traps) — never drop an uploaded image silently.
      if (!inserted && ref.current) {
        const img = document.createElement("img")
        img.src = url
        img.setAttribute("data-path", path)
        img.alt = ""
        ref.current.appendChild(img)
      }
      emit()
    } catch {
      // A too-big photo or a network hiccup must be VISIBLE — a silent
      // failure reads as "the editor ate my image".
      toast.error(t("richText.uploadFailed"))
    } finally {
      setUploading(false)
      onUploadingChange?.(false)
    }
  }

  type Tool =
    | {
        key: string
        icon: ReactNode
        label: string
        cmd?: string
        block?: string
        special?: "link" | "video" | "code" | "math"
      }
    | { divider: true }

  /** Run a toolbar descriptor — kept out of the array so no ref is read at render. */
  function runTool(tool: Extract<Tool, { key: string }>) {
    if (tool.special === "link") return openBar("link")
    if (tool.special === "video") return openBar("video")
    if (tool.special === "math") return openBar("math")
    if (tool.special === "code") return toggleInlineCode()
    if (tool.block) return toggleBlock(tool.block)
    if (tool.cmd) return exec(tool.cmd)
  }

  const allTools: Tool[] = [
    { key: "h2", icon: <Heading2 className="size-4" />, label: t("richText.heading"), block: "h2" },
    { key: "h3", icon: <Heading3 className="size-4" />, label: t("richText.subheading"), block: "h3" },
    { divider: true },
    { key: "bold", icon: <Bold className="size-4" />, label: t("richText.bold"), cmd: "bold" },
    { key: "italic", icon: <Italic className="size-4" />, label: t("richText.italic"), cmd: "italic" },
    { key: "underline", icon: <Underline className="size-4" />, label: t("richText.underline"), cmd: "underline" },
    { key: "strikeThrough", icon: <Strikethrough className="size-4" />, label: t("richText.strikethrough"), cmd: "strikeThrough" },
    { key: "subscript", icon: <Subscript className="size-4" />, label: t("richText.subscript"), cmd: "subscript" },
    { key: "superscript", icon: <Superscript className="size-4" />, label: t("richText.superscript"), cmd: "superscript" },
    { divider: true },
    { key: "insertUnorderedList", icon: <List className="size-4" />, label: t("richText.bulletList"), cmd: "insertUnorderedList" },
    { key: "insertOrderedList", icon: <ListOrdered className="size-4" />, label: t("richText.numberedList"), cmd: "insertOrderedList" },
    { key: "blockquote", icon: <Quote className="size-4" />, label: t("richText.quote"), block: "blockquote" },
    { key: "link", icon: <Link2 className="size-4" />, label: t("richText.link"), special: "link" },
    { key: "video", icon: <Clapperboard className="size-4" />, label: t("richText.video"), special: "video" },
    { key: "math", icon: <Sigma className="size-4" />, label: t("richText.math"), special: "math" },
    { key: "code", icon: <Code className="size-4" />, label: t("richText.inlineCode"), special: "code" },
    { key: "pre", icon: <Braces className="size-4" />, label: t("richText.codeBlock"), block: "pre" },
    { divider: true },
    { key: "removeFormat", icon: <RemoveFormatting className="size-4" />, label: t("richText.clearFormatting"), cmd: "removeFormat" },
  ]

  // An image dropped into the body uploads and inserts like the toolbar
  // button — without this the browser would drop a dead file:// <img> in.
  const imageDrop = useFileDrop({
    accept: IMAGE_ACCEPT,
    disabled: !onUploadImage || uploading,
    onFiles: ([file]) => void insertImage(file),
  })

  // Compact fields keep inline marks + math (+ image via the shared button);
  // block-level tools don't apply to a one-line value.
  const COMPACT_KEYS = new Set(["bold", "italic", "underline", "subscript", "superscript", "math"])
  const tools: Tool[] = compact
    ? allTools.filter((tool) => "key" in tool && COMPACT_KEYS.has(tool.key))
    : allTools

  return (
    <div className={cn("overflow-hidden rounded-xl border bg-background focus-within:ring-2 focus-within:ring-ring/30", className)}>
      <div
        ref={ref}
        {...imageDrop.dropProps}
        contentEditable
        role="textbox"
        aria-multiline="true"
        aria-label={placeholder}
        suppressContentEditableWarning
        onInput={emit}
        onBlur={emit}
        onKeyUp={refreshActive}
        onMouseUp={refreshActive}
        onClick={(e) => {
          // Remove a video preview card via its × button.
          const btn = (e.target as HTMLElement).closest?.("[data-video-remove]")
          if (btn) {
            e.preventDefault()
            btn.closest(".temari-video")?.remove()
            emit()
            return
          }
          // A math chip opens the formula bar prefilled for editing.
          const chip = (e.target as HTMLElement).closest?.<HTMLElement>("span[data-math]")
          if (chip && ref.current?.contains(chip)) {
            e.preventDefault()
            openBar("math", chip)
          }
        }}
        onPaste={(e) => {
          // Paste as sanitized fragment — Word/Docs markup never enters.
          e.preventDefault()
          const html = e.clipboardData.getData("text/html")
          const text = e.clipboardData.getData("text/plain")
          document.execCommand(
            "insertHTML",
            false,
            html ? sanitizeHtml(html) : text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/\n/g, "<br>"),
          )
          emit()
        }}
        className={cn(
          compact ? "min-h-11 px-3 py-2.5" : "min-h-28 px-4 py-3",
          "text-sm leading-relaxed outline-none",
          imageDrop.dragOver && DROP_ACTIVE,
          "[&_.temari-math]:mx-0.5 [&_.temari-math]:inline-block [&_.temari-math]:cursor-pointer [&_.temari-math]:rounded [&_.temari-math]:px-0.5 [&_.temari-math]:transition-colors hover:[&_.temari-math]:bg-primary/10",
          "[&_img]:my-2 [&_img]:max-h-72 [&_img]:max-w-full [&_img]:rounded-lg",
          "[&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5",
          "[&_h2]:text-base [&_h2]:font-semibold [&_h3]:text-sm [&_h3]:font-semibold",
          "[&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-3 [&_blockquote]:text-muted-foreground",
          "[&_a]:text-primary [&_a]:underline",
          "[&_code]:rounded [&_code]:bg-muted [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.85em]",
          "[&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-muted [&_pre]:p-3 [&_pre]:font-mono [&_pre]:text-[0.85em]",
          "[&_.temari-video]:max-w-sm",
          empty &&
            "before:pointer-events-none before:float-left before:text-muted-foreground before:content-[attr(data-placeholder)]",
        )}
        data-placeholder={placeholder}
      />

      {barMode && (
        <div className="flex flex-wrap items-center gap-2 border-t bg-muted/40 px-2 py-2">
          {barMode === "video" ? (
            <Clapperboard className="ml-1 size-4 shrink-0 text-muted-foreground" />
          ) : barMode === "math" ? (
            <Sigma className="ml-1 size-4 shrink-0 text-muted-foreground" />
          ) : (
            <Link2 className="ml-1 size-4 shrink-0 text-muted-foreground" />
          )}
          <Input
            ref={linkInputRef}
            value={barUrl}
            onChange={(e) => setBarUrl(e.target.value)}
            placeholder={
              barMode === "video"
                ? t("richText.videoPlaceholder")
                : barMode === "math"
                  ? t("richText.mathPlaceholder")
                  : t("richText.linkPlaceholder")
            }
            className={cn("h-8 min-w-40 flex-1", barMode === "math" && "font-mono")}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault()
                applyBar()
              } else if (e.key === "Escape") {
                e.preventDefault()
                setBarMode(null)
              }
            }}
          />
          {barMode === "math" && (
            <button
              type="button"
              onClick={() => setMathDisplay((v) => !v)}
              className={cn(
                "h-8 shrink-0 rounded-md border px-2 text-xs font-medium transition-colors",
                mathDisplay ? "border-primary/40 bg-primary/10 text-primary" : "text-muted-foreground hover:bg-muted/60",
              )}
            >
              {t("richText.mathBlock")}
            </button>
          )}
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type="button" variant="ghost" size="icon" className="size-8 text-muted-foreground" onClick={applyBar}>
                <Check className="size-4" />
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              {barMode === "video"
                ? t("richText.applyVideo")
                : barMode === "math"
                  ? t("richText.applyMath")
                  : t("richText.applyLink")}
            </TooltipContent>
          </Tooltip>
          {barMode === "link" && active.link && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button type="button" variant="ghost" size="icon" className="size-8 text-muted-foreground" onClick={removeLink}>
                  <Link2Off className="size-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>{t("richText.unlink")}</TooltipContent>
            </Tooltip>
          )}
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type="button" variant="ghost" size="icon" className="size-8 text-muted-foreground" onClick={() => setBarMode(null)}>
                <X className="size-4" />
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("actions.cancel")}</TooltipContent>
          </Tooltip>
          {barMode === "math" && mathPreview !== "" && (
            <div
              className="w-full overflow-x-auto rounded-lg border bg-background px-3 py-2"
              // KaTeX output rendered from LaTeX TEXT — never user HTML.
              dangerouslySetInnerHTML={{ __html: mathPreview }}
            />
          )}
          {barMode === "math" && (
            // The Docs/Word-style equation picker: click templates and
            // symbols to build the formula — typing LaTeX stays optional.
            <MathPalette onInsert={insertMathSnippet} />
          )}
        </div>
      )}

      <div className="flex flex-wrap items-center gap-0.5 border-t bg-muted/40 px-2 py-1.5">
        {tools.map((tool, i) =>
          "divider" in tool ? (
            <div key={`d${i}`} className="mx-1 h-4 w-px bg-border" />
          ) : (
            <Tooltip key={tool.key}>
              <TooltipTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className={cn(
                    "size-8 text-muted-foreground",
                    active[tool.key] && "bg-background text-foreground shadow-sm",
                  )}
                  aria-pressed={active[tool.key] ?? false}
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => runTool(tool)}
                >
                  {tool.icon}
                </Button>
              </TooltipTrigger>
              <TooltipContent>{tool.label}</TooltipContent>
            </Tooltip>
          ),
        )}
        {onUploadImage && (
          <>
            <div className="mx-1 h-4 w-px bg-border" />
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="size-8 text-muted-foreground"
                  disabled={uploading}
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => fileRef.current?.click()}
                >
                  {uploading ? <Loader2 className="size-4 animate-spin" /> : <ImagePlus className="size-4" />}
                </Button>
              </TooltipTrigger>
              <TooltipContent>{t("richText.insertImage")}</TooltipContent>
            </Tooltip>
            <input
              ref={fileRef}
              type="file"
              accept={IMAGE_ACCEPT}
              className="hidden"
              onChange={(e) => {
                imageDrop.takeFiles(e.target.files)
                e.target.value = ""
              }}
            />
          </>
        )}
      </div>
    </div>
  )
}
