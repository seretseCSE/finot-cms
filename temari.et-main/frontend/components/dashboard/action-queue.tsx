"use client"

import {
  ArrowLeftRight,
  BadgePercent,
  CheckCircle2,
  ChevronRight,
  ClipboardList,
  ListChecks,
  Receipt,
  UserPlus,
  Wallet,
  type LucideIcon,
} from "lucide-react"
import Link from "next/link"

import { Skeleton } from "@/components/ui/skeleton"
import { useTranslation } from "@/lib/i18n"
import type { DashboardQueueItem, DashboardQueueKey } from "@/lib/types"
import { cn } from "@/lib/utils"

/** Where each pile lives + how loud it should be. */
const QUEUE_META: Record<
  DashboardQueueKey,
  { href: string; icon: LucideIcon; tone: "warning" | "info" }
> = {
  pending_enrollments: { href: "/students", icon: UserPlus, tone: "warning" },
  payment_verifications: { href: "/invoices", icon: Receipt, tone: "warning" },
  expenses_pending: { href: "/finance", icon: Wallet, tone: "info" },
  leave_pending: { href: "/hr/leave", icon: ClipboardList, tone: "info" },
  transfers_incoming: {
    href: "/transfers",
    icon: ArrowLeftRight,
    tone: "warning",
  },
  marklists_submitted: { href: "/marklists", icon: ListChecks, tone: "info" },
  concessions_pending: {
    href: "/concessions",
    icon: BadgePercent,
    tone: "info",
  },
}

/**
 * "Needs your attention" — the piles waiting on THIS person's signature:
 * enrollments to activate, payments to verify, leave and expenses to decide,
 * marklists to approve. Only non-empty piles render; a clear desk celebrates
 * instead of showing zeros. This is the block that turns the dashboard from
 * a poster into a to-do list.
 */
export function ActionQueue({
  queue,
}: {
  queue: DashboardQueueItem[] | null | undefined
}) {
  const { t } = useTranslation("common")

  // Absent block = caller decides nothing here; hide entirely.
  if (queue === undefined) return null

  if (queue === null) {
    return <Skeleton className="h-24 rounded-2xl" />
  }

  const open = queue.filter((item) => item.count > 0)

  return (
    <section>
      <h2 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {t("dashboard.needsAttention")}
      </h2>

      {open.length === 0 ? (
        <div className="flex items-center gap-3.5 rounded-2xl border border-success/25 bg-success/5 p-4">
          <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
            <CheckCircle2 className="size-[18px]" strokeWidth={1.75} />
          </span>
          <span>
            <span className="block text-sm font-medium">
              {t("dashboard.allCaughtUp")}
            </span>
            <span className="block text-xs text-muted-foreground">
              {t("dashboard.allCaughtUpDesc")}
            </span>
          </span>
        </div>
      ) : (
        <div className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
          {open.map((item) => {
            const meta = QUEUE_META[item.key]
            const Icon = meta.icon
            return (
              <Link
                key={item.key}
                href={meta.href}
                className="group pressable flex items-center gap-3.5 rounded-2xl border bg-card p-3.5 shadow-xs transition-all hover:border-primary/30 hover:shadow-sm"
              >
                <span
                  className={cn(
                    "flex size-10 shrink-0 items-center justify-center rounded-xl",
                    meta.tone === "warning"
                      ? "bg-warning/10 text-warning"
                      : "bg-info/10 text-info"
                  )}
                >
                  <Icon className="size-[18px]" strokeWidth={1.75} />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-medium">
                    {t(`dashboard.queue.${item.key}`)}
                  </span>
                  <span className="block truncate text-xs text-muted-foreground">
                    {t(`dashboard.queueDesc.${item.key}`)}
                  </span>
                </span>
                <span className="flex shrink-0 items-center gap-1">
                  <span
                    className={cn(
                      "rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums",
                      meta.tone === "warning"
                        ? "bg-warning/15 text-warning"
                        : "bg-info/15 text-info"
                    )}
                  >
                    {item.count}
                  </span>
                  <ChevronRight className="size-4 text-muted-foreground/60 transition-colors group-hover:text-primary" />
                </span>
              </Link>
            )
          })}
        </div>
      )}
    </section>
  )
}
