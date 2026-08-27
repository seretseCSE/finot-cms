"use client"

import { ArrowRight, GraduationCap } from "lucide-react"
import Link from "next/link"
import { useEffect, useState } from "react"

import { PersonAvatar } from "@/components/ui/person-avatar"
import { Badge } from "@/components/ui/badge"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn, formatETB } from "@/lib/utils"

interface EngagementRow {
  id: number
  status: string
  payer_name: string | null
  learner_name: string | null
  subjects: { id: number; name: string }[]
  sessions_per_week: number
  hours_per_session: string
  hourly_rate: string
  mode: string
}

const STATUS_TONE: Record<string, string> = {
  active: "border-success/30 bg-success/10 text-success",
  paused: "border-warning/30 bg-warning/10 text-warning",
  ended: "border-border bg-muted text-muted-foreground",
}

export default function TutorEngagementsPage() {
  const { t } = useTranslation("tutoring")
  const [loading, setLoading] = useState(true)
  const [rows, setRows] = useState<EngagementRow[]>([])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      try {
        const res = await apiFetch<{ data: EngagementRow[] }>("/tutoring/engagements?as=tutor")
        if (!cancelled) setRows(res.data)
      } catch {
        // empty state
      } finally {
        if (!cancelled) setLoading(false)
      }
    })()
    return () => {
      cancelled = true
    }
  }, [])

  return (
    <div className="space-y-6">
      <PageHeader title={t("workspace.engagements")} description={t("workspace.engagementsDesc")} backHref="/tutoring" />

      <div className="page-gutter space-y-3">
        {loading ? (
          <>
            <Skeleton className="h-24 rounded-2xl" />
            <Skeleton className="h-24 rounded-2xl" />
          </>
        ) : rows.length === 0 ? (
          <EmptyState
            icon={GraduationCap}
            title={t("workspace.emptyEngagements")}
            description={t("workspace.emptyEngagementsDesc")}
          />
        ) : (
          rows.map((row) => (
            <Link
              key={row.id}
              href={`/tutoring/engagements/${row.id}`}
              className="pressable flex items-center gap-3 rounded-2xl border bg-card p-4 shadow-xs transition-colors hover:bg-accent/50"
            >
              <PersonAvatar className="size-11" name={row.learner_name ?? "?"} />
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <p className="truncate font-medium">{row.learner_name}</p>
                  <Badge variant="outline" className={cn("shrink-0", STATUS_TONE[row.status])}>
                    {t(`status.${row.status}`)}
                  </Badge>
                </div>
                <p className="truncate text-sm text-muted-foreground">
                  {row.subjects.map((s) => s.name).join(", ")}
                </p>
                <p className="text-xs text-muted-foreground">
                  {t("workspace.monthlyPlan", { sessions: row.sessions_per_week, hours: row.hours_per_session })} ·{" "}
                  {formatETB(row.hourly_rate)}/hr · {t(`mode.${row.mode}`)}
                </p>
              </div>
              <ArrowRight className="size-4 shrink-0 text-muted-foreground" strokeWidth={2} />
            </Link>
          ))
        )}
      </div>
    </div>
  )
}
