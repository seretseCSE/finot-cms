"use client"

import { FileText, Pencil, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { GuardianSheet } from "@/components/students/guardian-sheet"
import { PortalAccountChip } from "@/components/students/portal-account-chip"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import { useMediaPreview } from "@/components/ui/media-preview"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useTranslation } from "@/lib/i18n"
import type { Guardian } from "@/lib/types"

export function GuardiansTab({
  studentId,
  canManage,
}: {
  studentId: number
  canManage: boolean
}) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { openPreview, previewDialog } = useMediaPreview()

  const [guardians, setGuardians] = useState<Guardian[] | null>(null)
  const [editing, setEditing] = useState<Guardian | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: Guardian[] }>(`/students/${studentId}/guardians`)
      .then((res) => !cancelled && setGuardians(res.data))
      .catch(() => !cancelled && setGuardians([]))
    return () => {
      cancelled = true
    }
  }, [studentId])

  function handleSaved(guardian: Guardian) {
    setGuardians((prev) => {
      const list = prev ?? []
      const exists = list.some((g) => g.id === guardian.id)
      const next = exists
        ? list.map((g) => (g.id === guardian.id ? guardian : g))
        : [...list, guardian]
      return guardian.is_primary
        ? next.map((g) =>
            g.id === guardian.id ? g : { ...g, is_primary: false }
          )
        : next
    })
  }

  async function handleRemove(guardian: Guardian) {
    try {
      await apiFetch(`/guardians/${guardian.id}`, { method: "DELETE" })
      setGuardians((prev) => (prev ?? []).filter((g) => g.id !== guardian.id))
      toast.success(t("guardians.removed"))
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
  }

  return (
    <div className="space-y-4">
      {confirmDialog}
      {previewDialog}
      <div className="flex items-center justify-end">
        {canManage && (
          <GuardianSheet
            studentId={studentId}
            guardian={editing}
            open={sheetOpen}
            onOpenChange={(v) => {
              setSheetOpen(v)
              if (!v) setEditing(null)
            }}
            onSaved={handleSaved}
            showTrigger
          />
        )}
      </div>

      {guardians === null ? (
        <Skeleton className="h-24 w-full rounded-2xl" />
      ) : guardians.length === 0 ? (
        <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
          {t("detail.noGuardians")}
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {guardians.map((guardian) => (
            <Card key={guardian.id}>
              <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="flex min-w-0 items-start gap-3">
                  <PersonAvatar
                    name={guardian.name ?? "?"}
                    photoUrl={guardian.photo_url}
                    className="mt-0.5 size-10"
                  />
                  <div className="min-w-0">
                    <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                      {guardian.name}
                      <CopyableId value={guardian.public_id} />
                      {guardian.is_primary && (
                        <Badge>{t("guardians.primary")}</Badge>
                      )}
                      {guardian.emergency_contact && (
                        <Badge variant="secondary">
                          {t("guardians.emergency")}
                        </Badge>
                      )}
                    </CardTitle>
                    <p className="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
                      {t(`guardians.relationships.${guardian.relationship}`)}
                      {guardian.phone && (
                        <>
                          {" · "}
                          <ContactActionCell
                            value={guardian.phone}
                            name={guardian.name}
                            // One family thread per student — every guardian
                            // lands in the same conversation.
                            chat={{ kind: "student", studentId }}
                          />
                        </>
                      )}
                      {guardian.occupation ? ` · ${guardian.occupation}` : ""}
                    </p>
                  </div>
                </div>
                {canManage && (
                  <div className="flex shrink-0 gap-1">
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      aria-label={tc("actions.edit")}
                      onClick={() => {
                        setEditing(guardian)
                        setSheetOpen(true)
                      }}
                    >
                      <Pencil className="size-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      aria-label={tc("actions.delete")}
                      // A student must keep at least one guardian on file — the
                      // last remaining link can't be removed (matches the API).
                      disabled={(guardians?.length ?? 0) <= 1}
                      title={
                        (guardians?.length ?? 0) <= 1
                          ? t("guardians.lastCannotRemove")
                          : undefined
                      }
                      className="text-destructive"
                      onClick={() =>
                        confirmDelete(
                          () => handleRemove(guardian),
                          tc("confirmDelete.named", {
                            name: guardian.name ?? guardian.relationship_label,
                          })
                        )
                      }
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </div>
                )}
              </CardHeader>
              <CardContent className="space-y-2">
                <div className="flex flex-wrap gap-1.5 text-xs">
                  <PortalAccountChip account={guardian.account} />
                  {guardian.can_view_grades && (
                    <Badge variant="outline">
                      {t("guardians.canViewGrades")}
                    </Badge>
                  )}
                  {guardian.can_view_attendance && (
                    <Badge variant="outline">
                      {t("guardians.canViewAttendance")}
                    </Badge>
                  )}
                  {guardian.can_pay_fees && (
                    <Badge variant="outline">{t("guardians.canPayFees")}</Badge>
                  )}
                  {guardian.can_receive_sms && (
                    <Badge variant="outline">
                      {t("guardians.canReceiveSms")}
                    </Badge>
                  )}
                </div>
                {(guardian.attachments ?? []).length > 0 ? (
                  <div className="flex flex-wrap gap-1.5">
                    {(guardian.attachments ?? []).map((attachment, index) =>
                      attachment.url ? (
                        <button
                          key={attachment.id}
                          type="button"
                          onClick={() =>
                            openPreview(guardian.attachments ?? [], index)
                          }
                          className="inline-flex cursor-pointer items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-[11px] text-muted-foreground hover:text-foreground"
                        >
                          <FileText className="size-3" />
                          {attachment.name}
                        </button>
                      ) : null
                    )}
                  </div>
                ) : null}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
