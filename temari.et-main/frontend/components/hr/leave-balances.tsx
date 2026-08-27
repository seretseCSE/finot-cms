"use client"

import { CalendarOff } from "lucide-react"

import { EmptyState } from "@/components/ui/empty-state"
import { Skeleton } from "@/components/ui/skeleton"
import { useTranslation } from "@/lib/i18n"
import type { EmployeeLeaveBalances } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The branch balance grid: one row per staff member, one column per active
 * leave type, each cell showing used / entitled for the Ethiopian leave year.
 */
export function LeaveBalancesGrid({
  balances,
  yearWindow,
}: {
  balances: EmployeeLeaveBalances[] | null
  yearWindow: { start: string; end: string } | null
}) {
  const { t } = useTranslation("hr")

  if (balances === null) {
    return <Skeleton className="h-64 w-full rounded-2xl" />
  }

  if (balances.length === 0) {
    return (
      <div className="rounded-2xl border bg-card shadow-xs">
        <EmptyState icon={CalendarOff} title={t("leave.balances.empty")} compact />
      </div>
    )
  }

  // Union of types across rows — gender-scoped types (maternity/paternity)
  // are absent from some employees' balance lists, so rows can't drive headers.
  const types = balances
    .flatMap((row) => row.balances)
    .filter(
      (b, index, all) => all.findIndex((x) => x.leave_type_id === b.leave_type_id) === index,
    )

  return (
    <div className="space-y-2">
      {yearWindow && (
        <p className="text-xs text-muted-foreground tabular-nums">
          {t("leave.balances.yearWindow", { start: yearWindow.start, end: yearWindow.end })}
        </p>
      )}
      <div className="overflow-x-auto rounded-2xl border bg-card shadow-xs">
        <table className="w-full min-w-[640px] text-sm">
          <thead>
            <tr className="border-b bg-muted/40 text-left">
              <th className="px-4 py-3 font-medium text-muted-foreground">
                {t("leave.balances.employee")}
              </th>
              {types.map((type) => (
                <th
                  key={type.leave_type_id}
                  className="px-3 py-3 text-center font-medium whitespace-nowrap text-muted-foreground"
                >
                  {type.leave_type_name}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y">
            {balances.map((row) => (
              <tr key={row.employee_id}>
                <td className="px-4 py-3 font-medium whitespace-nowrap">{row.employee_name}</td>
                {types.map((type) => {
                  const b = row.balances.find(
                    (x) => x.leave_type_id === type.leave_type_id,
                  )
                  if (!b) {
                    return (
                      <td
                        key={type.leave_type_id}
                        className="px-3 py-3 text-center text-muted-foreground"
                      >
                        —
                      </td>
                    )
                  }
                  return (
                    <td key={b.leave_type_id} className="px-3 py-3 text-center tabular-nums">
                      {b.entitled == null ? (
                        <span className="text-muted-foreground">
                          {b.taken > 0 ? b.taken : "—"}
                        </span>
                      ) : (
                        <span
                          className={cn(
                            (b.remaining ?? 0) <= 0
                              ? "text-destructive"
                              : b.taken > 0
                                ? "text-foreground"
                                : "text-muted-foreground",
                          )}
                          title={
                            b.pending > 0
                              ? t("leave.balances.pending", { days: b.pending })
                              : undefined
                          }
                        >
                          {b.taken}/{b.entitled}
                          {b.pending > 0 && <span className="text-warning"> *</span>}
                        </span>
                      )}
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
