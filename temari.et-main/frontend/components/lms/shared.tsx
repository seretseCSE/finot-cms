"use client"

import { useEffect, useState } from "react"

import { Badge } from "@/components/ui/badge"
import { DatePicker } from "@/components/ui/date-picker"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { TimePicker } from "@/components/ui/time-picker"
import { apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { LmsClassOption, QuizStatus } from "@/lib/types"
import { fmtDate, fmtTime } from "@/lib/dates"

/** Compact date-time like "12 Jul, 14:30" in the viewer's locale. */
/** "Part I", "Part II"… labels for exam paper parts. */
export function romanNumeral(n: number): string {
  const map: [number, string][] = [
    [1000, "M"], [900, "CM"], [500, "D"], [400, "CD"], [100, "C"], [90, "XC"],
    [50, "L"], [40, "XL"], [10, "X"], [9, "IX"], [5, "V"], [4, "IV"], [1, "I"],
  ]
  let out = ""
  for (const [value, token] of map) {
    while (n >= value) {
      out += token
      n -= value
    }
  }
  return out
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return "—"
  return `${fmtDate(value, { noYear: true })}, ${fmtTime(value)}`
}

export function formatFileSize(bytes: number | null | undefined): string {
  if (!bytes) return ""
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const QUIZ_STATUS_TONES: Record<QuizStatus, string> = {
  draft: "bg-muted text-muted-foreground",
  published: "bg-success/10 text-success",
  closed: "bg-warning/10 text-warning",
  archived: "bg-muted text-muted-foreground",
}

export function QuizStatusBadge({ status }: { status: QuizStatus }) {
  const { t } = useTranslation("lms")

  return (
    <Badge variant="outline" className={`border-transparent ${QUIZ_STATUS_TONES[status]}`}>
      {t(`exams.statuses.${status}`)}
    </Badge>
  )
}

export function AssignmentStatusBadge({ status }: { status: "draft" | "published" | "closed" }) {
  const { t } = useTranslation("lms")
  const tone =
    status === "published"
      ? "bg-success/10 text-success"
      : status === "closed"
        ? "bg-warning/10 text-warning"
        : "bg-muted text-muted-foreground"

  return (
    <Badge variant="outline" className={`border-transparent ${tone}`}>
      {t(`assignments.statuses.${status}`)}
    </Badge>
  )
}

/**
 * The teacher's (or supervisor's) classes for the active semester —
 * subject × section options every LMS create flow anchors to. Sourced from
 * GET /marklists (already ownership/supervisory scoped by the backend).
 */
export function useClassOptions(): { classes: LmsClassOption[]; loading: boolean } {
  const { active } = useSchoolContext()
  const [classes, setClasses] = useState<LmsClassOption[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!active.schoolId) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset when the workspace clears
      setClasses([])
      setLoading(false)
      return
    }
    let cancelled = false
    setLoading(true)
    ;(async () => {
      try {
        const terms = await apiFetch<{ data: { id: number; is_current: boolean; status: string }[] }>(
          "/terms",
        )
        const current =
          terms.data.find((term) => term.is_current) ??
          terms.data.find((term) => term.status === "active")
        if (!current) {
          if (!cancelled) setClasses([])
          return
        }
        const res = await apiFetch<{ data: LmsClassOption[] }>(
          `/marklists?term_id=${current.id}&per_page=100`,
        )
        if (!cancelled) setClasses(res.data)
      } catch {
        if (!cancelled) setClasses([])
      } finally {
        if (!cancelled) setLoading(false)
      }
    })()
    return () => {
      cancelled = true
    }
  }, [active.schoolId, active.branchId])

  return { classes, loading }
}

export function classLabel(option: LmsClassOption): string {
  const grade = option.section.grade_level ? `${option.section.grade_level} ` : ""
  return `${option.subject.name ?? ""} · ${grade}${option.section.name ?? ""}`
}

/** Class dropdown used by every LMS create/edit sheet. */
export function ClassSelect({
  value,
  onChange,
  disabled,
}: {
  value: number | null
  onChange: (id: number) => void
  disabled?: boolean
}) {
  const { t } = useTranslation("lms")
  const { classes, loading } = useClassOptions()

  return (
    <div className="space-y-2">
      <Label>
        {t("classes.pick")} <span className="text-destructive">*</span>
      </Label>
      <Select
        value={value ? String(value) : undefined}
        onValueChange={(v) => onChange(Number(v))}
        disabled={disabled || loading}
      >
        <SelectTrigger className="w-full">
          <SelectValue placeholder={t("classes.pickPlaceholder")} />
        </SelectTrigger>
        <SelectContent>
          {classes.length === 0 ? (
            <p className="px-3 py-2 text-sm text-muted-foreground">{t("classes.none")}</p>
          ) : (
            classes.map((option) => (
              <SelectItem key={option.subject_assignment_id} value={String(option.subject_assignment_id)}>
                {classLabel(option)}
              </SelectItem>
            ))
          )}
        </SelectContent>
      </Select>
    </div>
  )
}

/** Split date + time inputs writing one ISO datetime string (local time). */
export function DateTimeField({
  label,
  value,
  onChange,
  min,
}: {
  label: string
  value: string | null
  onChange: (value: string | null) => void
  /** Earliest selectable day, ISO `yyyy-MM-dd`. */
  min?: string
}) {
  const date = value ? value.slice(0, 10) : ""
  const time = value ? new Date(value).toTimeString().slice(0, 5) : ""

  function emit(nextDate: string, nextTime: string) {
    if (!nextDate) {
      onChange(null)
      return
    }
    onChange(`${nextDate}T${nextTime || "08:00"}:00`)
  }

  return (
    <div className="space-y-2">
      <Label>{label}</Label>
      {/* Both columns are minmax(0,1fr): an `auto` time column sizes to the
          longest label ("2:30 morning") and pushes the pair past the panel. */}
      <div className="grid grid-cols-2 gap-2">
        <DatePicker value={date} onChange={(next) => emit(next, time)} min={min} />
        <TimePicker value={time} onChange={(next) => emit(date, next)} disabled={!date} />
      </div>
    </div>
  )
}
