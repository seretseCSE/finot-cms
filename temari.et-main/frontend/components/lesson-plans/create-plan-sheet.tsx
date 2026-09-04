"use client"

import { CalendarRange, Loader2, Plus, X } from "lucide-react"
import { useRouter } from "next/navigation"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { RichTextEditor } from "@/components/ui/rich-text"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import { stripHtml } from "@/lib/sanitize-html"
import type { AnnualLessonPlanRow, LessonPlanOption } from "@/lib/types"

/**
 * New annual plan — the full-screen studio (same shell as the question
 * editor): the goals/methods WYSIWYG canvas in the middle, and the plan
 * settings (the class picker from the teacher's REAL teaching load — combos
 * that already have a plan are marked and disabled — plus the period counts)
 * on the right rail. Creating lands straight in the plan workspace to build
 * the unit roadmap. Pass `open`/`onOpenChange` for controlled mode (no
 * trigger button) and `initial` to preselect a class — My Day's quick-create
 * opens the studio with the slot's subject × grade already picked in the
 * current year.
 */
export function CreatePlanSheet({
  onCreated,
  open: controlledOpen,
  onOpenChange,
  initial = null,
}: {
  onCreated?: () => void
  /** Controlled mode: the caller owns visibility, no trigger is rendered. */
  open?: boolean
  onOpenChange?: (open: boolean) => void
  /** Preselect this class once the options load. */
  initial?: { subjectId: number; gradeLevelId: number } | null
}) {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const router = useRouter()

  const controlled = controlledOpen !== undefined
  const [internalOpen, setInternalOpen] = useState(false)
  const open = controlled ? controlledOpen : internalOpen
  const setOpen = (next: boolean) => {
    if (!controlled) setInternalOpen(next)
    onOpenChange?.(next)
  }
  const [options, setOptions] = useState<LessonPlanOption[] | null>(null)
  const [choice, setChoice] = useState<string>("")
  const [goals, setGoals] = useState("")
  const [methods, setMethods] = useState("")
  const [periodsPerWeek, setPeriodsPerWeek] = useState("")
  const [totalPeriods, setTotalPeriods] = useState("")
  const [saving, setSaving] = useState(false)

  // Both editors report image uploads here — Create stays disabled until the
  // LAST in-flight upload lands, not just the most recent one.
  const uploadCount = useRef(0)
  const [imgUploading, setImgUploading] = useState(false)
  const trackUploading = (up: boolean) => {
    uploadCount.current = Math.max(0, uploadCount.current + (up ? 1 : -1))
    setImgUploading(uploadCount.current > 0)
  }

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- fresh form per open */
    setChoice("")
    setGoals("")
    setMethods("")
    setPeriodsPerWeek("")
    setTotalPeriods("")
    /* eslint-enable react-hooks/set-state-in-effect */
    let cancelled = false
    apiFetch<{ data: LessonPlanOption[] }>("/lesson-plans/options")
      .then((res) => {
        if (cancelled) return
        setOptions(res.data)
        // Quick-create path: preselect the caller's class, current year first.
        if (initial !== null) {
          const candidates = res.data.filter(
            (o) =>
              o.subject.id === initial.subjectId &&
              o.grade_level.id === initial.gradeLevelId &&
              o.plan_id === null
          )
          const pick =
            candidates.find((o) => o.academic_year_status === "active") ??
            candidates[0]
          if (pick) {
            setChoice(
              `${pick.academic_year_id}:${pick.subject.id}:${pick.grade_level.id}`
            )
          }
        }
      })
      .catch(() => !cancelled && setOptions([]))
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- initial is read per open
  }, [open, active.schoolId, active.branchId])

  const selected = options?.find(
    (o) =>
      `${o.academic_year_id}:${o.subject.id}:${o.grade_level.id}` === choice
  )

  async function uploadImage(file: File) {
    const form = new FormData()
    form.append("file", file)
    const res = await apiFetch<{ data: { url: string; path: string } }>(
      "/lms/uploads",
      { method: "POST", body: form }
    )
    return res.data
  }

  async function create() {
    if (!selected) return
    setSaving(true)
    try {
      const res = await apiFetch<{ data: AnnualLessonPlanRow }>(
        "/lesson-plans",
        {
          method: "POST",
          body: {
            academic_year_id: selected.academic_year_id,
            subject_id: selected.subject.id,
            grade_level_id: selected.grade_level.id,
            goals: stripHtml(goals).trim() ? goals : null,
            methods: stripHtml(methods).trim() ? methods : null,
            periods_per_week:
              periodsPerWeek === "" ? null : Number(periodsPerWeek),
            total_periods: totalPeriods === "" ? null : Number(totalPeriods),
          },
        }
      )
      toast.success(t("create.created"))
      setOpen(false)
      onCreated?.()
      router.push(`/lesson-plans/${res.data.id}`)
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setSaving(false)
    }
  }

  return (
    <DialogPrimitive.Root open={open} onOpenChange={setOpen}>
      {!controlled && (
        <DialogPrimitive.Trigger asChild>
          <Button className="h-11">
            <Plus className="size-4" />
            {t("register.newPlan")}
          </Button>
        </DialogPrimitive.Trigger>
      )}
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/20 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
        <DialogPrimitive.Content
          className="fixed inset-0 z-50 flex flex-col bg-background data-open:animate-in data-open:fade-in-0 data-open:zoom-in-[0.99] data-closed:animate-out data-closed:fade-out-0"
          onEscapeKeyDown={(e) => e.preventDefault()}
          onPointerDownOutside={(e) => e.preventDefault()}
          onInteractOutside={(e) => e.preventDefault()}
        >
          {/* ── Top bar ─────────────────────────────────────────────── */}
          <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-background px-3 md:px-5">
            <Button
              variant="ghost"
              size="icon"
              className="text-muted-foreground"
              onClick={() => setOpen(false)}
              aria-label={tc("actions.close")}
            >
              <X className="size-5" />
            </Button>
            <div className="flex min-w-0 items-center gap-2.5">
              <div className="hidden size-8 items-center justify-center rounded-lg bg-primary/10 text-primary sm:flex">
                <CalendarRange className="size-4.5" />
              </div>
              <div className="min-w-0">
                <DialogPrimitive.Title className="truncate text-sm font-semibold">
                  {t("create.title")}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {selected
                    ? `${selected.subject.name} · ${selected.grade_level.name} · ${selected.academic_year_name}`
                    : t("create.description")}
                </p>
              </div>
            </div>

            <div className="ml-auto">
              <Button
                className="h-10 px-4 md:px-5"
                disabled={saving || imgUploading || !selected}
                onClick={create}
              >
                {saving && <Loader2 className="size-4 animate-spin" />}
                {t("create.create")}
              </Button>
            </div>
          </header>

          {/* ── Body: canvas + settings rail. The class picker decides
              everything, so on mobile the rail comes FIRST (flex order);
              desktop keeps it on the right. */}
          <div className="flex min-h-0 flex-1 flex-col overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex-row md:overflow-hidden">
            <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
              <div className="mx-auto w-full max-w-5xl space-y-5 p-4 pb-8 md:p-8">
                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <Label className="mb-2 block">{t("create.goals")}</Label>
                  <RichTextEditor
                    value={goals}
                    onChange={setGoals}
                    placeholder={t("create.goalsPlaceholder")}
                    onUploadingChange={trackUploading}
                    onUploadImage={async (file) => {
                      const stored = await uploadImage(file)
                      return { url: stored.url, path: stored.path }
                    }}
                  />
                </section>

                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <Label className="mb-2 block">{t("create.methods")}</Label>
                  <RichTextEditor
                    value={methods}
                    onChange={setMethods}
                    placeholder={t("create.methodsPlaceholder")}
                    onUploadingChange={trackUploading}
                    onUploadImage={async (file) => {
                      const stored = await uploadImage(file)
                      return { url: stored.url, path: stored.path }
                    }}
                  />
                </section>
              </div>
            </main>

            <aside className="order-first border-b bg-background md:order-none md:min-h-0 md:w-[340px] md:shrink-0 md:overflow-y-auto md:border-l md:border-b-0">
              <div className="space-y-5 p-4 md:p-5">
                <div className="space-y-2">
                  <Label htmlFor="lp-class">
                    {t("create.classLabel")}{" "}
                    <span className="text-destructive">*</span>
                  </Label>
                  {options === null ? (
                    <Skeleton className="h-12 w-full rounded-xl" />
                  ) : options.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                      {t("create.noOptions")}
                    </p>
                  ) : (
                    <Select value={choice} onValueChange={setChoice}>
                      <SelectTrigger id="lp-class" className="h-12 w-full">
                        <SelectValue
                          placeholder={t("create.classPlaceholder")}
                        />
                      </SelectTrigger>
                      <SelectContent>
                        {options.map((o) => {
                          const value = `${o.academic_year_id}:${o.subject.id}:${o.grade_level.id}`
                          return (
                            <SelectItem
                              key={value}
                              value={value}
                              disabled={o.plan_id !== null}
                            >
                              {o.subject.name} · {o.grade_level.name} ·{" "}
                              {o.academic_year_name}
                              {o.plan_id !== null
                                ? ` — ${t("create.alreadyPlanned")}`
                                : ""}
                            </SelectItem>
                          )
                        })}
                      </SelectContent>
                    </Select>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="lp-ppw">{t("create.periodsPerWeek")}</Label>
                  <Input
                    id="lp-ppw"
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={60}
                    value={periodsPerWeek}
                    onChange={(e) => setPeriodsPerWeek(e.target.value)}
                    className="no-spinner h-12 w-full text-base md:text-sm"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="lp-total">{t("create.totalPeriods")}</Label>
                  <Input
                    id="lp-total"
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={2000}
                    value={totalPeriods}
                    onChange={(e) => setTotalPeriods(e.target.value)}
                    className="no-spinner h-12 w-full text-base md:text-sm"
                  />
                </div>
              </div>
            </aside>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
