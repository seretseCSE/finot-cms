"use client"

import {
  CalendarCheck,
  CreditCard,
  Eye,
  FileText,
  GraduationCap,
  HeartPulse,
  Home,
  MapPin,
  MessageSquare,
  Paperclip,
  Pencil,
  Phone,
  ShieldAlert,
  Star,
  Users,
} from "lucide-react"
import * as React from "react"
import type { UseFormReturn } from "react-hook-form"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { useTranslation } from "@/lib/i18n"
import type { AcademicYear, FeeStructure, GradeLevel, HealthCondition, Section } from "@/lib/types"
import { cn } from "@/lib/utils"

import type { DraftDocument, FeeSelection, RegistrationValues, WizardConcession } from "./schema"

interface Props {
  form: UseFormReturn<RegistrationValues>
  photo: File | null
  documents: DraftDocument[]
  conditions: HealthCondition[]
  academicYears: AcademicYear[]
  gradeLevels: GradeLevel[]
  sections: Section[]
  applicableFees: FeeStructure[]
  feeSelections: Record<number, FeeSelection>
  /** Standing concession being filed with the registration (fees.manage only). */
  concession?: WizardConcession | null
  /** Jump back to a step to fix something. */
  onEdit?: (step: number) => void
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-4 py-1.5">
      <span className="shrink-0 text-sm text-muted-foreground">{label}</span>
      <span className="min-w-0 text-right text-sm font-medium">{value || "—"}</span>
    </div>
  )
}

/** A review card: icon + step title + an edit button that jumps to the step. */
function Section({
  icon: Icon,
  title,
  step,
  onEdit,
  className,
  children,
}: {
  icon: React.ComponentType<{ className?: string }>
  title: string
  step: number
  onEdit?: (step: number) => void
  className?: string
  children: React.ReactNode
}) {
  const { t: tc } = useTranslation("common")
  return (
    <section className={cn("rounded-2xl border bg-card p-4 shadow-xs", className)}>
      <div className="mb-2 flex items-center justify-between gap-2">
        <h3 className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          <span className="flex size-7 items-center justify-center rounded-lg bg-accent">
            <Icon className="size-3.5" />
          </span>
          {title}
        </h3>
        {onEdit ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            className="text-muted-foreground"
            onClick={() => onEdit(step)}
            aria-label={tc("actions.edit")}
          >
            <Pencil className="size-3.5" />
          </Button>
        ) : null}
      </div>
      {children}
    </section>
  )
}

const PERMISSION_ICONS = [
  ["can_view_grades", Eye, "canViewGrades"],
  ["can_view_attendance", CalendarCheck, "canViewAttendance"],
  ["can_pay_fees", CreditCard, "canPayFees"],
  ["can_receive_sms", MessageSquare, "canReceiveSms"],
] as const

/** Staged (not yet uploaded) guardian photo — same object-URL dance as the
    student photo above: created in an effect so StrictMode can't revoke a
    URL that is still rendered. */
function StagedAvatar({ name, file, className }: { name: string; file: File | null; className?: string }) {
  const [url, setUrl] = React.useState<string | null>(null)
  React.useEffect(() => {
    const next = file ? URL.createObjectURL(file) : null
    const timer = setTimeout(() => setUrl(next), 0)
    return () => {
      clearTimeout(timer)
      if (next) URL.revokeObjectURL(next)
    }
  }, [file])
  return <PersonAvatar name={name} photoUrl={url} className={className} />
}

export function StepReview({
  form,
  photo,
  documents,
  conditions,
  academicYears,
  gradeLevels,
  sections,
  applicableFees,
  feeSelections,
  concession,
  onEdit,
}: Props) {
  const { t } = useTranslation("students")
  const { t: tf } = useTranslation("fees")
  const v = form.getValues()

  // Created inside the effect (not useMemo) so StrictMode's double-invoke
  // can't revoke a URL that is still being rendered. State lands via a timer
  // so the effect body never sets state synchronously.
  const [photoUrl, setPhotoUrl] = React.useState<string | null>(null)
  React.useEffect(() => {
    const url = photo ? URL.createObjectURL(photo) : null
    const timer = setTimeout(() => setPhotoUrl(url), 0)
    return () => {
      clearTimeout(timer)
      if (url) URL.revokeObjectURL(url)
    }
  }, [photo])

  const fullName = [v.first_name, v.father_name, v.grandfather_name].filter(Boolean).join(" ")
  const year = academicYears.find((y) => String(y.id) === v.academic_year_id)?.name
  const grade = gradeLevels.find((g) => String(g.id) === v.grade_level_id)?.name
  const section = sections.find((s) => String(s.id) === v.section_id)?.name
  const address = [v.house_no, v.woreda, v.sub_city, v.city, v.state].filter(Boolean).join(", ")
  const birthplace = [v.birth_woreda, v.birth_sub_city, v.birth_city, v.birth_state]
    .filter(Boolean)
    .join(", ")

  const selectedFees = applicableFees.filter((fee) => feeSelections[fee.id]?.selected)

  /**
   * Mirror of the server's pricing at billing time: a per-fee scholarship
   * wipes that fee; otherwise the standing concession being filed applies to
   * every invoice (percentage / fixed per bill / full scholarship).
   */
  function discountFor(fee: FeeStructure): { amount: number; label: string | null } {
    const gross = Number(fee.amount)
    const selection = feeSelections[fee.id]
    if (selection?.action === "scholarship") {
      return { amount: gross, label: tf("registration.scholarship") }
    }
    if (concession?.enabled) {
      const label = tf(`concessions.categories.${concession.category}`)
      if (concession.discount_type === "full_scholarship") return { amount: gross, label }
      const value = Number(concession.discount_value) || 0
      if (concession.discount_type === "percentage") {
        return { amount: (gross * Math.min(value, 100)) / 100, label }
      }
      return { amount: Math.min(value, gross), label }
    }
    return { amount: 0, label: null }
  }

  const feesTotal = selectedFees.reduce((sum, fee) => sum + Number(fee.amount), 0)
  const discountTotal = selectedFees.reduce((sum, fee) => sum + discountFor(fee).amount, 0)
  const payableTotal = feesTotal - discountTotal
  // What is being settled at the desk right now vs billed for later.
  const payingNow = selectedFees.reduce((sum, fee) => {
    if (feeSelections[fee.id]?.action !== "pay_now") return sum
    return sum + (Number(fee.amount) - discountFor(fee).amount)
  }, 0)
  const remaining = payableTotal - payingNow

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">{t("wizard.reviewHint")}</p>

      {/* Identity hero */}
      <section className="relative overflow-hidden rounded-2xl border bg-card p-5 shadow-xs">
        <div className="flex items-center gap-4">
          <PersonAvatar
            name={fullName || "?"}
            photoUrl={photoUrl}
            className="size-20 rounded-3xl text-xl"
          />
          <div className="min-w-0 flex-1 space-y-1.5">
            <p className="truncate font-display text-xl font-semibold">{fullName}</p>
            <div className="flex flex-wrap items-center gap-1.5 text-xs">
              <Badge variant="secondary">{t(`fields.${v.gender}`)}</Badge>
              {v.date_of_birth ? <Badge variant="secondary">{v.date_of_birth}</Badge> : null}
              {(v.languages ?? []).length > 0 ? (
                <Badge variant="secondary">
                  {(v.languages ?? []).map((code) => t(`fields.languageNames.${code}`)).join(", ")}
                </Badge>
              ) : null}
              {v.student_has_phone && v.primary_phone ? (
                <Badge variant="secondary" className="gap-1">
                  <Phone className="size-3" />
                  {v.primary_phone}
                </Badge>
              ) : null}
              {v.create_user_account ? (
                <Badge className="bg-primary/10 text-primary">
                  {v.student_has_phone && v.primary_phone
                    ? t("wizard.willCreateAccount")
                    : t("wizard.willCreateIdLogin")}
                </Badge>
              ) : null}
            </div>
          </div>
          {onEdit ? (
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="shrink-0 self-start text-muted-foreground"
              onClick={() => onEdit(0)}
              aria-label={t("wizard.steps.identity")}
            >
              <Pencil className="size-3.5" />
            </Button>
          ) : null}
        </div>
      </section>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Section icon={MapPin} title={t("wizard.steps.addresses")} step={0} onEdit={onEdit}>
          <div className="space-y-3 pt-1">
            {(
              [
                [MapPin, t("wizard.birthplace"), birthplace],
                [Home, t("wizard.currentAddress"), address],
              ] as const
            ).map(([Icon, label, value]) => (
              <div key={label} className="flex items-start gap-3">
                <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <span className="min-w-0">
                  <span className="block text-xs text-muted-foreground">{label}</span>
                  <span className="block text-sm font-medium">{value || "—"}</span>
                </span>
              </div>
            ))}
          </div>
        </Section>

        {/* -1 routes to the Student step with the optional disclosure opened. */}
        <Section icon={HeartPulse} title={t("wizard.steps.health")} step={-1} onEdit={onEdit}>
          <div className="flex flex-wrap items-center gap-1.5 pt-1">
            {v.blood_type ? (
              <Badge variant="secondary" className="bg-destructive/10 text-destructive">
                {v.blood_type}
              </Badge>
            ) : null}
            {v.health_conditions.map((row, index) => {
              const name = conditions.find((c) => String(c.id) === row.health_condition_id)?.name
              if (!name) return null
              return (
                <Badge key={index} variant="secondary">
                  {name}
                  {row.severity ? ` · ${t(`wizard.severities.${row.severity}`)}` : ""}
                </Badge>
              )
            })}
            {documents.length > 0 ? (
              <Badge variant="secondary" className="gap-1">
                <FileText className="size-3" />
                {t("wizard.documents")}: {documents.length}
              </Badge>
            ) : null}
            {!v.blood_type && v.health_conditions.length === 0 && documents.length === 0 ? (
              <span className="text-sm text-muted-foreground">—</span>
            ) : null}
          </div>
        </Section>
      </div>

      <Section icon={Users} title={t("wizard.steps.guardians")} step={1} onEdit={onEdit}>
        {v.guardians.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t("wizard.noGuardiansYet")}</p>
        ) : (
          <ul className="divide-y">
            {v.guardians.map((guardian, index) => {
              const name =
                guardian.mode === "search"
                  ? guardian.parent_label || guardian.parent_id || "—"
                  : [guardian.first_name, guardian.father_name].filter(Boolean).join(" ") || "—"
              const fileCount =
                guardian.documents.length + (guardian.photo ? 1 : 0)
              return (
                <li key={index} className="flex items-center gap-3 py-2.5">
                  <StagedAvatar name={String(name)} file={guardian.photo} className="size-9" />
                  <div className="min-w-0 flex-1">
                    <p className="flex items-center gap-1.5 truncate text-sm font-medium">
                      {name}
                      {guardian.is_primary ? (
                        <Star className="size-3.5 shrink-0 fill-warning text-warning" aria-label={t("guardians.primary")} />
                      ) : null}
                      {guardian.emergency_contact ? (
                        <ShieldAlert className="size-3.5 shrink-0 text-destructive" aria-label={t("guardians.emergency")} />
                      ) : null}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                      {t(`guardians.relationships.${guardian.relationship}`)}
                      {guardian.mode === "new" && guardian.phone ? ` · ${guardian.phone}` : ""}
                    </p>
                  </div>
                  <span className="flex shrink-0 items-center gap-1 text-muted-foreground">
                    {PERMISSION_ICONS.map(([flag, Icon, labelKey]) =>
                      guardian[flag] ? (
                        <Icon key={flag} className="size-3.5" aria-label={t(`guardians.${labelKey}`)} />
                      ) : null,
                    )}
                    {fileCount > 0 ? (
                      <span className="ml-1 flex items-center gap-0.5 text-xs">
                        <Paperclip className="size-3.5" />
                        {fileCount}
                      </span>
                    ) : null}
                  </span>
                </li>
              )
            })}
          </ul>
        )}
      </Section>

      <Section icon={GraduationCap} title={t("wizard.steps.enrollment")} step={2} onEdit={onEdit}>
        {v.enroll_now ? (
          <>
            <div className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
              <Row label={t("enroll.academicYear")} value={year} />
              <Row label={t("wizard.gradeLevel")} value={grade} />
              <Row label={t("enroll.section")} value={section ?? t("wizard.assignLater")} />
              <Row label={t("wizard.registeredOn")} value={v.enrolled_on} />
              {v.previous_school_label ? (
                <Row label={t("wizard.previousSchool")} value={v.previous_school_label} />
              ) : null}
            </div>
            {selectedFees.length > 0 ? (
              <div className="mt-3 space-y-1.5 border-t pt-3">
                {selectedFees.map((fee) => {
                  const selection = feeSelections[fee.id]
                  const discount = discountFor(fee)
                  const payable = Number(fee.amount) - discount.amount
                  return (
                    <div key={fee.id} className="flex items-center justify-between gap-3 text-sm">
                      <span className="min-w-0">
                        <span className="block truncate">{fee.name}</span>
                        {discount.amount > 0 ? (
                          <span className="block text-xs text-success">
                            −{discount.amount.toLocaleString()} ETB
                            {discount.label ? ` · ${discount.label}` : ""}
                          </span>
                        ) : null}
                      </span>
                      <span className="flex shrink-0 items-center gap-2">
                        <span className="tabular-nums text-muted-foreground">
                          {discount.amount > 0 ? (
                            <>
                              <span className="mr-1 line-through opacity-60">
                                {Number(fee.amount).toLocaleString()}
                              </span>
                              {payable.toLocaleString()} ETB
                            </>
                          ) : (
                            `${Number(fee.amount).toLocaleString()} ETB`
                          )}
                        </span>
                        <Badge
                          variant="secondary"
                          className={cn(
                            "text-[10px]",
                            selection.action === "pay_now" && "bg-success/10 text-success",
                            selection.action === "scholarship" && "bg-info/10 text-info",
                          )}
                        >
                          {selection.action === "pay_now"
                            ? tf("registration.payNow")
                            : selection.action === "scholarship"
                              ? tf("registration.scholarship")
                              : tf("registration.unpaid")}
                        </Badge>
                      </span>
                    </div>
                  )
                })}

                <div className="space-y-1 border-t pt-2 text-sm">
                  <div className="flex items-center justify-between gap-3 text-muted-foreground">
                    <span>{t("wizard.total")}</span>
                    <span className="tabular-nums">{feesTotal.toLocaleString()} ETB</span>
                  </div>
                  {discountTotal > 0 ? (
                    <>
                      <div className="flex items-center justify-between gap-3 text-success">
                        <span>
                          {t("wizard.discount")}
                          {concession?.enabled
                            ? ` · ${tf(`concessions.categories.${concession.category}`)}`
                            : ""}
                        </span>
                        <span className="tabular-nums">−{discountTotal.toLocaleString()} ETB</span>
                      </div>
                      <div className="flex items-center justify-between gap-3 font-semibold">
                        <span>{t("wizard.payable")}</span>
                        <span className="tabular-nums">{payableTotal.toLocaleString()} ETB</span>
                      </div>
                    </>
                  ) : null}
                  {payingNow > 0 ? (
                    <div className="flex items-center justify-between gap-3">
                      <span>{t("wizard.payingNow")}</span>
                      <span className="tabular-nums">{payingNow.toLocaleString()} ETB</span>
                    </div>
                  ) : null}
                  <div className="flex items-center justify-between gap-3 border-t pt-1.5 font-semibold">
                    <span>{t("wizard.remaining")}</span>
                    <span className="tabular-nums">{remaining.toLocaleString()} ETB</span>
                  </div>
                </div>
              </div>
            ) : null}
          </>
        ) : (
          <p className="text-sm text-muted-foreground">{t("wizard.enrollLater")}</p>
        )}
      </Section>
    </div>
  )
}
