"use client"

import { FileText, ImageIcon, Paperclip, Trash2, X } from "lucide-react"

import { ACCEPTED_FILES, MAX_FILE_BYTES } from "@/components/employees/wizard/schema"
import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { useTranslation } from "@/lib/i18n"
import type { EmployeeAttachment } from "@/lib/types"
import { cn } from "@/lib/utils"

/** A pending (not yet uploaded) staff document. `anchor` ties it to a record:
 * "" = general, p:<id>/q:<id> = saved position/qualification,
 * pi:<index>/qi:<index> = a not-yet-saved form row (resolved after save). */
export interface DraftFile {
  name: string
  file: File
  anchor: string
}

/** Effective account policy of the target branch (`/employees/account-policy`). */
export interface AccountPolicy {
  account_job_titles: string[]
  required_job_titles: string[]
}

/**
 * The attachment plumbing three steps need (positions, qualifications and the
 * general documents step). Passed as one prop because these travel together —
 * splitting them into eight separate props would say nothing extra.
 */
export interface AttachmentProps {
  attachments: EmployeeAttachment[]
  drafts: DraftFile[]
  setDrafts: React.Dispatch<React.SetStateAction<DraftFile[]>>
  removingId: number | null
  onRemoveExisting: (attachment: EmployeeAttachment) => void
  onPickFiles: (anchor: string) => void
  /** Dropped files know which record they landed on. */
  onDropFiles: (files: File[], anchor: string) => void
  onPreview: (files: EmployeeAttachment[] | EmployeeAttachment, at?: number) => void
  confirmDelete: (action: () => void | Promise<void>, description?: string) => void
}

export function fileIcon(mime: string | null | undefined) {
  return mime?.startsWith("image/") ? ImageIcon : FileText
}

export function formatBytes(bytes: number | null | undefined): string {
  if (!bytes) return ""
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

/**
 * Compact per-record document list: saved files + pending drafts + add.
 * Top-level on purpose — defined inside a step component it would remount on
 * every parent render and the rename input would drop focus after each
 * keystroke.
 */
export function RecordAttachments({
  anchor,
  recordId,
  kind,
  attachments,
  drafts,
  setDrafts,
  removingId,
  onRemoveExisting,
  onPickFiles,
  onDropFiles,
  onPreview,
  confirmDelete,
}: AttachmentProps & {
  anchor: string
  recordId?: number
  kind: "position" | "qualification"
}) {
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")

  // Files dropped on this record attach to THIS record.
  const { dragOver, dropProps } = useFileDrop({
    accept: ACCEPTED_FILES,
    maxSize: MAX_FILE_BYTES,
    multiple: true,
    onFiles: (files) => onDropFiles(files, anchor),
  })

  const existing = attachments.filter((a) =>
    kind === "position"
      ? recordId != null && a.employee_position_id === recordId
      : recordId != null && a.employee_qualification_id === recordId
  )
  const pendingDrafts = drafts
    .map((draft, draftIndex) => ({ draft, draftIndex }))
    .filter(({ draft }) => draft.anchor === anchor)

  return (
    <div {...dropProps} className={cn("space-y-1.5 rounded-xl", dragOver && DROP_ACTIVE)}>
      {existing.map((attachment) => {
        const Icon = fileIcon(attachment.mime_type)
        return (
          <div
            key={attachment.id}
            className="flex min-h-9 items-center gap-2 rounded-lg bg-muted/40 px-2.5 py-1.5"
          >
            <Icon className="size-3.5 shrink-0 text-muted-foreground" />
            {attachment.url ? (
              <button
                type="button"
                onClick={() => onPreview(existing, existing.indexOf(attachment))}
                className="min-w-0 flex-1 cursor-pointer truncate text-left text-xs font-medium hover:underline"
              >
                {attachment.name}
              </button>
            ) : (
              <span className="min-w-0 flex-1 truncate text-xs font-medium">{attachment.name}</span>
            )}
            <span className="text-[11px] text-muted-foreground">
              {formatBytes(attachment.size)}
            </span>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="size-7 shrink-0 text-muted-foreground hover:text-destructive"
              loading={removingId === attachment.id}
              onClick={() =>
                confirmDelete(
                  () => onRemoveExisting(attachment),
                  tc("confirmDelete.named", { name: attachment.name })
                )
              }
              aria-label={t("attachments.remove")}
            >
              <Trash2 className="size-3.5" />
            </Button>
          </div>
        )
      })}
      {pendingDrafts.map(({ draft, draftIndex }) => {
        const Icon = fileIcon(draft.file.type)
        return (
          <div
            key={`${draft.file.name}-${draftIndex}`}
            className="flex min-h-9 items-center gap-2 rounded-lg border border-dashed px-2.5 py-1.5"
          >
            <Icon className="size-3.5 shrink-0 text-muted-foreground" />
            <Input
              value={draft.name}
              onChange={(e) =>
                setDrafts((prev) =>
                  prev.map((d, i) => (i === draftIndex ? { ...d, name: e.target.value } : d))
                )
              }
              placeholder={t("attachments.namePlaceholder")}
              className="h-7 flex-1 text-xs"
            />
            <span className="text-[11px] text-muted-foreground">
              {formatBytes(draft.file.size)}
            </span>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="size-7 shrink-0 text-muted-foreground hover:text-destructive"
              onClick={() => setDrafts((prev) => prev.filter((_, i) => i !== draftIndex))}
              aria-label={t("attachments.remove")}
            >
              <X className="size-3.5" />
            </Button>
          </div>
        )
      })}
      <button
        type="button"
        onClick={() => onPickFiles(anchor)}
        className="pressable inline-flex min-h-8 items-center gap-1.5 rounded-full border border-dashed px-3 text-xs font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary"
      >
        <Paperclip className="size-3.5" />
        {t("attachments.attachToRecord")}
      </button>
    </div>
  )
}
