"use client"

import { Plus, Save, Sparkles, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { TimePicker } from "@/components/ui/time-picker"
import { ApiError, apiFetch } from "@/lib/api"
import { fmtTime } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import { addMinutes, editBlock, removeBlock } from "@/lib/period-schedule"
import type { TermPeriod } from "@/lib/types"
import { useCalendar } from "@/lib/use-calendar"
import { cn } from "@/lib/utils"

const TYPES: TermPeriod["type"][] = ["class", "break", "lunch", "flag"]

/**
 * The period schedule editor: the day's time blocks in order. Class rows get
 * their period number automatically from their position — the timetable
 * re-times itself when times change here.
 */
export function PeriodScheduleTab({
  termId,
  canManage,
  onSaved,
}: {
  termId: number
  canManage: boolean
  /** Fires after a successful save — the setup wizard unlocks its next step. */
  onSaved?: () => void
}) {
  const { t } = useTranslation("timetable")
  const { t: tc } = useTranslation("common")
  const { clock } = useCalendar()

  const [rows, setRows] = useState<TermPeriod[] | null>(null)
  const [working, setWorking] = useState(false)
  const [dirty, setDirty] = useState(false)

  useEffect(() => {
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on param change
    setRows(null)
    setDirty(false)
    apiFetch<{ data: TermPeriod[] }>(`/terms/${termId}/periods`)
      .then((res) => !cancelled && setRows(res.data))
      .catch(() => !cancelled && setRows([]))
    return () => {
      cancelled = true
    }
  }, [termId])

  /** Edits one block; later blocks slide so the day stays continuous. */
  function patch(index: number, changes: Partial<TermPeriod>) {
    setRows((prev) => editBlock(prev ?? [], index, changes))
    setDirty(true)
  }

  function addRow() {
    setRows((prev) => {
      const list = prev ?? []
      const start = list[list.length - 1]?.ends_at ?? "08:00"
      return [
        ...list,
        {
          sequence: list.length + 1,
          type: "class",
          period_number: null,
          label: null,
          starts_at: start,
          ends_at: addMinutes(start, 45),
        },
      ]
    })
    setDirty(true)
  }

  function removeRow(index: number) {
    setRows((prev) => removeBlock(prev ?? [], index))
    setDirty(true)
  }

  // Ethiopian-clock schools sometimes enter "2:00" meaning 2 ጠዋት (8:00 AM)
  // while the picker was still in standard mode — the whole day then sits in
  // the small hours. Detect it and offer a one-tap +6h daytime shift.
  const looksLikeNightSchedule =
    clock === "ethiopian" &&
    (rows?.length ?? 0) > 0 &&
    (rows ?? []).every((row) => row.starts_at < "06:00")

  function shiftToDaytime() {
    setRows((prev) =>
      (prev ?? []).map((row) => ({
        ...row,
        starts_at: addMinutes(row.starts_at, 360),
        ends_at: addMinutes(row.ends_at, 360),
      })),
    )
    setDirty(true)
  }

  async function generateDefaults() {
    setWorking(true)
    try {
      const res = await apiFetch<{ data: TermPeriod[] }>(`/terms/${termId}/periods/defaults`, {
        method: "POST",
      })
      setRows(res.data)
      setDirty(false)
      toast.success(t("periods.saved"))
      onSaved?.()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  async function save() {
    if (!rows) return
    setWorking(true)
    try {
      const res = await apiFetch<{ data: TermPeriod[] }>(`/terms/${termId}/periods`, {
        method: "PUT",
        body: {
          periods: rows.map((row) => ({
            type: row.type,
            label: row.label || null,
            starts_at: row.starts_at,
            ends_at: row.ends_at,
          })),
        },
      })
      setRows(res.data)
      setDirty(false)
      toast.success(t("periods.saved"))
      onSaved?.()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  if (rows === null) {
    return <Skeleton className="h-64 rounded-2xl" />
  }

  if (rows.length === 0) {
    return (
      <div className="rounded-2xl border bg-card shadow-xs">
        <EmptyState
          title={t("periods.empty")}
          description={t("periods.emptyDesc")}
          action={
            canManage ? (
              <Button onClick={generateDefaults} loading={working}>
                <Sparkles className="size-4" />
                {t("periods.generate")}
              </Button>
            ) : undefined
          }
        />
      </div>
    )
  }

  let classCount = 0

  return (
    <div className="space-y-4">
      <p className="text-xs text-muted-foreground">{t("periods.hint")}</p>
      {canManage && looksLikeNightSchedule && (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-warning/40 bg-warning/10 px-4 py-3">
          <p className="text-sm">{t("periods.nightWarning")}</p>
          <Button type="button" size="sm" variant="outline" onClick={shiftToDaytime}>
            {t("periods.shiftToDaytime")}
          </Button>
        </div>
      )}
      <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
        <div className="divide-y">
          {rows.map((row, index) => {
            if (row.type === "class") classCount++
            const number = row.type === "class" ? classCount : null
            return (
              <div
                key={index}
                className={cn(
                  "flex flex-wrap items-center gap-2 px-3 py-2.5 md:flex-nowrap",
                  row.type !== "class" && "bg-muted/30",
                )}
              >
                <span
                  className={cn(
                    "flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-semibold tabular-nums",
                    row.type === "class"
                      ? "bg-primary/10 text-primary"
                      : "bg-muted text-muted-foreground",
                  )}
                >
                  {number ?? "·"}
                </span>
                {canManage ? (
                  <>
                    <Select
                      value={row.type}
                      onValueChange={(v) => {
                        const type = v as TermPeriod["type"]
                        patch(index, {
                          type,
                          ends_at: addMinutes(row.starts_at, type === "class" ? 45 : type === "lunch" ? 45 : 15),
                        })
                      }}
                    >
                      <SelectTrigger className="h-9 w-32 rounded-xl bg-muted/30 text-xs" aria-label={t("rooms.type")}>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {TYPES.map((type) => (
                          <SelectItem key={type} value={type}>
                            {t(`periods.types.${type}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <TimePicker
                      value={row.starts_at}
                      onChange={(value) => patch(index, { starts_at: value })}
                      clearable={false}
                      className="h-10 w-40 text-sm"
                      aria-label={t("periods.starts")}
                    />
                    <span className="text-xs text-muted-foreground">–</span>
                    <TimePicker
                      value={row.ends_at}
                      onChange={(value) => patch(index, { ends_at: value })}
                      clearable={false}
                      className="h-10 w-40 text-sm"
                      aria-label={t("periods.ends")}
                    />
                    {row.type !== "class" && (
                      <Input
                        value={row.label ?? ""}
                        onChange={(e) => patch(index, { label: e.target.value })}
                        placeholder={t("periods.label")}
                        className="h-9 w-32 flex-1 rounded-xl bg-muted/30 text-xs md:flex-none"
                      />
                    )}
                    <Button
                      variant="ghost"
                      size="icon"
                      className="ml-auto size-8 text-muted-foreground hover:text-destructive"
                      onClick={() => removeRow(index)}
                      aria-label={tc("actions.delete")}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </>
                ) : (
                  <div className="flex flex-1 items-center justify-between gap-2 text-sm">
                    <span className="font-medium">
                      {row.type === "class"
                        ? t("period", { n: number ?? "" })
                        : (row.label ?? t(`periods.types.${row.type}`))}
                    </span>
                    <span className="text-muted-foreground tabular-nums">
                      {fmtTime(row.starts_at)} – {fmtTime(row.ends_at)}
                    </span>
                  </div>
                )}
              </div>
            )
          })}
        </div>
      </div>
      {canManage && (
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" onClick={addRow}>
            <Plus className="size-4" />
            {t("periods.addRow")}
          </Button>
          <Button
            onClick={save}
            loading={working} disabled={!dirty}
            className="bg-success text-success-foreground hover:bg-success/85"
          >
            <Save className="size-4" />
            {t("periods.save")}
          </Button>
        </div>
      )}
    </div>
  )
}
