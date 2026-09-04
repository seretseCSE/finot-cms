"use client"

import { Paperclip, Trash2, X } from "lucide-react"

import { ACCEPTED_FILES, MAX_FILE_BYTES } from "@/components/employees/wizard/schema"
import {
  fileIcon,
  formatBytes,
  type AttachmentProps,
} from "@/components/employees/wizard/steps/shared"
import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/**
 * General staff documents — the ones that belong to the person rather than to
 * a particular job or credential (those hang off their own rows).
 */
export function DocumentsStep({
  active,
  attachments,
  drafts,
  setDrafts,
  removingId,
  onRemoveExisting,
  onPickFiles,
  onDropFiles,
  onPreview,
  confirmDelete,
}: AttachmentProps & { active: boolean }) {
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")

  // General documents belong to the person, not to a job row — anchor "".
  const { dragOver, dropProps } = useFileDrop({
    accept: ACCEPTED_FILES,
    maxSize: MAX_FILE_BYTES,
    multiple: true,
    disabled: !active,
    onFiles: (files) => onDropFiles(files, ""),
  })

  return (
    <div
      {...dropProps}
      className={cn("space-y-3 rounded-2xl", !active && "hidden", dragOver && DROP_ACTIVE)}
    >
      <p className="text-xs text-muted-foreground">{t("attachments.hint")}</p>

      {attachments
        .filter((a) => !a.employee_position_id && !a.employee_qualification_id)
        .map((attachment) => {
          const Icon = fileIcon(attachment.mime_type)
          return (
            <div
              key={attachment.id}
              className="flex items-center gap-2.5 rounded-lg border bg-muted/30 px-3 py-2"
            >
              <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent">
                <Icon className="size-4 text-muted-foreground" />
              </div>
              <div className="min-w-0 flex-1">
                {attachment.url ? (
                  <button
                    type="button"
                    onClick={() => onPreview(attachment)}
                    className="block w-full cursor-pointer truncate text-left text-sm font-medium hover:underline"
                  >
                    {attachment.name}
                  </button>
                ) : (
                  <p className="truncate text-sm font-medium">{attachment.name}</p>
                )}
                <p className="truncate text-xs text-muted-foreground">
                  {formatBytes(attachment.size)}
                </p>
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                loading={removingId === attachment.id}
                onClick={() =>
                  confirmDelete(
                    () => onRemoveExisting(attachment),
                    tc("confirmDelete.named", { name: attachment.name })
                  )
                }
                aria-label={t("attachments.remove")}
              >
                <Trash2 className="size-4" />
              </Button>
            </div>
          )
        })}

      {drafts
        .map((draft, draftIndex) => ({ draft, draftIndex }))
        .filter(({ draft }) => draft.anchor === "")
        .map(({ draft, draftIndex }) => {
          const Icon = fileIcon(draft.file.type)
          return (
            <div
              key={`${draft.file.name}-${draftIndex}`}
              className="flex items-start gap-2.5 rounded-lg border border-dashed px-3 py-2"
            >
              <div className="mt-1 flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent">
                <Icon className="size-4 text-muted-foreground" />
              </div>
              <div className="min-w-0 flex-1 space-y-1.5">
                <Input
                  value={draft.name}
                  onChange={(e) =>
                    setDrafts((prev) =>
                      prev.map((d, i) => (i === draftIndex ? { ...d, name: e.target.value } : d))
                    )
                  }
                  placeholder={t("attachments.namePlaceholder")}
                  className="h-8 text-sm"
                />
                <p className="truncate text-xs text-muted-foreground">
                  {draft.file.name} · {formatBytes(draft.file.size)}
                </p>
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                onClick={() => setDrafts((prev) => prev.filter((_, i) => i !== draftIndex))}
                aria-label={t("attachments.remove")}
              >
                <X className="size-4" />
              </Button>
            </div>
          )
        })}

      <button
        type="button"
        onClick={() => onPickFiles("")}
        className="pressable flex min-h-12 w-full flex-wrap items-center justify-center gap-2 rounded-xl border border-dashed text-sm font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary"
      >
        <Paperclip className="size-4" />
        {t("attachments.add")}
        <DropHint />
      </button>
    </div>
  )
}
