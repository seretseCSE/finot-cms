"use client"

import { FileSpreadsheet, FileText, Loader2, Paperclip } from "lucide-react"
import { useEffect, useState } from "react"

import { useMediaPreview } from "@/components/ui/media-preview"
import { aiAuthHeaders } from "@/lib/ai"
import { API_URL } from "@/lib/api"
import type { MediaFile } from "@/lib/files"
import { useTranslation } from "@/lib/i18n"
import type { AiMessageAttachment } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * Attachments on a chat turn: image thumbnails and named file chips.
 * Clicking any of them opens the shared in-app MediaPreview lightbox
 * (DESIGN.md §8 — nothing opens a new page just to look at a file), with
 * every attachment of the message swipeable inside it.
 *
 * Server copies live base64-encoded on the SDK message row and are fetched
 * with auth headers (an <img src> can't send them), so previews resolve
 * through blob URLs cached per attachment for the session; just-sent local
 * echoes use the picked file's object URL directly.
 */
const blobCache = new Map<string, string>()

function attachmentPath(conversationId: number, messageId: string, index: number): string {
  return `/ai/conversations/${conversationId}/messages/${messageId}/attachments/${index}`
}

async function fetchBlobUrl(path: string): Promise<string> {
  const cached = blobCache.get(path)
  if (cached) return cached
  const response = await fetch(`${API_URL}${path}`, { headers: aiAuthHeaders() })
  if (!response.ok) throw new Error(`attachment ${response.status}`)
  const url = URL.createObjectURL(await response.blob())
  blobCache.set(path, url)
  return url
}

function resolveUrl(attachment: AiMessageAttachment, path: string): Promise<string | null> {
  if (attachment.localUrl !== undefined) return Promise.resolve(attachment.localUrl || null)
  return fetchBlobUrl(path).catch(() => null)
}

export function AiMessageAttachments({
  conversationId,
  messageId,
  attachments,
  align = "end",
}: {
  conversationId: number
  messageId: string
  attachments: AiMessageAttachment[]
  align?: "start" | "end"
}) {
  const { openPreview, previewDialog } = useMediaPreview()
  const [resolving, setResolving] = useState(false)

  if (attachments.length === 0) return null

  const preview = async (position: number) => {
    if (resolving) return
    setResolving(true)
    try {
      const files: MediaFile[] = await Promise.all(
        attachments.map(async (attachment): Promise<MediaFile> => ({
          name: attachment.name ?? "attachment",
          mime_type: attachment.mime,
          url: await resolveUrl(attachment, attachmentPath(conversationId, messageId, attachment.index)),
        })),
      )
      openPreview(files, position)
    } finally {
      setResolving(false)
    }
  }

  return (
    <div className={cn("flex flex-wrap gap-2", align === "end" ? "justify-end" : "justify-start")}>
      {attachments.map((attachment, position) =>
        attachment.kind === "image" ? (
          <AiImageThumb
            key={attachment.index}
            attachment={attachment}
            path={attachmentPath(conversationId, messageId, attachment.index)}
            onOpen={() => void preview(position)}
          />
        ) : (
          <AiFileChip
            key={attachment.index}
            attachment={attachment}
            busy={resolving}
            onOpen={() => void preview(position)}
          />
        ),
      )}
      {previewDialog}
    </div>
  )
}

function AiImageThumb({
  attachment,
  path,
  onOpen,
}: {
  attachment: AiMessageAttachment
  path: string
  onOpen: () => void
}) {
  const { t } = useTranslation("ai")
  const [url, setUrl] = useState<string | null>(attachment.localUrl ?? null)
  const [failed, setFailed] = useState(false)

  const isLocal = attachment.localUrl !== undefined

  useEffect(() => {
    if (isLocal) return
    let cancelled = false
    fetchBlobUrl(path)
      .then((blobUrl) => {
        if (!cancelled) setUrl(blobUrl)
      })
      .catch(() => {
        if (!cancelled) setFailed(true)
      })
    return () => {
      cancelled = true
    }
  }, [isLocal, path])

  if (failed) {
    return <AiFileChip attachment={attachment} busy={false} onOpen={onOpen} />
  }

  if (url === null) {
    return (
      <span className="flex h-24 w-32 items-center justify-center rounded-xl border bg-muted/40">
        <Loader2 className="size-4 animate-spin text-muted-foreground" />
      </span>
    )
  }

  return (
    <button
      type="button"
      onClick={onOpen}
      className="pressable overflow-hidden rounded-xl border"
      title={attachment.name ?? t("composer.imageAlt")}
      aria-label={attachment.name ?? t("composer.imageAlt")}
    >
      {/* eslint-disable-next-line @next/next/no-img-element -- blob URL, next/image cannot optimize it */}
      <img src={url} alt={attachment.name ?? t("composer.imageAlt")} className="h-28 max-w-48 object-cover" />
    </button>
  )
}

function FileChipIcon({ mime, name }: { mime: string | null; name: string | null }) {
  const lower = `${mime ?? ""} ${name ?? ""}`.toLowerCase()
  const Icon = /(sheet|excel|csv|xlsx)/.test(lower)
    ? FileSpreadsheet
    : /(pdf|word|document|text|docx|pptx|presentation|txt|md)/.test(lower)
      ? FileText
      : Paperclip
  return <Icon className="size-4 shrink-0 text-primary" />
}

function AiFileChip({
  attachment,
  busy,
  onOpen,
}: {
  attachment: AiMessageAttachment
  busy: boolean
  onOpen: () => void
}) {
  const { t } = useTranslation("ai")

  return (
    <button
      type="button"
      onClick={onOpen}
      className="pressable flex max-w-56 items-center gap-2 rounded-xl border bg-card px-3 py-2 text-start transition-colors hover:bg-accent"
      title={t("attachment.preview")}
      aria-label={`${t("attachment.preview")}: ${attachment.name ?? t("attachment.file")}`}
    >
      {busy ? (
        <Loader2 className="size-4 shrink-0 animate-spin text-muted-foreground" />
      ) : (
        <FileChipIcon mime={attachment.mime} name={attachment.name} />
      )}
      <span className="min-w-0">
        <span className="block truncate text-xs font-medium">{attachment.name ?? t("attachment.file")}</span>
      </span>
    </button>
  )
}
