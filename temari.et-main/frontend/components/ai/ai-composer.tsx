"use client"

import { ArrowUp, FileSpreadsheet, FileText, Paperclip, Square, X } from "lucide-react"
import { useEffect, useRef, useState } from "react"

import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { Textarea } from "@/components/ui/textarea"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/** Mirrors ChatAttachments::EXTENSIONS on the backend. */
const ACCEPT =
  ".jpg,.jpeg,.png,.webp,.gif,.heic,.pdf,.txt,.csv,.md,.docx,.xlsx,.pptx"

/**
 * The ChatGPT-style prompt box shared by the AI home screen and open
 * threads: a borderless textarea inside one rounded shell, attach on the
 * bottom row, and a round send/stop button. Text is
 * controlled by the parent (so a failed send can restore the draft);
 * attachments live here — picked, pasted (screenshots) or dropped, with
 * image previews.
 */
export function AiComposer({
  value,
  onChange,
  onSend,
  streaming = false,
  sending = false,
  onStop,
  disabled = false,
  maxAttachments,
  placeholder,
  leadingSlot,
  hint,
  autoFocus = false,
}: {
  value: string
  onChange: (value: string) => void
  onSend: (text: string, files: File[]) => void
  streaming?: boolean
  /** Creating the session / first request in flight — spinner on send. */
  sending?: boolean
  onStop?: () => void
  disabled?: boolean
  maxAttachments: number
  placeholder?: string
  /** Rendered next to attach on the bottom row. */
  leadingSlot?: React.ReactNode
  /** Small right-aligned note (e.g. remaining daily quota). */
  hint?: string
  autoFocus?: boolean
}) {
  const { t } = useTranslation("ai")
  const [files, setFiles] = useState<File[]>([])
  const fileInputRef = useRef<HTMLInputElement>(null)

  const addFiles = (incoming: Iterable<File>) => {
    const picked = Array.from(incoming).filter((file) => file.size > 0)
    if (picked.length === 0) return
    setFiles((prev) => [...prev, ...picked].slice(0, maxAttachments))
  }

  // Picked, pasted or dropped, attachments go through one validator.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: ACCEPT,
    multiple: true,
    disabled: disabled || streaming,
    onFiles: addFiles,
  })

  const submit = () => {
    const text = value.trim()
    if (text === "" || streaming || sending || disabled) return
    const sent = files
    setFiles([])
    onSend(text, sent)
  }

  return (
    <div className="w-full">
      <div
        {...dropProps}
        className={cn(
          "rounded-[1.625rem] border bg-card shadow-xs transition-[border-color,box-shadow] focus-within:border-ring/60 focus-within:shadow-sm",
          dragOver && DROP_ACTIVE,
          disabled && "opacity-70",
        )}
      >
        {files.length > 0 && (
          <div className="flex flex-wrap gap-2 px-4 pt-3">
            {files.map((file, index) => (
              <PendingAttachment
                key={`${file.name}-${file.lastModified}-${index}`}
                file={file}
                onRemove={() => setFiles((prev) => prev.filter((_, i) => i !== index))}
              />
            ))}
          </div>
        )}

        <Textarea
          value={value}
          onChange={(e) => onChange(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter" && !e.shiftKey) {
              e.preventDefault()
              submit()
            }
          }}
          onPaste={(e) => {
            if (disabled || streaming) return
            // Pasted screenshots are attachments too — same validator.
            if (e.clipboardData.files.length > 0) {
              e.preventDefault()
              takeFiles(e.clipboardData.files)
            }
          }}
          placeholder={placeholder ?? t("composer.placeholder")}
          rows={1}
          autoFocus={autoFocus}
          disabled={disabled}
          className="max-h-40 min-h-12 border-0 bg-transparent px-4 pt-3.5 pb-1 shadow-none focus-visible:ring-0 disabled:bg-transparent dark:bg-transparent dark:disabled:bg-transparent"
        />

        <div className="flex items-center gap-1.5 px-2.5 pb-2.5">
          <input
            ref={fileInputRef}
            type="file"
            accept={ACCEPT}
            multiple
            hidden
            onChange={(e) => {
              takeFiles(e.target.files)
              e.target.value = ""
            }}
          />
          <Button
            variant="ghost"
            size="icon"
            className="size-9 rounded-full text-muted-foreground"
            onClick={() => fileInputRef.current?.click()}
            disabled={disabled || streaming || files.length >= maxAttachments}
            title={t("composer.attach")}
            aria-label={t("composer.attach")}
          >
            <Paperclip className="size-4.5" />
          </Button>

          {leadingSlot}

          <div className="ms-auto flex shrink-0 items-center gap-2">
            {hint && (
              <span className="hidden pe-1 text-[11px] text-muted-foreground sm:block">{hint}</span>
            )}
            {streaming ? (
              <Button
                size="icon"
                variant="secondary"
                className="size-9 rounded-full"
                onClick={onStop}
                title={t("composer.stop")}
                aria-label={t("composer.stop")}
              >
                <Square className="size-4" />
              </Button>
            ) : (
              <Button
                size="icon"
                className="size-9 rounded-full"
                onClick={submit}
                loading={sending}
                disabled={value.trim() === "" || disabled}
                title={t("composer.send")}
                aria-label={t("composer.send")}
              >
                <ArrowUp className="size-4.5" />
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

/** One queued attachment: image preview or a typed file chip. */
function PendingAttachment({ file, onRemove }: { file: File; onRemove: () => void }) {
  const { t } = useTranslation("ai")
  const isImage = file.type.startsWith("image/")
  const [preview, setPreview] = useState<string | null>(null)

  useEffect(() => {
    if (!isImage) return
    const url = URL.createObjectURL(file)
    // eslint-disable-next-line react-hooks/set-state-in-effect -- object URL must be created client-side and revoked on cleanup
    setPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [file, isImage])

  const lower = `${file.type} ${file.name}`.toLowerCase()
  const Icon = /(sheet|excel|csv|xlsx)/.test(lower) ? FileSpreadsheet : FileText

  return (
    <span className="relative flex items-center gap-1.5 overflow-hidden rounded-xl border bg-muted/50">
      {isImage && preview ? (
        // eslint-disable-next-line @next/next/no-img-element -- transient object URL preview
        <img src={preview} alt={file.name} className="size-14 object-cover" />
      ) : (
        <span className="flex items-center gap-1.5 py-1.5 ps-2 text-xs">
          <Icon className="size-3.5 shrink-0 text-primary" />
          <span className="max-w-32 truncate">{file.name}</span>
        </span>
      )}
      <button
        type="button"
        onClick={onRemove}
        aria-label={t("composer.removeAttachment")}
        title={t("composer.removeAttachment")}
        className={cn(
          "shrink-0",
          isImage
            ? "absolute end-0.5 top-0.5 rounded-full bg-background/80 p-0.5 backdrop-blur"
            : "pe-2",
        )}
      >
        <X className="size-3.5" />
      </button>
    </span>
  )
}
