"use client"

import { ShieldX, Snowflake } from "lucide-react"
import dynamic from "next/dynamic"
import { useParams } from "next/navigation"
import { useEffect, useState } from "react"

import { Logo } from "@/components/ui/logo"
import { PublicDocumentActions } from "@/components/ui/public-document-actions"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { fmtDate } from "@/lib/dates"
import type {
  TermRoster,
  TermRosterMeta,
  YearRoster,
  YearRosterMeta,
} from "@/lib/types"

// The grids are heavy; keep the public shell light and load per scope.
const RosterMatrix = dynamic(
  () => import("@/components/grading/roster-matrix").then((m) => m.RosterMatrix),
  { ssr: false, loading: () => <Skeleton className="h-96 rounded-2xl" /> },
)
const YearlyRoster = dynamic(
  () => import("@/components/grading/yearly-roster").then((m) => m.YearlyRoster),
  { ssr: false, loading: () => <Skeleton className="h-96 rounded-2xl" /> },
)

interface PublicRoster {
  scope: "term" | "year"
  period_label: string
  scope_label: string | null
  show_section: boolean
  school_name: string | null
  branch_name: string | null
  data: TermRoster & YearRoster
  meta: TermRosterMeta & YearRosterMeta
  download_url: string | null
  /** Inline URL — Print opens the PDF in the viewer, never a download. */
  view_url: string | null
  issued_on: string | null
}

/**
 * PUBLIC roster page — what the QR on every official roster sheet opens.
 * No login: possession of the paper (its token) is possession of the record,
 * exactly like public transcripts. Always renders the AUTHORITATIVE live data;
 * revoking the document kills this page.
 */
export default function PublicRosterPage() {
  const { t } = useTranslation("grading")
  const params = useParams<{ token: string }>()

  const [payload, setPayload] = useState<PublicRoster | null>(null)
  const [failed, setFailed] = useState<"missing" | "revoked" | null>(null)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: PublicRoster }>(`/public/rosters/${params.token}`, {
      anonymous: true,
    })
      .then((res) => !cancelled && setPayload(res.data))
      .catch((error) => {
        if (cancelled) return
        setFailed(error instanceof ApiError && error.status === 410 ? "revoked" : "missing")
      })
    return () => {
      cancelled = true
    }
  }, [params.token])

  const frozenAt = payload?.meta?.computed_at
    ? fmtDate(payload.meta.computed_at)
    : null

  return (
    <div className="bg-muted/30 min-h-svh px-4 py-8 md:py-12 print:bg-white print:p-0">
      <div className="mx-auto max-w-6xl space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
          <Logo />
          {payload?.download_url && (
            <PublicDocumentActions
              downloadUrl={payload.download_url}
              viewUrl={payload.view_url}
            />
          )}
        </div>

        {payload === null ? (
          failed !== null ? (
            <div className="bg-card rounded-2xl border border-dashed px-6 py-16 text-center">
              {failed === "revoked" && (
                <ShieldX className="text-destructive mx-auto mb-3 size-8" strokeWidth={1.75} />
              )}
              <p className="text-muted-foreground text-sm">
                {failed === "revoked" ? t("rosters.publicRevoked") : t("rosters.publicNotFound")}
              </p>
            </div>
          ) : (
            <Skeleton className="h-[36rem] rounded-2xl" />
          )
        ) : (
          <div className="space-y-3">
            <div>
              <h1 className="text-lg font-semibold">
                {payload.scope === "year"
                  ? t("rosters.yearlyTab")
                  : t("rosters.semesterTab")}{" "}
                · {payload.scope_label ?? ""}
              </h1>
              <p className="text-muted-foreground text-sm">
                {[payload.school_name, payload.branch_name, payload.period_label]
                  .filter(Boolean)
                  .join(" · ")}
              </p>
            </div>
            {frozenAt && (
              <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                <Snowflake className="size-3.5" />
                {t("rosters.frozenAt", { date: frozenAt })}
              </p>
            )}
            {payload.scope === "year" ? (
              <YearlyRoster
                columns={payload.data.columns}
                students={payload.data.students}
                terms={payload.meta.terms}
                hasSemesterGroups={payload.meta.has_semester_groups}
                showSection={payload.show_section}
              />
            ) : (
              <RosterMatrix
                columns={payload.data.columns}
                rows={payload.data.rows}
                showSection={payload.show_section}
              />
            )}
          </div>
        )}
      </div>
    </div>
  )
}
