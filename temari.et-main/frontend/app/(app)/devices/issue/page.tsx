"use client"

import Link from "next/link"
import { ArrowLeft, Nfc, ScanLine } from "lucide-react"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { ScopeSelects, type ScopeValue } from "@/components/devices/scope-selects"
import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import { PageHeader } from "@/components/ui/page-header"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type { CardCandidate } from "@/lib/types"
import { cn } from "@/lib/utils"
import { addisToday } from "@/lib/dates"

interface WorkRow extends CardCandidate {
  uid: string
  issued_on: string
  note: string
}

function todayAddis(): string {
  return addisToday()
}

/**
 * Bulk card issuance (Temari.et staff): pick school → branch → who, get the
 * worklist of everyone WITHOUT an active card, then tap chips down the list.
 * Scan mode keeps one row hot: a USB reader types the UID and focus advances
 * to the next empty row — issuing a whole section is call name, tap, tap, tap.
 */
export default function BulkIssuePage() {
  const { t } = useTranslation("devices")
  const { t: tc } = useTranslation("common")
  const permissions = useEffectivePermissions()
  const canManage = permissions.includes("cards.manage")

  const [scope, setScope] = useState<ScopeValue>({ schoolId: null, branchId: null })
  const [type, setType] = useState<"student" | "employee">("student")
  const [rows, setRows] = useState<WorkRow[] | null>(null)
  const [grade, setGrade] = useState<string>("")
  const [section, setSection] = useState<string>("")
  const [scanMode, setScanMode] = useState(true)
  const [saving, setSaving] = useState(false)
  const inputRefs = useRef<Map<number, HTMLInputElement>>(new Map())

  useEffect(() => {
    if (scope.branchId == null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on scope change
    setRows(null)
    setGrade("")
    setSection("")
    apiFetch<{ data: CardCandidate[] }>(
      `/cards/candidates?branch_id=${scope.branchId}&type=${type}`
    )
      .then((res) => {
        if (cancelled) return
        setRows(
          res.data.map((c) => ({ ...c, uid: "", issued_on: todayAddis(), note: "" }))
        )
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    return () => {
      cancelled = true
    }
  }, [scope.branchId, type, tc])

  // Grade → section cascade, derived from the loaded worklist itself — no
  // extra requests, filters are instant. Options keep the grades' own order.
  const gradeOptions = useMemo(() => {
    const seen = new Map<string, number>()
    for (const r of rows ?? []) {
      if (r.grade) seen.set(r.grade, r.grade_sort ?? 0)
    }
    return [...seen.entries()].sort((a, b) => a[1] - b[1]).map(([name]) => name)
  }, [rows])

  const sectionOptions = useMemo(() => {
    if (!grade) return []
    const seen = new Set<string>()
    for (const r of rows ?? []) {
      if (r.grade === grade && r.section) seen.add(r.section)
    }
    return [...seen].sort()
  }, [rows, grade])

  const visible = useMemo(
    () =>
      (rows ?? []).filter(
        (r) =>
          (grade === "" || r.grade === grade) && (section === "" || r.section === section)
      ),
    [rows, grade, section]
  )

  const ready = useMemo(() => (rows ?? []).filter((r) => r.uid.trim() !== ""), [rows])
  const firstEmptyIndex = useMemo(
    () => visible.findIndex((r) => r.uid.trim() === ""),
    [visible]
  )

  function patchRow(id: number, patch: Partial<WorkRow>) {
    setRows((prev) => (prev ?? []).map((r) => (r.id === id ? { ...r, ...patch } : r)))
  }

  /** Scan mode: a filled UID hops focus to the next empty VISIBLE row. */
  function advanceFrom(index: number) {
    if (!scanMode) return
    const next = visible.findIndex((r, i) => i > index && r.uid.trim() === "")
    const target = next !== -1 ? next : visible.findIndex((r) => r.uid.trim() === "")
    if (target !== -1) inputRefs.current.get(visible[target].id)?.focus()
  }

  async function submit() {
    if (scope.branchId == null || ready.length === 0) return
    setSaving(true)
    try {
      const res = await apiFetch<{ meta: { issued: number } }>("/cards/bulk", {
        method: "POST",
        body: {
          branch_id: scope.branchId,
          holder_type: type,
          rows: ready.map((r) => ({
            holder_id: r.id,
            card_uid: r.uid.trim(),
            issued_on: r.issued_on || undefined,
            note: r.note.trim() || undefined,
          })),
        },
      })
      toast.success(t("bulk.issued", { count: res.meta.issued }))
      // Reload the worklist — issued people drop off it.
      setScope({ ...scope })
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  if (!canManage) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("bulk.title")} description={t("bulk.subtitle")} />
        <div className="page-gutter">
          <EmptyState icon={ScanLine} title={tc("errors.forbidden")} />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6 pb-28">
      <PageHeader title={t("bulk.title")} description={t("bulk.subtitle")}>
        <Button variant="outline" size="sm" asChild>
          <Link href="/devices">
            <ArrowLeft className="size-4" /> {tc("actions.back")}
          </Link>
        </Button>
      </PageHeader>

      <div className="page-gutter space-y-4">
        {/* Step 1: where + who */}
        <div className="rounded-2xl border bg-card p-4 shadow-xs">
          <div className="flex flex-wrap items-end gap-3">
            <ScopeSelects value={scope} onChange={setScope} required />
            <div className="space-y-1.5">
              <div className="grid grid-cols-2 gap-1 rounded-full border p-1">
                {(["student", "employee"] as const).map((option) => (
                  <button
                    key={option}
                    type="button"
                    onClick={() => setType(option)}
                    className={cn(
                      "pressable min-h-8 rounded-full px-4 text-xs font-medium transition-colors",
                      type === option
                        ? "bg-primary text-primary-foreground"
                        : "text-muted-foreground hover:bg-muted",
                    )}
                    aria-pressed={type === option}
                  >
                    {t(`bulk.types.${option}`)}
                  </button>
                ))}
              </div>
            </div>
            {type === "student" && rows !== null && gradeOptions.length > 0 && (
              <Select
                value={grade || "all"}
                onValueChange={(v) => {
                  setGrade(v === "all" ? "" : v)
                  setSection("")
                }}
              >
                <SelectTrigger className="h-10 w-36">
                  <SelectValue placeholder={t("bulk.allGrades")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t("bulk.allGrades")}</SelectItem>
                  {gradeOptions.map((g) => (
                    <SelectItem key={g} value={g}>
                      {g}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
            {type === "student" && grade !== "" && (
              <Select
                value={section || "all"}
                onValueChange={(v) => setSection(v === "all" ? "" : v)}
              >
                <SelectTrigger className="h-10 w-32">
                  <SelectValue placeholder={t("bulk.allSections")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t("bulk.allSections")}</SelectItem>
                  {sectionOptions.map((s) => (
                    <SelectItem key={s} value={s}>
                      {s}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
            {rows !== null && (
              <label className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                <Nfc className="size-4" />
                {t("bulk.scanMode")}
                <Switch checked={scanMode} onCheckedChange={setScanMode} />
              </label>
            )}
          </div>
        </div>

        {/* Step 2: the worklist */}
        {scope.branchId == null ? (
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("bulk.pickBranch")}
          </div>
        ) : rows === null ? (
          <Skeleton className="h-72 w-full rounded-2xl" />
        ) : rows.length === 0 ? (
          <div className="rounded-2xl border bg-card shadow-xs">
            <EmptyState icon={ScanLine} title={t("bulk.allCarded")} compact />
          </div>
        ) : (
          <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
            {/* Desktop header — students get the class as its own column */}
            <div
              className={cn(
                "hidden gap-3 border-b bg-muted/30 px-4 py-2.5 text-xs font-medium text-muted-foreground md:grid",
                type === "student"
                  ? "grid-cols-[1.6fr_0.9fr_1.5fr_10rem_1.5fr]"
                  : "grid-cols-[2fr_1.5fr_10rem_1.5fr]",
              )}
            >
              <span>{t(`bulk.types.${type}`)}</span>
              {type === "student" && <span>{t("bulk.classColumn")}</span>}
              <span>{t("cards.fields.uid")}</span>
              <span>{t("cards.fields.issuedOn")}</span>
              <span>{t("cards.fields.note")}</span>
            </div>
            {visible.length === 0 ? (
              <div className="px-6 py-10 text-center text-sm text-muted-foreground">
                {t("bulk.emptyFilter")}
              </div>
            ) : null}
            <ul className="divide-y">
              {visible.map((row, index) => {
                const isHot = scanMode && index === firstEmptyIndex
                const done = row.uid.trim() !== ""
                return (
                  <li
                    key={row.id}
                    className={cn(
                      "grid grid-cols-1 gap-2 px-4 py-3 transition-colors md:items-center md:gap-3 md:py-2",
                      type === "student"
                        ? "md:grid-cols-[1.6fr_0.9fr_1.5fr_10rem_1.5fr]"
                        : "md:grid-cols-[2fr_1.5fr_10rem_1.5fr]",
                      isHot && "bg-primary/5",
                      done && "bg-success/[0.04]",
                    )}
                  >
                    <div className="flex items-center gap-2 leading-tight">
                      <span
                        className={cn(
                          "size-1.5 shrink-0 rounded-full",
                          done ? "bg-success" : isHot ? "animate-pulse bg-primary" : "bg-muted-foreground/30",
                        )}
                      />
                      <div>
                        <span className="block text-sm font-medium">{row.name}</span>
                        {type === "employee" && (
                          <span className="block text-xs text-muted-foreground">
                            {row.detail || "—"}
                          </span>
                        )}
                      </div>
                    </div>
                    {type === "student" && (
                      <span className="text-xs text-muted-foreground md:text-sm">
                        {row.grade ?? "—"}
                        {row.section ? ` — ${row.section}` : ""}
                      </span>
                    )}
                    <div className="relative">
                      <Nfc className="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                      <Input
                        ref={(el) => {
                          if (el) inputRefs.current.set(row.id, el)
                          else inputRefs.current.delete(row.id)
                        }}
                        value={row.uid}
                        onChange={(e) => patchRow(row.id, { uid: e.target.value.toUpperCase() })}
                        onKeyDown={(e) => e.key === "Enter" && advanceFrom(index)}
                        placeholder={isHot ? t("bulk.tapNow") : t("cards.fields.uidPlaceholder")}
                        className={cn("h-9 pl-9 font-mono text-xs uppercase", isHot && "border-primary/50")}
                        maxLength={32}
                      />
                    </div>
                    <DatePicker
                      value={row.issued_on}
                      onChange={(value) => patchRow(row.id, { issued_on: value })}
                      clearable={false}
                      className="h-9 text-xs"
                    />
                    <Input
                      value={row.note}
                      onChange={(e) => patchRow(row.id, { note: e.target.value })}
                      placeholder={t("cards.fields.notePlaceholder")}
                      className="h-9 text-xs"
                    />
                  </li>
                )
              })}
            </ul>
          </div>
        )}
      </div>

      {/* Sticky submit bar — the standard bottom action */}
      {ready.length > 0 && (
        <div className="fixed inset-x-0 bottom-20 z-30 flex justify-center px-4 md:bottom-6">
          <div className="flex items-center gap-3 rounded-full border bg-background/95 p-1.5 pl-4 shadow-lg backdrop-blur-xl">
            <span className="text-xs font-medium text-muted-foreground tabular-nums">
              {t("bulk.readyCount", { ready: ready.length, total: rows?.length ?? 0 })}
            </span>
            <Button size="sm" onClick={submit} loading={saving}>
              {t("bulk.issueAll", { count: ready.length })}
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
