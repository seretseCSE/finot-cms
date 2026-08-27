"use client"

import { useMemo } from "react"

import { useTranslation } from "@/lib/i18n"
import type { StudentTimetable } from "@/lib/types"
import { fmtTime } from "@/lib/dates"

/** Shared read-only weekly grid (also used by /me surfaces). */
export function WeeklyGridReadOnly({ data }: { data: StudentTimetable }) {
  const { t } = useTranslation("timetable")

  const byCell = useMemo(() => {
    const map = new Map<string, StudentTimetable["slots"]>()
    for (const slot of data.slots) {
      const key = `${slot.day_of_week}-${slot.period_number}`
      map.set(key, [...(map.get(key) ?? []), slot])
    }
    return map
  }, [data])

  return (
    <div className="overflow-x-auto rounded-2xl border bg-card shadow-xs">
      <table className="w-full min-w-[560px] border-collapse text-xs">
        <thead>
          <tr className="border-b bg-muted/30">
            <th className="w-20 px-2 py-2 text-left font-medium text-muted-foreground">
              {t("periods.title")}
            </th>
            {data.days.map((day) => (
              <th key={day} className="px-2 py-2 text-left font-medium">
                {t(`daysShort.${day}`)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.periods.map((period) => {
            if (period.type !== "class") {
              return (
                <tr key={`sep-${period.sequence}`} className="border-b bg-muted/40">
                  <td
                    colSpan={data.days.length + 1}
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
                  <p className="font-semibold tabular-nums">{period.period_number}</p>
                  <p className="text-[10px] text-muted-foreground tabular-nums">
                    {fmtTime(period.starts_at)}–{fmtTime(period.ends_at)}
                  </p>
                </td>
                {data.days.map((day) => {
                  const slots = byCell.get(`${day}-${period.period_number}`) ?? []
                  return (
                    <td key={day} className="min-w-24 p-1 align-top">
                      {slots.map((slot, i) => (
                        <div
                          key={i}
                          className="flex min-h-11 flex-col justify-center rounded-lg bg-accent/60 px-2 py-1"
                        >
                          <span className="truncate font-semibold">{slot.subject}</span>
                          <span className="truncate text-[10px] text-muted-foreground">
                            {"section" in slot && (slot as { section?: string }).section
                              ? (slot as { section?: string }).section
                              : slot.teacher}
                            {slot.room ? ` · ${slot.room}` : ""}
                          </span>
                        </div>
                      ))}
                    </td>
                  )
                })}
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
