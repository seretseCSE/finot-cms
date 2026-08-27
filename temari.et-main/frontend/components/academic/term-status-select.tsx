"use client"

import { useState } from "react"
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
import type { Term, TermStatus } from "@/lib/types"
import { cn } from "@/lib/utils"

const STATUS_DOT: Record<TermStatus, string> = {
  planned: "bg-info",
  active: "bg-success",
  closed: "bg-muted-foreground",
}

/** Legal moves per current status — the lifecycle, not a free choice. */
const TRANSITIONS: Record<TermStatus, TermStatus[]> = {
  planned: ["active", "closed"],
  active: ["closed"],
  closed: ["active", "planned"],
}

/**
 * The semester lifecycle as an in-place dropdown with a confirmation step.
 * ONE ACTIVE SEMESTER per academic year + program: activating this one closes
 * every other active sibling (listed by name in the dialog — closed semesters
 * become read-only). Closing freezes the semester; reopening returns it to
 * planned without activating it.
 */
export function TermStatusSelect({
  term,
  siblings,
  canUpdate,
  onChanged,
}: {
  term: Term
  /** Other terms of the same table/page — used to name what activation closes. */
  siblings: Term[]
  canUpdate: boolean
  onChanged: (term: Term, closedNames: string[]) => void
}) {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")
  const [pending, setPending] = useState<TermStatus | null>(null)
  const [working, setWorking] = useState(false)

  if (!canUpdate) {
    return (
      <Badge variant={term.status === "active" ? "default" : "secondary"}>
        {t(`terms.statuses.${term.status}`)}
      </Badge>
    )
  }

  // Same year + same program + currently active = will be auto-closed.
  const affected = siblings.filter(
    (s) =>
      s.id !== term.id &&
      s.academic_year_id === term.academic_year_id &&
      (s.program?.id ?? null) === (term.program?.id ?? null) &&
      s.status === "active",
  )

  async function confirm() {
    if (!pending) return
    setWorking(true)
    try {
      const res = await apiFetch<{ data: Term; meta?: { closed_terms?: string[] } }>(
        `/terms/${term.id}/status`,
        { method: "PATCH", body: { status: pending } },
      )
      toast.success(t("terms.statusChanged", { status: t(`terms.statuses.${pending}`) }))
      onChanged(res.data, res.meta?.closed_terms ?? [])
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("terms.statusChangeFailed"))
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  const programName = term.program?.name ?? ""
  const affectedNames = affected.map((s) => s.name).join(", ")

  function description(): string {
    if (pending === "active") {
      return affected.length > 0
        ? t("terms.confirmActivateWithSiblings", {
            name: term.name,
            siblings: affectedNames,
            program: programName,
          })
        : t("terms.confirmActivate", { name: term.name })
    }
    if (pending === "closed") return t("terms.confirmClose", { name: term.name })
    return t("terms.confirmReopen", { name: term.name })
  }

  return (
    <div onClick={(e) => e.stopPropagation()}>
      <Select
        value={term.status}
        onValueChange={(value) => {
          if (value !== term.status) setPending(value as TermStatus)
        }}
      >
        <SelectTrigger
          className="h-8 w-auto min-w-28 gap-1.5 rounded-full border-border/70 bg-muted/30 px-3 text-xs font-medium"
          aria-label={t("terms.status")}
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {/* Current status renders (for SelectValue) but stays non-actionable;
              only legal lifecycle moves are offered. */}
          {([term.status, ...TRANSITIONS[term.status]] as TermStatus[]).map((s) => (
            <SelectItem key={s} value={s} disabled={s === term.status}>
              <span className="flex items-center gap-2">
                <span className={cn("size-1.5 rounded-full", STATUS_DOT[s])} />
                {t(`terms.statuses.${s}`)}
              </span>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <AlertDialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("terms.statusConfirmTitle", {
                status: pending ? t(`terms.statuses.${pending}`) : "",
                name: term.name,
              })}
            </AlertDialogTitle>
            <AlertDialogDescription>{description()}</AlertDialogDescription>
          </AlertDialogHeader>
          {pending === "active" && affected.length > 0 && (
            <div className="rounded-xl border border-warning/30 bg-warning/10 px-3 py-2 text-sm">
              <p className="font-medium">{t("terms.willBeClosed")}</p>
              <ul className="mt-1 space-y-0.5 text-muted-foreground">
                {affected.map((s) => (
                  <li key={s.id}>
                    {s.name} · {s.program?.name}
                  </li>
                ))}
              </ul>
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
              {t("terms.statusConfirmCta")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
