"use client"

import { CheckCheck, GraduationCap, MessageSquarePlus, Save, TrendingUp, Undo2 } from "lucide-react"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
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
import { BranchScopePicker, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
  DataTable,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type {
  AcademicYear,
  Paginated,
  PromotionBoardMeta,
  PromotionBoardRow,
  PromotionDecision,
  RevertResult,
  RolloverResult,
  Section,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const NONE = "none"
const DECISIONS: PromotionDecision[] = ["promoted", "repeated", "graduated", "withdrawn"]

type Draft = { decision: PromotionDecision | null; notes: string }

/** Board row + flat fields DataTable can search/sort on. */
type BoardRow = PromotionBoardRow & { id: number; student_name: string; public_id: string }

/** BoardRow + client-computed flat fields the table filters/tints on. */
type DisplayRow = BoardRow & {
  average_band: "pass" | "below" | "none"
  attendance_band: "high" | "medium" | "low" | "none"
  suggestion_key: string
  decision_key: string
  section_key: string
}

/** Soft row tint per (effective) decision — readable in light and dark. */
const ROW_TINTS: Record<string, string> = {
  promoted: "bg-success/5 hover:bg-success/10",
  graduated: "bg-info/5 hover:bg-info/10",
  repeated: "bg-warning/[0.07] hover:bg-warning/[0.12]",
  withdrawn: "bg-destructive/5 hover:bg-destructive/10",
}

/** Color of an annual average vs the school threshold. */
function averageTone(value: number | null, threshold: number): string {
  if (value === null) return "text-muted-foreground"
  return value >= threshold ? "text-success" : "text-destructive"
}

/** "Add note" text button → popover textarea. Edits write into the draft. */
function NoteCell({
  note,
  studentName,
  onChange,
}: {
  note: string
  studentName: string
  onChange: (notes: string) => void
}) {
  const { t } = useTranslation("promotion")

  return (
    <div onClick={(e) => e.stopPropagation()}>
      <Popover>
        <PopoverTrigger asChild>
          <button
            type="button"
            className={cn(
              "pressable inline-flex h-7 max-w-40 items-center gap-1 rounded-full px-2 text-xs transition-colors hover:bg-muted",
              note ? "text-foreground" : "text-muted-foreground",
            )}
          >
            <MessageSquarePlus className="size-3.5 shrink-0 opacity-60" />
            <span className="truncate">{note || t("addNote")}</span>
          </button>
        </PopoverTrigger>
        <PopoverContent align="start" className="w-72 rounded-xl p-3">
          <p className="mb-2 truncate text-xs font-medium text-muted-foreground">{studentName}</p>
          <textarea
            value={note}
            onChange={(e) => onChange(e.target.value)}
            placeholder={t("notesPlaceholder")}
            rows={3}
            autoFocus
            className="w-full resize-none rounded-lg border border-input/70 bg-muted/30 px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
          />
        </PopoverContent>
      </Popover>
    </div>
  )
}

export default function PromotionBoardPage() {
  const { t } = useTranslation("promotion")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const [years, setYears] = useState<AcademicYear[]>([])
  const [yearId, setYearId] = useState<number | null>(null)
  // Branch-scoped grade offering, session-cached across pages.
  const { grades } = useGradeLevels()
  const [gradeId, setGradeId] = useState<number | null>(null)
  const [sections, setSections] = useState<Section[]>([])
  const [rows, setRows] = useState<BoardRow[] | null>(null)
  const [meta, setMeta] = useState<PromotionBoardMeta | null>(null)
  // Unsaved edits, keyed by enrollment id.
  const [drafts, setDrafts] = useState<Record<number, Draft>>({})
  const [saving, setSaving] = useState(false)
  const [rolloverOpen, setRolloverOpen] = useState(false)
  const [targetYearId, setTargetYearId] = useState<number | null>(null)
  const [rolling, setRolling] = useState(false)
  const [rolloverResult, setRolloverResult] = useState<RolloverResult | null>(null)
  // Revert (the rollover safety net): null target = the whole executed batch
  // in the current grade scope; a row = that one student.
  const [revertOpen, setRevertOpen] = useState(false)
  const [revertTarget, setRevertTarget] = useState<BoardRow | null>(null)
  const [reverting, setReverting] = useState(false)
  const [revertResult, setRevertResult] = useState<RevertResult | null>(null)

  // Pass-mark preview: null = use the branch setting from meta. Editing it
  // recomputes suggestions/colors live WITHOUT touching the branch default —
  // unless the "update branch default" checkbox is on.
  const [passMarkText, setPassMarkText] = useState<string | null>(null)
  const [updateBranchDefault, setUpdateBranchDefault] = useState(false)
  const [tableFilters, setTableFilters] = useState<Record<string, string>>({})

  const hasBranch = active.branchId != null
  const canManageSettings = permissions.includes("branch_settings.manage")

  // School-wide workspace: the board is one-branch-at-a-time (a promotion
  // year is branch-anchored), so school managers pick which branch's board
  // to run — no workspace switch needed. Backend permission checks run
  // against the chosen year's own branch.
  const { needsBranch } = useBranchScope()
  const [pickedBranchId, setPickedBranchId] = useState<number | null>(null)
  const branchReady = hasBranch || (needsBranch && pickedBranchId != null)
  const effectiveBranchId = active.branchId ?? pickedBranchId
  const branchParam = !hasBranch && pickedBranchId != null ? `&branch_id=${pickedBranchId}` : ""

  useEffect(() => {
    if (!branchReady) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on context switch
      setYears([])
      setYearId(null)
      setRows(null)
      setSections([])
      setPassMarkText(null)
      setUpdateBranchDefault(false)
      return
    }
    let cancelled = false
    setPassMarkText(null)
    setUpdateBranchDefault(false)
    apiFetch<Paginated<AcademicYear>>(`/academic-years?per_page=100${branchParam}`)
      .then((res) => {
        if (cancelled) return
        setYears(res.data)
        setYearId((prev) =>
          prev && res.data.some((y) => y.id === prev)
            ? prev
            : (res.data.find((y) => y.status === "active")?.id ?? res.data[0]?.id ?? null),
        )
      })
      .catch(() => {})
    apiFetch<Paginated<Section>>(`/sections?per_page=100${branchParam}`)
      .then((res) => {
        if (cancelled) return
        setSections(res.data.filter((s) => s.is_active))
      })
      .catch(() => {
        if (!cancelled) setSections([])
      })
    return () => {
      cancelled = true
    }
  }, [branchReady, branchParam, active.branchId])

  // Default the grade picker to the FIRST grade this branch actually runs
  // (has active sections for) — once per branch, user choice wins after that.
  const gradeDefaultedFor = useRef<number | null>(null)
  useEffect(() => {
    if (!branchReady || grades.length === 0 || sections.length === 0) return
    if (gradeDefaultedFor.current === effectiveBranchId) return
    gradeDefaultedFor.current = effectiveBranchId
    const withSections = new Set(sections.map((s) => s.grade_level_id))
    const first = [...grades]
      .sort((a, b) => a.sort_order - b.sort_order)
      .find((g) => withSections.has(g.id))
    // eslint-disable-next-line react-hooks/set-state-in-effect -- one-time default per branch
    if (first) setGradeId(first.id)
  }, [branchReady, effectiveBranchId, grades, sections])

  // A stale section filter from another grade would blank the table.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear the dependent filter
    setTableFilters((prev) => (prev.section_key ? { ...prev, section_key: "" } : prev))
  }, [gradeId])

  const loadBoard = useCallback(() => {
    if (!yearId) return
    setRows(null)
    let cancelled = false
    const params = new URLSearchParams({ academic_year_id: String(yearId) })
    if (gradeId) params.set("grade_level_id", String(gradeId))
    apiFetch<{ data: PromotionBoardRow[]; meta: PromotionBoardMeta }>(
      `/promotions/board?${params}`,
    )
      .then((res) => {
        if (cancelled) return
        setRows(
          res.data.map((row) => ({
            ...row,
            id: row.enrollment_id,
            student_name: row.student.full_name,
            public_id: row.student.public_id ?? "",
          })),
        )
        setMeta(res.meta)
        setDrafts({})
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    return () => {
      cancelled = true
    }
  }, [yearId, gradeId, tc])

  // eslint-disable-next-line react-hooks/set-state-in-effect -- loadBoard resets to the loading state
  useEffect(() => loadBoard(), [loadBoard])

  /** Effective (draft-over-saved) decision of a row. */
  const effective = useCallback(
    (row: BoardRow): Draft => {
      const draft = drafts[row.enrollment_id]
      if (draft) return draft
      return { decision: row.decision?.value ?? null, notes: row.decision?.notes ?? "" }
    },
    [drafts],
  )

  // ── Pass mark: branch default from meta, overridable for this board ──────
  const branchDefault = meta?.threshold ?? 50
  const threshold = useMemo(() => {
    if (passMarkText === null || passMarkText.trim() === "") return branchDefault
    const parsed = Number(passMarkText)
    if (Number.isNaN(parsed)) return branchDefault
    return Math.min(100, Math.max(0, parsed))
  }, [passMarkText, branchDefault])
  const isPreview = threshold !== branchDefault

  /** Persist the current pass mark as the BRANCH default (checkbox flow). */
  const persistBranchDefault = useCallback(
    async (value: number) => {
      if (!effectiveBranchId || value === (meta?.threshold ?? 50)) return
      try {
        await apiFetch(`/branches/${effectiveBranchId}/settings`, {
          method: "PATCH",
          body: { promotion_threshold: value },
        })
        setMeta((prev) => (prev ? { ...prev, threshold: value } : prev))
        setPassMarkText(null)
        toast.success(t("branchDefaultSaved", { value }))
      } catch (error) {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      }
    },
    [effectiveBranchId, meta?.threshold, t, tc],
  )

  /** Same policy the backend applies, against the LIVE pass mark. */
  const gradeSortById = useMemo(
    () => new Map(grades.map((g) => [g.id, g.sort_order])),
    [grades],
  )
  const suggestFor = useCallback(
    (row: BoardRow): PromotionDecision | null => {
      if (row.annual_average === null) return null
      if (row.annual_average < threshold) return "repeated"
      const sort = gradeSortById.get(row.grade_level_id)
      const topSort = meta?.top_grade_sort
      if (topSort != null && sort != null && sort >= topSort) return "graduated"
      return "promoted"
    },
    [threshold, gradeSortById, meta?.top_grade_sort],
  )

  /** Rows with recomputed suggestions + flat keys the table filters/tints on. */
  const displayRows = useMemo<DisplayRow[] | null>(() => {
    if (rows === null) return null
    return rows.map((row) => {
      const suggestion = suggestFor(row)
      const decision = effective(row).decision
      return {
        ...row,
        suggestion,
        average_band:
          row.annual_average === null
            ? "none"
            : row.annual_average >= threshold
              ? "pass"
              : "below",
        attendance_band:
          row.attendance_rate === null
            ? "none"
            : row.attendance_rate >= 90
              ? "high"
              : row.attendance_rate >= 75
                ? "medium"
                : "low",
        suggestion_key: suggestion ?? "none",
        decision_key: decision ?? "none",
        section_key: row.section_name ?? "__none",
      }
    })
  }, [rows, suggestFor, effective, threshold])

  const stats = useMemo(() => {
    const list = rows ?? []
    let decided = 0
    let executed = 0
    // Transfers are executed too, but their lifecycle belongs to the transfer
    // workflow — the revert lane refuses them, so they never count as revertable.
    let revertable = 0
    for (const row of list) {
      if (row.decision?.executed_at) {
        executed++
        if (row.decision.value !== "transferred") revertable++
      } else if (effective(row).decision) decided++
    }
    return { decided, executed, revertable, undecided: list.length - decided - executed }
  }, [rows, effective])

  const dirtyCount = Object.keys(drafts).length

  function setDraft(row: BoardRow, patch: Partial<Draft>) {
    setDrafts((prev) => ({
      ...prev,
      [row.enrollment_id]: { ...effective(row), ...patch },
    }))
  }

  function acceptSuggestions() {
    setDrafts((prev) => {
      const next = { ...prev }
      for (const row of displayRows ?? []) {
        if (row.decision?.executed_at) continue
        const current = next[row.enrollment_id]?.decision ?? row.decision?.value ?? null
        if (!current && row.suggestion) {
          next[row.enrollment_id] = {
            decision: row.suggestion,
            notes: next[row.enrollment_id]?.notes ?? row.decision?.notes ?? "",
          }
        }
      }
      return next
    })
  }

  async function saveDecisions() {
    if (!yearId || dirtyCount === 0) return
    setSaving(true)
    try {
      const decisions = Object.entries(drafts).map(([enrollmentId, draft]) => ({
        enrollment_id: Number(enrollmentId),
        decision: draft.decision,
        notes: draft.notes || null,
      }))
      for (let i = 0; i < decisions.length; i += 400) {
        await apiFetch("/promotions/decisions", {
          method: "POST",
          body: { academic_year_id: yearId, decisions: decisions.slice(i, i + 400) },
        })
      }
      toast.success(t("saved"))
      loadBoard()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  async function runRollover() {
    if (!yearId || !targetYearId) return
    setRolling(true)
    try {
      const res = await apiFetch<{ data: RolloverResult }>("/promotions/rollover", {
        method: "POST",
        body: { academic_year_id: yearId, to_academic_year_id: targetYearId },
      })
      setRolloverResult(res.data)
      toast.success(t("rolloverDone", { count: res.data.executed }))
      loadBoard()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      setRolloverOpen(false)
    } finally {
      setRolling(false)
    }
  }

  async function runRevert() {
    if (!yearId) return
    setReverting(true)
    try {
      const res = await apiFetch<{ data: RevertResult }>("/promotions/revert", {
        method: "POST",
        body: {
          academic_year_id: yearId,
          ...(revertTarget
            ? { enrollment_ids: [revertTarget.enrollment_id] }
            : gradeId
              ? { grade_level_id: gradeId }
              : {}),
        },
      })
      setRevertResult(res.data)
      toast.success(t("revertDone", { count: res.data.reverted }))
      loadBoard()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      setRevertOpen(false)
    } finally {
      setReverting(false)
    }
  }

  const targetYears = useMemo(
    () => years.filter((y) => y.id !== yearId && (y.status === "planned" || y.status === "active")),
    [years, yearId],
  )

  // ── Table filters: average band, attendance band, suggestion, decision,
  //    section — all client-side over the loaded board. ─────────────────────
  const sectionOptions = useMemo(() => {
    const inScope = gradeId
      ? sections.filter((s) => s.grade_level_id === gradeId)
      : sections
    const names = [...new Set(inScope.map((s) => s.name))].sort((a, b) =>
      a.localeCompare(b, undefined, { numeric: true }),
    )
    return names
  }, [sections, gradeId])

  const filterDefs: DataTableFilter[] = useMemo(
    () => [
      {
        key: "average_band",
        label: t("columns.average"),
        options: [
          { label: t("filters.aboveMark"), value: "pass" },
          { label: t("filters.belowMark"), value: "below" },
          { label: t("noMarks"), value: "none" },
        ],
      },
      {
        key: "attendance_band",
        label: t("columns.attendance"),
        options: [
          { label: t("filters.attHigh"), value: "high" },
          { label: t("filters.attMedium"), value: "medium" },
          { label: t("filters.attLow"), value: "low" },
          { label: t("filters.noData"), value: "none" },
        ],
      },
      {
        key: "suggestion_key",
        label: t("columns.suggestion"),
        options: [
          { label: t("labels.promoted"), value: "promoted" },
          { label: t("labels.graduated"), value: "graduated" },
          { label: t("labels.repeated"), value: "repeated" },
          { label: t("noMarks"), value: "none" },
        ],
      },
      {
        key: "decision_key",
        label: t("columns.decision"),
        options: [
          { label: t("decisions.none"), value: "none" },
          ...DECISIONS.map((decision) => ({
            label: t(`decisions.${decision}`),
            value: decision,
          })),
        ],
      },
    ],
    [t],
  )

  const columns: DataTableColumn<DisplayRow>[] = useMemo(
    () => [
      {
        key: "student_name",
        label: t("columns.student"),
        primary: true,
        sortable: true,
        // One line per student — name and class side by side keeps the
        // register short enough to scan hundreds of rows.
        render: (row) => (
          <div className="flex min-w-0 items-center gap-2">
            <PersonAvatar
              name={row.student.full_name}
              photoUrl={row.student.photo_url}
              className="size-6 text-[10px]"
            />
            <span className="truncate text-sm font-medium">{row.student.full_name}</span>
            <span className="shrink-0 text-xs text-muted-foreground">
              {row.grade_level_name}
              {row.section_name ? ` · ${row.section_name}` : ""}
            </span>
          </div>
        ),
        exportValue: (row) => row.student.full_name,
      },
      {
        key: "annual_average",
        label: t("columns.average"),
        sortable: true,
        render: (row) => (
          <div className="flex items-baseline gap-2 whitespace-nowrap">
            <span className={cn("text-sm font-semibold tabular-nums", averageTone(row.annual_average, threshold))}>
              {row.annual_average !== null ? row.annual_average.toFixed(1) : "—"}
            </span>
            {row.term_averages.length > 1 && (
              <span className="text-[11px] text-muted-foreground tabular-nums">
                {row.term_averages
                  .map((term, i) => `${t("semester", { n: i + 1 })} ${term.average ?? "—"}`)
                  .join(" · ")}
              </span>
            )}
          </div>
        ),
        exportValue: (row) => (row.annual_average !== null ? String(row.annual_average) : ""),
      },
      {
        key: "attendance_rate",
        label: t("columns.attendance"),
        mobileHidden: true,
        sortable: true,
        render: (row) =>
          row.attendance_rate !== null ? (
            <span className="text-sm tabular-nums">{row.attendance_rate}%</span>
          ) : (
            "—"
          ),
        exportValue: (row) => (row.attendance_rate !== null ? `${row.attendance_rate}%` : ""),
      },
      {
        key: "suggestion",
        label: t("columns.suggestion"),
        mobileHidden: true,
        render: (row) =>
          row.suggestion ? (
            <Badge
              variant="outline"
              className={cn(
                row.suggestion === "promoted" || row.suggestion === "graduated"
                  ? "border-success/30 bg-success/10 text-success"
                  : "border-warning/30 bg-warning/10 text-warning",
              )}
            >
              {t(`labels.${row.suggestion}`)}
            </Badge>
          ) : (
            <span className="text-xs text-muted-foreground">{t("noMarks")}</span>
          ),
        exportValue: (row) => (row.suggestion ? t(`labels.${row.suggestion}`) : ""),
      },
      {
        key: "decision",
        label: t("columns.decision"),
        render: (row) => {
          if (row.decision?.executed_at) {
            return (
              <Badge className="bg-primary/10 text-primary" variant="outline">
                <CheckCheck className="size-3" />
                {t(`labels.${row.decision.value}`)}
              </Badge>
            )
          }
          const current = effective(row)
          return (
            <div onClick={(e) => e.stopPropagation()}>
              <Select
                value={current.decision ?? NONE}
                onValueChange={(v) =>
                  setDraft(row, { decision: v === NONE ? null : (v as PromotionDecision) })
                }
              >
                <SelectTrigger
                  className={cn(
                    "h-7 min-h-7 w-auto min-w-28 gap-1.5 rounded-full border-border/70 bg-muted/30 px-2.5 text-xs font-medium",
                    !current.decision && "text-muted-foreground",
                    drafts[row.enrollment_id] && "border-primary/50",
                  )}
                  aria-label={t("columns.decision")}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={NONE}>{t("decisions.none")}</SelectItem>
                  {DECISIONS.map((decision) => (
                    <SelectItem key={decision} value={decision}>
                      {t(`decisions.${decision}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )
        },
        exportValue: (row) => {
          const decision = effective(row).decision
          return decision ? t(`labels.${decision}`) : ""
        },
      },
      {
        key: "notes",
        label: t("columns.notes"),
        mobileHidden: true,
        // A note is the exception, not the rule — a quiet text button keeps
        // the row a single line; the popover holds the actual textarea.
        render: (row) =>
          row.decision?.executed_at ? (
            <span className="block max-w-40 truncate text-xs text-muted-foreground">
              {row.decision?.notes ?? "—"}
            </span>
          ) : (
            <NoteCell
              note={effective(row).notes}
              studentName={row.student.full_name}
              onChange={(notes) => setDraft(row, { notes })}
            />
          ),
        exportValue: (row) => effective(row).notes,
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps -- setDraft is stable in behaviour
    [t, threshold, effective, drafts],
  )

  return (
    <div className="space-y-6 pb-24">
      <PageHeader
        title={t("title")}
        description={t("subtitle")}
        actions={
          branchReady && (rows?.length ?? 0) > 0 ? (
            <Button
              variant="outline"
              onClick={acceptSuggestions}
              disabled={stats.undecided === 0}
            >
              <CheckCheck className="size-4" />
              {t("acceptSuggestions")}
            </Button>
          ) : undefined
        }
      />

      {needsBranch && (
        <div className="page-gutter">
          <BranchScopePicker value={pickedBranchId} onChange={setPickedBranchId} />
        </div>
      )}

      {!branchReady ? (
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("noBranch")}
          </div>
        </div>
      ) : (
        <>
          <div className="page-gutter flex flex-wrap items-center gap-2">
            <Select
              value={yearId ? String(yearId) : ""}
              onValueChange={(v) => setYearId(Number(v))}
            >
              <SelectTrigger className="h-9 w-auto min-w-36 rounded-full bg-muted/30 text-xs font-medium" aria-label={t("year")}>
                <SelectValue placeholder={t("year")} />
              </SelectTrigger>
              <SelectContent>
                {years.map((year) => (
                  <SelectItem key={year.id} value={String(year.id)}>
                    {year.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select
              value={gradeId ? String(gradeId) : NONE}
              onValueChange={(v) => setGradeId(v === NONE ? null : Number(v))}
            >
              <SelectTrigger className="h-9 w-auto min-w-32 rounded-full bg-muted/30 text-xs font-medium" aria-label={t("allGrades")}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NONE}>{t("allGrades")}</SelectItem>
                {grades.map((grade) => (
                  <SelectItem key={grade.id} value={String(grade.id)}>
                    {grade.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {sectionOptions.length > 0 && (
              <Select
                value={tableFilters.section_key || NONE}
                onValueChange={(v) =>
                  setTableFilters((prev) => ({ ...prev, section_key: v === NONE ? "" : v }))
                }
              >
                <SelectTrigger className="h-9 w-auto min-w-32 rounded-full bg-muted/30 text-xs font-medium" aria-label={t("columns.section")}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={NONE}>{t("allSections")}</SelectItem>
                  {sectionOptions.map((name) => (
                    <SelectItem key={name} value={name}>
                      {name}
                    </SelectItem>
                  ))}
                  <SelectItem value="__none">{t("filters.noSection")}</SelectItem>
                </SelectContent>
              </Select>
            )}
            {/* Pass mark: shows the branch setting; editing it re-scores the
                board live. Only the checkbox writes it back to the branch. */}
            <div
              className={cn(
                "flex h-9 items-center gap-1.5 rounded-full border bg-muted/30 pl-3 pr-1.5",
                isPreview && "border-primary/40 bg-primary/5",
              )}
            >
              <label
                htmlFor="pass-mark"
                className="text-xs font-medium text-muted-foreground whitespace-nowrap"
              >
                {t("passMark")}
              </label>
              <Input
                id="pass-mark"
                type="number"
                inputMode="numeric"
                min={0}
                max={100}
                value={passMarkText ?? String(branchDefault)}
                onChange={(e) => setPassMarkText(e.target.value)}
                onBlur={() => {
                  if (updateBranchDefault && isPreview) persistBranchDefault(threshold)
                }}
                onKeyDown={(e) => {
                  if (e.key === "Enter") e.currentTarget.blur()
                }}
                className="h-7 w-14 rounded-full border-0 bg-background px-2 text-center text-xs font-semibold tabular-nums shadow-none"
                aria-label={t("passMark")}
              />
              <span className="pr-1 text-xs text-muted-foreground">%</span>
            </div>
            {isPreview && (
              <Badge
                variant="outline"
                className="rounded-full border-primary/30 bg-primary/5 text-xs text-primary"
              >
                {t("passMarkPreview", { value: branchDefault })}
              </Badge>
            )}
            {canManageSettings && (
              <label className="flex h-9 cursor-pointer items-center gap-1.5 rounded-full px-1.5 text-xs text-muted-foreground select-none">
                <Checkbox
                  checked={updateBranchDefault}
                  onCheckedChange={(v) => {
                    const checked = v === true
                    setUpdateBranchDefault(checked)
                    if (checked && isPreview) persistBranchDefault(threshold)
                  }}
                />
                {t("updateBranchDefault")}
              </label>
            )}
            {rows !== null && rows.length > 0 && (
              <div className="ml-auto flex flex-wrap gap-1.5 text-xs">
                <Badge variant="outline" className="rounded-full text-muted-foreground">
                  {t("pendingCount", { count: stats.undecided })}
                </Badge>
                <Badge variant="outline" className="rounded-full border-info/30 bg-info/10 text-info">
                  {t("decidedCount", { count: stats.decided })}
                </Badge>
                <Badge variant="outline" className="rounded-full border-success/30 bg-success/10 text-success">
                  {t("executedCount", { count: stats.executed })}
                </Badge>
              </div>
            )}
          </div>

          <DataTable
            columns={columns}
            data={displayRows ?? []}
            loading={displayRows === null}
            dense
            searchKeys={["student_name", "public_id"]}
            searchPlaceholder={tc("actions.search")}
            filters={filterDefs}
            filterValues={tableFilters}
            onFilterChange={(key, value) =>
              setTableFilters((prev) => ({ ...prev, [key]: value }))
            }
            actions={[
              {
                label: t("revertRow"),
                icon: Undo2,
                destructive: true,
                hidden: (row) =>
                  !row.decision?.executed_at || row.decision.value === "transferred",
                onClick: (row) => {
                  setRevertTarget(row)
                  setRevertResult(null)
                  setRevertOpen(true)
                },
              },
            ]}
            rowClassName={(row) => ROW_TINTS[row.decision_key]}
            emptyMessage={t("empty")}
            exportFilename="promotion-board"
          />

          {/* Sticky action bar: save + rollover + revert — always reachable. */}
          {(dirtyCount > 0 || stats.decided > 0 || stats.executed > 0) && (
            <div className="fixed inset-x-0 bottom-20 z-30 flex justify-center px-4 md:bottom-6">
              <div className="flex items-center gap-2 rounded-full border bg-background/95 p-1.5 pl-4 shadow-lg backdrop-blur-xl">
                <span className="text-xs font-medium text-muted-foreground">
                  {dirtyCount > 0
                    ? t("unsaved", { count: dirtyCount })
                    : stats.decided > 0
                      ? t("decidedCount", { count: stats.decided })
                      : t("executedCount", { count: stats.executed })}
                </span>
                {dirtyCount > 0 && (
                  <Button size="sm" onClick={saveDecisions} loading={saving}>
                    <Save className="size-4" />
                    {t("saveDecisions")}
                  </Button>
                )}
                {dirtyCount === 0 && stats.decided > 0 && (
                  <Button
                    size="sm"
                    onClick={() => {
                      setRolloverResult(null)
                      setTargetYearId(targetYears[0]?.id ?? null)
                      setRolloverOpen(true)
                    }}
                  >
                    <TrendingUp className="size-4" />
                    {t("rollover")}
                  </Button>
                )}
                {dirtyCount === 0 && stats.revertable > 0 && (
                  <Button
                    size="sm"
                    variant="outline"
                    className="text-destructive hover:text-destructive"
                    onClick={() => {
                      setRevertTarget(null)
                      setRevertResult(null)
                      setRevertOpen(true)
                    }}
                  >
                    <Undo2 className="size-4" />
                    {t("revert")}
                  </Button>
                )}
              </div>
            </div>
          )}
        </>
      )}

      {/* Rollover dialog: pick the target year → run → results. */}
      <AlertDialog open={rolloverOpen} onOpenChange={(open) => !rolling && setRolloverOpen(open)}>
        <AlertDialogContent>
          {/* Once it has run, the header states the OUTCOME — leaving the
              question up (with a now-stale count) reads as a failure. */}
          <AlertDialogHeader>
            <AlertDialogTitle>
              {rolloverResult !== null
                ? t("rolloverResultTitle")
                : t("rolloverTitle", {
                    year: targetYears.find((y) => y.id === targetYearId)?.name ?? "…",
                  })}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {rolloverResult !== null ? t("rolloverResultDesc") : t("rolloverDesc")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          {rolloverResult === null ? (
            <>
              {targetYears.length === 0 ? (
                <p className="rounded-xl bg-warning/10 px-4 py-3 text-sm text-warning">
                  {t("noTargetYear")}
                </p>
              ) : (
                <Select
                  value={targetYearId ? String(targetYearId) : ""}
                  onValueChange={(v) => setTargetYearId(Number(v))}
                >
                  <SelectTrigger className="w-full rounded-xl bg-muted/30" aria-label={t("targetYear")}>
                    <SelectValue placeholder={t("targetYear")} />
                  </SelectTrigger>
                  <SelectContent>
                    {targetYears.map((year) => (
                      <SelectItem key={year.id} value={String(year.id)}>
                        {year.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
              <AlertDialogFooter>
                <AlertDialogCancel disabled={rolling}>{tc("actions.cancel")}</AlertDialogCancel>
                <AlertDialogAction
                  loading={rolling} disabled={!targetYearId}
                  onClick={(e) => {
                    e.preventDefault()
                    runRollover()
                  }}
                >
                  {t("rollover")}
                </AlertDialogAction>
              </AlertDialogFooter>
            </>
          ) : (
            <>
              <div className="space-y-2">
                <p className="flex items-center gap-2 text-sm font-medium text-success">
                  <GraduationCap className="size-4" />
                  {t("rolloverDone", { count: rolloverResult.executed })}
                </p>
                {rolloverResult.errors.length > 0 && (
                  <div className="max-h-40 space-y-1 overflow-y-auto rounded-xl bg-destructive/5 p-3">
                    <p className="text-xs font-medium text-destructive">
                      {t("rolloverSkipped", { count: rolloverResult.errors.length })}
                    </p>
                    {rolloverResult.errors.map((error) => (
                      <p key={error.enrollment_id} className="text-xs text-muted-foreground">
                        {error.student}: {error.message}
                      </p>
                    ))}
                  </div>
                )}
              </div>
              <AlertDialogFooter>
                <AlertDialogAction onClick={() => setRolloverOpen(false)}>
                  {t("done")}
                </AlertDialogAction>
              </AlertDialogFooter>
            </>
          )}
        </AlertDialogContent>
      </AlertDialog>

      {/* Revert dialog: whole batch / one student → run → results. Students
          with attendance, marks or received payments in the new year are
          skipped and reported — never silently unwound. */}
      <AlertDialog open={revertOpen} onOpenChange={(open) => !reverting && setRevertOpen(open)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {revertResult !== null
                ? t("revertResultTitle")
                : revertTarget
                  ? t("revertRowTitle", { student: revertTarget.student.full_name })
                  : t("revertTitle", { count: stats.revertable })}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {revertResult !== null ? t("revertResultDesc") : t("revertDesc")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          {revertResult === null ? (
            <AlertDialogFooter>
              <AlertDialogCancel disabled={reverting}>{tc("actions.cancel")}</AlertDialogCancel>
              <AlertDialogAction
                loading={reverting}
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                onClick={(e) => {
                  e.preventDefault()
                  runRevert()
                }}
              >
                {t("revert")}
              </AlertDialogAction>
            </AlertDialogFooter>
          ) : (
            <>
              <div className="space-y-2">
                <p className="flex items-center gap-2 text-sm font-medium text-success">
                  <Undo2 className="size-4" />
                  {t("revertDone", { count: revertResult.reverted })}
                </p>
                {revertResult.errors.length > 0 && (
                  <div className="max-h-40 space-y-1 overflow-y-auto rounded-xl bg-destructive/5 p-3">
                    <p className="text-xs font-medium text-destructive">
                      {t("revertSkipped", { count: revertResult.errors.length })}
                    </p>
                    {revertResult.errors.map((error) => (
                      <p key={error.enrollment_id} className="text-xs text-muted-foreground">
                        {error.student}: {error.message}
                      </p>
                    ))}
                  </div>
                )}
              </div>
              <AlertDialogFooter>
                <AlertDialogAction onClick={() => setRevertOpen(false)}>
                  {t("done")}
                </AlertDialogAction>
              </AlertDialogFooter>
            </>
          )}
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
