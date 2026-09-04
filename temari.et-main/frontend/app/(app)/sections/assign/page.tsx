"use client"

import { LayoutGrid, Save, Shuffle, Sparkles, Users } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  AcademicYear,
  AssignBoardSection,
  AssignBoardStudent,
  Paginated,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const POOL = 0 // pseudo section id for "unassigned"

export default function SectionAssignPage() {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const [years, setYears] = useState<AcademicYear[]>([])
  const [yearId, setYearId] = useState<number | null>(null)
  // Branch-scoped grade offering, session-cached across pages.
  const { grades } = useGradeLevels()
  const [gradeId, setGradeId] = useState<number | null>(null)
  const [sections, setSections] = useState<AssignBoardSection[] | null>(null)
  const [students, setStudents] = useState<AssignBoardStudent[]>([])
  // enrollment_id → section_id|null as currently EDITED (server state lives on
  // the student rows; this map holds the working copy).
  const [placement, setPlacement] = useState<Record<number, number | null>>({})
  const [saving, setSaving] = useState(false)
  const [proposing, setProposing] = useState(false)

  const hasBranch = active.branchId != null
  const canUpdate = permissions.includes("sections.update")

  // Default to the first offered grade once the (cached) ladder arrives.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- default selection on data arrival
    if (grades.length > 0) setGradeId((prev) => prev ?? grades[0]?.id ?? null)
  }, [grades])

  useEffect(() => {
    if (!hasBranch) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on context switch
      setYears([])
      setYearId(null)
      setSections(null)
      return
    }
    let cancelled = false
    apiFetch<Paginated<AcademicYear>>("/academic-years?per_page=100")
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
    return () => {
      cancelled = true
    }
  }, [hasBranch, active.branchId])

  const loadBoard = useCallback(() => {
    if (!yearId || !gradeId) return
    setSections(null)
    let cancelled = false
    apiFetch<{
      data: { sections: AssignBoardSection[]; students: AssignBoardStudent[] }
    }>(`/section-assignments/board?academic_year_id=${yearId}&grade_level_id=${gradeId}`)
      .then((res) => {
        if (cancelled) return
        setSections(res.data.sections)
        setStudents(res.data.students)
        setPlacement(
          Object.fromEntries(res.data.students.map((s) => [s.enrollment_id, s.section_id])),
        )
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setSections([])
        setStudents([])
      })
    return () => {
      cancelled = true
    }
  }, [yearId, gradeId, tc])

  // eslint-disable-next-line react-hooks/set-state-in-effect -- loadBoard resets to the loading state
  useEffect(() => loadBoard(), [loadBoard])

  const dirty = useMemo(
    () =>
      students.filter(
        (s) => (placement[s.enrollment_id] ?? null) !== (s.section_id ?? null),
      ),
    [students, placement],
  )

  /** Students grouped by their EDITED section. */
  const grouped = useMemo(() => {
    const map = new Map<number, AssignBoardStudent[]>()
    map.set(POOL, [])
    for (const section of sections ?? []) map.set(section.id, [])
    for (const student of students) {
      const sectionId = placement[student.enrollment_id] ?? null
      const key = sectionId !== null && map.has(sectionId) ? sectionId : POOL
      map.get(key)!.push(student)
    }
    return map
  }, [students, placement, sections])

  async function autoBalance(mode: "fill" | "reshuffle") {
    if (!yearId || !gradeId) return
    setProposing(true)
    try {
      const res = await apiFetch<{
        data: { assignments: { enrollment_id: number; section_id: number | null }[]; unplaced: number }
      }>("/section-assignments/propose", {
        method: "POST",
        body: { academic_year_id: yearId, grade_level_id: gradeId, mode },
      })
      setPlacement((prev) => {
        const next = { ...prev }
        for (const row of res.data.assignments) next[row.enrollment_id] = row.section_id
        return next
      })
      if (res.data.unplaced > 0) {
        toast.warning(t("assign.unplacedWarning", { count: res.data.unplaced }))
      } else {
        toast.success(t("assign.proposalReady"))
      }
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setProposing(false)
    }
  }

  async function commit() {
    if (!yearId || dirty.length === 0) return
    setSaving(true)
    try {
      await apiFetch("/section-assignments/commit", {
        method: "POST",
        body: {
          academic_year_id: yearId,
          assignments: dirty.map((s) => ({
            enrollment_id: s.enrollment_id,
            section_id: placement[s.enrollment_id] ?? null,
          })),
        },
      })
      toast.success(t("assign.committed"))
      loadBoard()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6 pb-24">
      <PageHeader
        title={t("assign.title")}
        description={t("assign.subtitle")}
        backHref="/sections"
        actions={
          hasBranch && canUpdate ? (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button loading={proposing} disabled={!sections?.length}>
                  <Sparkles className="size-4" />
                  {t("assign.autoAssign")}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => autoBalance("fill")}>
                  <Users className="size-4" />
                  {t("assign.autoMode.fill")}
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => autoBalance("reshuffle")}>
                  <Shuffle className="size-4" />
                  {t("assign.autoMode.reshuffle")}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          ) : undefined
        }
      />

      {!hasBranch ? (
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("assign.noBranch")}
          </div>
        </div>
      ) : (
        <>
          <div className="page-gutter flex flex-wrap items-center gap-2">
            <Select value={yearId ? String(yearId) : ""} onValueChange={(v) => setYearId(Number(v))}>
              <SelectTrigger className="h-9 w-auto min-w-36 rounded-full bg-muted/30 text-xs font-medium" aria-label={t("assign.year")}>
                <SelectValue placeholder={t("assign.year")} />
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
              value={gradeId ? String(gradeId) : ""}
              onValueChange={(v) => setGradeId(Number(v))}
            >
              <SelectTrigger className="h-9 w-auto min-w-32 rounded-full bg-muted/30 text-xs font-medium" aria-label={t("assign.grade")}>
                <SelectValue placeholder={t("assign.grade")} />
              </SelectTrigger>
              <SelectContent>
                {grades.map((grade) => (
                  <SelectItem key={grade.id} value={String(grade.id)}>
                    {grade.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="page-gutter">
            {sections === null ? (
              <div className="flex gap-3 overflow-hidden">
                {[0, 1, 2].map((i) => (
                  <Skeleton key={i} className="h-72 w-64 shrink-0 rounded-2xl" />
                ))}
              </div>
            ) : students.length === 0 ? (
              <div className="rounded-2xl border bg-card shadow-xs">
                <EmptyState
                  icon={LayoutGrid}
                  title={t("assign.empty")}
                  description={t("assign.subtitle")}
                />
              </div>
            ) : (
              <div className="scrollbar-none -mx-4 flex gap-3 overflow-x-auto px-4 pb-2 md:-mx-8 md:px-8">
                <Column
                  title={t("assign.unassigned")}
                  list={grouped.get(POOL) ?? []}
                  isPool
                  sections={sections ?? []}
                  placement={placement}
                  setPlacement={setPlacement}
                  canUpdate={canUpdate}
                />
                {(sections ?? []).map((section) => (
                  <Column
                    key={section.id}
                    title={section.name}
                    list={grouped.get(section.id) ?? []}
                    capacity={section.capacity}
                    sections={sections ?? []}
                    placement={placement}
                    setPlacement={setPlacement}
                    canUpdate={canUpdate}
                  />
                ))}
              </div>
            )}
          </div>

          {dirty.length > 0 && (
            <div className="fixed inset-x-0 bottom-20 z-30 flex justify-center px-4 md:bottom-6">
              <div className="flex items-center gap-2 rounded-full border bg-background/95 p-1.5 pl-4 shadow-lg backdrop-blur-xl">
                <span className="text-xs font-medium text-muted-foreground">
                  {t("assign.changes", { count: dirty.length })}
                </span>
                <Button size="sm" onClick={commit} loading={saving} disabled={!canUpdate}>
                  <Save className="size-4" />
                  {t("assign.commit")}
                </Button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}

type PlacementMap = Record<number, number | null>

interface ColumnSharedProps {
  sections: AssignBoardSection[]
  placement: PlacementMap
  setPlacement: React.Dispatch<React.SetStateAction<PlacementMap>>
  canUpdate: boolean
}

function columnStats(list: AssignBoardStudent[]) {
  const girls = list.filter((s) => s.gender === "female").length
  const scored = list.filter((s) => s.last_average !== null)
  const avg =
    scored.length > 0
      ? scored.reduce((sum, s) => sum + (s.last_average ?? 0), 0) / scored.length
      : null
  return { girls, boys: list.length - girls, avg }
}

function StudentChip({
  student,
  sections,
  placement,
  setPlacement,
  canUpdate,
}: { student: AssignBoardStudent } & ColumnSharedProps) {
  const { t } = useTranslation("academic")
  const moved = (placement[student.enrollment_id] ?? null) !== (student.section_id ?? null)
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild disabled={!canUpdate}>
        <button
          type="button"
          className={cn(
            "pressable flex w-full items-center gap-2 rounded-xl border bg-card px-2.5 py-2 text-left transition-colors hover:bg-accent",
            moved && "border-primary/50 bg-primary/5",
          )}
        >
          <PersonAvatar
            name={student.full_name}
            photoUrl={student.photo_url}
            className="size-7 text-[10px]"
          />
          <span className="min-w-0 flex-1">
            <span className="block truncate text-xs font-medium">{student.full_name}</span>
            <span className="block text-[11px] text-muted-foreground tabular-nums">
              {student.last_average !== null
                ? t("assign.avg", { value: student.last_average.toFixed(1) })
                : "—"}
            </span>
          </span>
          <span
            className={cn(
              "size-1.5 shrink-0 rounded-full",
              student.gender === "female" ? "bg-info" : "bg-warning",
            )}
            aria-hidden
          />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-44">
        <DropdownMenuLabel className="text-xs">{t("assign.moveTo")}</DropdownMenuLabel>
        {sections
          .filter((section) => section.id !== (placement[student.enrollment_id] ?? null))
          .map((section) => (
            <DropdownMenuItem
              key={section.id}
              onClick={() =>
                setPlacement((prev) => ({ ...prev, [student.enrollment_id]: section.id }))
              }
            >
              {section.name}
            </DropdownMenuItem>
          ))}
        {(placement[student.enrollment_id] ?? null) !== null && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={() =>
                setPlacement((prev) => ({ ...prev, [student.enrollment_id]: null }))
              }
            >
              {t("assign.removeFromSection")}
            </DropdownMenuItem>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

function Column({
  title,
  list,
  capacity,
  isPool = false,
  ...shared
}: {
  title: string
  list: AssignBoardStudent[]
  capacity?: number | null
  isPool?: boolean
} & ColumnSharedProps) {
  const { t } = useTranslation("academic")
  const stats = columnStats(list)
  return (
    <div
      className={cn(
        "flex w-64 shrink-0 flex-col rounded-2xl border bg-card shadow-xs",
        isPool && "border-dashed bg-muted/20",
      )}
    >
      <div className="border-b px-3 py-2.5">
        <div className="flex items-center justify-between gap-2">
          <p className="truncate text-sm font-semibold">{title}</p>
          <Badge variant="outline" className="rounded-full text-[11px] tabular-nums">
            {capacity != null
              ? t("assign.capacity", { count: list.length, cap: capacity })
              : list.length}
          </Badge>
        </div>
        <p className="mt-0.5 text-[11px] text-muted-foreground tabular-nums">
          {stats.boys} {t("assign.boys")} · {stats.girls} {t("assign.girls")}
          {stats.avg !== null && <> · {t("assign.avg", { value: stats.avg.toFixed(1) })}</>}
        </p>
      </div>
      <div className="flex-1 space-y-1.5 overflow-y-auto p-2" style={{ maxHeight: "60vh" }}>
        {list.length === 0 ? (
          <p className="px-2 py-6 text-center text-xs text-muted-foreground">—</p>
        ) : (
          list.map((student) => (
            <StudentChip key={student.enrollment_id} student={student} {...shared} />
          ))
        )}
      </div>
    </div>
  )
}
