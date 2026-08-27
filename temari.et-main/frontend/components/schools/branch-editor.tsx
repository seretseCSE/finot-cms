"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Building2, Copy, GraduationCap, Loader2, MapPin, Phone, UserCog, UserRound, X } from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useEffect, useMemo, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { ContactDialog } from "@/components/schools/contact-dialog"
import { GradeProgramMatrix, type ProgramGrades } from "@/components/schools/grade-program-matrix"
import { AddressFields } from "@/components/ui/address-fields"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { FormSectionHeading } from "@/components/ui/form-section-heading"
import { Input } from "@/components/ui/input"
import { PhoneInput } from "@/components/ui/phone-input"
import { ApiError, apiFetch } from "@/lib/api"
import { bustGradeLevels, useGradeLevels } from "@/lib/data/use-grade-levels"
import { useTranslation } from "@/lib/i18n"
import type { Branch, BranchGradeUsage } from "@/lib/types"
import { useLiveValidation } from "@/lib/use-live-validation"
import { optionalEthContactPhone, optionalEthPhone } from "@/lib/validators"

const schema = z.object({
  name: z.string().min(2, "Branch name is required"),
  code: z.string().min(1, "Branch code is required"),
  state: z.string().optional(),
  city: z.string().optional(),
  sub_city: z.string().optional(),
  woreda: z.string().optional(),
  house_no: z.string().optional(),
  // Office line — accepts landlines (0111…) as well as mobiles, like the
  // school's own phone.
  phone: optionalEthContactPhone(),
  director_name: z.string().optional(),
  director_phone: optionalEthPhone(),
  latitude: z.string().optional(),
  longitude: z.string().optional(),
})

type FormValues = z.infer<typeof schema>

function toDefaults(branch: Branch | null): FormValues {
  return {
    name: branch?.name ?? "",
    code: branch?.code ?? "",
    // Most schools onboard from Addis Ababa — prefilled but freely editable.
    state: branch?.address.state ?? (branch ? "" : "Addis Ababa"),
    city: branch?.address.city ?? (branch ? "" : "Addis Ababa"),
    sub_city: branch?.address.sub_city ?? "",
    woreda: branch?.address.woreda ?? "",
    house_no: branch?.address.house_no ?? "",
    phone: branch?.phone ?? "",
    director_name: "",
    director_phone: "",
    latitude: branch?.latitude ?? "",
    longitude: branch?.longitude ?? "",
  }
}

/**
 * Full-screen branch studio (same shell as the LMS editors): basics, location
 * and director alongside the grade × program offering matrix that drives every
 * branch-scoped grade filter. Create mode defaults to Regular across all
 * grades; edit mode refetches the branch with live usage so in-use matrix
 * cells lock instead of failing on save.
 */
export function BranchEditor({
  open,
  onOpenChange,
  schoolId,
  branch,
  showGeo = false,
  onSaved,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Create target — required in create mode (no `branch`). */
  schoolId?: number
  /** Edit mode when set; the editor refetches the full record itself. */
  branch?: Branch | null
  showGeo?: boolean
  onSaved: (branch: Branch) => void
}) {
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const isEdit = branch != null

  const { grades } = useGradeLevels({ all: true, enabled: open })

  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: toDefaults(branch ?? null) })
  useLiveValidation(form)

  const [matrix, setMatrix] = useState<ProgramGrades[]>([])
  const [matrixError, setMatrixError] = useState<string | null>(null)
  const [usage, setUsage] = useState<BranchGradeUsage | undefined>(undefined)
  const [fresh, setFresh] = useState<Branch | null>(null)
  const [saving, setSaving] = useState(false)

  // Hydrate on open: create = clean slate; edit = refetch the branch for the
  // saved matrix + live usage (list rows don't carry either).
  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- hydrate on open */
    setMatrixError(null)
    setUsage(undefined)
    setFresh(null)

    if (!isEdit) {
      form.reset(toDefaults(null))
      setMatrix([])
      return
    }

    form.reset(toDefaults(branch))
    setMatrix(
      (branch.programs ?? []).map((program) => ({
        type: program.type,
        grade_level_ids: program.grade_level_ids ?? [],
      })),
    )
    /* eslint-enable react-hooks/set-state-in-effect */
    apiFetch<{ data: Branch; meta?: { grade_usage?: BranchGradeUsage } }>(`/branches/${branch.id}`)
      .then((response) => {
        setFresh(response.data)
        setUsage(response.meta?.grade_usage ?? {})
        form.reset(toDefaults(response.data))
        setMatrix(
          (response.data.programs ?? []).map((program) => ({
            type: program.type,
            grade_level_ids: program.grade_level_ids ?? [],
          })),
        )
      })
      .catch(() => toast.error(t("branches.loadFailed")))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, branch?.id])

  // Create mode: once the ladder arrives, default to Regular × every grade.
  useEffect(() => {
    if (open && !isEdit && matrix.length === 0 && grades.length > 0) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- default once the ladder arrives
      setMatrix([{ type: "regular", grade_level_ids: grades.map((g) => g.id) }])
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, isEdit, grades])

  const current = fresh ?? branch ?? null
  const director = current?.director

  // Live rail summary: per-program grade span + the branch-wide union.
  const gradeById = useMemo(() => new Map(grades.map((g) => [g.id, g])), [grades])
  const supportedIds = useMemo(
    () => new Set(matrix.flatMap((entry) => entry.grade_level_ids)),
    [matrix],
  )

  function spanLabel(ids: number[]): string {
    const sorted = ids
      .map((id) => gradeById.get(id))
      .filter((g) => g != null)
      .sort((a, b) => a.sort_order - b.sort_order)
    if (sorted.length === 0) return t("branches.matrix.noGrades")
    if (sorted.length === 1) return sorted[0].name
    return `${sorted[0].name} – ${sorted[sorted.length - 1].name}`
  }

  async function copyPhone(phone: string) {
    await navigator.clipboard.writeText(phone)
    toast.success(t("contacts.copied"))
  }

  async function onSubmit(values: FormValues) {
    if (matrix.length === 0 || matrix.every((entry) => entry.grade_level_ids.length === 0)) {
      setMatrixError(t("branches.matrix.atLeastOneGrade"))
      return
    }
    setMatrixError(null)
    setSaving(true)
    try {
      const shared = {
        name: values.name,
        code: values.code,
        state: values.state || null,
        city: values.city || null,
        sub_city: values.sub_city || null,
        woreda: values.woreda || null,
        house_no: values.house_no || null,
        phone: values.phone || null,
        programs: matrix,
      }
      const response = isEdit
        ? await apiFetch<{ data: Branch }>(`/branches/${branch.id}`, {
            method: "PATCH",
            body: {
              ...shared,
              ...(showGeo
                ? {
                    latitude: values.latitude ? Number(values.latitude) : null,
                    longitude: values.longitude ? Number(values.longitude) : null,
                  }
                : {}),
            },
          })
        : await apiFetch<{ data: Branch }>(`/schools/${schoolId}/branches`, {
            method: "POST",
            body: {
              ...shared,
              director_name: values.director_name || undefined,
              director_phone: values.director_phone || undefined,
            },
          })

      // Scoped grade filters across the app must pick up the new offering.
      bustGradeLevels()
      toast.success(isEdit ? tc("actions.saved") : t("branches.created"))
      onSaved(response.data)
      onOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          if (field === "programs" || field.startsWith("programs.")) {
            setMatrixError(messages[0])
          } else {
            form.setError(field as keyof FormValues, { type: "server", message: messages[0] })
          }
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error("Something went wrong.")
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
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
              onClick={() => onOpenChange(false)}
              aria-label={tc("actions.close")}
            >
              <X className="size-5" />
            </Button>
            <div className="flex min-w-0 items-center gap-2.5">
              <div className="hidden size-8 items-center justify-center rounded-lg bg-primary/10 text-primary sm:flex">
                <Building2 className="size-4.5" />
              </div>
              <div className="min-w-0">
                <DialogPrimitive.Title className="truncate text-sm font-semibold">
                  {isEdit ? t("branches.editTitle") : t("branches.createTitle")}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {isEdit ? (current?.name ?? "") : t("branches.createDescription")}
                </p>
              </div>
            </div>

            <div className="ml-auto flex items-center">
              <Button className="h-10 px-4 md:px-5" disabled={saving} onClick={form.handleSubmit(onSubmit)}>
                {saving && <Loader2 className="size-4 animate-spin" />}
                {isEdit ? tc("actions.save") : t("branches.create")}
              </Button>
            </div>
          </header>

          {/* ── Body: canvas + summary rail ──────────────────────────── */}
          <div className="min-h-0 flex-1 overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex md:overflow-hidden">
            <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
              <Form {...form}>
                <form
                  onSubmit={form.handleSubmit(onSubmit)}
                  className="mx-auto w-full max-w-5xl space-y-5 p-4 pb-8 md:p-8"
                >
                  {/* Basics */}
                  <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                    <FormSectionHeading icon={<Building2 />}>{t("branches.sections.basics")}</FormSectionHeading>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                      <FormField
                        control={form.control}
                        name="name"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel>{t("branches.name")}</FormLabel>
                            <FormControl>
                              <Input placeholder={t("branches.namePlaceholder")} {...field} />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                      <FormField
                        control={form.control}
                        name="code"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel>{t("branches.code")}</FormLabel>
                            <FormControl>
                              <Input placeholder={t("branches.codePlaceholder")} {...field} />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                    </div>
                  </section>

                  {/* Grade × program offering */}
                  <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                    <FormSectionHeading icon={<GraduationCap />}>
                      {t("branches.matrix.title")}
                    </FormSectionHeading>
                    <p className="-mt-2 text-xs text-muted-foreground">{t("branches.matrix.hint")}</p>
                    <GradeProgramMatrix
                      grades={grades}
                      value={matrix}
                      onChange={(next) => {
                        setMatrix(next)
                        setMatrixError(null)
                      }}
                      usage={usage}
                      existingPrograms={isEdit ? (current?.programs ?? []) : undefined}
                    />
                    {matrixError && <p className="text-sm text-destructive">{matrixError}</p>}
                  </section>

                  {/* Location */}
                  <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                    <FormSectionHeading icon={<MapPin />}>{t("branches.sections.location")}</FormSectionHeading>
                    <AddressFields<FormValues> />
                    <FormField
                      control={form.control}
                      name="phone"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{t("branches.phone")}</FormLabel>
                          <FormControl>
                            <PhoneInput mode="contact" placeholder="0911 234 567" {...field} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    {isEdit && showGeo && (
                      <div className="grid grid-cols-2 gap-3">
                        <FormField
                          control={form.control}
                          name="latitude"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel>{t("branches.latitude")}</FormLabel>
                              <FormControl>
                                <Input inputMode="decimal" placeholder="9.03" {...field} />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                        <FormField
                          control={form.control}
                          name="longitude"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel>{t("branches.longitude")}</FormLabel>
                              <FormControl>
                                <Input inputMode="decimal" placeholder="38.74" {...field} />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                      </div>
                    )}
                  </section>

                  {/* Director */}
                  <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                    <FormSectionHeading icon={<UserRound />}>{t("branches.sections.director")}</FormSectionHeading>
                    {isEdit ? (
                      <div className="rounded-xl border bg-muted/30 p-3">
                        {director?.name ? (
                          <div className="flex items-center gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                              {director.name
                                .split(/\s+/)
                                .slice(0, 2)
                                .map((p) => p[0]?.toUpperCase() ?? "")
                                .join("")}
                            </div>
                            <div className="min-w-0 flex-1">
                              <div className="flex flex-wrap items-center gap-1.5">
                                <p className="truncate text-sm font-medium">{director.name}</p>
                                {!director.is_active && (
                                  <Badge variant="secondary" className="px-1.5 py-0 text-[11px]">
                                    {tc("states.inactive")}
                                  </Badge>
                                )}
                              </div>
                              {director.phone && (
                                <p className="font-mono text-xs text-muted-foreground tabular-nums">
                                  {director.phone}
                                </p>
                              )}
                            </div>
                            {director.phone && (
                              <div className="flex items-center gap-1">
                                <Button
                                  asChild
                                  type="button"
                                  variant="ghost"
                                  size="icon"
                                  className="size-9"
                                  title={t("contacts.call")}
                                >
                                  <a href={`tel:${director.phone}`}>
                                    <Phone className="size-4" />
                                  </a>
                                </Button>
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="icon"
                                  className="size-9"
                                  title={t("contacts.copy")}
                                  onClick={() => copyPhone(director.phone!)}
                                >
                                  <Copy className="size-4" />
                                </Button>
                              </div>
                            )}
                          </div>
                        ) : (
                          <p className="px-1 py-2 text-center text-sm text-muted-foreground">{t("contacts.none")}</p>
                        )}
                        {current && (
                          <ContactDialog
                            target={{ kind: "branch", branchId: current.id }}
                            current={director}
                            onBranchSaved={(saved) => setFresh(saved)}
                            title={t("contacts.replaceDirectorTitle")}
                            description={t("contacts.replaceDescription")}
                            trigger={
                              <Button type="button" variant="outline" size="sm" className="mt-2.5 h-9 w-full">
                                <UserCog className="size-4" />
                                {director?.name ? t("contacts.replaceDirector") : t("contacts.assignDirector")}
                              </Button>
                            }
                          />
                        )}
                      </div>
                    ) : (
                      <>
                        <p className="-mt-2 text-xs text-muted-foreground">{t("branches.directorHint")}</p>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                          <FormField
                            control={form.control}
                            name="director_name"
                            render={({ field }) => (
                              <FormItem>
                                <FormLabel>{t("branches.directorName")}</FormLabel>
                                <FormControl>
                                  <Input placeholder={t("branches.directorNamePlaceholder")} {...field} />
                                </FormControl>
                                <FormMessage />
                              </FormItem>
                            )}
                          />
                          <FormField
                            control={form.control}
                            name="director_phone"
                            render={({ field }) => (
                              <FormItem>
                                <FormLabel>{t("branches.directorPhone")}</FormLabel>
                                <FormControl>
                                  <PhoneInput placeholder={t("branches.directorPhonePlaceholder")} {...field} />
                                </FormControl>
                                <FormMessage />
                              </FormItem>
                            )}
                          />
                        </div>
                      </>
                    )}
                  </section>
                </form>
              </Form>
            </main>

            {/* ── Summary rail ─────────────────────────────────────────── */}
            <aside className="border-t bg-background md:min-h-0 md:w-[340px] md:shrink-0 md:overflow-y-auto md:border-l md:border-t-0">
              <div className="space-y-5 p-4 md:p-5">
                <div>
                  <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    {t("branches.matrix.summaryTitle")}
                  </p>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {t("branches.matrix.summaryGrades", {
                      count: String(supportedIds.size),
                      total: String(grades.length),
                    })}
                  </p>
                </div>

                <div className="space-y-2.5">
                  {matrix.map((entry) => (
                    <div key={entry.type} className="rounded-xl border bg-muted/30 px-3 py-2.5">
                      <div className="flex items-center justify-between gap-2">
                        <span className="text-sm font-medium">{tc(`programs.${entry.type}`)}</span>
                        <Badge variant="secondary" className="rounded-full px-2 py-0 text-[11px] tabular-nums">
                          {entry.grade_level_ids.length}
                        </Badge>
                      </div>
                      <p className="mt-0.5 text-xs text-muted-foreground">{spanLabel(entry.grade_level_ids)}</p>
                    </div>
                  ))}
                </div>

                <p className="text-xs leading-relaxed text-muted-foreground">{t("branches.matrix.scopeNote")}</p>
                {isEdit && usage && Object.keys(usage).length > 0 && (
                  <p className="text-xs leading-relaxed text-muted-foreground">{t("branches.matrix.lockNote")}</p>
                )}
              </div>
            </aside>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
