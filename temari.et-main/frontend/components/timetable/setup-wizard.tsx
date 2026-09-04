"use client"

import {
  ArrowLeft,
  ArrowRight,
  CalendarClock,
  Check,
  Clock,
  DoorOpen,
  Loader2,
  Sparkles,
} from "lucide-react"
import Link from "next/link"
import { useState } from "react"
import { toast } from "sonner"

import { PeriodScheduleTab } from "@/components/timetable/period-schedule-tab"
import { RoomsTab } from "@/components/timetable/rooms-tab"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { TimetableVersion } from "@/lib/types"
import { cn } from "@/lib/utils"

const STEPS = [
  { key: "periods", icon: Clock },
  { key: "rooms", icon: DoorOpen },
  { key: "create", icon: CalendarClock },
] as const

/**
 * First-time guided setup for a term with no timetable versions yet: the
 * period schedule (required — a timetable cannot exist without it), shared
 * rooms (optional), then the first draft. Each step explains WHY it exists
 * in plain words, so a school setting up for the first time is never lost.
 */
export function SetupWizard({
  termId,
  termName,
  hasPeriods,
  hasLoads = true,
  onDone,
  branchId = null,
}: {
  termId: number
  termName: string
  /** From the versions index meta — pre-completes step 1 on revisit. */
  hasPeriods: boolean
  /** False = no subject assignment has periods/week yet — generation would be empty. */
  hasLoads?: boolean
  /** The first draft was created — the parent reloads and shows the grid. */
  onDone: (versionId: number) => void
  /** Working branch when run from the school-wide workspace (rooms step). */
  branchId?: number | null
}) {
  const { t } = useTranslation("timetable")
  const { t: tc } = useTranslation("common")

  const [periodsReady, setPeriodsReady] = useState(hasPeriods)
  // Resume where the school left off: periods done → straight to rooms.
  const [step, setStep] = useState(hasPeriods ? 1 : 0)
  const [name, setName] = useState(t("setup.create.defaultName", { term: termName }))
  const [saturday, setSaturday] = useState(false)
  const [autoGenerate, setAutoGenerate] = useState(true)
  const [working, setWorking] = useState(false)

  const continueBlocked = step === 0 && !periodsReady

  async function createDraft() {
    if (!name.trim()) return
    setWorking(true)
    try {
      const res = await apiFetch<{ data: TimetableVersion }>(
        `/terms/${termId}/timetable-versions`,
        {
          method: "POST",
          body: {
            name: name.trim(),
            days: saturday ? [1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5],
          },
        },
      )
      const versionId = res.data.id
      if (autoGenerate) {
        try {
          await apiFetch(`/timetable-versions/${versionId}/generate`, { method: "POST", body: {} })
          toast.success(t("setup.create.generating"))
        } catch {
          // The draft exists — they can still generate by hand from the grid.
          toast.success(t("setup.create.created"))
        }
      } else {
        toast.success(t("setup.create.created"))
      }
      onDone(versionId)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      setWorking(false)
    }
  }

  return (
    <div className="page-gutter">
      <div className="mx-auto flex min-h-[calc(100svh-21rem)] max-w-3xl flex-col gap-5 pb-6 md:min-h-[calc(100svh-16rem)]">
        {/* Welcome header */}
        <div className="flex items-start gap-3 rounded-2xl border bg-card p-4 shadow-xs sm:items-center">
          <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <Sparkles className="size-5" strokeWidth={1.75} />
          </div>
          <div className="min-w-0">
            <h2 className="text-sm font-semibold">{t("setup.title")}</h2>
            <p className="text-xs text-muted-foreground">{t("setup.subtitle")}</p>
          </div>
        </div>

        {/* Stepper */}
        <ol className="flex items-center gap-1.5 overflow-x-auto pb-1">
          {STEPS.map(({ key }, index) => {
            const done = index < step || (index === 0 && periodsReady && step > 0)
            return (
              <li key={key} className="flex shrink-0 items-center gap-1.5">
                <button
                  type="button"
                  onClick={() => index < step && setStep(index)}
                  disabled={index > step}
                  className={cn(
                    "flex h-8 items-center gap-1.5 rounded-full px-3 text-xs font-medium transition-colors",
                    index === step
                      ? "bg-primary text-primary-foreground"
                      : done
                        ? "bg-primary/10 text-primary"
                        : "bg-muted text-muted-foreground",
                  )}
                >
                  {done && index !== step ? <Check className="size-3" /> : <span>{index + 1}</span>}
                  <span className={cn(index !== step && "hidden sm:inline")}>
                    {t(`setup.steps.${key}`)}
                  </span>
                </button>
                {index < STEPS.length - 1 ? (
                  <span className="h-px w-3 shrink-0 bg-border" aria-hidden />
                ) : null}
              </li>
            )
          })}
        </ol>

        {/* Step explanation card */}
        <StepIntro step={step} />

        {/* Step body */}
        {step === 0 && (
          <PeriodScheduleTab termId={termId} canManage onSaved={() => setPeriodsReady(true)} />
        )}
        {step === 1 && <RoomsTab canManage branchId={branchId} />}
        {step === 2 && !hasLoads && (
          <div className="rounded-2xl border border-warning/40 bg-warning/10 p-4">
            <p className="text-sm font-semibold">{t("setup.create.noLoadsTitle")}</p>
            <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
              {t("setup.create.noLoadsBody")}
            </p>
            <Button asChild variant="outline" size="sm" className="mt-2.5 h-9">
              <Link href={`/semesters/${termId}`}>
                {t("setup.create.noLoadsCta")}
                <ArrowRight className="size-3.5" />
              </Link>
            </Button>
          </div>
        )}
        {step === 2 && (
          <div className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs">
            <div className="space-y-2">
              <Label>{t("versions.name")}</Label>
              <Input
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder={t("versions.namePlaceholder")}
              />
            </div>
            <label className="flex min-h-11 items-center gap-2 text-sm">
              <Checkbox checked={saturday} onCheckedChange={(v) => setSaturday(v === true)} />
              {t("versions.saturday")}
            </label>
            <label className="flex items-start gap-2.5 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm">
              <Checkbox
                checked={autoGenerate}
                onCheckedChange={(v) => setAutoGenerate(v === true)}
                className="mt-0.5"
              />
              <span className="min-w-0">
                <span className="font-medium">{t("setup.create.autoGenerate")}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">
                  {t("setup.create.autoGenerateHint")}
                </span>
              </span>
            </label>
            {!autoGenerate && (
              <div className="rounded-xl bg-muted/40 p-3.5">
                <p className="mb-2 text-xs font-semibold">{t("setup.create.nextTitle")}</p>
                <ol className="space-y-1.5">
                  {([1, 2, 3] as const).map((n) => (
                    <li key={n} className="flex items-start gap-2 text-xs text-muted-foreground">
                      <span className="flex size-4.5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-semibold text-primary">
                        {n}
                      </span>
                      {t(`setup.create.next${n}`)}
                    </li>
                  ))}
                </ol>
              </div>
            )}
          </div>
        )}

        {/* Sticky footer nav — clears the mobile bottom tab bar. */}
        <div className="sticky bottom-24 z-10 mt-auto md:bottom-4">
          <div className="flex items-center justify-between gap-3 rounded-2xl border bg-background/95 px-4 py-3 shadow-lg backdrop-blur supports-[backdrop-filter]:bg-background/85">
            <Button
              type="button"
              variant="outline"
              className="h-11 rounded-full px-5"
              onClick={() => setStep((s) => Math.max(0, s - 1))}
              loading={working} disabled={step === 0}
            >
              <ArrowLeft className="size-4" />
              {tc("actions.back")}
            </Button>
            {step < STEPS.length - 1 ? (
              <div className="flex flex-1 flex-col items-end gap-1 sm:max-w-[280px]">
                <Button
                  type="button"
                  className="h-11 w-full rounded-full"
                  onClick={() => setStep((s) => s + 1)}
                  disabled={continueBlocked}
                >
                  {t("setup.continue")}
                  <ArrowRight className="size-4" />
                </Button>
                {continueBlocked && (
                  <p className="text-[11px] text-muted-foreground">{t("setup.periodsRequired")}</p>
                )}
              </div>
            ) : (
              <Button
                type="button"
                className="h-11 flex-1 rounded-full sm:max-w-[280px]"
                onClick={createDraft}
                disabled={working || !name.trim()}
              >
                {working ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : autoGenerate ? (
                  <Sparkles className="size-4" />
                ) : (
                  <Check className="size-4" />
                )}
                {autoGenerate ? t("setup.create.actionGenerate") : t("setup.create.action")}
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

/** The plain-words "what is this and why" card above each step. */
function StepIntro({ step }: { step: number }) {
  const { t } = useTranslation("timetable")
  const key = STEPS[step].key
  const Icon = STEPS[step].icon

  return (
    <div className="rounded-2xl border bg-card p-4 shadow-xs">
      <div className="mb-1.5 flex items-center gap-2">
        <Icon className="size-4 text-primary" strokeWidth={1.75} />
        <h3 className="text-sm font-semibold">{t(`setup.${key}.title`)}</h3>
        {key === "rooms" && (
          <Badge variant="outline" className="rounded-full text-[10px] text-muted-foreground">
            {t("setup.optional")}
          </Badge>
        )}
      </div>
      <p className="text-xs leading-relaxed text-muted-foreground">{t(`setup.${key}.what`)}</p>
      <p className="mt-1.5 text-xs leading-relaxed text-muted-foreground">
        {t(`setup.${key}.why`)}
      </p>
    </div>
  )
}
