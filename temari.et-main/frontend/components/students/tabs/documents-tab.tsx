"use client"

import { Paperclip } from "lucide-react"
import { useRef, useState } from "react"
import { toast } from "sonner"

import {
  DocumentCategoryBadge,
  DocumentCategorySelect,
} from "@/components/students/document-category"
import { AttachmentTile } from "@/components/ui/attachment"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { useMediaPreview } from "@/components/ui/media-preview"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"
import type { Student, StudentAttachment, StudentTransferFileGroup } from "@/lib/types"
import { fmtDate } from "@/lib/dates"

const ACCEPTED_FILES = ".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
const MAX_FILE_BYTES = 10 * 1024 * 1024

export function DocumentsTab({
  student,
  canUpdate,
  onChanged,
  transferFiles,
}: {
  student: Student
  canUpdate: boolean
  onChanged: () => void
  /** Transfer supporting documents — participant schools only, read-only. */
  transferFiles?: StudentTransferFileGroup[]
}) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { openPreview, previewDialog } = useMediaPreview()
  const { active } = useSchoolContext()

  // Provenance rule (ADR-017): only the school that ADDED a document may
  // delete it — another school's certified copies are never yours to destroy.
  // Platform staff (no school context) may clean anything.
  const mayDelete = (attachment: StudentAttachment) =>
    canUpdate &&
    (attachment.school_id == null ||
      active.schoolId == null ||
      attachment.school_id === active.schoolId)

  const fileInput = useRef<HTMLInputElement>(null)
  const [pendingFile, setPendingFile] = useState<File | null>(null)
  const [pendingName, setPendingName] = useState("")
  const [pendingCategory, setPendingCategory] = useState("")
  const [uploading, setUploading] = useState(false)

  const attachments = student.attachments ?? []

  // Picked or dropped, a document lands in the same pending card — the rename
  // + category step is never skipped.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: ACCEPTED_FILES,
    maxSize: MAX_FILE_BYTES,
    disabled: !canUpdate,
    onFiles: ([file]) => {
      setPendingFile(file)
      setPendingName(file.name.replace(/\.[^.]+$/, ""))
    },
  })

  async function upload() {
    if (!pendingFile) return
    setUploading(true)
    try {
      const body = new FormData()
      body.append("name", pendingName || pendingFile.name)
      if (pendingCategory) body.append("category", pendingCategory)
      body.append("file", pendingFile)
      await apiFetch(`/students/${student.id}/attachments`, {
        method: "POST",
        body,
      })
      toast.success(t("documents.uploaded"))
      setPendingFile(null)
      setPendingName("")
      setPendingCategory("")
      onChanged()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setUploading(false)
    }
  }

  async function remove(attachment: StudentAttachment) {
    try {
      await apiFetch(`/students/${student.id}/attachments/${attachment.id}`, {
        method: "DELETE",
      })
      toast.success(t("documents.removed"))
      onChanged()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
  }

  return (
    <div {...dropProps} className={cn("space-y-4 rounded-2xl", dragOver && DROP_ACTIVE)}>
      {confirmDialog}
      {previewDialog}

      {attachments.length === 0 && !pendingFile ? (
        <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
          {t("documents.empty")}
        </div>
      ) : (
        <ul className="space-y-2">
          {attachments.map((attachment, index) => (
            <li key={attachment.id}>
              <AttachmentTile
                file={attachment}
                description={
                  <span className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <DocumentCategoryBadge category={attachment.category} />
                    {/* Provenance — documents travel with the student across
                        schools, so say which branch collected this one. */}
                    {attachment.branch_name ? (
                      <span className="text-xs text-muted-foreground">
                        {t("documents.uploadedBy", { branch: attachment.branch_name })}
                      </span>
                    ) : null}
                  </span>
                }
                onPreview={() => openPreview(attachments, index)}
                onDelete={
                  mayDelete(attachment)
                    ? () =>
                        confirmDelete(
                          () => remove(attachment),
                          tc("confirmDelete.named", { name: attachment.name })
                        )
                    : undefined
                }
              />
            </li>
          ))}
        </ul>
      )}

      {canUpdate && (
        <>
          {pendingFile ? (
            <div className="space-y-2 rounded-2xl border p-3">
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <Input
                  value={pendingName}
                  onChange={(e) => setPendingName(e.target.value)}
                  placeholder={t("wizard.documentNamePlaceholder")}
                  className="h-10"
                />
                <DocumentCategorySelect
                  value={pendingCategory}
                  onChange={setPendingCategory}
                  className="h-10"
                />
              </div>
              <div className="flex items-center justify-between gap-2">
                <span className="min-w-0 truncate text-xs text-muted-foreground">
                  {pendingFile.name}
                </span>
                <div className="flex shrink-0 gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    className="h-10 rounded-full"
                    onClick={() => {
                      setPendingFile(null)
                      setPendingCategory("")
                    }}
                  >
                    {tc("actions.cancel")}
                  </Button>
                  <Button
                    type="button"
                    className="h-10 rounded-full"
                    onClick={upload}
                    loading={uploading}
                  >
                    {t("documents.upload")}
                  </Button>
                </div>
              </div>
            </div>
          ) : (
            <div className="flex flex-wrap items-center gap-2">
              <Button
                type="button"
                variant="outline"
                className="h-10 rounded-full"
                onClick={() => fileInput.current?.click()}
              >
                <Paperclip className="size-4" />
                {t("wizard.attachDocument")}
              </Button>
              <DropHint />
            </div>
          )}
          <input
            ref={fileInput}
            type="file"
            accept={ACCEPTED_FILES}
            className="hidden"
            onChange={(e) => {
              takeFiles(e.target.files)
              e.target.value = ""
            }}
          />
        </>
      )}

      {/* Transfer files — supporting documents from transfer requests this
          school participated in. Kept on the record so they are never lost;
          read-only (they belong to the transfer, not the student file). */}
      {(transferFiles?.length ?? 0) > 0 && (
        <div className="space-y-2 border-t pt-4">
          <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {t("documents.transferFiles")}
          </p>
          <ul className="space-y-3">
            {transferFiles!.map((group) => (
              <li key={group.id} className="space-y-1.5 rounded-2xl border p-3">
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                  <span className="font-medium text-foreground">
                    {group.from_school_name} → {group.to_school_name}
                  </span>
                  <Badge variant="secondary" className="text-[11px]">
                    {t(`documents.transferStatus.${group.status}`)}
                  </Badge>
                  <span>{fmtDate(group.created_at)}</span>
                </div>
                <ul className="space-y-1.5">
                  {group.files.map((file, index) => (
                    <li key={file.id}>
                      <AttachmentTile
                        file={file}
                        onPreview={() => openPreview(group.files, index)}
                      />
                    </li>
                  ))}
                </ul>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}
