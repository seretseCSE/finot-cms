"use client"

import { Check, Copy, SendHorizontal } from "lucide-react"
import dynamic from "next/dynamic"
import { useMemo, useRef, useState } from "react"
import remarkGfm from "remark-gfm"

import { AiExamPreviewCard, parseExamBlock, type AiExamBlock } from "@/components/ai/ai-exam-preview"
import { AiSendMessageCard, parseSendBlock, type AiSendBlock } from "@/components/ai/ai-send-message"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/**
 * Markdown for AI answers (tables, lists, code). react-markdown renders to
 * React elements — no dangerouslySetInnerHTML, so model output can never
 * inject markup. Lazy-loaded: the chat page is the only consumer. Code
 * blocks get a copy button.
 */
const ReactMarkdown = dynamic(() => import("react-markdown"), {
  loading: () => null,
})

/** Top-level app sections the autolinker may turn into links. */
const APP_SECTIONS = new Set([
  "academic", "attendance", "branch-settings", "branches", "catalogs",
  "concessions", "dashboard", "devices", "docs", "employees", "fees",
  "finance", "hr", "invoices", "lesson-plans", "lms", "marklists",
  "marketplace", "me", "messages", "parents", "payment-accounts", "payroll",
  "schools", "sections", "semesters", "settings", "students", "timetable",
  "transfers", "tutoring", "users",
])

const PATH_PATTERN = /\/[a-z][a-z0-9-]*(?:\/[A-Za-z0-9_-]+)*/g

interface MdNode {
  type: string
  value?: string
  url?: string
  children?: MdNode[]
}

/**
 * remark plugin: bare app paths in plain text ("see /students") become
 * links, so answers written before the model linked things — and any slip
 * where it emits a bare path — are still clickable. Only whitelisted
 * top-level sections match; link and code nodes are left alone.
 */
function remarkAppLinks() {
  return (tree: MdNode) => {
    const walk = (node: MdNode) => {
      if (!node.children || node.type === "link" || node.type === "linkReference") return
      node.children = node.children.flatMap((child) => {
        if (child.type !== "text" || !child.value) {
          walk(child)
          return [child]
        }
        return splitTextNode(child.value)
      })
    }
    walk(tree)
  }
}

function splitTextNode(value: string): MdNode[] {
  const out: MdNode[] = []
  let last = 0
  for (const match of value.matchAll(PATH_PATTERN)) {
    const start = match.index
    const path = match[0]
    const before = start === 0 ? "" : value[start - 1]
    const section = path.split("/")[1]
    if ((before !== "" && !/[\s(*_'"“‘[:,;>-]/.test(before)) || !APP_SECTIONS.has(section)) continue
    if (start > last) out.push({ type: "text", value: value.slice(last, start) })
    out.push({ type: "link", url: path, children: [{ type: "text", value: path }] })
    last = start + path.length
  }
  if (out.length === 0) return [{ type: "text", value }]
  if (last < value.length) out.push({ type: "text", value: value.slice(last) })
  return out
}

/**
 * Links in AI answers open in a new tab (the chat stays put); only
 * relative in-app paths and http(s) survive — anything else renders as
 * plain text. A markdown link TITLE (`[Grade 12 A](/students "Grade 12 ·
 * Section A · Semester 2")`) becomes a small hover card — the agent uses it
 * to carry record context (grade, section, semester, year) without
 * cluttering the sentence. Pure client rendering: the facts come from the
 * stored message, no request is made on hover.
 */
function MarkdownLink({
  href,
  title,
  children,
}: {
  href?: string
  title?: string
  children?: React.ReactNode
}) {
  const url = href ?? ""
  if (!url.startsWith("/") && !/^https?:\/\//.test(url)) return <>{children}</>

  const anchor = (
    <a href={url} target="_blank" rel="noopener noreferrer" className="font-medium text-primary underline underline-offset-2">
      {children}
    </a>
  )

  const facts = (title ?? "").split("·").map((fact) => fact.trim()).filter((fact) => fact !== "")
  if (facts.length === 0) return anchor

  // Desktop: a stacked fact card on hover. Mobile: tapping still navigates
  // (the tooltip never blocks the link) and the native title remains.
  return (
    <Tooltip>
      <TooltipTrigger asChild>{anchor}</TooltipTrigger>
      <TooltipContent side="top" className="max-w-60">
        <div className="space-y-0.5">
          {facts.map((fact, index) => (
            <p key={index} className={index === 0 ? "font-medium" : "opacity-80"}>
              {fact}
            </p>
          ))}
        </div>
      </TooltipContent>
    </Tooltip>
  )
}

function CodeBlock(props: React.HTMLAttributes<HTMLPreElement>) {
  const { t } = useTranslation("ai")
  const preRef = useRef<HTMLPreElement>(null)
  const [copied, setCopied] = useState(false)

  const copy = () => {
    const text = preRef.current?.innerText ?? ""
    if (text === "") return
    void navigator.clipboard.writeText(text).then(() => {
      setCopied(true)
      window.setTimeout(() => setCopied(false), 1500)
    })
  }

  return (
    <div className="relative">
      <pre ref={preRef} {...props} />
      <button
        type="button"
        onClick={copy}
        className="absolute end-2 top-2 rounded-md border bg-background/80 p-1.5 text-muted-foreground backdrop-blur transition-colors hover:text-foreground"
        title={t("thread.copy")}
        aria-label={t("thread.copy")}
      >
        {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
      </button>
    </div>
  )
}

// ── Interactive choice blocks ────────────────────────────────────────────
//
// The agent may end a question with ONE fenced block tagged `choices`
// holding JSON ({prompt, multi, options: [{label, value?}]}). Because the
// block lives inside the stored assistant message, the chips re-render on
// every load — they survive refresh and stay tappable, so the user can
// re-answer any step. Tapping sends the option's value as the reply.

export interface AiChoiceOption {
  label: string
}

export interface AiChoiceBlock {
  prompt?: string
  multi?: boolean
  options: AiChoiceOption[]
}

type Segment =
  | { type: "md"; text: string }
  | { type: "choices"; block: AiChoiceBlock }
  | { type: "send"; block: AiSendBlock }
  | { type: "exam"; block: AiExamBlock }
  /** An unterminated interactive fence mid-stream — masked until it closes. */
  | { type: "pending" }

function parseChoiceBlock(json: string): AiChoiceBlock | null {
  try {
    const raw = JSON.parse(json) as AiChoiceBlock
    const options = (Array.isArray(raw.options) ? raw.options : [])
      .filter((o): o is AiChoiceOption => typeof o === "object" && o !== null && typeof o.label === "string" && o.label.trim() !== "")
      .slice(0, 12)
      // Tapping sends the LABEL — the one thing the user actually saw.
      // Any `value` the model adds (ids, codes) is deliberately ignored.
      .map((o) => ({ label: o.label.trim() }))
    if (options.length < 2) return null
    return { prompt: typeof raw.prompt === "string" ? raw.prompt : undefined, multi: raw.multi === true, options }
  } catch {
    return null
  }
}

function splitChoices(content: string): Segment[] {
  const segments: Segment[] = []
  let rest = content

  for (;;) {
    const match = /```(choices|send_message|exam_preview)[^\S\n]*\n/.exec(rest)
    if (!match) break

    const before = rest.slice(0, match.index)
    if (before.trim() !== "") segments.push({ type: "md", text: before })

    const after = rest.slice(match.index + match[0].length)
    const close = after.indexOf("```")

    if (close === -1) {
      segments.push({ type: "pending" })
      rest = ""
      break
    }

    if (match[1] === "choices") {
      const block = parseChoiceBlock(after.slice(0, close))
      if (block) segments.push({ type: "choices", block })
    } else if (match[1] === "exam_preview") {
      const block = parseExamBlock(after.slice(0, close))
      if (block) segments.push({ type: "exam", block })
    } else {
      const block = parseSendBlock(after.slice(0, close))
      if (block) segments.push({ type: "send", block })
    }
    rest = after.slice(close + 3)
  }

  if (rest.trim() !== "") segments.push({ type: "md", text: rest })
  return segments
}

function AiChoices({
  block,
  onChoice,
  disabled,
}: {
  block: AiChoiceBlock
  onChoice?: (text: string) => void
  disabled?: boolean
}) {
  const { t } = useTranslation("ai")
  const [picked, setPicked] = useState<string[]>([])
  const multi = block.multi === true
  const inactive = disabled || !onChoice

  const tap = (option: AiChoiceOption) => {
    if (inactive) return
    if (!multi) {
      onChoice?.(option.label)
      return
    }
    setPicked((prev) =>
      prev.includes(option.label) ? prev.filter((v) => v !== option.label) : [...prev, option.label],
    )
  }

  return (
    <div className="my-2.5 flex flex-wrap items-center gap-2" role="group" aria-label={block.prompt}>
      {block.options.map((option, index) => {
        const selected = multi && picked.includes(option.label)
        return (
          <button
            key={index}
            type="button"
            disabled={inactive}
            onClick={() => tap(option)}
            aria-pressed={multi ? selected : undefined}
            className={cn(
              "pressable min-h-11 rounded-full border bg-card px-4 py-2 text-start text-sm transition-colors",
              selected
                ? "border-primary bg-primary/10 font-medium text-primary"
                : "hover:bg-accent hover:text-foreground",
              inactive && "cursor-default opacity-60",
            )}
          >
            {selected && <Check className="me-1.5 inline size-3.5" aria-hidden />}
            {option.label}
          </button>
        )
      })}
      {multi && (
        <button
          type="button"
          disabled={inactive || picked.length === 0}
          onClick={() => onChoice?.(picked.join(", "))}
          className="pressable inline-flex min-h-11 items-center gap-1.5 rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-opacity disabled:opacity-50"
        >
          <SendHorizontal className="size-3.5" aria-hidden /> {t("choices.send")}
        </button>
      )}
    </div>
  )
}

/** Ghost chips shown while a choices block is still streaming in. */
function PendingChoices() {
  return (
    <div className="my-2.5 flex flex-wrap gap-2">
      {[16, 24, 20].map((w, i) => (
        <div key={i} className="h-11 animate-pulse rounded-full border bg-muted" style={{ width: `${w * 4}px` }} />
      ))}
    </div>
  )
}

export function AiMarkdown({
  content,
  onChoice,
  choicesDisabled,
}: {
  content: string
  /** Enables tappable choice chips — chip taps send this as the user's reply. */
  onChoice?: (text: string) => void
  choicesDisabled?: boolean
}) {
  const segments = useMemo(() => splitChoices(content), [content])

  return (
    <div className="ai-markdown min-w-0 text-sm leading-relaxed [&_a]:underline [&_blockquote]:my-2 [&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_blockquote]:text-muted-foreground [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-[0.85em] [&_h1]:mt-3 [&_h1]:mb-1.5 [&_h1]:text-base [&_h1]:font-semibold [&_h2]:mt-3 [&_h2]:mb-1.5 [&_h2]:text-base [&_h2]:font-semibold [&_h3]:mt-2 [&_h3]:mb-1 [&_h3]:text-sm [&_h3]:font-semibold [&_li]:my-0.5 [&_ol]:my-1.5 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-1.5 [&_pre]:my-2 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-muted [&_pre]:p-3 [&_pre]:pe-10 [&_table]:my-2 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:bg-muted/60 [&_th]:px-2 [&_th]:py-1 [&_th]:text-start [&_ul]:my-1.5 [&_ul]:list-disc [&_ul]:pl-5">
      {segments.map((segment, index) =>
        segment.type === "md" ? (
          <ReactMarkdown key={index} remarkPlugins={[remarkGfm, remarkAppLinks]} components={{ pre: CodeBlock, a: MarkdownLink }}>
            {segment.text}
          </ReactMarkdown>
        ) : segment.type === "choices" ? (
          <AiChoices key={index} block={segment.block} onChoice={onChoice} disabled={choicesDisabled} />
        ) : segment.type === "send" ? (
          <AiSendMessageCard key={index} block={segment.block} />
        ) : segment.type === "exam" ? (
          <AiExamPreviewCard key={index} block={segment.block} />
        ) : (
          <PendingChoices key={index} />
        ),
      )}
    </div>
  )
}
