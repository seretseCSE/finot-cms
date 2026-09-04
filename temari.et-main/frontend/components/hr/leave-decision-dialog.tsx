"use client"

import { useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { LeaveRequest } from "@/lib/types"

/**
 * Approve / reject a pending leave request. Approval enforces the balance —
 * when the server blocks it (422), the override checkbox is surfaced so the
 * decision to exceed is always explicit.
 */
export function LeaveDecisionDialog({
  request,
  mode,
  open,
  onOpenChange,
  onDecided,
}: {
  request: LeaveRequest | null
  mode: "approve" | "reject"
  open: boolean
  onOpenChange: (open: boolean) => void
  onDecided: () => void
}) {
  const { t } = useTranslation("hr")
  const [note, setNote] = useState("")
  const [allowExceed, setAllowExceed] = useState(false)
  const [showExceed, setShowExceed] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  function handleOpenChange(value: boolean) {
    onOpenChange(value)
    if (!value) {
      setNote("")
      setAllowExceed(false)
      setShowExceed(false)
    }
  }

  async function submit() {
    if (!request) return
    if (mode === "reject" && !note.trim()) {
      toast.error(t("leave.rejectNotePlaceholder"))
      return
    }
    setSubmitting(true)
    try {
      await apiFetch(`/hr/leave-requests/${request.id}/${mode}`, {
        method: "POST",
        body:
          mode === "approve"
            ? { decision_note: note.trim() || undefined, allow_exceeding_balance: allowExceed }
            : { decision_note: note.trim() },
      })
      toast.success(mode === "approve" ? t("leave.approved") : t("leave.rejected"))
      onDecided()
      handleOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError && error.status === 422 && mode === "approve" && !allowExceed) {
        // Balance exceeded — surface the explicit override.
        setShowExceed(true)
        toast.error(error.message)
      } else {
        toast.error(error instanceof ApiError ? error.message : t("error"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {mode === "approve" ? t("leave.approveTitle") : t("leave.rejectTitle")}
          </DialogTitle>
          {request && (
            <DialogDescription>
              {t("leave.approveDesc", {
                name: request.employee_name ?? "",
                type: request.leave_type_name ?? "",
                days: request.days,
              })}
            </DialogDescription>
          )}
        </DialogHeader>

        <div className="space-y-3">
          <div className="space-y-1.5">
            <label className="text-sm font-medium">{t("leave.decisionNote")}</label>
            <textarea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              rows={3}
              placeholder={
                mode === "approve"
                  ? t("leave.decisionNoteOptional")
                  : t("leave.rejectNotePlaceholder")
              }
              className="w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"
            />
          </div>

          {mode === "approve" && showExceed && (
            <div className="space-y-2 rounded-xl bg-warning/10 px-3.5 py-3">
              <p className="text-sm text-warning">{t("leave.exceedHint")}</p>
              <label className="flex items-center gap-2.5 text-sm">
                <Checkbox
                  checked={allowExceed}
                  onCheckedChange={(checked) => setAllowExceed(checked === true)}
                />
                {t("leave.allowExceed")}
              </label>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button
            onClick={submit}
            loading={submitting}
            variant={mode === "reject" ? "destructive" : "default"}
            className="h-11 w-full sm:w-auto"
          >
            {mode === "approve" ? t("leave.actions.approve") : t("leave.actions.reject")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
