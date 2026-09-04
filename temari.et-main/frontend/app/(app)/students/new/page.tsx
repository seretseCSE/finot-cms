"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { ArrowLeft, ArrowRight, Check, ChevronDown, Loader2 } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useState } from "react"
import { useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"

import { activeAcademicYearId } from "@/components/students/academic-year-select"
import { StepAddresses } from "@/components/students/registration/step-addresses"
import { StepEnrollmentFees } from "@/components/students/registration/step-enrollment-fees"
import { StepGuardians } from "@/components/students/registration/step-guardians"
import { StepDocuments } from "@/components/students/registration/step-documents"
import { StepHealth } from "@/components/students/registration/step-health"
import { StepIdentity } from "@/components/students/registration/step-identity"
import { StepReview } from "@/components/students/registration/step-review"
import {
  defaultWizardConcession,
  registrationDefaults,
  registrationSchema,
  STEP_FIELDS,
  type DraftDocument,
  type FeeSelection,
  type RegistrationValues,
  type WizardConcession,
} from "@/components/students/registration/schema"
import { Button } from "@/components/ui/button"
import { Form } from "@/components/ui/form"
import { PageHeader } from "@/components/ui/page-header"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type {
  AcademicYear,
  BankAccount,
  FeeStructure,
  GradeLevel,
  Guardian,
  HealthCondition,
  Paginated,
  ProgramRef,
  Section,
  Student,
} from "@/lib/types"
import { cn } from "@/lib/utils"

// Four steps — a registration-desk queue moves fast. Health and documents
// (optional, often unknown at the desk) live inside the Student step as a
// collapsed "More details" section, completable later from the profile.
const STEP_KEYS = ["student", "guardians", "enrollment", "review"] as const

/** Which step owns a (possibly nested) field path — "guardians.2.phone" → the guardians step. */
function stepForField(path: string): number {
  const root = path.split(".")[0]
  const index = STEP_FIELDS.findIndex((fields) => (fields as string[]).includes(root))
  return index === -1 ? 0 : index
}

/** Flatten RHF's nested errors object into dotted paths with messages. */
function flattenErrors(node: unknown, prefix = ""): { path: string; message: string }[] {
  if (node === null || typeof node !== "object") return []
  const record = node as Record<string, unknown>
  if (typeof record.message === "string" && record.message) {
    return [{ path: prefix, message: record.message }]
  }
  return Object.entries(record).flatMap(([key, value]) =>
    key === "ref" ? [] : flattenErrors(value, prefix ? `${prefix}.${key}` : key),
  )
}

export default function RegisterStudentPage() {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  const canCreate = permissions.includes("students.create")
  const hasBranch = active.branchId != null

  // School-wide workspace: the wizard first asks which branch the student is
  // being registered at; every branch-scoped catalog then follows that pick.
  const { needsBranch } = useBranchScope()
  const [targetBranchId, setTargetBranchId] = useState<number | null>(null)
  const branchReady = hasBranch || (needsBranch && targetBranchId != null)

  const [step, setStep] = useState(0)
  const [photo, setPhoto] = useState<File | null>(null)
  const [documents, setDocuments] = useState<DraftDocument[]>([])
  /** The optional health/documents disclosure inside the Student step. */
  const [moreOpen, setMoreOpen] = useState(false)
  const [feeSelections, setFeeSelections] = useState<Record<number, FeeSelection>>({})
  const [applicableFees, setApplicableFees] = useState<FeeStructure[]>([])
  const [concession, setConcession] = useState<WizardConcession>(defaultWizardConcession)
  const canGrantConcession = permissions.includes("fees.manage")
  /** A created student id — retry after a failed upload updates, not duplicates. */
  const [savedId, setSavedId] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const [academicYears, setAcademicYears] = useState<AcademicYear[]>([])
  const [gradeLevels, setGradeLevels] = useState<GradeLevel[]>([])
  const [sections, setSections] = useState<Section[]>([])
  const [programs, setPrograms] = useState<ProgramRef[]>([])
  const [conditions, setConditions] = useState<HealthCondition[]>([])
  /** Accounts the branch can take money on — the pay-now account picker. */
  const [accounts, setAccounts] = useState<BankAccount[]>([])

  const form = useForm<RegistrationValues>({
    resolver: zodResolver(registrationSchema),
    defaultValues: registrationDefaults,
    mode: "onTouched",
  })

  // Errors clear live as the user fixes the offending field — including
  // server-side ones (duplicate phone…) surfaced after submit.
  useLiveValidation(form)

  // Reading formState.errors subscribes this render to validation changes, so
  // the stepper's error dots appear/clear live as fields are fixed.
  const stepsWithErrors = new Set(
    flattenErrors(form.formState.errors).map(({ path }) => stepForField(path)),
  )

  // Collapsed-state summary of what the optional section already holds, so a
  // registrar returning from review sees the data didn't vanish.
  const bloodType = useWatch({ control: form.control, name: "blood_type" })
  const healthConditions = useWatch({ control: form.control, name: "health_conditions" })
  const moreSummary = [
    bloodType ? `${t("fields.bloodType")} ${bloodType}` : null,
    (healthConditions?.length ?? 0) > 0
      ? t("wizard.moreSummaryConditions", { count: healthConditions.length })
      : null,
    documents.length > 0 ? t("wizard.moreSummaryDocuments", { count: documents.length }) : null,
  ]
    .filter(Boolean)
    .join(" · ")

  /** A hidden invalid field can't be fixed — pop the disclosure open. */
  function revealMoreIfErrored(errors: { path: string }[]) {
    const MORE_FIELDS = ["blood_type", "health_notes", "health_conditions"]
    if (errors.some((e) => MORE_FIELDS.includes(e.path.split(".")[0]))) setMoreOpen(true)
  }

  useEffect(() => {
    if (!branchReady) return
    let cancelled = false
    // School-wide: branch-scoped catalogs follow the picked target branch.
    const branchParam = !hasBranch && targetBranchId != null ? `branch_id=${targetBranchId}` : ""
    const withBranch = (path: string) =>
      branchParam ? `${path}${path.includes("?") ? "&" : "?"}${branchParam}` : path

    apiFetch<Paginated<AcademicYear>>(withBranch("/academic-years"))
      .then((res) => !cancelled && setAcademicYears(res.data))
      .catch(() => {})
    // Scoped to the branch's grade × program offering (target-branch aware).
    apiFetch<{ data: GradeLevel[] }>(withBranch("/grade-levels"))
      .then((res) => !cancelled && setGradeLevels(res.data))
      .catch(() => {})
    apiFetch<Paginated<Section>>(withBranch("/sections"))
      .then((res) => !cancelled && setSections(res.data))
      .catch(() => {})
    // `/programs` returns { branch_programs, catalog } — the wizard only
    // offers programs that already exist on the branch.
    apiFetch<{ data: { branch_programs: ProgramRef[] } }>(withBranch("/programs"))
      .then((res) => !cancelled && setPrograms(res.data.branch_programs ?? []))
      .catch(() => {})
    apiFetch<{ data: HealthCondition[] }>("/health-conditions")
      .then((res) => !cancelled && setConditions(res.data))
      .catch(() => {})
    apiFetch<{ data: BankAccount[] }>(withBranch("/bank-accounts?usable=1"))
      .then((res) => !cancelled && setAccounts(res.data))
      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [branchReady, hasBranch, targetBranchId, active.branchId])

  // Auto-pick the branch's ACTIVE year so new enrollments land in the current
  // cycle without an extra click; re-picks after a branch switch when the
  // chosen year isn't offered there. Runs AFTER the years state commits — the
  // Select must already render the matching option, or Radix coerces a value
  // set in the same tick back to "".
  useEffect(() => {
    if (academicYears.length === 0) return
    const chosen = form.getValues("academic_year_id")
    if (!chosen || !academicYears.some((year) => String(year.id) === chosen)) {
      form.setValue("academic_year_id", activeAcademicYearId(academicYears))
    }
  }, [academicYears, form])

  /**
   * A pay-now over a bank/wallet channel must name the exact account the
   * money landed in whenever the branch has usable accounts — "bank transfer"
   * with no account is unreconcilable.
   */
  function missingPayNowAccount(): boolean {
    if (accounts.length === 0) return false
    return applicableFees.some((fee) => {
      const selection = feeSelections[fee.id]
      return (
        selection?.selected &&
        selection.action === "pay_now" &&
        (selection.method === "wallet" || selection.method === "bank_transfer") &&
        !selection.bank_account_id
      )
    })
  }

  async function next() {
    const valid = await form.trigger(STEP_FIELDS[step], { shouldFocus: true })
    if (!valid) {
      if (step === 0) revealMoreIfErrored(flattenErrors(form.formState.errors))
      return
    }
    if (step === 2 && missingPayNowAccount()) {
      toast.error(t("wizard.payAccountRequired"))
      return
    }
    setStep((s) => Math.min(s + 1, STEP_KEYS.length - 1))
    window.scrollTo({ top: 0 })
  }

  /**
   * Make validation failures actionable: jump to the FIRST step that has an
   * error and toast the offending fields (step · field — message), instead of
   * a blind "check the steps".
   */
  function reportErrors(errors: { path: string; message: string }[]) {
    if (errors.length === 0) return
    const first = errors.reduce(
      (min, e) => Math.min(min, stepForField(e.path)),
      STEP_KEYS.length - 1,
    )
    setStep(first)
    revealMoreIfErrored(errors)
    window.scrollTo({ top: 0 })

    toast.error(t("wizard.checkErrors"), {
      description: (
        <ul className="mt-1 space-y-1">
          {errors.slice(0, 5).map(({ path, message }) => {
            const parts = path.split(".")
            const ordinal = parts.length > 1 && /^\d+$/.test(parts[1]) ? ` ${Number(parts[1]) + 1}` : ""
            const field = parts.filter((p) => !/^\d+$/.test(p)).pop() ?? path
            return (
              <li key={path}>
                <span className="font-medium">
                  {t(`wizard.steps.${STEP_KEYS[stepForField(path)]}`)}
                  {ordinal} · {field.replaceAll("_", " ")}
                </span>
                {" — "}
                {message}
              </li>
            )
          })}
          {errors.length > 5 ? <li>…+{errors.length - 5}</li> : null}
        </ul>
      ),
      duration: 8000,
    })
  }

  function back() {
    setStep((s) => Math.max(s - 1, 0))
    window.scrollTo({ top: 0 })
  }

  function buildBody(values: RegistrationValues): Record<string, unknown> {
    const selectedFees = applicableFees.filter((fee) => feeSelections[fee.id]?.selected)

    return {
      ...(targetBranchId != null ? { branch_id: targetBranchId } : {}),
      first_name: values.first_name,
      father_name: values.father_name,
      grandfather_name: values.grandfather_name || undefined,
      mother_name: values.mother_name || undefined,
      gender: values.gender,
      date_of_birth: values.date_of_birth || null,
      national_student_id: values.national_student_id || undefined,
      fayda_id: values.fayda_id || undefined,
      citizenship: values.citizenship || undefined,
      marital_status: values.marital_status || undefined,
      languages: values.languages,
      // Student contact only when the toggle says the phone is the STUDENT's
      // own; without it, create_user_account provisions an ID login instead.
      primary_phone: (values.student_has_phone && values.primary_phone) || undefined,
      email: (values.student_has_phone && values.email) || undefined,
      create_user_account: values.create_user_account,
      birth_state: values.birth_state || undefined,
      birth_city: values.birth_city || undefined,
      birth_sub_city: values.birth_sub_city || undefined,
      birth_woreda: values.birth_woreda || undefined,
      state: values.state || undefined,
      city: values.city || undefined,
      sub_city: values.sub_city || undefined,
      woreda: values.woreda || undefined,
      house_no: values.house_no || undefined,
      blood_type: values.blood_type || undefined,
      health_notes: values.health_notes || undefined,
      health_conditions: values.health_conditions
        .filter((row) => row.health_condition_id)
        .map((row) => ({
          health_condition_id: Number(row.health_condition_id),
          severity: row.severity || undefined,
          notes: row.notes || undefined,
          medication: row.medication || undefined,
        })),
      guardians: values.guardians.map((guardian) =>
        guardian.mode === "search"
          ? {
              parent_id: Number(guardian.parent_id),
              relationship: guardian.relationship,
              is_primary: guardian.is_primary,
              emergency_contact: guardian.emergency_contact,
              can_view_grades: guardian.can_view_grades,
              can_view_attendance: guardian.can_view_attendance,
              can_pay_fees: guardian.can_pay_fees,
              can_receive_sms: guardian.can_receive_sms,
            }
          : {
              first_name: guardian.first_name,
              father_name: guardian.father_name || undefined,
              grandfather_name: guardian.grandfather_name || undefined,
              phone: guardian.phone,
              email: guardian.email || undefined,
              gender: guardian.gender || undefined,
              occupation: guardian.occupation || undefined,
              relationship: guardian.relationship,
              is_primary: guardian.is_primary,
              emergency_contact: guardian.emergency_contact,
              can_view_grades: guardian.can_view_grades,
              can_view_attendance: guardian.can_view_attendance,
              can_pay_fees: guardian.can_pay_fees,
              can_receive_sms: guardian.can_receive_sms,
            },
      ),
      ...(values.enroll_now && values.academic_year_id
        ? {
            academic_year_id: Number(values.academic_year_id),
            grade_level_id: values.grade_level_id ? Number(values.grade_level_id) : undefined,
            section_id: values.section_id ? Number(values.section_id) : undefined,
            school_program_id: values.school_program_id ? Number(values.school_program_id) : undefined,
            previous_school_id: values.previous_school_id ? Number(values.previous_school_id) : undefined,
            enrolled_on: values.enrolled_on || undefined,
            fee_structure_ids: selectedFees.map((fee) => fee.id),
            pay_now: selectedFees
              .filter((fee) => feeSelections[fee.id]?.action === "pay_now")
              .map((fee) => ({
                fee_structure_id: fee.id,
                method: feeSelections[fee.id].method,
                bank_account_id: feeSelections[fee.id].bank_account_id
                  ? Number(feeSelections[fee.id].bank_account_id)
                  : undefined,
              })),
            scholarships: selectedFees
              .filter((fee) => feeSelections[fee.id]?.action === "scholarship")
              .map((fee) => ({
                fee_structure_id: fee.id,
                reason: feeSelections[fee.id].scholarship_reason || "Scholarship",
              })),
            // Filed server-side BEFORE invoicing, so the first bill is
            // already discounted (fees.manage staff only).
            ...(canGrantConcession && concession.enabled
              ? {
                  concession: {
                    category: concession.category,
                    discount_type: concession.discount_type,
                    discount_value:
                      concession.discount_type === "full_scholarship"
                        ? undefined
                        : Number(concession.discount_value),
                    reason: concession.reason || undefined,
                  },
                }
              : {}),
          }
        : {}),
    }
  }

  async function onSubmit(values: RegistrationValues) {
    // Only the review step may submit. Without this, the browser's implicit
    // submission (Enter key, or the Next button's click landing on the
    // re-rendered submit button) would register the student prematurely.
    if (step !== STEP_KEYS.length - 1) return

    // Scholarship reasons are collected outside RHF — check before submitting.
    const missingReason = applicableFees.some(
      (fee) =>
        feeSelections[fee.id]?.selected &&
        feeSelections[fee.id].action === "scholarship" &&
        !feeSelections[fee.id].scholarship_reason.trim(),
    )
    if (missingReason) {
      toast.error(t("wizard.scholarshipReasonRequired"))
      return
    }

    if (missingPayNowAccount()) {
      setStep(2)
      toast.error(t("wizard.payAccountRequired"))
      return
    }

    // The concession lives outside RHF too — a percent/fixed grant needs a value.
    if (
      canGrantConcession &&
      concession.enabled &&
      concession.discount_type !== "full_scholarship" &&
      !(Number(concession.discount_value) > 0)
    ) {
      toast.error(t("wizard.concessionValueRequired"))
      return
    }

    setSubmitting(true)
    try {
      let studentId = savedId

      if (studentId === null) {
        const res = await apiFetch<{ data: Student }>("/students", {
          method: "POST",
          body: buildBody(values),
        })
        studentId = res.data.id
        setSavedId(studentId)
      }

      if (photo) {
        const photoForm = new FormData()
        photoForm.append("photo", photo)
        await apiFetch(`/students/${studentId}/photo`, { method: "POST", body: photoForm })
        setPhoto(null)
      }

      for (const doc of documents) {
        const docForm = new FormData()
        docForm.append("name", doc.name || doc.file.name)
        if (doc.category) docForm.append("category", doc.category)
        docForm.append("file", doc.file)
        await apiFetch(`/students/${studentId}/attachments`, { method: "POST", body: docForm })
        setDocuments((prev) => prev.filter((d) => d.id !== doc.id))
      }

      // Guardian profile files go up once parent ids are known — search-mode
      // rows already carry theirs; new-mode rows are matched by the phone that
      // provisioned the parent. Uploaded files are cleared from the form so a
      // retry after a failure never duplicates them.
      const guardianRows = values.guardians
      if (guardianRows.some((row) => row.photo || row.documents.length > 0)) {
        const linked = await apiFetch<{ data: Guardian[] }>(`/students/${studentId}/guardians`)
        for (const [i, row] of guardianRows.entries()) {
          const parentId =
            row.mode === "search"
              ? Number(row.parent_id)
              : linked.data.find((g) => g.phone && g.phone === row.phone?.trim())?.parent_id
          if (!parentId) continue

          if (row.photo) {
            const photoBody = new FormData()
            photoBody.append("photo", row.photo)
            await apiFetch(`/parents/${parentId}/photo`, { method: "POST", body: photoBody })
            form.setValue(`guardians.${i}.photo`, null)
          }
          for (const doc of row.documents) {
            const docBody = new FormData()
            docBody.append("name", doc.name || doc.file.name)
            if (doc.category) docBody.append("category", doc.category)
            docBody.append("file", doc.file)
            await apiFetch(`/parents/${parentId}/attachments`, { method: "POST", body: docBody })
            form.setValue(
              `guardians.${i}.documents`,
              form.getValues(`guardians.${i}.documents`).filter((d) => d.id !== doc.id),
            )
          }
        }
      }

      // Say out loud what happened invisibly: newly provisioned guardians
      // (and the student's own login) got their setup SMS.
      toast.success(t("wizard.registered"), {
        description: values.guardians.some((g) => g.mode === "new")
          ? t("wizard.registeredGuardianSms")
          : undefined,
      })
      router.push(`/students/${studentId}`)
    } catch (error) {
      if (error instanceof ApiError) {
        const fieldErrors = Object.entries(error.errors).map(([field, messages]) => {
          form.setError(field as keyof RegistrationValues, { type: "server", message: messages[0] })
          return { path: field, message: messages[0] }
        })
        if (savedId !== null) {
          toast.error(t("wizard.uploadFailed"))
        } else if (fieldErrors.length > 0) {
          reportErrors(fieldErrors)
        } else {
          toast.error(error.message)
        }
      } else {
        toast.error(tc("errors.generic"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  if (!canCreate || (!hasBranch && !needsBranch)) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("wizard.title")} backHref="/students" backLabel={t("title")} />
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {!canCreate ? tc("errors.forbidden") : t("noBranch")}
          </div>
        </div>
      </div>
    )
  }

  // School-wide: pick the destination branch before the wizard starts — the
  // years, sections and programs offered all belong to it.
  if (needsBranch && targetBranchId == null) {
    return (
      <div className="space-y-6">
        <PageHeader
          title={t("wizard.title")}
          description={t("wizard.subtitle")}
          backHref="/students"
          backLabel={t("title")}
        />
        <div className="page-gutter">
          <div className="max-w-md space-y-3 rounded-2xl border p-4">
            <p className="text-sm text-muted-foreground">{t("wizard.pickBranch")}</p>
            <BranchField value={targetBranchId} onChange={setTargetBranchId} />
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6 pb-6">
      <PageHeader
        title={t("wizard.title")}
        description={t("wizard.subtitle")}
        backHref="/students"
        backLabel={t("title")}
      />

      {/* Stepper */}
      <div className="page-gutter">
        <ol className="flex items-center gap-1.5 overflow-x-auto pb-1">
          {STEP_KEYS.map((key, index) => (
            <li key={key} className="flex shrink-0 items-center gap-1.5">
              <button
                type="button"
                onClick={() => index < step && setStep(index)}
                disabled={index > step}
                className={cn(
                  "relative flex h-8 items-center gap-1.5 rounded-full px-3 text-xs font-medium transition-colors",
                  index === step
                    ? "bg-primary text-primary-foreground"
                    : index < step
                      ? "bg-primary/10 text-primary"
                      : "bg-muted text-muted-foreground",
                  stepsWithErrors.has(index) && index !== step && "ring-2 ring-destructive/50",
                )}
              >
                {index < step ? <Check className="size-3" /> : <span>{index + 1}</span>}
                <span className={cn(index !== step && "hidden sm:inline")}>
                  {t(`wizard.steps.${key}`)}
                </span>
                {stepsWithErrors.has(index) ? (
                  <span
                    className="absolute -right-0.5 -top-0.5 size-2.5 rounded-full border-2 border-background bg-destructive"
                    aria-hidden
                  />
                ) : null}
              </button>
              {index < STEP_KEYS.length - 1 ? (
                <span className="h-px w-3 shrink-0 bg-border" aria-hidden />
              ) : null}
            </li>
          ))}
        </ol>
      </div>

      <div className="page-gutter">
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(onSubmit, (errors) => reportErrors(flattenErrors(errors)))}
            // A flex column with a viewport-derived min-height keeps the nav
            // bar's natural position at the bottom of the screen on EVERY
            // step (mt-auto), so it never jumps up on short steps; sticky
            // takes over on long ones.
            className="mx-auto flex min-h-[calc(100svh-21rem)] max-w-3xl flex-col gap-6 md:min-h-[calc(100svh-16rem)]"
            noValidate
          >
            <div className={cn("space-y-8", step !== 0 && "hidden")}>
              <StepIdentity form={form} photo={photo} onPhotoChange={setPhoto} />
              <div className="space-y-4 border-t pt-6">
                <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("wizard.steps.addresses")}
                </h2>
                <StepAddresses form={form} />
              </div>
              {/* Health + documents: optional at the desk, collapsed by default —
                  the queue moves on; the profile finishes the file later. */}
              <div className="rounded-2xl border border-dashed">
                <button
                  type="button"
                  onClick={() => setMoreOpen((open) => !open)}
                  aria-expanded={moreOpen}
                  className="flex min-h-11 w-full items-center gap-2.5 px-4 py-3 text-left"
                >
                  <span className="flex-1">
                    <span className="block text-sm font-medium">{t("wizard.moreDetails")}</span>
                    <span className="text-muted-foreground block text-xs">
                      {moreSummary || t("wizard.moreDetailsHint")}
                    </span>
                  </span>
                  <ChevronDown
                    className={cn(
                      "text-muted-foreground size-4 shrink-0 transition-transform",
                      moreOpen && "rotate-180",
                    )}
                  />
                </button>
                {moreOpen && (
                  <div className="space-y-6 border-t border-dashed p-4">
                    <div className="space-y-4">
                      <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {t("wizard.steps.health")}
                      </h3>
                      <StepHealth form={form} conditions={conditions} />
                    </div>
                    <div className="space-y-4">
                      <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {t("wizard.steps.documents")}
                      </h3>
                      <StepDocuments documents={documents} onDocumentsChange={setDocuments} />
                    </div>
                  </div>
                )}
              </div>
            </div>
            <div className={cn(step !== 1 && "hidden")}>
              <StepGuardians form={form} />
            </div>
            <div className={cn(step !== 2 && "hidden")}>
              <StepEnrollmentFees
                form={form}
                academicYears={academicYears}
                gradeLevels={gradeLevels}
                sections={sections}
                programs={programs}
                applicableFees={applicableFees}
                onApplicableFees={setApplicableFees}
                feeSelections={feeSelections}
                onFeeSelections={setFeeSelections}
                accounts={accounts}
                canGrantConcession={canGrantConcession}
                concession={concession}
                onConcession={setConcession}
              />
            </div>
            {step === 3 ? (
              <StepReview
                form={form}
                photo={photo}
                documents={documents}
                conditions={conditions}
                academicYears={academicYears}
                gradeLevels={gradeLevels}
                sections={sections}
                applicableFees={applicableFees}
                feeSelections={feeSelections}
                concession={canGrantConcession ? concession : null}
                onEdit={(target) => {
                  // -1 = the health/documents disclosure inside the Student step.
                  if (target === -1) {
                    setStep(0)
                    setMoreOpen(true)
                  } else {
                    setStep(target)
                  }
                  window.scrollTo({ top: 0 })
                }}
              />
            ) : null}

            {/* Sticky footer nav — floats inside the content column (never over
                the sidebar) and clears the mobile bottom tab bar. */}
            <div className="sticky bottom-24 z-10 mt-auto md:bottom-4">
              <div className="flex items-center justify-between gap-3 rounded-2xl border bg-background/95 px-4 py-3 shadow-lg backdrop-blur supports-[backdrop-filter]:bg-background/85">
                <Button
                  type="button"
                  variant="outline"
                  className="h-11 rounded-full px-5"
                  onClick={back}
                  loading={submitting} disabled={step === 0}
                >
                  <ArrowLeft className="size-4" />
                  {tc("actions.back")}
                </Button>
                {/* Distinct keys force a remount so React never flips the same
                    DOM button from type=button to type=submit mid-click (which
                    made the browser submit as soon as the review step opened). */}
                {step < STEP_KEYS.length - 1 ? (
                  <Button
                    key="next"
                    type="button"
                    className="h-11 flex-1 rounded-full sm:max-w-[240px]"
                    onClick={next}
                  >
                    {tc("actions.next")}
                    <ArrowRight className="size-4" />
                  </Button>
                ) : (
                  <Button
                    key="submit"
                    type="submit"
                    className="h-11 flex-1 rounded-full sm:max-w-[280px]"
                    disabled={submitting}
                  >
                    {submitting ? <Loader2 className="size-4 animate-spin" /> : <Check className="size-4" />}
                    {t("wizard.submit")}
                  </Button>
                )}
              </div>
            </div>
          </form>
        </Form>
      </div>
    </div>
  )
}
