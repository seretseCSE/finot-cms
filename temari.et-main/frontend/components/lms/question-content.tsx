"use client"

import { BookOpenText, ExternalLink, Link2, CirclePlay } from "lucide-react"
import { useEffect, useMemo, useRef, useState } from "react"

import { AttachmentMedia } from "@/components/ui/attachment"
import { MediaPreview } from "@/components/ui/media-preview"
import { formatFileSize, type MediaFile } from "@/lib/files"
import { useTranslation } from "@/lib/i18n"
import { hasMath, renderMathIn } from "@/lib/math"
import { sanitizeHtml, stripHtml, videoEmbedUrl } from "@/lib/sanitize-html"
import type { QuestionAttachment, QuestionGroupStem, QuestionType } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The customary Ethiopian paper order for grouping questions by type —
 * shared by the exam studio's "Group by type" and the passage editor's.
 */
export const TYPE_GROUP_ORDER: QuestionType[] = [
  "true_false",
  "mcq_single",
  "mcq_multi",
  "matching",
  "fill_blank",
  "short_answer",
  "numeric",
  "essay",
]

/**
 * Renders a question stem — rich HTML (sanitized) for WYSIWYG-authored
 * questions, plain text with preserved line breaks for legacy rows.
 */
/**
 * Replace validated `<div data-video="provider:id">` markers with a responsive
 * embed. The iframe src is built from a fixed host + the sanitizer-validated
 * id, so no user input ever reaches the frame.
 */
function withVideoEmbeds(safe: string): string {
  if (typeof window === "undefined" || !safe.includes("data-video")) return safe
  const doc = new DOMParser().parseFromString(safe, "text/html")
  doc.querySelectorAll("div[data-video]").forEach((el) => {
    const [provider, id] = (el.getAttribute("data-video") ?? "").split(":")
    if (!provider || !id) return el.remove()
    const wrap = doc.createElement("div")
    wrap.className = "temari-embed"
    const frame = doc.createElement("iframe")
    frame.src = videoEmbedUrl(provider, id)
    frame.setAttribute("loading", "lazy")
    frame.setAttribute("allowfullscreen", "")
    frame.setAttribute(
      "allow",
      "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture",
    )
    frame.setAttribute("referrerpolicy", "strict-origin-when-cross-origin")
    wrap.appendChild(frame)
    el.replaceWith(wrap)
  })
  return doc.body.innerHTML
}

export function QuestionStem({ html, className }: { html: string; className?: string }) {
  const isHtml = /<[a-z][^>]*>/i.test(html)
  const safe = useMemo(() => (isHtml ? withVideoEmbeds(sanitizeHtml(html)) : null), [html, isHtml])
  const ref = useRef<HTMLDivElement>(null)

  // KaTeX pass: lazy-loads only when a math marker is actually present.
  useEffect(() => {
    if (ref.current && safe && hasMath(safe)) void renderMathIn(ref.current)
  }, [safe])

  if (!isHtml) {
    return <div className={cn("whitespace-pre-wrap", className)}>{html}</div>
  }

  return (
    <div
      ref={ref}
      className={cn(
        "space-y-1.5 [&_img]:my-2 [&_img]:max-h-80 [&_img]:max-w-full [&_img]:rounded-lg",
        "[&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:text-primary [&_a]:underline",
        "[&_h1]:text-lg [&_h1]:font-semibold [&_h2]:text-base [&_h2]:font-semibold [&_h3]:text-sm [&_h3]:font-semibold [&_h4]:text-sm [&_h4]:font-medium",
        "[&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-3 [&_blockquote]:text-muted-foreground",
        "[&_code]:rounded [&_code]:bg-muted [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.85em]",
        "[&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-muted [&_pre]:p-3 [&_pre]:font-mono [&_pre]:text-[0.85em] [&_pre_code]:bg-transparent [&_pre_code]:p-0",
        "[&_hr]:my-3 [&_hr]:border-border",
        "[&_.temari-embed]:my-2 [&_.temari-embed]:aspect-video [&_.temari-embed]:w-full [&_.temari-embed]:overflow-hidden [&_.temari-embed]:rounded-xl [&_.temari-embed]:border [&_.temari-embed]:bg-muted [&_.temari-embed_iframe]:h-full [&_.temari-embed_iframe]:w-full",
        className,
      )}
      // Sanitized above — allowlist tags/attrs only.
      dangerouslySetInnerHTML={{ __html: safe ?? "" }}
    />
  )
}

/** One-line plain-text version of a stem for table cells and exports. */
export function stemText(html: string): string {
  return stripHtml(html)
}

/**
 * The reading passage / introduction of a question group, shown once above
 * its sub-questions (player, preview, results, grading). Long passages
 * collapse behind a toggle after the first sub-question so the taker isn't
 * re-scrolled through the same text on every page.
 */
export function PassageCard({
  group,
  defaultOpen = true,
  className,
}: {
  group: QuestionGroupStem
  defaultOpen?: boolean
  className?: string
}) {
  const { t } = useTranslation("lms")
  const [open, setOpen] = useState(defaultOpen)

  // Re-collapse when moving between groups within one mounted card slot.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per group
    setOpen(defaultOpen)
  }, [group.id, defaultOpen])

  return (
    <div className={cn("overflow-hidden rounded-2xl border border-primary/20 bg-primary/[0.03]", className)}>
      <button
        type="button"
        className="flex w-full items-center gap-2 px-4 py-2.5 text-left"
        aria-expanded={open}
        onClick={() => setOpen((v) => !v)}
      >
        <BookOpenText className="size-4 shrink-0 text-primary" />
        <span className="text-xs font-semibold uppercase tracking-wide text-primary">
          {t("questions.passage")}
        </span>
        <span className="ml-auto text-xs text-muted-foreground">
          {open ? t("questions.passageHide") : t("questions.passageShow")}
        </span>
      </button>
      {open && (
        <div className="border-t border-primary/10 px-4 py-3">
          <QuestionStem html={group.stem} className="text-sm leading-relaxed" />
          {group.attachments && group.attachments.length > 0 && (
            <QuestionAttachments attachments={group.attachments} className="mt-3" />
          )}
        </div>
      )}
    </div>
  )
}

/**
 * The "Part II — Short Answer" divider shown wherever a paper's questions
 * render (player, preview, results, grading). Instructions are rich HTML
 * from the exam author, sanitized like any stem.
 */
export function PartBanner({
  numeral,
  title,
  instructions,
  compact = false,
  className,
}: {
  numeral: string
  title: string
  instructions?: string | null
  compact?: boolean
  className?: string
}) {
  const { t } = useTranslation("lms")

  return (
    <div
      className={cn(
        "rounded-xl border border-primary/20 bg-primary/5",
        compact ? "px-3 py-2" : "px-4 py-3",
        className,
      )}
    >
      <p className={cn("font-semibold text-primary", compact ? "text-xs" : "text-sm")}>
        {t("exams.partLabel", { numeral })}
        {title ? ` — ${title}` : ""}
      </p>
      {!compact && instructions && (
        <QuestionStem html={instructions} className="mt-1 text-sm text-muted-foreground" />
      )}
    </div>
  )
}

/**
 * The attachments strip under a question: uploaded files open the in-page
 * MediaPreview, links/videos open in a new tab. Same component for authors
 * (with remove) and takers (read-only).
 */
export function QuestionAttachments({
  attachments,
  onRemove,
  onRename,
  className,
}: {
  attachments: QuestionAttachment[]
  onRemove?: (index: number) => void
  /** Author mode: makes the display name editable (upload-rename standard). */
  onRename?: (index: number, name: string) => void
  className?: string
}) {
  const { t: tc } = useTranslation("common")
  const [previewIndex, setPreviewIndex] = useState<number | null>(null)

  const files: MediaFile[] = attachments
    .filter((a) => a.kind === "file")
    .map((a) => ({
      name: a.name ?? "file",
      url: a.url ?? null,
      mime_type: a.mime_type,
      size: a.size,
    }))

  if (attachments.length === 0) return null

  let fileCursor = -1

  return (
    <div className={cn("flex flex-wrap gap-2", className)}>
      {attachments.map((attachment, index) => {
        const isFile = attachment.kind === "file"
        if (isFile) fileCursor++
        const fileIndex = fileCursor

        return (
          <div
            key={index}
            className="group relative flex min-w-0 max-w-full items-center gap-2.5 rounded-xl border bg-card py-2 pl-2.5 pr-3 text-left"
          >
            <button
              type="button"
              className="absolute inset-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring"
              aria-label={attachment.name ?? attachment.url ?? ""}
              onClick={() => {
                if (isFile) {
                  setPreviewIndex(fileIndex)
                } else if (attachment.url) {
                  window.open(attachment.url, "_blank", "noopener,noreferrer")
                }
              }}
            />
            {isFile ? (
              <AttachmentMedia
                file={{
                  name: attachment.name ?? "file",
                  url: attachment.url ?? null,
                  mime_type: attachment.mime_type,
                }}
                className="size-9"
              />
            ) : (
              <div
                className={cn(
                  "flex size-9 shrink-0 items-center justify-center rounded-lg",
                  attachment.kind === "youtube"
                    ? "bg-destructive/10 text-destructive"
                    : "bg-info/15 text-info",
                )}
              >
                {attachment.kind === "youtube" ? (
                  <CirclePlay className="size-4.5" />
                ) : (
                  <Link2 className="size-4.5" />
                )}
              </div>
            )}
            <div className="min-w-0">
              {onRename ? (
                <input
                  value={attachment.name ?? ""}
                  onChange={(e) => onRename(index, e.target.value)}
                  placeholder={attachment.url ?? ""}
                  aria-label={tc("attachment.fileName")}
                  className="relative z-10 w-48 max-w-full truncate border-0 bg-transparent p-0 text-sm font-medium outline-none placeholder:text-muted-foreground focus-visible:underline"
                />
              ) : (
                <p className="max-w-48 truncate text-sm font-medium">
                  {attachment.name || attachment.url}
                </p>
              )}
              <p className="truncate text-xs text-muted-foreground">
                {isFile
                  ? formatFileSize(attachment.size ?? null)
                  : (() => {
                      try {
                        return new URL(attachment.url ?? "").hostname
                      } catch {
                        return attachment.url
                      }
                    })()}
              </p>
            </div>
            {!isFile && <ExternalLink className="size-3.5 shrink-0 text-muted-foreground" />}
            {onRemove && (
              <button
                type="button"
                aria-label={tc("actions.delete")}
                onClick={(e) => {
                  e.stopPropagation()
                  onRemove(index)
                }}
                className="relative z-10 -mr-1 flex size-6 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
              >
                <span aria-hidden>×</span>
              </button>
            )}
          </div>
        )
      })}

      <MediaPreview
        files={files}
        index={previewIndex ?? 0}
        onIndexChange={(i) => setPreviewIndex(i)}
        open={previewIndex !== null}
        onOpenChange={(open) => !open && setPreviewIndex(null)}
      />
    </div>
  )
}
