"use client"

import { FileText, Paperclip } from "lucide-react"
import { useRef, useState } from "react"
import { toast } from "sonner"

import { AttachmentTile } from "@/components/ui/attachment"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { useMediaPreview } from "@/components/ui/media-preview"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"
import type { Employee, EmployeeAttachment } from "@/lib/types"

const ACCEPTED_FILES = ".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
const MAX_FILE_BYTES = 10 * 1024 * 1024

/**
 * Staff documents with in-place add/remove — no full edit modal. Mirrors the
 * student Documents tab: pick a file, name it, upload; delete behind a confirm.
 */
export function EmployeeDocumentsTab({
  employee,
  canUpdate,
  onChanged,
}: {
  employee: Employee
  canUpdate: boolean
  onChanged: () => void
}) {
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { openPreview, previewDialog } = useMediaPreview()

  const fileInput = useRef<HTMLInputElement>(null)
  const [pendingFile, setPendingFile] = useState<File | null>(null)
  const [pendingName, setPendingName] = useState("")
  const [uploading, setUploading] = useState(false)

  const attachments = employee.attachments ?? []

  // Picked or dropped, the file lands in the same pending card (rename first).
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
      body.append("file", pendingFile)
      await apiFetch(`/employees/${employee.id}/attachments`, { method: "POST", body })
      toast.success(t("attachments.uploaded"))
      setPendingFile(null)
      setPendingName("")
      onChanged()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setUploading(false)
    }
  }

  async function remove(attachment: EmployeeAttachment) {
    try {
      await apiFetch(`/employees/${employee.id}/attachments/${attachment.id}`, {
        method: "DELETE",
      })
      toast.success(t("attachments.removed"))
      onChanged()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <span className="flex size-8 items-center justify-center rounded-lg bg-accent text-muted-foreground">
            <FileText className="size-4" />
          </span>
          {t("profile.documents")}
        </CardTitle>
      </CardHeader>
      <CardContent
        {...dropProps}
        className={cn("space-y-4 rounded-2xl", dragOver && DROP_ACTIVE)}
      >
        {confirmDialog}
        {previewDialog}

        {attachments.length === 0 && !pendingFile ? (
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("attachments.empty")}
          </div>
        ) : (
          <ul className="space-y-1.5">
            {attachments.map((attachment, index) => (
              <li key={attachment.id}>
                <AttachmentTile
                  file={attachment}
                  onPreview={() => openPreview(attachments, index)}
                  onDelete={
                    canUpdate
                      ? () =>
                          confirmDelete(
                            () => remove(attachment),
                            tc("confirmDelete.named", { name: attachment.name }),
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
                <Input
                  value={pendingName}
                  onChange={(e) => setPendingName(e.target.value)}
                  placeholder={t("attachments.namePlaceholder")}
                  className="h-10"
                />
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
                        setPendingName("")
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
                      {t("attachments.upload")}
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
                  {t("attachments.add")}
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
      </CardContent>
    </Card>
  )
}
