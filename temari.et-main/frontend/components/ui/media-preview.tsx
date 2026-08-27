"use client"

import {
  ChevronLeft,
  ChevronRight,
  Download,
  ExternalLink,
  FileQuestion,
  Share2,
  X,
} from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useCallback, useEffect, useRef, useState, type ReactNode } from "react"
import { toast } from "sonner"

import { AttachmentIcon } from "@/components/ui/attachment"
import { Button } from "@/components/ui/button"
import {
  downloadFile,
  fileKind,
  formatFileSize,
  shareFile,
  type MediaFile,
} from "@/lib/files"
import { useTranslation } from "@/lib/i18n"
import { cn, registerOverlayOpenState } from "@/lib/utils"

/**
 * In-page media lightbox (DESIGN.md §8 — nothing opens a new page just to
 * look at a file). Images zoom, PDFs/video/audio render inline, everything
 * else gets a download panel. Toolbar: download, native share, open in tab.
 */
export function MediaPreview({
  files,
  index,
  onIndexChange,
  open,
  onOpenChange,
}: {
  files: MediaFile[]
  index: number
  onIndexChange: (index: number) => void
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation("common")
  const [zoomed, setZoomed] = useState(false)
  const touchStartX = useRef<number | null>(null)

  const file = files[index]
  const kind = file ? fileKind(file.mime_type) : "other"

  const goTo = useCallback(
    (next: number) => {
      if (files.length < 2) return
      setZoomed(false)
      onIndexChange((next + files.length) % files.length)
    },
    [files.length, onIndexChange]
  )

  // The lightbox often opens ON TOP of a Sheet/Dialog (e.g. the transfer
  // detail sheet). Register it as an overlay so the modal underneath ignores
  // the "outside" interactions that are really aimed at the preview —
  // otherwise closing the preview also closes the sheet under it.
  useEffect(() => {
    if (!open) return
    registerOverlayOpenState(true)
    return () => registerOverlayOpenState(false)
  }, [open])

  // Reset zoom whenever the dialog re-opens or moves to another file
  // (render-time state adjustment, not an effect).
  const displayKey = open ? index : -1
  const [lastKey, setLastKey] = useState(displayKey)
  if (displayKey !== lastKey) {
    setLastKey(displayKey)
    setZoomed(false)
  }

  if (!file) return null

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
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/85 duration-150 supports-backdrop-filter:backdrop-blur-sm data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
        <DialogPrimitive.Content
          className="fixed inset-0 z-50 flex flex-col duration-150 outline-none data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0"
          onKeyDown={(e) => {
            if (e.key === "ArrowRight") goTo(index + 1)
            if (e.key === "ArrowLeft") goTo(index - 1)
          }}
          // Mobile navigation is swipe-based (the arrow buttons are sm+ only).
          onTouchStart={(e) => {
            touchStartX.current =
              e.touches.length === 1 ? e.touches[0].clientX : null
          }}
          onTouchEnd={(e) => {
            const start = touchStartX.current
            touchStartX.current = null
            if (start === null || zoomed) return
            const delta = e.changedTouches[0].clientX - start
            if (Math.abs(delta) > 56) goTo(index + (delta < 0 ? 1 : -1))
          }}
        >
          <DialogPrimitive.Title className="sr-only">
            {file.name}
          </DialogPrimitive.Title>
          <DialogPrimitive.Description className="sr-only">
            {t("attachment.preview")}
          </DialogPrimitive.Description>

          {/* Toolbar */}
          <div className="flex shrink-0 items-center gap-1 px-3 pt-[max(0.5rem,env(safe-area-inset-top))] pb-2 text-white">
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium">{file.name}</p>
              {file.size ? (
                <p className="text-xs text-white/60">
                  {formatFileSize(file.size)}
                </p>
              ) : null}
            </div>
            {file.url && (
              <>
                <ToolbarButton
                  label={t("attachment.download")}
                  onClick={handleDownload}
                >
                  <Download />
                </ToolbarButton>
                <ToolbarButton
                  label={t("attachment.share")}
                  onClick={handleShare}
                >
                  <Share2 />
                </ToolbarButton>
                <ToolbarButton
                  label={t("attachment.openInNewTab")}
                  onClick={() => window.open(file.url!, "_blank", "noopener")}
                  className="max-sm:hidden"
                >
                  <ExternalLink />
                </ToolbarButton>
              </>
            )}
            <DialogPrimitive.Close asChild>
              <ToolbarButton label={t("actions.cancel")}>
                <X />
              </ToolbarButton>
            </DialogPrimitive.Close>
          </div>

          {/* Stage — clicking the empty area closes, like every lightbox. */}
          <div
            className={cn(
              "relative flex min-h-0 flex-1 flex-col p-3 pt-0",
              files.length > 1 && "sm:px-14"
            )}
            onClick={(e) => {
              if (e.target === e.currentTarget) onOpenChange(false)
            }}
          >
            <Stage
              key={`${index}-${file.url}`}
              file={file}
              kind={kind}
              zoomed={zoomed}
              onToggleZoom={() => setZoomed((z) => !z)}
              onBackdropClick={() => onOpenChange(false)}
              fallback={
                <FallbackPanel file={file} onDownload={handleDownload}>
                  {t("attachment.noPreview")}
                </FallbackPanel>
              }
            />

            {files.length > 1 && (
              <>
                <NavButton
                  side="left"
                  label={t("attachment.previous")}
                  onClick={() => goTo(index - 1)}
                >
                  <ChevronLeft />
                </NavButton>
                <NavButton
                  side="right"
                  label={t("attachment.next")}
                  onClick={() => goTo(index + 1)}
                >
                  <ChevronRight />
                </NavButton>
              </>
            )}
          </div>

          {files.length > 1 && (
            <p className="shrink-0 pb-[max(0.75rem,env(safe-area-inset-bottom))] text-center text-xs text-white/70">
              {t("attachment.counter", {
                current: index + 1,
                total: files.length,
              })}
            </p>
          )}
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}

/** State + element pair, same shape as useConfirmDelete: render `previewDialog`, call `openPreview`. */
export function useMediaPreview() {
  const [files, setFiles] = useState<MediaFile[]>([])
  const [index, setIndex] = useState(0)
  const [open, setOpen] = useState(false)

  const openPreview = useCallback((list: MediaFile[] | MediaFile, at = 0) => {
    const next = Array.isArray(list) ? list : [list]
    if (next.length === 0) return
    setFiles(next)
    setIndex(Math.min(Math.max(at, 0), next.length - 1))
    setOpen(true)
  }, [])

  const previewDialog = (
    <MediaPreview
      files={files}
      index={index}
      onIndexChange={setIndex}
      open={open}
      onOpenChange={setOpen}
    />
  )

  return { openPreview, previewDialog }
}

function Stage({
  file,
  kind,
  zoomed,
  onToggleZoom,
  onBackdropClick,
  fallback,
}: {
  file: MediaFile
  kind: ReturnType<typeof fileKind>
  zoomed: boolean
  onToggleZoom: () => void
  onBackdropClick: () => void
  fallback: ReactNode
}) {
  if (!file.url) return <div className="m-auto">{fallback}</div>

  switch (kind) {
    case "image":
      return (
        <div
          className="flex min-h-0 flex-1 overflow-auto"
          onClick={(e) => {
            if (e.target === e.currentTarget) onBackdropClick()
          }}
        >
          {/* eslint-disable-next-line @next/next/no-img-element -- signed URL */}
          <img
            src={file.url}
            alt={file.name}
            onClick={onToggleZoom}
            className={cn(
              "m-auto rounded-lg",
              zoomed
                ? "max-h-none max-w-none cursor-zoom-out"
                : "max-h-full max-w-full cursor-zoom-in object-contain"
            )}
          />
        </div>
      )
    case "pdf":
      return (
        <object
          data={file.url}
          type="application/pdf"
          className="min-h-0 w-full flex-1 overflow-hidden rounded-lg bg-white"
          aria-label={file.name}
        >
          <div className="flex h-full items-center justify-center">
            {fallback}
          </div>
        </object>
      )
    case "video":
      return (
        <div className="flex min-h-0 flex-1 items-center justify-center">
          <video
            src={file.url}
            controls
            className="max-h-full max-w-full rounded-lg"
          />
        </div>
      )
    case "audio":
      return (
        <div className="flex min-h-0 flex-1 items-center justify-center">
          <audio src={file.url} controls className="w-full max-w-md" />
        </div>
      )
    default:
      return <div className="m-auto">{fallback}</div>
  }
}

function FallbackPanel({
  file,
  onDownload,
  children,
}: {
  file: MediaFile
  onDownload: () => void
  children: ReactNode
}) {
  const { t } = useTranslation("common")
  return (
    <div className="flex max-w-xs flex-col items-center gap-3 rounded-2xl bg-white/5 px-8 py-10 text-center text-white ring-1 ring-white/15">
      {file.mime_type ? (
        <AttachmentIcon
          mimeType={file.mime_type}
          className="size-12 rounded-xl [&_svg]:size-6"
        />
      ) : (
        <FileQuestion className="size-10 text-white/70" />
      )}
      <div className="space-y-1">
        <p className="text-sm font-medium break-all">{file.name}</p>
        <p className="text-xs text-white/60">{children}</p>
      </div>
      {file.url && (
        <Button
          type="button"
          size="sm"
          className="rounded-full"
          onClick={onDownload}
        >
          <Download />
          {t("attachment.download")}
        </Button>
      )}
    </div>
  )
}

function ToolbarButton({
  label,
  className,
  children,
  ...props
}: React.ComponentProps<typeof Button> & { label: string }) {
  return (
    <Button
      type="button"
      variant="ghost"
      size="icon-sm"
      aria-label={label}
      title={label}
      className={cn(
        "shrink-0 text-white hover:bg-white/15 hover:text-white",
        className
      )}
      {...props}
    >
      {children}
    </Button>
  )
}

function NavButton({
  side,
  label,
  onClick,
  children,
}: {
  side: "left" | "right"
  label: string
  onClick: () => void
  children: ReactNode
}) {
  return (
    <Button
      type="button"
      variant="ghost"
      size="icon"
      aria-label={label}
      onClick={onClick}
      className={cn(
        "absolute top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/40 text-white hover:bg-black/60 hover:text-white max-sm:hidden",
        side === "left" ? "left-2" : "right-2"
      )}
    >
      {children}
    </Button>
  )
}
