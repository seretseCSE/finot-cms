"use client"

import { AlertTriangle } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Invoice, Paginated, Student } from "@/lib/types"

const TEXTAREA_CLASS =
  "w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"

interface Props {
  student: Student | null
  onOpenChange: (open: boolean) => void
  onDone: () => void
}

/**
 * Mid-year withdrawal: the student leaves the school entirely or moves to a
 * school OUTSIDE Temari (in-platform moves go through the transfer flow).
 * Shows the open balance up-front — fees never block the withdrawal, they are
 * noted on the printable clearance letter instead.
 */
export function WithdrawStudentDialog({ student, onOpenChange, onDone }: Props) {
  const { t } = useTranslation("transfers")
  const { t: tc } = useTranslation("common")
  const router = useRouter()

  const [reason, setReason] = useState("")
  const [destination, setDestination] = useState("")
  const [withdrawnOn, setWithdrawnOn] = useState("")
  const [outstanding, setOutstanding] = useState<number | null>(null)
  const [working, setWorking] = useState(false)

  const enrollmentId = student?.current_enrollment?.id

  useEffect(() => {
    if (!student) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per student
    setReason("")
    setDestination("")
    setWithdrawnOn(new Date().toISOString().slice(0, 10))
    setOutstanding(null)
    let cancelled = false
    apiFetch<Paginated<Invoice>>(
      `/invoices?student_id=${student.id}&status=unpaid,partial&per_page=100`,
    )
      .then((res) => {
        if (cancelled) return
        setOutstanding(res.data.reduce((sum, invoice) => sum + Number(invoice.balance), 0))
      })
      .catch(() => !cancelled && setOutstanding(0))
    return () => {
      cancelled = true
    }
  }, [student])

  async function submit() {
    if (!enrollmentId) return
    if (!reason.trim()) {
      toast.error(t("withdrawal.reasonRequired"))
      return
    }
    setWorking(true)
    try {
      await apiFetch(`/enrollments/${enrollmentId}/withdraw`, {
        method: "POST",
        body: {
          reason: reason.trim(),
          destination: destination.trim() || undefined,
          withdrawn_on: withdrawnOn || undefined,
        },
      })
      toast.success(t("withdrawal.done"), {
        action: {
          label: t("withdrawal.printLetter"),
          onClick: () => router.push(`/transfers/withdrawal/${enrollmentId}`),
        },
        duration: 10000,
      })
      onOpenChange(false)
      onDone()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  return (
    <Dialog open={student !== null} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t("withdrawal.title")}</DialogTitle>
          <DialogDescription>
            {t("withdrawal.desc", { name: student?.full_name ?? "" })}
          </DialogDescription>
        </DialogHeader>

        {outstanding !== null && outstanding > 0 && (
          <p className="flex items-start gap-2 rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm">
            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning" />
            {t("withdrawal.outstandingWarning", { amount: outstanding.toLocaleString() })}
          </p>
        )}

        <div className="space-y-3">
          <div className="space-y-1.5">
            <p className="text-sm font-medium">{t("withdrawal.reason")}</p>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder={t("withdrawal.reasonPlaceholder")}
              rows={3}
              className={TEXTAREA_CLASS}
            />
          </div>
          <div className="space-y-1.5">
            <p className="text-sm font-medium">{t("withdrawal.destinationOptional")}</p>
            <Input
              value={destination}
              onChange={(e) => setDestination(e.target.value)}
              placeholder={t("withdrawal.destinationPlaceholder")}
            />
          </div>
          <div className="space-y-1.5">
            <p className="text-sm font-medium">{t("withdrawal.withdrawnOn")}</p>
            <DatePicker value={withdrawnOn} onChange={setWithdrawnOn} />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" className="h-11" onClick={() => onOpenChange(false)}>
            {tc("actions.cancel")}
          </Button>
          <Button variant="destructive" className="h-11" onClick={submit} loading={working}>
            {t("withdrawal.confirm")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
