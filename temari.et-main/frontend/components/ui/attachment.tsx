"use client"

import {
  Download,
  File as FileIcon,
  FileArchive,
  FileAudio,
  FileSpreadsheet,
  FileText,
  FileVideo,
  ImageIcon,
  Play,
  Share2,
  Trash2,
  type LucideIcon,
} from "lucide-react"
import { useState, type ReactNode } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import {
  downloadFile,
  fileKind,
  formatFileSize,
  shareFile,
  type FileKind,
  type MediaFile,
} from "@/lib/files"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/**
 * Attachment tile (shadcn attachment anatomy: media / content / actions with
 * a full-card trigger overlay). Clicking the card opens the in-page
 * MediaPreview; download / share / delete stay independently clickable.
 */

const KIND_META: Record<FileKind, { icon: LucideIcon; className: string }> = {
  image: { icon: ImageIcon, className: "bg-primary/10 text-primary" },
  pdf: { icon: FileText, className: "bg-destructive/10 text-destructive" },
  doc: { icon: FileText, className: "bg-info/15 text-info" },
  sheet: { icon: FileSpreadsheet, className: "bg-success/15 text-success" },
  video: { icon: FileVideo, className: "bg-warning/15 text-warning" },
  audio: { icon: FileAudio, className: "bg-warning/15 text-warning" },
  archive: { icon: FileArchive, className: "bg-muted text-muted-foreground" },
  other: { icon: FileIcon, className: "bg-muted text-muted-foreground" },
}

/** Tinted file-type icon tile; also used by the MediaPreview fallback panel. */
export function AttachmentIcon({
  mimeType,
  className,
}: {
  mimeType?: string | null
  className?: string
}) {
  const meta = KIND_META[fileKind(mimeType)]
  const Icon = meta.icon
  return (
    <div
      className={cn(
        "flex size-10 shrink-0 items-center justify-center rounded-lg",
        meta.className,
        className
      )}
    >
      <Icon className="size-4.5" />
    </div>
  )
}

/** Image/video files show their actual thumbnail (signed URL); everything else the kind icon. */
export function AttachmentMedia({
  file,
  className,
}: {
  file: MediaFile
  className?: string
}) {
  const [broken, setBroken] = useState(false)
  const kind = fileKind(file.mime_type)
  if (file.url && !broken && kind === "image") {
    return (
      // eslint-disable-next-line @next/next/no-img-element -- signed URL
      <img
        src={file.url}
        alt=""
        loading="lazy"
        onError={() => setBroken(true)}
        className={cn(
          "size-10 shrink-0 rounded-lg border object-cover",
          className
        )}
      />
    )
  }
  if (file.url && !broken && kind === "video") {
    return (
      <div
        className={cn(
          "relative size-10 shrink-0 overflow-hidden rounded-lg border bg-black",
          className
        )}
      >
        {/* preload="metadata" paints the first frame without pulling the file. */}
        <video
          src={file.url}
          preload="metadata"
          muted
          playsInline
          onError={() => setBroken(true)}
          className="size-full object-cover"
        />
        <span className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <Play className="size-4 fill-white text-white drop-shadow-sm" />
        </span>
      </div>
    )
  }
  return <AttachmentIcon mimeType={file.mime_type} className={className} />
}

export function AttachmentTile({
  file,
  description,
  onPreview,
  onDelete,
  className,
}: {
  file: MediaFile
  /** Extra description content (e.g. a category badge); size renders automatically. */
  description?: ReactNode
  /** Opens the media preview; the whole card becomes tappable when set and the file has a URL. */
  onPreview?: () => void
  /** Renders the delete action; callers wrap it in useConfirmDelete themselves. */
  onDelete?: () => void
  className?: string
}) {
  const { t } = useTranslation("common")
  const canOpen = !!onPreview && !!file.url

  async function handleDownload() {
    if ((await downloadFile(file)) === "failed")
      toast.error(t("attachment.downloadFailed"))
  }

  async function handleShare() {
    const result = await shareFile(file)
    if (result === "copied") toast.success(t("attachment.linkCopied"))
    if (result === "failed") toast.error(t("attachment.shareFailed"))
  }

  return (
    <div
      className={cn(
        "group relative flex items-center gap-3 rounded-xl border bg-card px-3 py-2.5 transition-colors",
        canOpen && "hover:bg-accent/50",
        className
      )}
    >
      {canOpen && (
        <button
          type="button"
          onClick={onPreview}
          aria-label={t("attachment.preview")}
          className="absolute inset-0 cursor-pointer rounded-xl outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
        />
      )}

      <AttachmentMedia file={file} />

      <div className="pointer-events-none z-10 min-w-0 flex-1">
        <p className="truncate text-sm font-medium">{file.name}</p>
        <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
          {description}
          {file.size ? <span>{formatFileSize(file.size)}</span> : null}
        </div>
      </div>

      <div className="z-10 flex shrink-0 items-center gap-0.5">
        {file.url && (
          <>
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              aria-label={t("attachment.download")}
              title={t("attachment.download")}
              className="text-muted-foreground max-sm:hidden"
              onClick={handleDownload}
            >
              <Download />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              aria-label={t("attachment.share")}
              title={t("attachment.share")}
              className="text-muted-foreground max-sm:hidden"
              onClick={handleShare}
            >
              <Share2 />
            </Button>
          </>
        )}
        {onDelete && (
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label={t("actions.delete")}
            className="text-destructive"
            onClick={onDelete}
          >
            <Trash2 />
          </Button>
        )}
      </div>
    </div>
  )
}
