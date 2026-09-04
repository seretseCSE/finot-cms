"use client"

import { CalendarOff, Plus, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
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

interface AvailabilityWindow {
  id?: number
  day_of_week: number
  from_period: number | null
  to_period: number | null
  note: string | null
}

/**
 * The teacher's unavailable windows — the timetable solver's hard
 * constraints. Fetches lazily (only when this tab opens) and is editable
 * with timetable.manage.
 */
export function EmployeeAvailabilityTab({
  employeeId,
  canManage,
}: {
  employeeId: number
  canManage: boolean
}) {
  const { t: tt } = useTranslation("timetable")
  const { t: tc } = useTranslation("common")
  const [windows, setWindows] = useState<AvailabilityWindow[] | null>(null)
  const [dirty, setDirty] = useState(false)
  const [working, setWorking] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: AvailabilityWindow[] }>(`/employees/${employeeId}/availability`)
      .then((res) => !cancelled && setWindows(res.data))
      .catch(() => !cancelled && setWindows([]))
    return () => {
      cancelled = true
    }
  }, [employeeId])

  function patch(index: number, changes: Partial<AvailabilityWindow>) {
    setWindows((prev) => (prev ?? []).map((w, i) => (i === index ? { ...w, ...changes } : w)))
    setDirty(true)
  }

  async function save() {
    if (!windows) return
    setWorking(true)
    try {
      const res = await apiFetch<{ data: AvailabilityWindow[] }>(
        `/employees/${employeeId}/availability`,
        {
          method: "PUT",
          body: {
            windows: windows.map((w) => ({
              day_of_week: w.day_of_week,
              from_period: w.from_period,
              to_period: w.to_period,
              note: w.note,
            })),
          },
        },
      )
      setWindows(res.data)
      setDirty(false)
      toast.success(tt("availability.saved"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  if (windows === null) return <Skeleton className="h-40 rounded-2xl" />

  return (
    <section className="rounded-2xl border bg-card p-4 shadow-xs">
      <div className="mb-3 flex items-start justify-between gap-3">
        <div>
          <h3 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            <CalendarOff className="size-3.5" />
            {tt("availability.title")}
          </h3>
          <p className="mt-1 text-xs text-muted-foreground">{tt("availability.hint")}</p>
        </div>
        {canManage && (
          <div className="flex gap-1.5">
            <Button
              size="sm"
              variant="outline"
              onClick={() => {
                setWindows((prev) => [
                  ...(prev ?? []),
                  { day_of_week: 1, from_period: null, to_period: null, note: null },
                ])
                setDirty(true)
              }}
            >
              <Plus className="size-4" />
              {tt("availability.add")}
            </Button>
            {dirty && (
              <Button size="sm" onClick={save} loading={working}>
                {tc("actions.save")}
              </Button>
            )}
          </div>
        )}
      </div>

      {windows.length === 0 ? (
        <p className="py-3 text-sm text-success">{tt("availability.empty")}</p>
      ) : (
        <ul className="space-y-2">
          {windows.map((w, index) => (
            <li
              key={index}
              className="flex flex-wrap items-center gap-2 rounded-xl border px-3 py-2"
            >
              {canManage ? (
                <>
                  <Select
                    value={String(w.day_of_week)}
                    onValueChange={(v) => patch(index, { day_of_week: Number(v) })}
                  >
                    <SelectTrigger
                      className="h-9 w-32 rounded-xl bg-muted/30 text-xs"
                      aria-label={tt("availability.title")}
                    >
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {[1, 2, 3, 4, 5, 6].map((day) => (
                        <SelectItem key={day} value={String(day)}>
                          {tt(`days.${day}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Select
                    value={
                      w.from_period === null
                        ? "day"
                        : `${w.from_period}-${w.to_period ?? w.from_period}`
                    }
                    onValueChange={(v) => {
                      if (v === "day") {
                        patch(index, { from_period: null, to_period: null })
                      } else {
                        const [from, to] = v.split("-").map(Number)
                        patch(index, { from_period: from, to_period: to })
                      }
                    }}
                  >
                    <SelectTrigger
                      className="h-9 w-40 rounded-xl bg-muted/30 text-xs"
                      aria-label={tt("availability.fromPeriod")}
                    >
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="day">{tt("availability.wholeDay")}</SelectItem>
                      <SelectItem value="1-4">{tt("availability.morning")}</SelectItem>
                      <SelectItem value="5-8">{tt("availability.afternoon")}</SelectItem>
                    </SelectContent>
                  </Select>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="ml-auto size-8 text-muted-foreground hover:text-destructive"
                    aria-label={tc("actions.delete")}
                    onClick={() => {
                      setWindows((prev) => (prev ?? []).filter((_, i) => i !== index))
                      setDirty(true)
                    }}
                  >
                    <Trash2 className="size-4" />
                  </Button>
                </>
              ) : (
                <span className="text-sm">
                  {tt(`days.${w.day_of_week}`)} —{" "}
                  {w.from_period === null
                    ? tt("availability.wholeDay")
                    : `${tt("period", { n: w.from_period })}–${tt("period", { n: w.to_period ?? w.from_period })}`}
                </span>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
