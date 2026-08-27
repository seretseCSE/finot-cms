"use client"

import { Lock, LockOpen, MoveRight, Trash2, X } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { TimetableGrid, TimetableGridSlot } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtTime } from "@/lib/dates"

type Armed =
  | { type: "place"; assignmentId: number }
  | { type: "move"; slotId: number }
  | null

/**
 * The timetable grid editor — the aSc loop: tap a lesson from the tray (or an
 * existing cell) then tap where it goes; hard conflicts are refused by the
 * backend and explained; lock the cells you like and regenerate the rest.
 */
export function GridEditor({
  versionId,
  canManage,
  editable,
  refreshKey = 0,
}: {
  versionId: number
  canManage: boolean
  /** Draft or published — generating/archived versions are read-only. */
  editable: boolean
  /** Bump to force a grid refetch (after generate finishes). */
  refreshKey?: number
}) {
  const { t } = useTranslation("timetable")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [grid, setGrid] = useState<TimetableGrid | null>(null)
  const [view, setView] = useState<"section" | "teacher">("section")
  const [sectionId, setSectionId] = useState<number | null>(null)
  const [teacherId, setTeacherId] = useState<number | null>(null)
  const [armed, setArmed] = useState<Armed>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on param change
    setGrid(null)
    apiFetch<{ data: TimetableGrid }>(`/timetable-versions/${versionId}`)
      .then((res) => {
        if (cancelled) return
        setGrid(res.data)
        setSectionId((prev) =>
          prev && res.data.sections.some((s) => s.id === prev)
            ? prev
            : (res.data.sections[0]?.id ?? null),
        )
      })
      .catch((error) => {
        if (!cancelled) {
          toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        }
      })
    return () => {
      cancelled = true
    }
  }, [versionId, refreshKey, tc])

  // Esc cancels the armed placement/move.
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setArmed(null)
    }
    window.addEventListener("keydown", onKey)
    return () => window.removeEventListener("keydown", onKey)
  }, [])

  const assignmentById = useMemo(
    () => new Map((grid?.assignments ?? []).map((a) => [a.id, a])),
    [grid],
  )

  /** section_id → "Grade 1 — A" (used everywhere a bare letter is ambiguous). */
  const sectionLabels = useMemo(() => {
    const map = new Map<number, string>()
    for (const section of grid?.sections ?? []) {
      map.set(
        section.id,
        section.grade_level_name ? `${section.grade_level_name} — ${section.name}` : section.name,
      )
    }
    return map
  }, [grid])

  const teachers = useMemo(() => {
    const map = new Map<number, string>()
    for (const assignment of grid?.assignments ?? []) {
      if (assignment.teacher_id && assignment.teacher_name) {
        map.set(assignment.teacher_id, assignment.teacher_name)
      }
    }
    return [...map.entries()].sort((a, b) => a[1].localeCompare(b[1]))
  }, [grid])

  /** Slots visible in the current view, keyed "day-period". */
  const cellMap = useMemo(() => {
    const map = new Map<string, TimetableGridSlot[]>()
    for (const slot of grid?.slots ?? []) {
      const assignment = assignmentById.get(slot.subject_assignment_id)
      if (!assignment) continue
      const visible =
        view === "section"
          ? assignment.section_id === sectionId
          : assignment.teacher_id === teacherId
      if (!visible) continue
      const key = `${slot.day_of_week}-${slot.period_number}`
      map.set(key, [...(map.get(key) ?? []), slot])
    }
    return map
  }, [grid, assignmentById, view, sectionId, teacherId])

  /** Lessons of the selected section still needing placement. */
  const tray = useMemo(() => {
    if (!grid || sectionId === null) return []
    return grid.assignments
      .filter((a) => a.section_id === sectionId && a.placed < a.periods_per_week)
      .map((a) => ({ ...a, remaining: a.periods_per_week - a.placed }))
  }, [grid, sectionId])

  const conflictToast = useCallback(
    (error: unknown) => {
      if (!(error instanceof ApiError)) {
        toast.error(tc("errors.generic"))
        return
      }

      // The backend sends structured violations: code + the clashing
      // section/subject, so the toast can say WHO is in the way.
      const conflicts = (error.payload?.conflicts ?? []) as {
        code?: string
        section?: string | null
        subject?: string | null
        max?: number
      }[]
      const first = conflicts.find(
        (c) => c.code && t(`grid.conflictCodes.${c.code}`) !== `grid.conflictCodes.${c.code}`,
      )

      if (first?.code) {
        const message = t(`grid.conflictCodes.${first.code}`, {
          section: first.section ?? "…",
          subject: first.subject ?? "…",
          max: first.max ?? 6,
        })
        toast.error(first.subject || first.section
          ? `${message}${first.subject && first.code !== "teacher_clash" ? ` (${first.subject})` : ""}`
          : message)
      } else {
        toast.error(error.message)
      }
    },
    [t, tc],
  )

  function applySlot(updated: TimetableGridSlot, previousId?: number) {
    setGrid((prev) => {
      if (!prev) return prev
      const others = prev.slots.filter((s) => s.id !== (previousId ?? updated.id))
      const assignments = prev.assignments.map((a) => {
        if (a.id !== updated.subject_assignment_id || previousId) return a
        return { ...a, placed: a.placed + 1 }
      })
      return { ...prev, slots: [...others, updated], assignments }
    })
  }

  async function handleCellTap(day: number, period: number) {
    if (!armed || busy || !editable) return
    setBusy(true)
    try {
      if (armed.type === "place") {
        const res = await apiFetch<{ data: TimetableGridSlot }>(
          `/timetable-versions/${versionId}/slots`,
          {
            method: "POST",
            body: {
              subject_assignment_id: armed.assignmentId,
              day_of_week: day,
              period_number: period,
            },
          },
        )
        applySlot(res.data)
        toast.success(t("grid.placed"))
        // Stay armed while the lesson still has periods to place.
        const assignment = assignmentById.get(armed.assignmentId)
        if (assignment && assignment.placed + 2 > assignment.periods_per_week) setArmed(null)
      } else {
        const res = await apiFetch<{ data: TimetableGridSlot }>(
          `/timetable-versions/${versionId}/slots/${armed.slotId}`,
          { method: "PUT", body: { day_of_week: day, period_number: period } },
        )
        applySlot(res.data, armed.slotId)
        toast.success(t("grid.movedOk"))
        setArmed(null)
      }
    } catch (error) {
      conflictToast(error)
    } finally {
      setBusy(false)
    }
  }

  async function toggleLock(slot: TimetableGridSlot) {
    try {
      const res = await apiFetch<{ data: TimetableGridSlot }>(
        `/timetable-versions/${versionId}/slots/${slot.id}`,
        { method: "PUT", body: { is_locked: !slot.is_locked } },
      )
      applySlot(res.data, slot.id)
    } catch (error) {
      conflictToast(error)
    }
  }

  async function setRoom(slot: TimetableGridSlot, roomId: number | null) {
    try {
      const res = await apiFetch<{ data: TimetableGridSlot }>(
        `/timetable-versions/${versionId}/slots/${slot.id}`,
        { method: "PUT", body: { room_id: roomId } },
      )
      applySlot(res.data, slot.id)
    } catch (error) {
      conflictToast(error)
    }
  }

  async function removeSlot(slot: TimetableGridSlot) {
    try {
      await apiFetch(`/timetable-versions/${versionId}/slots/${slot.id}`, { method: "DELETE" })
      setGrid((prev) => {
        if (!prev) return prev
        return {
          ...prev,
          slots: prev.slots.filter((s) => s.id !== slot.id),
          assignments: prev.assignments.map((a) =>
            a.id === slot.subject_assignment_id ? { ...a, placed: Math.max(0, a.placed - 1) } : a,
          ),
        }
      })
      toast.success(t("grid.removed"))
    } catch (error) {
      conflictToast(error)
    }
  }

  if (grid === null) return <Skeleton className="h-96 rounded-2xl" />

  const days = grid.version.days

  return (
    <div className="space-y-4">
      {/* View switch + entity picker. */}
      <div className="flex flex-wrap items-center gap-2">
        <div className="flex rounded-full border p-0.5">
          {(["section", "teacher"] as const).map((mode) => (
            <button
              key={mode}
              type="button"
              onClick={() => {
                setView(mode)
                setArmed(null)
                if (mode === "teacher" && teacherId === null) setTeacherId(teachers[0]?.[0] ?? null)
              }}
              className={cn(
                "pressable rounded-full px-3 py-1.5 text-xs font-medium transition-colors",
                view === mode ? "bg-primary text-primary-foreground" : "text-muted-foreground",
              )}
              aria-pressed={view === mode}
            >
              {mode === "section" ? t("grid.sectionView") : t("grid.teacherView")}
            </button>
          ))}
        </div>
        {view === "section" ? (
          <Select
            value={sectionId ? String(sectionId) : ""}
            onValueChange={(v) => {
              setSectionId(Number(v))
              setArmed(null)
            }}
          >
            <SelectTrigger className="h-9 w-auto min-w-36 rounded-full bg-muted/30 text-xs font-medium" aria-label={t("grid.section")}>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {grid.sections.map((section) => (
                <SelectItem key={section.id} value={String(section.id)}>
                  {section.grade_level_name} — {section.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : (
          <Select
            value={teacherId ? String(teacherId) : ""}
            onValueChange={(v) => setTeacherId(Number(v))}
          >
            <SelectTrigger className="h-9 w-auto min-w-36 rounded-full bg-muted/30 text-xs font-medium" aria-label={t("grid.teacherView")}>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {teachers.map(([id, name]) => (
                <SelectItem key={id} value={String(id)}>
                  {name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
        {armed && (
          <span className="inline-flex items-center gap-1 rounded-full border border-primary/40 bg-primary/10 py-0.5 pr-1 pl-3 text-xs font-medium text-primary">
            {armed.type === "place" ? t("grid.placeHint") : t("grid.moveHint")}
            <button
              type="button"
              onClick={() => setArmed(null)}
              className="pressable flex size-6 items-center justify-center rounded-full hover:bg-primary/15"
              aria-label={tc("actions.cancel")}
            >
              <X className="size-3.5" />
            </button>
          </span>
        )}
      </div>

      {/* Unplaced tray — the lessons waiting for a cell. */}
      {canManage && editable && view === "section" && (
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {t("grid.unplaced")}
          </span>
          {tray.length === 0 ? (
            <span className="text-xs text-success">{t("grid.allPlaced")}</span>
          ) : (
            tray.map((lesson) => (
              <button
                key={lesson.id}
                type="button"
                onClick={() =>
                  setArmed(
                    armed?.type === "place" && armed.assignmentId === lesson.id
                      ? null
                      : { type: "place", assignmentId: lesson.id },
                  )
                }
                className={cn(
                  "pressable inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors",
                  armed?.type === "place" && armed.assignmentId === lesson.id
                    ? "border-primary bg-primary text-primary-foreground"
                    : "hover:bg-muted",
                )}
              >
                {lesson.subject.name}
                <Badge
                  variant="secondary"
                  className="rounded-full px-1.5 py-0 text-[10px] tabular-nums"
                >
                  ×{lesson.remaining}
                </Badge>
              </button>
            ))
          )}
        </div>
      )}

      {/* The grid: period rows × days. Inner horizontal scroll on mobile. */}
      <div className="overflow-x-auto rounded-2xl border bg-card shadow-xs">
        <table className="w-full min-w-[640px] border-collapse text-xs">
          <thead>
            <tr className="border-b bg-muted/30">
              <th className="w-24 px-2 py-2 text-left font-medium text-muted-foreground">
                {t("periods.title")}
              </th>
              {days.map((day) => (
                <th key={day} className="px-2 py-2 text-left font-medium">
                  {t(`days.${day}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {grid.periods.map((period) => {
              if (period.type !== "class") {
                return (
                  <tr key={`sep-${period.sequence}`} className="border-b bg-muted/40">
                    <td
                      colSpan={days.length + 1}
                      className="px-2 py-1 text-center text-[10px] tracking-wide text-muted-foreground uppercase"
                    >
                      {period.label ?? t(`periods.types.${period.type}`)} · {fmtTime(period.starts_at)}–
                      {fmtTime(period.ends_at)}
                    </td>
                  </tr>
                )
              }
              return (
                <tr key={`p-${period.period_number}`} className="border-b last:border-0">
                  <td className="px-2 py-1.5 align-top">
                    <p className="font-semibold tabular-nums">
                      {t("period", { n: period.period_number ?? "" })}
                    </p>
                    <p className="text-[10px] text-muted-foreground tabular-nums">
                      {fmtTime(period.starts_at)}–{fmtTime(period.ends_at)}
                    </p>
                  </td>
                  {days.map((day) => {
                    const key = `${day}-${period.period_number}`
                    const slots = cellMap.get(key) ?? []
                    const free = slots.length === 0
                    const targetable = armed !== null && free && editable && !busy
                    return (
                      <td key={key} className="h-14 min-w-28 p-1 align-top">
                        {free ? (
                          <button
                            type="button"
                            disabled={!targetable}
                            onClick={() => handleCellTap(day, period.period_number!)}
                            className={cn(
                              "flex h-full min-h-12 w-full items-center justify-center rounded-lg border border-transparent text-[10px] text-transparent transition-colors",
                              targetable &&
                                "border-dashed border-primary/50 bg-primary/5 text-primary hover:bg-primary/10",
                            )}
                            aria-label={`${t(`days.${day}`)} ${t("period", { n: period.period_number ?? "" })}`}
                          >
                            {t("grid.free")}
                          </button>
                        ) : (
                          slots.map((slot) => {
                            const assignment = assignmentById.get(slot.subject_assignment_id)
                            if (!assignment) return null
                            const isMoving = armed?.type === "move" && armed.slotId === slot.id
                            const chip = (
                              <div
                                className={cn(
                                  "flex h-full min-h-12 w-full flex-col justify-center rounded-lg border bg-accent/60 px-2 py-1 text-left transition-colors",
                                  canManage && editable && "cursor-pointer hover:bg-accent",
                                  slot.is_locked && "border-primary/40",
                                  isMoving && "border-primary bg-primary/10",
                                  (assignment.subject.weight ?? 3) >= 4 && "border-l-2 border-l-warning",
                                )}
                              >
                                <span className="flex items-center gap-1 truncate font-semibold">
                                  {slot.is_locked && <Lock className="size-2.5 shrink-0 text-primary" />}
                                  {assignment.subject.name}
                                </span>
                                <span className="truncate text-[10px] text-muted-foreground">
                                  {view === "section"
                                    ? (assignment.teacher_name ?? "—")
                                    : (sectionLabels.get(assignment.section_id) ?? "")}
                                  {slot.room_id &&
                                    ` · ${grid.rooms.find((r) => r.id === slot.room_id)?.name ?? ""}`}
                                </span>
                              </div>
                            )
                            if (!canManage || !editable) {
                              return <div key={slot.id}>{chip}</div>
                            }
                            return (
                              <DropdownMenu key={slot.id}>
                                <DropdownMenuTrigger asChild>
                                  <button type="button" className="w-full">
                                    {chip}
                                  </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" className="w-44">
                                  <DropdownMenuLabel className="text-xs">
                                    {assignment.subject.name}
                                  </DropdownMenuLabel>
                                  <DropdownMenuItem
                                    onClick={() => setArmed({ type: "move", slotId: slot.id })}
                                  >
                                    <MoveRight className="size-4" />
                                    {t("grid.moveHint").split(" — ")[0]}
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => toggleLock(slot)}>
                                    {slot.is_locked ? (
                                      <LockOpen className="size-4" />
                                    ) : (
                                      <Lock className="size-4" />
                                    )}
                                    {slot.is_locked ? t("grid.unlock") : t("grid.lock")}
                                  </DropdownMenuItem>
                                  {grid.rooms.length > 0 && (
                                    <DropdownMenuSub>
                                      <DropdownMenuSubTrigger>
                                        {t("grid.room")}
                                      </DropdownMenuSubTrigger>
                                      <DropdownMenuSubContent>
                                        <DropdownMenuItem onClick={() => setRoom(slot, null)}>
                                          {t("grid.noRoom")}
                                        </DropdownMenuItem>
                                        {grid.rooms.map((room) => (
                                          <DropdownMenuItem
                                            key={room.id}
                                            onClick={() => setRoom(slot, room.id)}
                                          >
                                            {room.name}
                                          </DropdownMenuItem>
                                        ))}
                                      </DropdownMenuSubContent>
                                    </DropdownMenuSub>
                                  )}
                                  <DropdownMenuSeparator />
                                  <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() =>
                                      confirmDelete(
                                        () => removeSlot(slot),
                                        tc("confirmDelete.named", { name: assignment.subject.name }),
                                      )
                                    }
                                  >
                                    <Trash2 className="size-4" />
                                    {t("grid.remove")}
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            )
                          })
                        )}
                      </td>
                    )
                  })}
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
      {confirmDialog}
    </div>
  )
}
