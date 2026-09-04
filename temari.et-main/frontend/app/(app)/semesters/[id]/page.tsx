"use client"

import Link from "next/link"
import { useParams } from "next/navigation"
import {
  ArrowLeft,
  BookOpen,
  CalendarRange,
  Check,
  ChevronLeft,
  ChevronRight,
  ChevronsUpDown,
  Copy,
  Minus,
  Plus,
  Sparkles,
  Timer,
  UserX,
  Wand2,
  X,
} from "lucide-react"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { TermStatusSelect } from "@/components/academic/term-status-select"
import {
  TermCloneCopyDialog,
  type TermGridAction,
} from "@/components/academic/term-clone-copy-dialog"
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { OptionCombobox } from "@/components/ui/combobox"
import { EmptyState } from "@/components/ui/empty-state"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import { subjectAppliesToSort } from "@/lib/subjects"
import type { AssignmentMatrixMeta, Paginated, Term } from "@/lib/types"
import { cn } from "@/lib/utils"

/** The matrix endpoint's assignment row (subject/employee come embedded). */
interface MatrixRow {
  id: number
  section_id: number
  subject_id: number
  term_id: number
  employee_id: number | null
  periods_per_week: number
  subject?: { id: number; code: string; name: string } | null
  employee?: { id: number; full_name: string } | null
}

const UNASSIGNED = "none"

export default function SemesterAssignmentsPage() {
  const params = useParams<{ id: string }>()
  const termId = Number(params.id)
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const permissions = useEffectivePermissions()

  const canManage = permissions.includes("timetable.manage")
  const canUpdateTerm = permissions.includes("academic_years.update")

  const [term, setTerm] = useState<Term | null>(null)
  const [terms, setTerms] = useState<Term[]>([])
  const [rows, setRows] = useState<MatrixRow[] | null>(null)
  const [meta, setMeta] = useState<AssignmentMatrixMeta | null>(null)
  const [generateOpen, setGenerateOpen] = useState(false)
  const [generating, setGenerating] = useState(false)
  const [gridAction, setGridAction] = useState<TermGridAction>(null)
  /** Section currently showing its "add subject" picker. */
  const [addingFor, setAddingFor] = useState<number | null>(null)
  /** Tab selection — null means "first visible" (resolved at render time). */
  const [activeGradeId, setActiveGradeId] = useState<number | null>(null)
  const [activeSectionId, setActiveSectionId] = useState<number | null>(null)
  /** Focus mode: show only unassigned slots across all sections. */
  const [focusUnassigned, setFocusUnassigned] = useState(false)
  const [autofilling, setAutofilling] = useState(false)
  /** Latest rows for the debounced load-save closures (state goes stale). */
  const rowsRef = useRef<MatrixRow[] | null>(null)
  /** Per-row debounce timers for periods/week edits. */
  const loadTimers = useRef(new Map<number, ReturnType<typeof setTimeout>>())

  const loadMatrix = useCallback(async () => {
    const res = await apiFetch<{ data: MatrixRow[]; meta: AssignmentMatrixMeta }>(
      `/terms/${termId}/assignment-matrix`,
    )
    setRows(res.data)
    setMeta(res.meta)
  }, [termId])

  useEffect(() => {
    let cancelled = false
    Promise.all([
      apiFetch<{ data: Term }>(`/terms/${termId}`),
      apiFetch<Paginated<Term>>("/terms?per_page=100"),
    ])
      .then(([termRes, termsRes]) => {
        if (cancelled) return
        setTerm(termRes.data)
        setTerms(termsRes.data)
      })
      .catch((error) => {
        if (!cancelled)
          toast.error(error instanceof ApiError ? error.message : t("terms.loadFailed"))
      })
    // eslint-disable-next-line react-hooks/set-state-in-effect -- async load, guarded by `cancelled`
    loadMatrix().catch((error) => {
      if (cancelled) return
      toast.error(error instanceof ApiError ? error.message : t("terms.loadFailed"))
      setRows([])
    })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [termId])

  useEffect(() => {
    rowsRef.current = rows
  }, [rows])

  // Pending debounced saves still fire after unmount (the edit must not be
  // lost) — this flag only mutes their toasts/state updates.
  const unmountedRef = useRef(false)
  useEffect(() => {
    unmountedRef.current = false
    return () => {
      unmountedRef.current = true
    }
  }, [])

  const closed = term?.status === "closed"
  const editable = canManage && !closed

  // subject:grade → capable teacher ids, for ranking the per-row dropdown.
  const capableBy = useMemo(() => {
    const map = new Map<string, Set<number>>()
    for (const cap of meta?.capabilities ?? []) {
      const key = `${cap.subject_id}:${cap.grade_level_id}`
      if (!map.has(key)) map.set(key, new Set())
      map.get(key)!.add(cap.employee_id)
    }
    return map
  }, [meta])

  const sections = useMemo(
    () =>
      [...(meta?.sections ?? [])].sort(
        (a, b) =>
          (a.grade_level_sort ?? 0) - (b.grade_level_sort ?? 0) || a.name.localeCompare(b.name),
      ),
    [meta],
  )

  // Grade → sections tree for the grid.
  const gradeGroups = useMemo(() => {
    const groups = new Map<number, { name: string; sections: typeof sections }>()
    for (const section of sections) {
      if (!groups.has(section.grade_level_id)) {
        groups.set(section.grade_level_id, { name: section.grade_level_name ?? "", sections: [] })
      }
      groups.get(section.grade_level_id)!.sections.push(section)
    }
    return [...groups.entries()]
  }, [sections])

  const rowsBySection = useMemo(() => {
    const map = new Map<number, MatrixRow[]>()
    for (const row of rows ?? []) {
      if (!map.has(row.section_id)) map.set(row.section_id, [])
      map.get(row.section_id)!.push(row)
    }
    for (const list of map.values())
      list.sort((a, b) => (a.subject?.name ?? "").localeCompare(b.subject?.name ?? ""))
    return map
  }, [rows])

  const unassignedCount = (rows ?? []).filter((r) => r.employee_id === null).length
  const zeroLoadCount = (rows ?? []).filter((r) => r.periods_per_week === 0).length
  const totalCount = rows?.length ?? 0
  const assignedCount = totalCount - unassignedCount

  const missingBySection = useMemo(() => {
    const map = new Map<number, number>()
    for (const row of rows ?? []) {
      if (row.employee_id === null)
        map.set(row.section_id, (map.get(row.section_id) ?? 0) + 1)
    }
    return map
  }, [rows])

  // Focus mode drops out automatically once the last gap is filled.
  const focusActive = focusUnassigned && unassignedCount > 0

  // Tab data: in focus mode only grades/sections that still have gaps exist.
  const visibleGradeGroups = useMemo(() => {
    if (!focusActive) return gradeGroups
    return gradeGroups
      .map(
        ([gradeId, group]) =>
          [
            gradeId,
            {
              ...group,
              sections: group.sections.filter((s) => (missingBySection.get(s.id) ?? 0) > 0),
            },
          ] as (typeof gradeGroups)[number],
      )
      .filter(([, group]) => group.sections.length > 0)
  }, [gradeGroups, focusActive, missingBySection])

  // Resolve the selection against what is visible — falls back to the first
  // tab, so stale ids (data reload, focus toggle) can never strand the user.
  const activeGradeEntry =
    visibleGradeGroups.find(([gradeId]) => gradeId === activeGradeId) ??
    visibleGradeGroups[0] ??
    null
  const gradeSections = activeGradeEntry?.[1].sections ?? []
  const activeSection =
    gradeSections.find((s) => s.id === activeSectionId) ?? gradeSections[0] ?? null

  // Flat prev/next order across every visible section (grade by grade).
  const flatSections = useMemo(
    () =>
      visibleGradeGroups.flatMap(([gradeId, group]) =>
        group.sections.map((section) => ({ gradeId, gradeName: group.name, section })),
      ),
    [visibleGradeGroups],
  )
  const flatIndex = flatSections.findIndex((x) => x.section.id === activeSection?.id)

  function goToFlat(index: number) {
    const target = flatSections[index]
    if (!target) return
    setActiveGradeId(target.gradeId)
    setActiveSectionId(target.section.id)
    setAddingFor(null)
  }

  async function changeTeacher(row: MatrixRow, value: string) {
    const employeeId = value === UNASSIGNED ? null : Number(value)
    const previous = rows
    // Optimistic swap — the dropdown must feel instant on 3G.
    setRows((prev) =>
      (prev ?? []).map((r) =>
        r.id === row.id
          ? {
              ...r,
              employee_id: employeeId,
              employee: employeeId
                ? {
                    id: employeeId,
                    full_name: meta?.teachers.find((x) => x.id === employeeId)?.name ?? "",
                  }
                : null,
            }
          : r,
      ),
    )
    try {
      await apiFetch(`/subject-assignments/${row.id}`, {
        method: "PUT",
        body: { employee_id: employeeId },
        pendingKey: "pending.actions.assignment",
      })
    } catch (error) {
      setRows(previous)
      toast.error(error instanceof ApiError ? error.message : t("matrix.updateFailed"))
    }
  }

  /**
   * Periods/week stepper: optimistic bump, one debounced PUT per row. The
   * solver only schedules rows with a load, so this field is the timetable's
   * actual work order.
   */
  function changeLoad(row: MatrixRow, next: number, gradeLevelId: number | null) {
    const value = Math.max(0, Math.min(30, next))
    setRows((prev) =>
      (prev ?? []).map((r) => (r.id === row.id ? { ...r, periods_per_week: value } : r)),
    )

    const existing = loadTimers.current.get(row.id)
    if (existing) clearTimeout(existing)
    loadTimers.current.set(
      row.id,
      setTimeout(async () => {
        loadTimers.current.delete(row.id)
        try {
          await apiFetch(`/subject-assignments/${row.id}`, {
            method: "PUT",
            body: { periods_per_week: value },
            pendingKey: "pending.actions.assignment",
          })
          if (!unmountedRef.current) offerLoadPropagation(row, value, gradeLevelId)
        } catch (error) {
          if (unmountedRef.current) return
          toast.error(error instanceof ApiError ? error.message : t("matrix.updateFailed"))
          await loadMatrix().catch(() => undefined)
        }
      }, 600),
    )
  }

  /** Same subject, same grade, other sections with a different load → offer one-tap sync. */
  function offerLoadPropagation(row: MatrixRow, value: number, gradeLevelId: number | null) {
    if (gradeLevelId === null) return
    const gradeSectionIds = new Set(
      (meta?.sections ?? []).filter((sec) => sec.grade_level_id === gradeLevelId).map((sec) => sec.id),
    )
    const siblings = (rowsRef.current ?? []).filter(
      (r) =>
        r.id !== row.id &&
        r.subject_id === row.subject_id &&
        r.periods_per_week !== value &&
        gradeSectionIds.has(r.section_id),
    )
    if (siblings.length === 0) return

    toast(t("matrix.loadApplyTitle", { subject: row.subject?.name ?? "", count: value }), {
      description: t("matrix.loadApplyDesc", { count: siblings.length }),
      action: {
        label: t("matrix.applyToAllCta", { count: siblings.length }),
        onClick: () => applyLoadMany(siblings, value),
      },
      duration: 8000,
    })
  }

  async function applyLoadMany(targets: MatrixRow[], value: number) {
    setRows((prev) =>
      (prev ?? []).map((r) =>
        targets.some((x) => x.id === r.id) ? { ...r, periods_per_week: value } : r,
      ),
    )
    const results = await Promise.allSettled(
      targets.map((row) =>
        apiFetch(`/subject-assignments/${row.id}`, {
          method: "PUT",
          body: { periods_per_week: value },
          pendingKey: "pending.actions.assignment",
        }),
      ),
    )
    if (results.some((r) => r.status === "rejected")) {
      toast.error(t("matrix.updateFailed"))
      await loadMatrix().catch(() => undefined)
    } else {
      toast.success(t("matrix.applied", { count: targets.length }))
    }
  }

  /** Assign one teacher to several rows at once (grade-wide propagation). */
  async function assignMany(targets: MatrixRow[], employeeId: number) {
    const name = meta?.teachers.find((x) => x.id === employeeId)?.name ?? ""
    setRows((prev) =>
      (prev ?? []).map((r) =>
        targets.some((x) => x.id === r.id)
          ? { ...r, employee_id: employeeId, employee: { id: employeeId, full_name: name } }
          : r,
      ),
    )
    const results = await Promise.allSettled(
      targets.map((row) =>
        apiFetch(`/subject-assignments/${row.id}`, {
          method: "PUT",
          body: { employee_id: employeeId },
          pendingKey: "pending.actions.assignment",
        }),
      ),
    )
    const failed = results.filter((r) => r.status === "rejected").length
    if (failed > 0) {
      toast.error(t("matrix.updateFailed"))
      await loadMatrix()
    } else {
      toast.success(t("matrix.applied", { count: targets.length }))
    }
  }

  /** One-tap chip assign + offer to propagate across the grade's sections. */
  async function assignWithPropagation(row: MatrixRow, employeeId: number, gradeLevelId: number | null) {
    await changeTeacher(row, String(employeeId))

    // Same subject, same grade, other sections, still unassigned, teacher capable.
    const capable = capableBy.get(`${row.subject_id}:${gradeLevelId}`) ?? new Set<number>()
    if (!capable.has(employeeId)) return
    const gradeSectionIds = new Set(
      (meta?.sections ?? []).filter((sec) => sec.grade_level_id === gradeLevelId).map((sec) => sec.id),
    )
    const siblings = (rows ?? []).filter(
      (r) =>
        r.id !== row.id &&
        r.subject_id === row.subject_id &&
        r.employee_id === null &&
        gradeSectionIds.has(r.section_id),
    )
    if (siblings.length === 0) return

    const teacherName = meta?.teachers.find((x) => x.id === employeeId)?.name ?? ""
    toast(t("matrix.applyToAllTitle", { teacher: teacherName }), {
      description: t("matrix.applyToAllDesc", { count: siblings.length }),
      action: {
        label: t("matrix.applyToAllCta", { count: siblings.length }),
        onClick: () => assignMany(siblings, employeeId),
      },
      duration: 8000,
    })
  }

  async function autofill() {
    setAutofilling(true)
    try {
      const res = await apiFetch<{ data: { filled: number }; message?: string }>(
        `/terms/${termId}/autofill-assignments`,
        { method: "POST" },
      )
      toast.success(res.message ?? t("matrix.autofillDone"))
      if (res.data.filled > 0) await loadMatrix()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("matrix.updateFailed"))
    } finally {
      setAutofilling(false)
    }
  }

  async function addSubject(sectionId: number, subjectId: number) {
    try {
      await apiFetch(`/sections/${sectionId}/subject-assignments`, {
        method: "POST",
        body: { subject_id: subjectId, term_id: termId },
      })
      setAddingFor(null)
      await loadMatrix()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("matrix.addFailed"))
    }
  }

  async function removeRow(row: MatrixRow) {
    try {
      await apiFetch(`/subject-assignments/${row.id}`, { method: "DELETE" })
      setRows((prev) => (prev ?? []).filter((r) => r.id !== row.id))
      toast.success(t("matrix.removed"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("matrix.removeFailed"))
    }
  }

  async function generate() {
    setGenerating(true)
    try {
      const res = await apiFetch<{ data: { created: number }; message?: string }>(
        `/terms/${termId}/generate-assignments`,
        { method: "POST" },
      )
      toast.success(res.message ?? t("matrix.generated"))
      setGenerateOpen(false)
      await loadMatrix()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("matrix.generateFailed"))
    } finally {
      setGenerating(false)
    }
  }

  /** Searchable teacher picker: capable teachers first, then the rest. */
  function TeacherSelect({ row, gradeLevelId }: { row: MatrixRow; gradeLevelId: number | null }) {
    const capable = capableBy.get(`${row.subject_id}:${gradeLevelId}`) ?? new Set<number>()
    const capableTeachers = (meta?.teachers ?? []).filter((teacher) => capable.has(teacher.id))
    const otherTeachers = (meta?.teachers ?? []).filter((teacher) => !capable.has(teacher.id))

    const options = [
      {
        value: UNASSIGNED,
        label: t("matrix.unassigned"),
        leading: <UserX className="size-3.5 text-muted-foreground" />,
      },
      ...capableTeachers.map((teacher) => ({
        value: String(teacher.id),
        label: teacher.name,
        group: t("matrix.capableTeachers"),
        leading: <Check className="size-3.5 text-success" />,
      })),
      ...otherTeachers.map((teacher) => ({
        value: String(teacher.id),
        label: teacher.name,
        group: t("matrix.otherTeachers"),
      })),
    ]

    if (!editable) {
      return row.employee ? (
        <span className="truncate text-[11px] leading-none">{row.employee.full_name}</span>
      ) : (
        <Badge variant="outline" className="h-5 gap-1 border-warning/40 px-1.5 py-0 text-[10px] text-warning">
          <UserX className="size-2.5" />
          {t("matrix.unassigned")}
        </Badge>
      )
    }

    return (
      <OptionCombobox
        options={options}
        value={row.employee_id ? String(row.employee_id) : UNASSIGNED}
        onChange={(value) => {
          // OptionCombobox toggles to "" on re-select — ignore that so
          // clearing only happens via the explicit Unassigned option.
          if (!value) return
          changeTeacher(row, value)
        }}
        placeholder={t("matrix.teacher")}
        searchPlaceholder={t("matrix.searchTeacher")}
        emptyText={t("matrix.noTeacherMatch")}
        align="end"
        contentClassName="w-56"
        className={cn(
          "h-7 min-h-7 w-[7.25rem] shrink-0 gap-1 rounded-lg px-2.5 text-xs sm:w-40",
          "[&_svg]:size-3.5",
          row.employee_id === null && "border-warning/50 text-warning",
        )}
      />
    )
  }

  /** Compact −/＋ stepper for the row's weekly periods (0 = never scheduled). */
  function LoadStepper({ row, gradeLevelId }: { row: MatrixRow; gradeLevelId: number | null }) {
    const zero = row.periods_per_week === 0

    if (!editable) {
      return (
        <span
          className={cn(
            "shrink-0 text-[11px] leading-none tabular-nums",
            zero ? "text-warning" : "text-muted-foreground",
          )}
        >
          {t("matrix.perWeekShort", { count: row.periods_per_week })}
        </span>
      )
    }

    return (
      <div
        className={cn(
          "flex h-7 shrink-0 items-stretch overflow-hidden rounded-lg border",
          zero && "border-warning/50",
        )}
        title={t("matrix.periodsPerWeek")}
      >
        <button
          type="button"
          onClick={() => changeLoad(row, row.periods_per_week - 1, gradeLevelId)}
          disabled={row.periods_per_week <= 0}
          className="pressable inline-flex w-6 items-center justify-center text-muted-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-35"
          aria-label={t("matrix.loadDecrease", { subject: row.subject?.name ?? "" })}
        >
          <Minus className="size-3" />
        </button>
        <span
          className={cn(
            "inline-flex w-5 items-center justify-center text-xs font-semibold tabular-nums",
            zero && "text-warning",
          )}
          aria-live="polite"
        >
          {row.periods_per_week}
        </span>
        <button
          type="button"
          onClick={() => changeLoad(row, row.periods_per_week + 1, gradeLevelId)}
          disabled={row.periods_per_week >= 30}
          className="pressable inline-flex w-6 items-center justify-center text-muted-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-35"
          aria-label={t("matrix.loadIncrease", { subject: row.subject?.name ?? "" })}
        >
          <Plus className="size-3" />
        </button>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {confirmDialog}
      <Button asChild variant="ghost" size="sm" className="ml-4 md:ml-8">
        <Link href="/semesters">
          <ArrowLeft className="size-4" />
          {tc("nav.semesters")}
        </Link>
      </Button>

      {/* Semester header: name, program, lifecycle + grid actions */}
      <div className="flex flex-col gap-3 px-4 sm:flex-row sm:items-start sm:justify-between md:px-8">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">
              {term ? term.name : <Skeleton className="h-8 w-40" />}
            </h1>
            {term && (
              <TermStatusSelect
                term={term}
                siblings={terms}
                canUpdate={canUpdateTerm}
                onChanged={(updated) => {
                  setTerm(updated)
                  setTerms((prev) => prev.map((x) => (x.id === updated.id ? updated : x)))
                }}
              />
            )}
          </div>
          {term && (
            <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm text-muted-foreground">
              <CalendarRange className="size-3.5" />
              {term.academic_year_name}
              {term.program?.name && <> · {term.program.name}</>}
              {unassignedCount > 0 && (
                <Badge variant="outline" className="gap-1 border-warning/40 text-warning">
                  <UserX className="size-3" />
                  {t("matrix.unassignedCount", { count: unassignedCount })}
                </Badge>
              )}
              {zeroLoadCount > 0 && (
                <Badge variant="outline" className="gap-1 border-warning/40 text-warning">
                  <Timer className="size-3" />
                  {t("matrix.zeroLoadCount", { count: zeroLoadCount })}
                </Badge>
              )}
            </p>
          )}
        </div>
        {editable && term && (
          <div className="flex flex-wrap gap-2">
            {unassignedCount > 0 && (
              <Button className="h-10" loading={autofilling} onClick={() => autofill()}>
                <Wand2 className="size-4" />
                {t("matrix.autofill")}
              </Button>
            )}
            <Button variant="outline" className="h-10" onClick={() => setGenerateOpen(true)}>
              <Sparkles className="size-4" />
              {t("matrix.generate")}
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" className="h-10">
                  <Copy className="size-4" />
                  {t("matrix.reuse")}
                  <ChevronsUpDown className="size-3.5 text-muted-foreground" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => setGridAction({ mode: "copy", term })}>
                  {t("terms.copyAction")}
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => setGridAction({ mode: "clone", term })}>
                  {t("terms.cloneAction")}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        )}
      </div>

      {closed && (
        <div className="mx-4 rounded-2xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm md:mx-8">
          {t("matrix.closedNotice", { name: term?.name ?? "" })}
        </div>
      )}

      {/* Burn-down strip: progress + one-tap focus on what's left. */}
      {totalCount > 0 && (
        <div className="flex flex-wrap items-center gap-3 px-4 md:px-8">
          <div className="min-w-0 flex-1">
            <div className="flex items-baseline justify-between gap-2 text-xs">
              <span className="font-medium">
                {t("matrix.progress", { assigned: assignedCount, total: totalCount })}
              </span>
              <span className="text-muted-foreground tabular-nums">
                {Math.round((assignedCount / Math.max(totalCount, 1)) * 100)}%
              </span>
            </div>
            <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted">
              <div
                className={cn(
                  "h-full rounded-full transition-all duration-300",
                  unassignedCount === 0 ? "bg-success" : "bg-primary",
                )}
                style={{ width: `${(assignedCount / Math.max(totalCount, 1)) * 100}%` }}
              />
            </div>
          </div>
          {unassignedCount > 0 && (
            <button
              type="button"
              onClick={() => setFocusUnassigned((v) => !v)}
              className={cn(
                "pressable inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-xs font-medium transition-colors",
                focusUnassigned
                  ? "border-warning/50 bg-warning/10 text-warning"
                  : "text-muted-foreground hover:bg-muted",
              )}
              aria-pressed={focusUnassigned}
            >
              <UserX className="size-3.5" />
              {t("matrix.onlyUnassigned", { count: unassignedCount })}
            </button>
          )}
        </div>
      )}

      {/* ── Grade tabs → section tabs → the active section's card ──────── */}
      <div className="space-y-3 px-4 pb-4 md:px-8">
        {rows === null ? (
          <>
            <Skeleton className="h-10 rounded-full" />
            <Skeleton className="h-9 w-2/3 rounded-full" />
            <Skeleton className="h-64 rounded-2xl" />
          </>
        ) : sections.length === 0 ? (
          <EmptyState
            icon={BookOpen}
            title={t("matrix.noSections")}
            description={t("matrix.noSectionsDescription")}
          />
        ) : visibleGradeGroups.length === 0 ? (
          <EmptyState icon={Check} title={t("matrix.allAssigned")} />
        ) : (
          <>
            {/* Grade tabs — swipeable strip on mobile, wrapping on desktop */}
            <div
              role="tablist"
              aria-label={t("matrix.gradeTabs")}
              className="scrollbar-none -mx-4 flex snap-x gap-2 overflow-x-auto px-4 pb-0.5 md:mx-0 md:flex-wrap md:px-0"
            >
              {visibleGradeGroups.map(([gradeId, group]) => {
                const gradeTotal = group.sections.reduce(
                  (acc, sec) => acc + (rowsBySection.get(sec.id)?.length ?? 0),
                  0,
                )
                const gradeMissing = group.sections.reduce(
                  (acc, sec) => acc + (missingBySection.get(sec.id) ?? 0),
                  0,
                )
                const active = gradeId === activeGradeEntry?.[0]
                return (
                  <button
                    key={gradeId}
                    type="button"
                    role="tab"
                    aria-selected={active}
                    onClick={() => {
                      setActiveGradeId(gradeId)
                      setActiveSectionId(null)
                      setAddingFor(null)
                    }}
                    className={cn(
                      "pressable inline-flex min-h-10 shrink-0 snap-start items-center gap-1.5 rounded-full border px-4 text-sm font-medium transition-colors",
                      active
                        ? "border-primary bg-primary text-primary-foreground shadow-sm"
                        : "bg-card text-muted-foreground hover:bg-muted hover:text-foreground",
                    )}
                  >
                    {group.name}
                    {gradeMissing > 0 ? (
                      <span
                        className={cn(
                          "rounded-full px-1.5 py-0.5 text-[10px] font-semibold leading-none tabular-nums",
                          active ? "bg-primary-foreground/25" : "bg-warning/15 text-warning",
                        )}
                      >
                        {gradeMissing}
                      </span>
                    ) : (
                      gradeTotal > 0 && (
                        <Check
                          className={cn("size-3.5", active ? "text-primary-foreground" : "text-success")}
                          aria-label={t("matrix.allAssigned")}
                        />
                      )
                    )}
                  </button>
                )
              })}
            </div>

            {/* Section tabs of the active grade */}
            <div
              role="tablist"
              aria-label={t("matrix.sectionTabs")}
              className="scrollbar-none -mx-4 flex snap-x gap-1.5 overflow-x-auto px-4 pb-0.5 md:mx-0 md:flex-wrap md:px-0"
            >
              {gradeSections.map((section) => {
                const missing = missingBySection.get(section.id) ?? 0
                const count = rowsBySection.get(section.id)?.length ?? 0
                const active = section.id === activeSection?.id
                return (
                  <button
                    key={section.id}
                    type="button"
                    role="tab"
                    aria-selected={active}
                    onClick={() => {
                      setActiveSectionId(section.id)
                      setAddingFor(null)
                    }}
                    className={cn(
                      "pressable inline-flex min-h-9 shrink-0 snap-start items-center gap-1.5 rounded-xl border px-3 text-xs font-medium transition-colors",
                      active
                        ? "border-primary/50 bg-primary/10 text-primary"
                        : "text-muted-foreground hover:bg-muted hover:text-foreground",
                    )}
                  >
                    <span className="max-w-32 truncate">{section.name}</span>
                    {missing > 0 ? (
                      <span
                        className={cn(
                          "rounded-full px-1.5 py-0.5 text-[10px] font-semibold leading-none tabular-nums",
                          active ? "bg-primary/15" : "bg-warning/15 text-warning",
                        )}
                      >
                        {missing}
                      </span>
                    ) : (
                      count > 0 && (
                        <Check className="size-3 text-success" aria-label={t("matrix.allAssigned")} />
                      )
                    )}
                  </button>
                )
              })}
            </div>

            {/* Active section card */}
            {activeSection &&
              (() => {
                const allSectionRows = rowsBySection.get(activeSection.id) ?? []
                const missing = missingBySection.get(activeSection.id) ?? 0
                // Focus mode: gaps only.
                const sectionRows = focusActive
                  ? allSectionRows.filter((row) => row.employee_id === null)
                  : allSectionRows
                const applicable = (meta?.subjects ?? []).filter(
                  (subject) =>
                    subjectAppliesToSort(subject, activeSection.grade_level_sort) &&
                    !allSectionRows.some((row) => row.subject_id === subject.id),
                )
                return (
                  <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
                    {/* Section header */}
                    <header className="flex flex-wrap items-center gap-x-2.5 gap-y-2 border-b bg-muted/40 px-3 py-2.5">
                      {/* Short tile — long section names must never overflow. */}
                      <span className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-accent text-xs font-semibold">
                        {activeSection.name.slice(0, 2).toUpperCase()}
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold leading-tight">
                          {activeSection.name}
                        </p>
                        <p className="text-muted-foreground truncate text-[11px] leading-tight">
                          {activeGradeEntry?.[1].name} ·{" "}
                          {t("matrix.subjectCount", { count: allSectionRows.length })}
                        </p>
                      </div>
                      {missing > 0 ? (
                        <Badge
                          variant="outline"
                          className="gap-1 border-warning/40 px-1.5 py-0 text-[11px] text-warning"
                        >
                          <UserX className="size-3" />
                          {missing}
                        </Badge>
                      ) : (
                        allSectionRows.length > 0 && (
                          <Check
                            className="size-4 shrink-0 text-success"
                            aria-label={t("matrix.allAssigned")}
                          />
                        )
                      )}
                      {editable && !focusActive && applicable.length > 0 && (
                        <div className="basis-full sm:ml-2 sm:basis-auto">
                          {addingFor === activeSection.id ? (
                            <Select
                              value=""
                              onValueChange={(v) => addSubject(activeSection.id, Number(v))}
                            >
                              <SelectTrigger className="h-8 w-full text-xs sm:w-56">
                                <SelectValue placeholder={t("matrix.pickSubject")} />
                              </SelectTrigger>
                              <SelectContent>
                                {applicable.map((subject) => (
                                  <SelectItem key={subject.id} value={String(subject.id)}>
                                    {subject.name}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          ) : (
                            <Button
                              variant="outline"
                              size="sm"
                              className="h-8 w-full text-xs sm:w-auto"
                              onClick={() => setAddingFor(activeSection.id)}
                            >
                              <Plus className="size-3.5" />
                              {t("matrix.addSubject")}
                            </Button>
                          )}
                        </div>
                      )}
                    </header>

                    {sectionRows.length === 0 ? (
                      <p className="px-3 py-5 text-center text-xs text-muted-foreground">
                        {t("matrix.noSubjects")}
                      </p>
                    ) : (
                      <ul className="divide-y divide-border/50">
                        {sectionRows.map((row) => {
                          // Initial-only chips: one-tap assign without
                          // wrapping the row onto a second line.
                          const rowCapable =
                            editable && row.employee_id === null
                              ? (meta?.teachers ?? []).filter((teacher) =>
                                  (
                                    capableBy.get(
                                      `${row.subject_id}:${activeSection.grade_level_id}`,
                                    ) ?? new Set()
                                  ).has(teacher.id),
                                )
                              : []
                          const assigned = row.employee_id !== null
                          return (
                            <li
                              key={row.id}
                              className={cn(
                                "group flex h-10 items-center gap-2 px-3 transition-colors hover:bg-muted/35",
                                !assigned && "bg-warning/[0.03]",
                              )}
                            >
                              <span
                                className={cn(
                                  "size-1.5 shrink-0 rounded-full",
                                  assigned ? "bg-success" : "bg-warning",
                                )}
                                aria-hidden
                              />
                              <span className="min-w-0 flex-1 truncate text-[13px] font-medium leading-none tracking-tight">
                                {row.subject?.name ?? "—"}
                              </span>
                              {rowCapable.slice(0, 2).map((teacher) => {
                                const initial = teacher.name.trim().charAt(0).toUpperCase() || "?"
                                return (
                                  <button
                                    key={teacher.id}
                                    type="button"
                                    onClick={() =>
                                      assignWithPropagation(
                                        row,
                                        teacher.id,
                                        activeSection.grade_level_id,
                                      )
                                    }
                                    className="pressable inline-flex size-6 shrink-0 items-center justify-center rounded-full border border-primary/35 bg-primary/8 text-[10px] font-semibold text-primary transition-colors hover:bg-primary/15"
                                    title={t("matrix.assignChipHint", { teacher: teacher.name })}
                                    aria-label={t("matrix.assignChipHint", {
                                      teacher: teacher.name,
                                    })}
                                  >
                                    {initial}
                                  </button>
                                )
                              })}
                              <LoadStepper
                                row={row}
                                gradeLevelId={activeSection.grade_level_id}
                              />
                              <TeacherSelect
                                row={row}
                                gradeLevelId={activeSection.grade_level_id}
                              />
                              {editable && (
                                <Button
                                  variant="ghost"
                                  size="icon"
                                  className="size-6 shrink-0 text-muted-foreground opacity-50 hover:text-destructive group-hover:opacity-100"
                                  onClick={() =>
                                    confirmDelete(
                                      () => removeRow(row),
                                      tc("confirmDelete.named", {
                                        name: `${row.subject?.name ?? ""} · ${activeGradeEntry?.[1].name ?? ""} ${activeSection.name}`,
                                      }),
                                    )
                                  }
                                  aria-label={tc("actions.delete")}
                                >
                                  <X className="size-3.5" />
                                </Button>
                              )}
                            </li>
                          )
                        })}
                      </ul>
                    )}

                    {/* Prev/next pager — walk every section like app screens */}
                    {flatSections.length > 1 && (
                      <footer className="flex items-center justify-between gap-2 border-t bg-muted/20 px-2 py-1.5">
                        <Button
                          variant="ghost"
                          size="sm"
                          className="h-9 text-xs"
                          disabled={flatIndex <= 0}
                          onClick={() => goToFlat(flatIndex - 1)}
                        >
                          <ChevronLeft className="size-4" />
                          {t("matrix.prevSection")}
                        </Button>
                        <span className="text-muted-foreground text-[11px] tabular-nums">
                          {t("matrix.sectionPosition", {
                            index: flatIndex + 1,
                            total: flatSections.length,
                          })}
                        </span>
                        <Button
                          variant="ghost"
                          size="sm"
                          className="h-9 text-xs"
                          disabled={flatIndex >= flatSections.length - 1}
                          onClick={() => goToFlat(flatIndex + 1)}
                        >
                          {t("matrix.nextSection")}
                          <ChevronRight className="size-4" />
                        </Button>
                      </footer>
                    )}
                  </section>
                )
              })()}
          </>
        )}
      </div>

      {/* Generate confirmation — the same warning the create-forms show. */}
      <AlertDialog open={generateOpen} onOpenChange={setGenerateOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("matrix.generateConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("matrix.generateConfirmDescription")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={generating}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={generating}
              onClick={(e) => {
                e.preventDefault()
                generate()
              }}
            >
              {t("matrix.generateCta")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <TermCloneCopyDialog
        action={gridAction}
        terms={terms}
        onOpenChange={(open) => !open && setGridAction(null)}
        onDone={() => loadMatrix()}
      />
    </div>
  )
}
