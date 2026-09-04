"use client"

import { ArrowRight } from "lucide-react"
import { useMemo, useState } from "react"
import { toast } from "sonner"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Badge } from "@/components/ui/badge"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AcademicYear, AcademicYearStatus } from "@/lib/types"
import { cn } from "@/lib/utils"

const STATUSES: AcademicYearStatus[] = ["planned", "active", "completed", "archived"]

const STATUS_DOT: Record<AcademicYearStatus, string> = {
  planned: "bg-info",
  active: "bg-success",
  completed: "bg-muted-foreground",
  archived: "bg-warning",
}

/**
 * The year's lifecycle as an in-place dropdown with a confirmation step —
 * switching to Active hands the branch's operating year over to this one
 * (the previous active year completes automatically).
 */
export function YearStatusSelect({
  year,
  years = [],
  canUpdate,
  onChanged,
}: {
  year: AcademicYear
  /** Sibling years, so the confirm step can name the one being completed. */
  years?: AcademicYear[]
  canUpdate: boolean
  onChanged: (year: AcademicYear) => void
}) {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")
  const [pending, setPending] = useState<AcademicYearStatus | null>(null)
  const [working, setWorking] = useState(false)

  // The branch's current operating year — it is demoted to completed when this
  // one is activated (one active year per branch).
  const outgoing = useMemo(
    () =>
      years.find(
        (y) => y.id !== year.id && y.branch_id === year.branch_id && y.status === "active"
      ) ?? null,
    [years, year.id, year.branch_id]
  )

  if (!canUpdate) {
    return (
      <Badge variant={year.status === "active" ? "default" : "secondary"}>
        {t(`years.statuses.${year.status}`)}
      </Badge>
    )
  }

  async function confirm() {
    if (!pending) return
    setWorking(true)
    try {
      const res = await apiFetch<{ data: AcademicYear }>(`/academic-years/${year.id}/status`, {
        method: "PATCH",
        body: { status: pending },
      })
      toast.success(t("years.statusChanged", { status: t(`years.statuses.${pending}`) }))
      onChanged(res.data)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("years.statusChangeFailed"))
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  return (
    <div onClick={(e) => e.stopPropagation()}>
      <Select
        value={year.status}
        onValueChange={(value) => {
          if (value !== year.status) setPending(value as AcademicYearStatus)
        }}
      >
        {/* The dot lives inside each item's content, which SelectValue mirrors
            for the selected one — no extra dot in the trigger itself. */}
        <SelectTrigger
          className="h-8 w-auto min-w-32 gap-1.5 rounded-full border-border/70 bg-muted/30 px-3 text-xs font-medium"
          aria-label={t("years.status")}
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {STATUSES.map((s) => (
            <SelectItem key={s} value={s}>
              <span className="flex items-center gap-2">
                <span className={cn("size-1.5 rounded-full", STATUS_DOT[s])} />
                {t(`years.statuses.${s}`)}
              </span>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <AlertDialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {pending === "active"
                ? t("years.statusConfirmActiveTitle", { name: year.name })
                : t("years.statusConfirmTitle", {
                    status: pending ? t(`years.statuses.${pending}`) : "",
                  })}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {pending === "active"
                ? t("years.statusConfirmActive", { name: year.name })
                : t("years.statusConfirmDesc", {
                    name: year.name,
                    status: pending ? t(`years.statuses.${pending}`) : "",
                  })}
            </AlertDialogDescription>
          </AlertDialogHeader>

          {pending === "active" && (
            <div className="space-y-2 rounded-xl border bg-muted/30 p-3 text-sm">
              {year.branch_name && (
                <p className="text-xs text-muted-foreground">
                  {t("years.statusConfirmBranch", {
                    branch: year.school_name
                      ? `${year.school_name} · ${year.branch_name}`
                      : year.branch_name,
                  })}
                </p>
              )}
              {outgoing && (
                <TransitionRow name={outgoing.name} from="active" to="completed" t={t} />
              )}
              <TransitionRow name={year.name} from={year.status} to="active" t={t} />
            </div>
          )}
          <AlertDialogFooter>
            <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={working}
              onClick={(e) => {
                e.preventDefault()
                confirm()
              }}
            >
              {t("years.statusConfirmCta")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

/** One "{name}: from → to" line in the activation summary. */
function TransitionRow({
  name,
  from,
  to,
  t,
}: {
  name: string
  from: AcademicYearStatus
  to: AcademicYearStatus
  t: (key: string) => string
}) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="font-medium">{name}</span>
      <span className="flex shrink-0 items-center gap-1.5 text-xs">
        <span className="flex items-center gap-1 text-muted-foreground">
          <span className={cn("size-1.5 rounded-full", STATUS_DOT[from])} />
          {t(`years.statuses.${from}`)}
        </span>
        <ArrowRight className="size-3 text-muted-foreground" />
        <span
          className={cn(
            "flex items-center gap-1 font-medium",
            to === "active" ? "text-success" : "text-muted-foreground"
          )}
        >
          <span className={cn("size-1.5 rounded-full", STATUS_DOT[to])} />
          {t(`years.statuses.${to}`)}
        </span>
      </span>
    </div>
  )
}
