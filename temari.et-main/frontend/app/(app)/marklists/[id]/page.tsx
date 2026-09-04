"use client"

import {
  CheckCheck,
  CheckCircle2,
  Download,
  LockOpen,
  Percent,
  Plus,
  Save,
  Send,
  ShieldAlert,
  UserPen,
} from "lucide-react"
import { useParams } from "next/navigation"
import { useCallback, useEffect, useMemo, useState } from "react"
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
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { PageHeader } from "@/components/ui/page-header"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { fmtDate } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type {
  ContinuousAssessmentItemType,
  MarklistAssessment,
  MarklistGrid,
  MarklistStatus,
} from "@/lib/types"
import { cn } from "@/lib/utils"

interface CellState {
  score: string
  is_absent: boolean
  dirty: boolean
}

type CellMap = Record<string, CellState>

const cellKey = (assessmentId: number, studentId: number) =>
  `${assessmentId}:${studentId}`

const WEIGHTED_VIEW_KEY = "temari.marklists.weighted"

/** Raw mark converted to the assessment's weight scale: raw ÷ max × weight. */
const weightedOf = (
  assessment: MarklistAssessment,
  cell: CellState
): number | null => {
  if (cell.is_absent || cell.score === "" || assessment.max_score <= 0)
    return null
  const raw = Number(cell.score)
  if (!Number.isFinite(raw)) return null
  return (
    Math.round((raw / assessment.max_score) * assessment.weight * 100) / 100
  )
}

const average = (totals: (number | null)[]): number | null => {
  const scored = totals.filter((v): v is number => v !== null)
  if (scored.length === 0) return null
  return (
    Math.round((scored.reduce((a, b) => a + b, 0) / scored.length) * 100) / 100
  )
}

const ITEM_TYPES: ContinuousAssessmentItemType[] = [
  "quiz",
  "test",
  "assignment",
  "project",
  "mid_exam",
  "final_exam",
]

// Same status tints as the register: amber = awaiting approval, green = approved.
const STATUS_BADGE: Record<MarklistStatus, string> = {
  draft: "bg-muted text-muted-foreground",
  submitted: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-success/30 bg-success/10 text-success",
}

export default function MarklistGridPage() {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ id: string }>()

  const [grid, setGrid] = useState<MarklistGrid | null>(null)
  const [failed, setFailed] = useState(false)
  const [cells, setCells] = useState<CellMap>({})
  const [saving, setSaving] = useState(false)
  const [action, setAction] = useState<"submit" | "approve" | "reopen" | null>(
    null
  )
  const [working, setWorking] = useState(false)

  // Mobile entry mode: one assessment column at a time.
  const [activeAssessmentId, setActiveAssessmentId] = useState<number | null>(
    null
  )

  // Show the weighted conversion under each raw mark (on by default —
  // the weighted score is what totals and report cards actually use).
  const [showWeighted, setShowWeighted] = useState(true)

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- hydrate the persisted preference client-side
    setShowWeighted(localStorage.getItem(WEIGHTED_VIEW_KEY) !== "0")
  }, [])

  function toggleWeighted() {
    setShowWeighted((prev) => {
      localStorage.setItem(WEIGHTED_VIEW_KEY, prev ? "0" : "1")
      return !prev
    })
  }

  // Ad-hoc assessment sheet (free-form continuous assessments only).
  const [addOpen, setAddOpen] = useState(false)
  const [newName, setNewName] = useState("")
  const [newType, setNewType] = useState<ContinuousAssessmentItemType>("quiz")
  const [newMax, setNewMax] = useState("10")
  const [newWeight, setNewWeight] = useState("10")

  // On-behalf entry (supervisor on a teacher-owned draft — the trust rule).
  const [assistOpen, setAssistOpen] = useState(false)
  const [assistReason, setAssistReason] = useState("")
  const [assistBusy, setAssistBusy] = useState(false)

  const load = useCallback(async () => {
    try {
      const res = await apiFetch<{ data: MarklistGrid }>(
        `/marklists/${params.id}`
      )
      setGrid(res.data)
      const next: CellMap = {}
      for (const student of res.data.students) {
        for (const score of student.scores) {
          next[cellKey(score.assessment_id, student.student_id)] = {
            score: score.score !== null ? String(score.score) : "",
            is_absent: score.is_absent,
            dirty: false,
          }
        }
      }
      setCells(next)
    } catch {
      setFailed(true)
    }
  }, [params.id])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- async fetch resolves into state
    load()
  }, [load])

  const activeAssessment =
    grid?.assessments.find((a) => a.id === activeAssessmentId) ??
    grid?.assessments[0] ??
    null

  const status: MarklistStatus = grid?.marklist.status ?? "draft"
  const locked =
    grid !== null && (grid.marklist.is_locked || grid.term.is_closed)
  // Server-decided trust rule: a supervisor sees a teacher-owned draft
  // read-only until they declare on-behalf entry.
  const canEdit = grid !== null && grid.can_edit_marks
  const dirtyCount = useMemo(
    () => Object.values(cells).filter((c) => c.dirty).length,
    [cells]
  )

  /** Saved cells recorded by someone OTHER than the owning teacher —
   *  each gets an amber marker so authorship is visible in place. */
  const assistedCells = useMemo(() => {
    const set = new Set<string>()
    if (!grid || grid.recorders.length === 0) return set
    const others = new Set(grid.recorders.map((r) => r.employee_id))
    for (const student of grid.students) {
      for (const score of student.scores) {
        if (score.recorded_by != null && others.has(score.recorded_by)) {
          set.add(cellKey(score.assessment_id, student.student_id))
        }
      }
    }
    return set
  }, [grid])

  function setCell(
    assessmentId: number,
    studentId: number,
    patch: Partial<CellState>
  ) {
    setCells((prev) => {
      const key = cellKey(assessmentId, studentId)
      const current = prev[key] ?? { score: "", is_absent: false, dirty: false }
      return { ...prev, [key]: { ...current, ...patch, dirty: true } }
    })
  }

  /** Weighted total for one student across all assessments, from live cells. */
  const totalFor = useCallback(
    (studentId: number): number | null => {
      if (!grid) return null
      let sum = 0
      let scored = false
      for (const assessment of grid.assessments) {
        const cell = cells[cellKey(assessment.id, studentId)]
        if (
          !cell ||
          cell.is_absent ||
          cell.score === "" ||
          assessment.max_score <= 0
        )
          continue
        const raw = Number(cell.score)
        // An unsaveable mark (over max / negative / NaN) must not pollute the
        // live total — the cell is flagged red and blocks saving instead.
        if (!Number.isFinite(raw) || raw < 0 || raw > assessment.max_score)
          continue
        sum += (raw / assessment.max_score) * assessment.weight
        scored = true
      }
      return scored ? Math.round(sum * 100) / 100 : null
    },
    [grid, cells]
  )

  /** Totals as last SAVED (from the server payload) — the "before" of the
   *  mobile delta line, so a teacher sees what each edit changes. */
  const savedTotals = useMemo(() => {
    const map: Record<number, number | null> = {}
    if (!grid) return map
    for (const student of grid.students) {
      const byAssessment = new Map(
        student.scores.map((s) => [s.assessment_id, s])
      )
      let sum = 0
      let scored = false
      for (const assessment of grid.assessments) {
        const score = byAssessment.get(assessment.id)
        if (
          !score ||
          score.is_absent ||
          score.score === null ||
          assessment.max_score <= 0
        )
          continue
        sum += (score.score / assessment.max_score) * assessment.weight
        scored = true
      }
      map[student.student_id] = scored ? Math.round(sum * 100) / 100 : null
    }
    return map
  }, [grid])

  /** Sum of assessment weights — the scale the weighted Total column lives on. */
  const totalWeight = useMemo(
    () =>
      grid
        ? Math.round(
            grid.assessments.reduce((sum, a) => sum + a.weight, 0) * 100
          ) / 100
        : 0,
    [grid]
  )

  /**
   * Every cell whose entered mark can't be saved (over max, negative, or not
   * a number) — computed live so Save is blocked BEFORE the request and each
   * offender can be named and jumped to, even across mobile assessment tabs.
   */
  const invalidCells = useMemo(() => {
    if (!grid) return []
    const list: {
      assessmentId: number
      studentId: number
      student: string
      assessment: string
      entered: string
      max: number
      weight: number
    }[] = []
    for (const assessment of grid.assessments) {
      for (const student of grid.students) {
        const cell = cells[cellKey(assessment.id, student.student_id)]
        if (!cell || cell.is_absent || cell.score === "") continue
        const n = Number(cell.score)
        if (!Number.isFinite(n) || n < 0 || n > assessment.max_score) {
          list.push({
            assessmentId: assessment.id,
            studentId: student.student_id,
            student: student.full_name,
            assessment: assessment.name,
            entered: cell.score,
            max: assessment.max_score,
            weight: assessment.weight,
          })
        }
      }
    }
    return list
  }, [grid, cells])

  const cellIsInvalid = useCallback(
    (assessmentId: number, studentId: number) =>
      invalidCells.some(
        (c) => c.assessmentId === assessmentId && c.studentId === studentId
      ),
    [invalidCells]
  )

  /** Switch to the cell's assessment (mobile tab), scroll it into view, focus it. */
  const jumpToCell = useCallback((assessmentId: number, studentId: number) => {
    setActiveAssessmentId(assessmentId)
    // Wait a frame so the mobile tab switch has rendered the target input.
    setTimeout(() => {
      const candidates = document.querySelectorAll<HTMLInputElement>(
        `[data-cell="${assessmentId}:${studentId}"]`
      )
      // Mobile and desktop grids coexist in the DOM — focus the visible one.
      const el = Array.from(candidates).find((c) => c.offsetParent !== null)
      el?.scrollIntoView({ behavior: "smooth", block: "center" })
      el?.focus({ preventScroll: true })
    }, 80)
  }, [])

  const liveAverage = useMemo(
    () =>
      grid ? average(grid.students.map((s) => totalFor(s.student_id))) : null,
    [grid, totalFor]
  )
  const savedAverage = useMemo(
    () => average(Object.values(savedTotals)),
    [savedTotals]
  )

  async function saveMarks() {
    if (!grid) return
    // Never send marks we already know are wrong — name the first offender
    // and take the teacher straight to that cell instead.
    if (invalidCells.length > 0) {
      const first = invalidCells[0]
      toast.error(
        t("marklists.grid.invalidEntry", {
          student: first.student,
          assessment: first.assessment,
          entered: first.entered,
          max: first.max,
        })
      )
      jumpToCell(first.assessmentId, first.studentId)
      return
    }
    setSaving(true)
    try {
      // One upsert per assessment that has dirty cells — only the dirty rows.
      const requests = grid.assessments
        .map((assessment) => {
          const results = grid.students
            .map((student) => {
              const cell = cells[cellKey(assessment.id, student.student_id)]
              if (!cell?.dirty) return null
              return {
                student_id: student.student_id,
                score:
                  cell.is_absent || cell.score === ""
                    ? null
                    : Number(cell.score),
                is_absent: cell.is_absent,
              }
            })
            .filter((row): row is NonNullable<typeof row> => row !== null)
          return results.length > 0
            ? apiFetch(`/assessments/${assessment.id}/results`, {
                method: "POST",
                body: { results },
              })
            : null
        })
        .filter((r): r is Promise<unknown> => r !== null)

      await Promise.all(requests)
      toast.success(t("marklists.grid.saved"))
      setCells((prev) =>
        Object.fromEntries(
          Object.entries(prev).map(([k, v]) => [k, { ...v, dirty: false }])
        )
      )
    } catch (error) {
      // Surface every server-side line (it names student + assessment + max),
      // not just the first one.
      const lines =
        error instanceof ApiError && error.errors
          ? Object.values(error.errors).flat()
          : []
      toast.error(
        lines.length > 0
          ? lines.slice(0, 4).join("\n") +
              (lines.length > 4 ? `\n(+${lines.length - 4})` : "")
          : error instanceof ApiError
            ? error.message
            : tc("errors.generic")
      )
    } finally {
      setSaving(false)
    }
  }

  async function runAction() {
    if (!grid || !action) return
    setWorking(true)
    try {
      await apiFetch(`/marklists/${grid.subject_assignment_id}/${action}`, {
        method: "POST",
      })
      toast.success(
        action === "submit"
          ? t("marklists.grid.submitted")
          : action === "approve"
            ? t("marklists.grid.approved")
            : t("marklists.grid.reopened")
      )
      setAction(null)
      await load()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setWorking(false)
    }
  }

  /** Declare on-behalf entry: reason required, teacher notified, grid unlocks. */
  async function startAssist() {
    if (!grid) return
    setAssistBusy(true)
    try {
      await apiFetch(`/marklists/${grid.subject_assignment_id}/assist`, {
        method: "POST",
        body: { reason: assistReason.trim() },
      })
      toast.success(t("marklists.grid.assistStarted"))
      setAssistOpen(false)
      setAssistReason("")
      await load()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setAssistBusy(false)
    }
  }

  /** The whole grid as CSV — raw AND weighted columns per assessment + the weighted total. */
  function exportCsv() {
    if (!grid) return
    const esc = (v: string | number | null) =>
      `"${String(v ?? "").replaceAll('"', '""')}"`
    const header = [
      t("marklists.grid.student"),
      ...grid.assessments.flatMap((a) => [
        `${a.name} (${t("marklists.grid.raw")} /${a.max_score})`,
        `${a.name} (${t("marklists.grid.weighted")} /${a.weight})`,
      ]),
      `${t("marklists.grid.total")} (/${totalWeight})`,
    ]
    const rows = grid.students.map((student) => {
      const scores = grid.assessments.flatMap((a) => {
        const cell = cells[cellKey(a.id, student.student_id)]
        if (!cell) return ["", ""]
        if (cell.is_absent) return ["ABS", ""]
        return [cell.score, weightedOf(a, cell) ?? ""]
      })
      return [
        esc(student.full_name),
        ...scores.map(esc),
        totalFor(student.student_id) ?? "",
      ]
    })
    const csv = [
      header.map(esc).join(","),
      ...rows.map((r) => r.join(",")),
    ].join("\n")
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8" })
    const url = URL.createObjectURL(blob)
    const link = document.createElement("a")
    link.href = url
    link.download =
      `marklist-${grid.subject.code ?? grid.subject.name}-${grid.section.name}.csv`.toLowerCase()
    link.click()
    URL.revokeObjectURL(url)
  }

  async function addAssessment() {
    if (!grid) return
    setWorking(true)
    try {
      await apiFetch(
        `/subject-assignments/${grid.subject_assignment_id}/assessments`,
        {
          method: "POST",
          body: {
            type: newType,
            name: newName,
            max_score: Number(newMax),
            weight: Number(newWeight),
          },
        }
      )
      setAddOpen(false)
      setNewName("")
      await load()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setWorking(false)
    }
  }

  if (failed) {
    return (
      <div className="space-y-6">
        <PageHeader
          title={t("marklists.title")}
          backHref="/marklists"
          backLabel={tc("actions.back")}
        />
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {tc("errors.generic")}
          </div>
        </div>
      </div>
    )
  }

  if (grid === null) {
    return (
      <div className="space-y-6">
        <PageHeader
          title={t("marklists.title")}
          backHref="/marklists"
          backLabel={tc("actions.back")}
        />
        <div className="page-gutter space-y-3">
          <Skeleton className="h-10 rounded-xl" />
          <Skeleton className="h-96 rounded-2xl" />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6 pb-24">
      <PageHeader
        backHref="/marklists"
        backLabel={t("marklists.title")}
        title={`${grid.subject.name} — ${grid.section.grade_level ?? ""} ${grid.section.name}`}
        description={
          grid.continuous_assessment
            ? t("marklists.grid.plan", {
                name: grid.continuous_assessment.name,
              })
            : canEdit
              ? t("marklists.grid.freeForm")
              : undefined
        }
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="outline" className={STATUS_BADGE[status]}>
              {t(`marklists.statuses.${status}`)}
            </Badge>
            <Button
              variant={showWeighted ? "secondary" : "outline"}
              size="sm"
              onClick={toggleWeighted}
              aria-pressed={showWeighted}
              title={t("marklists.grid.weightedExplainer")}
            >
              <Percent className="size-4" />
              {t("marklists.grid.showWeighted")}
            </Button>
            <Button variant="outline" size="sm" onClick={exportCsv}>
              <Download className="size-4" />
              {t("reports.export")}
            </Button>
            {canEdit && grid.can_define_assessments && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setAddOpen(true)}
              >
                <Plus className="size-4" />
                {t("marklists.grid.addAssessment")}
              </Button>
            )}
            {canEdit && (
              <Button
                size="sm"
                onClick={saveMarks}
                loading={saving}
                disabled={dirtyCount === 0 || invalidCells.length > 0}
                title={
                  invalidCells.length > 0
                    ? t("marklists.grid.invalidTitle", {
                        count: invalidCells.length,
                      })
                    : undefined
                }
              >
                <Save className="size-4" />
                {t("marklists.grid.saveMarks")}
                {dirtyCount > 0 && (
                  <span className="tabular-nums">({dirtyCount})</span>
                )}
              </Button>
            )}
            {grid.can_request_assist && (
              <Button
                variant="outline"
                size="sm"
                className="border-warning/50 text-warning hover:bg-warning/10 hover:text-warning"
                onClick={() => setAssistOpen(true)}
              >
                <UserPen className="size-4" />
                {t("marklists.grid.assistCta")}
              </Button>
            )}
            {!locked &&
              status === "draft" &&
              (grid.is_own || grid.can_approve) && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setAction("submit")}
                  disabled={dirtyCount > 0}
                >
                  <Send className="size-4" />
                  {t("marklists.grid.submit")}
                </Button>
              )}
            {status === "submitted" &&
              grid.can_approve &&
              !grid.four_eyes_blocked && (
                <Button size="sm" onClick={() => setAction("approve")}>
                  <CheckCheck className="size-4" />
                  {t("marklists.grid.approve")}
                </Button>
              )}
            {status !== "draft" &&
              (grid.can_approve || status === "submitted") &&
              !grid.term.is_closed && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setAction("reopen")}
                >
                  <LockOpen className="size-4" />
                  {t("marklists.grid.reopen")}
                </Button>
              )}
          </div>
        }
      />

      {(locked || grid.term.is_closed) && (
        <div className="page-gutter">
          <div className="rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm">
            {grid.term.is_closed
              ? t("marklists.grid.closedTerm")
              : t("marklists.grid.lockedBanner", {
                  status: t(`marklists.statuses.${status}`),
                })}
            {grid.marklist.submitted_by_name && (
              <span className="text-muted-foreground">
                {" · "}
                {t("marklists.grid.submittedBy", {
                  name: grid.marklist.submitted_by_name,
                })}
              </span>
            )}
            {grid.marklist.approved_by_name && (
              <span className="text-muted-foreground">
                {" · "}
                {t("marklists.grid.approvedBy", {
                  name: grid.marklist.approved_by_name,
                })}
              </span>
            )}
          </div>
        </div>
      )}

      {/* Four-eyes: the viewer put marks on this sheet — approval belongs to
        someone else. Shown instead of the Approve button, never beside it. */}
      {status === "submitted" && grid.can_approve && grid.four_eyes_blocked && (
        <div className="page-gutter">
          <div className="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm">
            <ShieldAlert className="mt-0.5 size-4 shrink-0 text-warning" />
            <p>{t("marklists.grid.fourEyes")}</p>
          </div>
        </div>
      )}

      {/* Supervisor on a teacher-owned draft: read-only until declared. */}
      {grid.can_request_assist && (
        <div className="page-gutter">
          <div className="flex flex-col gap-3 rounded-2xl border border-warning/40 bg-warning/5 p-4 sm:flex-row sm:items-center">
            <div className="flex min-w-0 flex-1 items-start gap-3">
              <UserPen className="mt-0.5 size-4 shrink-0 text-warning" />
              <p className="text-sm">
                {t("marklists.grid.assistReadOnly", {
                  teacher: grid.teacher_name ?? "",
                })}
              </p>
            </div>
            <Button
              size="sm"
              className="h-10 w-full shrink-0 bg-warning text-warning-foreground hover:bg-warning/90 sm:w-auto"
              onClick={() => setAssistOpen(true)}
            >
              <UserPen className="size-4" />
              {t("marklists.grid.assistCta")}
            </Button>
          </div>
        </div>
      )}

      {/* Active on-behalf declaration — visible to teacher AND supervisor
        until the sheet is countersigned. */}
      {grid.marklist.assisted_by_name && status !== "approved" && (
        <div className="page-gutter">
          <div className="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm">
            <UserPen className="mt-0.5 size-4 shrink-0 text-warning" />
            <div className="min-w-0">
              <p className="font-medium">
                {t("marklists.grid.assistActive", {
                  name: grid.marklist.assisted_by_name,
                })}
                {grid.marklist.assisted_at && (
                  <span className="font-normal text-muted-foreground">
                    {" · "}
                    {fmtDate(grid.marklist.assisted_at)}
                  </span>
                )}
              </p>
              {grid.marklist.assist_reason && (
                <p className="mt-0.5 text-muted-foreground">
                  {t("marklists.grid.assistReasonLine", {
                    reason: grid.marklist.assist_reason,
                  })}
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Who entered what: one chip per non-owner recorder. Their cells are
        dotted in the grid so authorship is checkable mark by mark. */}
      {grid.recorders.length > 0 && (
        <div className="page-gutter flex flex-wrap gap-2">
          {grid.recorders.map((recorder) => (
            <span
              key={recorder.employee_id}
              className="inline-flex items-center gap-1.5 rounded-full border border-warning/40 bg-warning/10 px-3 py-1 text-xs font-medium"
            >
              <span className="size-1.5 rounded-full bg-warning" />
              {t("marklists.grid.enteredBy", {
                name: recorder.name,
                count: recorder.cells,
              })}
            </span>
          ))}
        </div>
      )}

      <div className="page-gutter">
        {grid.assessments.length === 0 ? (
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("marklists.grid.noAssessments")}
          </div>
        ) : grid.students.length === 0 ? (
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("marklists.grid.noStudents")}
          </div>
        ) : (
          <>
            {/* Every unsaveable mark, named — tap a line to jump to that cell
              (switches the mobile assessment tab and focuses the input). */}
            {invalidCells.length > 0 && (
              <div className="mb-3 rounded-2xl border border-destructive/40 bg-destructive/5 p-3">
                <p className="text-sm font-semibold text-destructive">
                  {t("marklists.grid.invalidTitle", {
                    count: invalidCells.length,
                  })}
                </p>
                <div className="mt-1.5 space-y-1">
                  {invalidCells.slice(0, 6).map((c) => (
                    <button
                      key={`${c.assessmentId}:${c.studentId}`}
                      type="button"
                      onClick={() => jumpToCell(c.assessmentId, c.studentId)}
                      className="block w-full text-left text-xs text-destructive/90 underline-offset-2 hover:underline"
                    >
                      {t("marklists.grid.invalidEntry", {
                        student: c.student,
                        assessment: c.assessment,
                        entered: c.entered,
                        max: c.max,
                      })}
                    </button>
                  ))}
                  {invalidCells.length > 6 && (
                    <p className="text-xs text-destructive/70">
                      {t("marklists.grid.invalidMore", {
                        count: invalidCells.length - 6,
                      })}
                    </p>
                  )}
                </div>
              </div>
            )}

            {/* ── Mobile: one assessment at a time, native-app entry ── */}
            <div className="space-y-3 md:hidden">
              {/* Assessment switcher: one mini-card per column — entry progress,
                completion check and weight at a glance, all visible in a grid
                (no horizontal scrolling). */}
              <div className="grid grid-cols-2 gap-2">
                {grid.assessments.map((assessment) => {
                  const filled = grid.students.filter((s) => {
                    const cell = cells[cellKey(assessment.id, s.student_id)]
                    return cell && (cell.is_absent || cell.score !== "")
                  }).length
                  const complete =
                    grid.students.length > 0 && filled === grid.students.length
                  const isActive = activeAssessment?.id === assessment.id
                  const invalidHere = invalidCells.filter(
                    (c) => c.assessmentId === assessment.id
                  ).length
                  return (
                    <button
                      key={assessment.id}
                      type="button"
                      onClick={() => setActiveAssessmentId(assessment.id)}
                      aria-pressed={isActive}
                      className={cn(
                        "rounded-2xl border p-2.5 text-left transition-all",
                        isActive
                          ? "border-primary bg-primary/5 shadow-sm"
                          : "bg-card",
                        invalidHere > 0 && "border-destructive/60"
                      )}
                    >
                      <span className="flex items-center justify-between gap-1">
                        <span className="truncate text-xs leading-tight font-semibold">
                          {assessment.name}
                        </span>
                        {invalidHere > 0 ? (
                          <span className="shrink-0 rounded-full bg-destructive px-1.5 text-[10px] font-bold text-destructive-foreground tabular-nums">
                            {invalidHere}
                          </span>
                        ) : (
                          complete && (
                            <CheckCircle2 className="size-3.5 shrink-0 text-success" />
                          )
                        )}
                      </span>
                      <span className="mt-1.5 flex items-center justify-between text-[10px]">
                        <span className="text-muted-foreground tabular-nums">
                          {filled}/{grid.students.length}
                        </span>
                        <span
                          className={cn(
                            "rounded-full px-1.5 font-semibold tabular-nums",
                            isActive
                              ? "bg-primary/10 text-primary"
                              : "bg-muted text-muted-foreground"
                          )}
                        >
                          {t("marklists.grid.weight", {
                            weight: assessment.weight,
                          })}
                        </span>
                      </span>
                      <span className="mt-1.5 block h-1 overflow-hidden rounded-full bg-muted">
                        <span
                          className={cn(
                            "block h-full rounded-full transition-all",
                            complete ? "bg-success" : "bg-primary"
                          )}
                          style={{
                            width: `${
                              grid.students.length > 0
                                ? (filled / grid.students.length) * 100
                                : 0
                            }%`,
                          }}
                        />
                      </span>
                    </button>
                  )
                })}
              </div>

              {activeAssessment && (
                <div className="divide-y rounded-2xl border bg-card">
                  <div className="flex items-center justify-between px-4 py-2 text-xs text-muted-foreground">
                    <span>{t("marklists.grid.student")}</span>
                    <span>
                      {t("marklists.grid.outOf", {
                        max: activeAssessment.max_score,
                      })}
                    </span>
                  </div>
                  {grid.students.map((student) => {
                    const key = cellKey(activeAssessment.id, student.student_id)
                    const cell = cells[key] ?? {
                      score: "",
                      is_absent: false,
                      dirty: false,
                    }
                    const saved = savedTotals[student.student_id] ?? null
                    const live = totalFor(student.student_id)
                    const changed = live !== saved
                    const delta =
                      live !== null && saved !== null
                        ? Math.round((live - saved) * 100) / 100
                        : null
                    return (
                      <div
                        key={student.student_id}
                        className="flex items-center gap-3 px-4 py-2"
                      >
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium">
                            {student.full_name}
                          </p>
                          {/* Weighted total as a progress bar + bold score — updates live while typing. */}
                          {(saved !== null || live !== null) && (
                            <div className="mt-1 flex items-center gap-1.5">
                              <div className="h-1.5 w-14 shrink-0 overflow-hidden rounded-full bg-muted">
                                <div
                                  className="h-full rounded-full bg-primary transition-all"
                                  style={{
                                    width: `${
                                      totalWeight > 0 && live !== null
                                        ? Math.min(
                                            100,
                                            (live / totalWeight) * 100
                                          )
                                        : 0
                                    }%`,
                                  }}
                                />
                              </div>
                              <span className="text-xs font-semibold tabular-nums">
                                {live ?? "—"}
                                {totalWeight > 0 && (
                                  <span className="font-normal text-muted-foreground">
                                    /{totalWeight}
                                  </span>
                                )}
                              </span>
                              {changed && delta !== null && delta !== 0 && (
                                <span
                                  className={cn(
                                    "rounded-full px-1.5 text-[10px] font-semibold tabular-nums",
                                    delta > 0
                                      ? "bg-success/10 text-success"
                                      : "bg-destructive/10 text-destructive"
                                  )}
                                >
                                  {delta > 0 ? "+" : ""}
                                  {delta}
                                </span>
                              )}
                            </div>
                          )}
                        </div>
                        {/* Input group: raw mark on the left, its weighted conversion attached on the right. */}
                        <div
                          className={cn(
                            "relative flex h-11 shrink-0 items-stretch overflow-hidden rounded-xl border border-input/70 bg-muted/30 transition-colors focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50",
                            cell.dirty && "border-primary",
                            cellIsInvalid(
                              activeAssessment.id,
                              student.student_id
                            ) && "border-destructive ring-3 ring-destructive/20"
                          )}
                        >
                          {/* Amber dot: this saved mark was recorded by a
                            non-owner (see the "entered by" chips above). */}
                          {!cell.dirty &&
                            assistedCells.has(
                              cellKey(activeAssessment.id, student.student_id)
                            ) && (
                              <span className="pointer-events-none absolute top-1 left-1 size-1.5 rounded-full bg-warning" />
                            )}
                          <Input
                            type="number"
                            inputMode="decimal"
                            min={0}
                            max={activeAssessment.max_score}
                            data-cell={`${activeAssessment.id}:${student.student_id}`}
                            disabled={!canEdit || cell.is_absent}
                            value={cell.is_absent ? "" : cell.score}
                            placeholder={
                              cell.is_absent ? t("marklists.grid.absent") : "—"
                            }
                            aria-label={`${student.full_name} — ${activeAssessment.name}`}
                            aria-invalid={cellIsInvalid(
                              activeAssessment.id,
                              student.student_id
                            )}
                            className={cn(
                              "no-spinner h-full rounded-none border-0 bg-transparent px-1.5 text-center text-base tabular-nums focus-visible:ring-0",
                              showWeighted ? "w-16" : "w-24"
                            )}
                            onChange={(e) =>
                              setCell(activeAssessment.id, student.student_id, {
                                score: e.target.value,
                              })
                            }
                          />
                          {showWeighted && (
                            <span className="flex min-w-14 items-center justify-center border-l border-input/70 bg-primary/10 px-1.5 text-xs font-medium text-primary tabular-nums">
                              {(() => {
                                const w = weightedOf(activeAssessment, cell)
                                return `${w ?? "—"}/${activeAssessment.weight}`
                              })()}
                            </span>
                          )}
                        </div>
                        <button
                          type="button"
                          disabled={!canEdit}
                          onClick={() =>
                            setCell(activeAssessment.id, student.student_id, {
                              is_absent: !cell.is_absent,
                              score: "",
                            })
                          }
                          aria-label={t("marklists.grid.absent")}
                          aria-pressed={cell.is_absent}
                          className={cn(
                            "flex size-11 shrink-0 items-center justify-center rounded-xl border text-xs font-semibold transition-colors",
                            cell.is_absent
                              ? "border-destructive/40 bg-destructive/10 text-destructive"
                              : "text-muted-foreground",
                            !canEdit && "opacity-40"
                          )}
                        >
                          A
                        </button>
                      </div>
                    )
                  })}
                </div>
              )}

              {showWeighted && (
                <p className="px-1 text-[11px] text-muted-foreground">
                  {t("marklists.grid.weightedExplainer")}
                </p>
              )}

              {/* Sticky save bar keeps the primary action under the thumb. */}
              {canEdit && dirtyCount > 0 && (
                <div className="fixed inset-x-0 bottom-16 z-30 px-4">
                  {invalidCells.length > 0 ? (
                    // Errors own the bar: one tap lands on the first bad cell.
                    <button
                      type="button"
                      onClick={() =>
                        jumpToCell(
                          invalidCells[0].assessmentId,
                          invalidCells[0].studentId
                        )
                      }
                      className="mx-auto mb-2 block w-fit rounded-full bg-destructive px-3 py-1 text-xs font-semibold text-destructive-foreground shadow-md"
                    >
                      {t("marklists.grid.invalidSticky", {
                        count: invalidCells.length,
                      })}
                    </button>
                  ) : (
                    liveAverage !== null &&
                    liveAverage !== savedAverage && (
                      <div className="mx-auto mb-2 w-fit rounded-full border bg-card/95 px-3 py-1 text-xs tabular-nums shadow-md backdrop-blur">
                        <span className="text-muted-foreground">
                          {t("marklists.grid.classAverage")}{" "}
                          {savedAverage ?? "—"} →{" "}
                        </span>
                        <span className="font-semibold">{liveAverage}</span>
                      </div>
                    )
                  )}
                  <Button
                    className="h-12 w-full shadow-lg"
                    onClick={saveMarks}
                    loading={saving}
                    disabled={invalidCells.length > 0}
                  >
                    <Save className="size-4" />
                    {t("marklists.grid.saveMarks")} ({dirtyCount})
                  </Button>
                </div>
              )}
            </div>

            {/* ── Desktop: the full spreadsheet grid ── */}
            <div className="hidden overflow-x-auto rounded-2xl border bg-card md:block">
              <table className="w-full min-w-[42rem] border-collapse text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="sticky left-0 z-10 bg-card px-4 py-3 text-left font-medium">
                      {t("marklists.grid.student")}
                    </th>
                    {grid.assessments.map((assessment) => (
                      <th
                        key={assessment.id}
                        className="min-w-28 px-2 py-2 text-center align-bottom"
                      >
                        <p className="text-xs font-semibold">
                          {assessment.name}
                        </p>
                        <p className="text-[11px] font-normal text-muted-foreground">
                          {t("marklists.grid.outOf", {
                            max: assessment.max_score,
                          })}{" "}
                          ·{" "}
                          {t("marklists.grid.weight", {
                            weight: assessment.weight,
                          })}
                        </p>
                      </th>
                    ))}
                    <th className="min-w-20 px-3 py-2 text-right align-bottom font-medium">
                      <p className="text-xs font-semibold">
                        {t("marklists.grid.total")}
                      </p>
                      {totalWeight > 0 && (
                        <p className="text-[11px] font-normal text-muted-foreground">
                          {t("marklists.grid.outOf", { max: totalWeight })}
                        </p>
                      )}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {grid.students.map((student, rowIndex) => {
                    const total = totalFor(student.student_id)
                    return (
                      <tr
                        key={student.student_id}
                        className={cn(
                          "border-b last:border-0",
                          rowIndex % 2 === 1 && "bg-muted/20"
                        )}
                      >
                        <td className="sticky left-0 z-10 max-w-52 truncate bg-card px-4 py-1.5 font-medium">
                          {student.full_name}
                        </td>
                        {grid.assessments.map((assessment) => {
                          const key = cellKey(assessment.id, student.student_id)
                          const cell = cells[key] ?? {
                            score: "",
                            is_absent: false,
                            dirty: false,
                          }
                          return (
                            <td key={assessment.id} className="px-1.5 py-1.5">
                              <div className="flex items-center justify-center gap-1">
                                {/* Input group: raw mark on the left, its weighted conversion attached on the right. */}
                                <div
                                  className={cn(
                                    "relative flex h-8 items-stretch overflow-hidden rounded-lg border border-input/70 bg-muted/30 transition-colors focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50",
                                    cell.dirty && "border-primary",
                                    cellIsInvalid(
                                      assessment.id,
                                      student.student_id
                                    ) &&
                                      "border-destructive ring-3 ring-destructive/20"
                                  )}
                                >
                                  {/* Amber dot: saved mark recorded by a non-owner. */}
                                  {!cell.dirty &&
                                    assistedCells.has(
                                      cellKey(assessment.id, student.student_id)
                                    ) && (
                                      <span className="pointer-events-none absolute top-0.5 left-0.5 size-1.5 rounded-full bg-warning" />
                                    )}
                                  <Input
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    max={assessment.max_score}
                                    data-cell={`${assessment.id}:${student.student_id}`}
                                    disabled={!canEdit || cell.is_absent}
                                    value={cell.is_absent ? "" : cell.score}
                                    placeholder={
                                      cell.is_absent
                                        ? t("marklists.grid.absent")
                                        : ""
                                    }
                                    aria-label={`${student.full_name} — ${assessment.name}`}
                                    aria-invalid={cellIsInvalid(
                                      assessment.id,
                                      student.student_id
                                    )}
                                    className={cn(
                                      "no-spinner h-full rounded-none border-0 bg-transparent px-1 text-center tabular-nums focus-visible:ring-0",
                                      showWeighted ? "w-14" : "w-20"
                                    )}
                                    onChange={(e) =>
                                      setCell(
                                        assessment.id,
                                        student.student_id,
                                        {
                                          score: e.target.value,
                                        }
                                      )
                                    }
                                  />
                                  {showWeighted && (
                                    <span className="flex min-w-14 items-center justify-center border-l border-input/70 bg-primary/10 px-1.5 text-[11px] font-medium text-primary tabular-nums">
                                      {(() => {
                                        const w = weightedOf(assessment, cell)
                                        return `${w ?? "—"}/${assessment.weight}`
                                      })()}
                                    </span>
                                  )}
                                </div>
                                <button
                                  type="button"
                                  disabled={!canEdit}
                                  onClick={() =>
                                    setCell(assessment.id, student.student_id, {
                                      is_absent: !cell.is_absent,
                                      score: "",
                                    })
                                  }
                                  aria-label={t("marklists.grid.absent")}
                                  aria-pressed={cell.is_absent}
                                  className={cn(
                                    "rounded-md px-1.5 py-1 text-[10px] font-semibold transition-colors",
                                    cell.is_absent
                                      ? "bg-destructive/10 text-destructive"
                                      : "text-muted-foreground hover:bg-muted",
                                    !canEdit && "opacity-40"
                                  )}
                                >
                                  A
                                </button>
                              </div>
                            </td>
                          )
                        })}
                        <td className="px-3 py-1.5 text-right font-semibold tabular-nums">
                          {total ?? "—"}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
              {showWeighted && (
                <p className="border-t px-4 py-2 text-[11px] text-muted-foreground">
                  {t("marklists.grid.weightedExplainer")}
                </p>
              )}
            </div>
          </>
        )}
      </div>

      {/* Workflow confirmations. */}
      <AlertDialog
        open={action !== null}
        onOpenChange={(open) => !open && setAction(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {action === "submit" && t("marklists.grid.submitConfirmTitle")}
              {action === "approve" && t("marklists.grid.approveConfirmTitle")}
              {action === "reopen" && t("marklists.grid.reopenConfirmTitle")}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {action === "submit" && t("marklists.grid.submitConfirmDesc")}
              {action === "approve" && t("marklists.grid.approveConfirmDesc")}
              {action === "reopen" && t("marklists.grid.reopenConfirmDesc")}
            </AlertDialogDescription>
            {/* Attestation: submitting a sheet that carries someone else's
              entries names them explicitly — signing is informed, always. */}
            {action === "submit" && grid !== null && grid.recorders.length > 0 && (
              <div className="flex items-start gap-2 rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-sm">
                <UserPen className="mt-0.5 size-4 shrink-0 text-warning" />
                <p>
                  {t("marklists.grid.submitConfirmAssisted", {
                    count: grid.recorders.reduce((sum, r) => sum + r.cells, 0),
                    names: grid.recorders.map((r) => r.name).join(", "),
                  })}
                </p>
              </div>
            )}
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={working}>
              {tc("actions.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              loading={working}
              onClick={(e) => {
                e.preventDefault()
                runAction()
              }}
            >
              {tc("actions.confirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* On-behalf declaration: reason first, then the grid unlocks. */}
      <ResponsiveSheet open={assistOpen} onOpenChange={setAssistOpen}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>
              {t("marklists.grid.assistSheetTitle", {
                teacher: grid?.teacher_name ?? "",
              })}
            </ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            <ul className="space-y-2.5 rounded-xl border bg-muted/30 p-3.5 text-sm">
              {(["assistExplain1", "assistExplain2", "assistExplain3"] as const).map(
                (key) => (
                  <li key={key} className="flex items-start gap-2.5">
                    <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-warning" />
                    <span>
                      {t(`marklists.grid.${key}`, {
                        teacher: grid?.teacher_name ?? "",
                      })}
                    </span>
                  </li>
                )
              )}
            </ul>
            <div className="space-y-2">
              <Label htmlFor="assist-reason">
                {t("marklists.grid.assistReason")}{" "}
                <span className="text-destructive">*</span>
              </Label>
              <Textarea
                id="assist-reason"
                value={assistReason}
                onChange={(e) => setAssistReason(e.target.value)}
                placeholder={t("marklists.grid.assistReasonPlaceholder")}
                rows={3}
                maxLength={500}
              />
            </div>
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setAssistOpen(false)}
              disabled={assistBusy}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              onClick={startAssist}
              loading={assistBusy}
              disabled={assistReason.trim().length < 5}
            >
              <UserPen className="size-4" />
              {t("marklists.grid.assistStart")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>

      {/* Ad-hoc assessment (free-form continuous assessments only). */}
      <ResponsiveSheet open={addOpen} onOpenChange={setAddOpen}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>
              {t("marklists.grid.addAssessment")}
            </ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            <div className="space-y-2">
              <Label>
                {t("continuousAssessment.itemName")}{" "}
                <span className="text-destructive">*</span>
              </Label>
              <Input
                value={newName}
                onChange={(e) => setNewName(e.target.value)}
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <div className="space-y-2">
                <Label>{t("continuousAssessment.itemType")}</Label>
                <Select
                  value={newType}
                  onValueChange={(v) =>
                    setNewType(v as ContinuousAssessmentItemType)
                  }
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {ITEM_TYPES.map((type) => (
                      <SelectItem key={type} value={type}>
                        {t(`types.${type}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t("continuousAssessment.itemMax")}</Label>
                <Input
                  type="number"
                  inputMode="decimal"
                  value={newMax}
                  onChange={(e) => setNewMax(e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("continuousAssessment.itemWeight")}</Label>
                <Input
                  type="number"
                  inputMode="decimal"
                  value={newWeight}
                  onChange={(e) => setNewWeight(e.target.value)}
                />
              </div>
            </div>
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setAddOpen(false)}
              disabled={working}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              onClick={addAssessment}
              loading={working}
              disabled={!newName}
            >
              {tc("actions.save")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
