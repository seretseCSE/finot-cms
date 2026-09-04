"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import {
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  BookOpen,
  Briefcase,
  Check,
  FileText,
  GraduationCap,
  IdCard,
  Loader2,
  MapPin,
  Wallet,
} from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useRef, useState } from "react"
import { useFieldArray, useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"

import {
  ACCEPTED_FILES,
  MAX_FILE_BYTES,
  ROLE_MAPPED_TITLES,
  STEP_FIELDS,
  WIZARD_STEPS,
  employeeDefaults,
  employeeSchema,
  flattenErrors,
  stepForField,
  toFormValues,
  type EmployeeFormValues,
  type WizardStepKey,
} from "@/components/employees/wizard/schema"
import { AddressStep } from "@/components/employees/wizard/steps/address-step"
import { CompensationStep } from "@/components/employees/wizard/steps/compensation-step"
import { DocumentsStep } from "@/components/employees/wizard/steps/documents-step"
import { IdentityStep } from "@/components/employees/wizard/steps/identity-step"
import { PositionsStep } from "@/components/employees/wizard/steps/positions-step"
import { QualificationsStep } from "@/components/employees/wizard/steps/qualifications-step"
import { ReviewStep } from "@/components/employees/wizard/steps/review-step"
import type {
  AccountPolicy,
  AttachmentProps,
  DraftFile,
} from "@/components/employees/wizard/steps/shared"
import { TeachingStep } from "@/components/employees/wizard/steps/teaching-step"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { useFileDrop } from "@/components/ui/dropzone"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { Form } from "@/components/ui/form"
import { useMediaPreview } from "@/components/ui/media-preview"
import { PageHeader } from "@/components/ui/page-header"
import { ApiError, apiFetch } from "@/lib/api"
import { loadCountries } from "@/lib/data"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { Employee, EmployeeAttachment, GradeLevel, Paginated, Subject } from "@/lib/types"
import { cn } from "@/lib/utils"

const STEP_ICONS: Record<WizardStepKey, React.ComponentType<{ className?: string }>> = {
  identity: IdCard,
  address: MapPin,
  positions: Briefcase,
  teaching: BookOpen,
  qualifications: GraduationCap,
  compensation: Wallet,
  documents: FileText,
  review: BadgeCheck,
}

interface Props {
  /** Present = edit mode; absent = hire (create) mode. */
  employee?: Employee | null
  /** Land on a specific step (profile deep-links, edit mode only). */
  initialStep?: string
}

/**
 * Full-screen staff hire/edit wizard — replaces the old 2,000-line sheet.
 * Create mode walks the steps linearly (validated per step, review at the
 * end); edit mode unlocks every step and saves from anywhere. Mobile gets the
 * same app-like layout as the student registration wizard: pill stepper,
 * one-column steps, sticky bottom nav clearing the tab bar.
 *
 * This component owns the wizard's STATE — the form, the step cursor, the
 * catalogs, the account policy, the staged files and the save. Each step's
 * markup lives in `./steps/`, taking what it needs as explicit props. Steps
 * stay mounted and hide themselves so a half-filled step is never unmounted
 * mid-flow (and so RHF keeps every field registered for validation).
 */
export function EmployeeWizard({ employee, initialStep }: Props) {
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")
  const router = useRouter()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { openPreview, previewDialog } = useMediaPreview()
  const isEdit = !!employee

  // School-wide workspace: hiring requires naming the target branch first.
  const { needsBranch } = useBranchScope()
  const [targetBranchId, setTargetBranchId] = useState<number | null>(null)
  const branchReady = isEdit || !needsBranch || targetBranchId != null

  const form = useForm<EmployeeFormValues>({
    resolver: zodResolver(employeeSchema),
    defaultValues: employee ? toFormValues(employee) : employeeDefaults,
    mode: "onTouched",
  })
  useLiveValidation(form)

  const positionArray = useFieldArray({
    control: form.control,
    name: "positions",
  })
  const qualificationArray = useFieldArray({
    control: form.control,
    name: "qualifications",
  })
  const allowanceArray = useFieldArray({
    control: form.control,
    name: "allowances",
  })
  const deductionArray = useFieldArray({
    control: form.control,
    name: "deductions",
  })

  const watched = useWatch({ control: form.control })
  const watchedPositions = watched.positions ?? []
  const activeTitles = watchedPositions
    .filter((p) => p?.job_title && !p?.ended_on)
    .map((p) => p!.job_title as string)
  const isTeacher = activeTitles.includes("teacher")
  const teacherSubjects = watched.teacher_subjects ?? []

  // ── Steps ──────────────────────────────────────────────────────────────
  const steps = WIZARD_STEPS.filter((key) => {
    if (key === "teaching") return isTeacher
    if (key === "review") return !isEdit
    return true
  })
  const [step, setStep] = useState(0)
  // Deep-link (?step=positions): resolved once against the visible step list.
  const deepLinked = useRef(false)
  useEffect(() => {
    if (deepLinked.current || !initialStep) return
    const index = steps.indexOf(initialStep as WizardStepKey)
    if (index !== -1) {
      deepLinked.current = true
      // eslint-disable-next-line react-hooks/set-state-in-effect -- URL → state sync
      setStep(index)
    }
  }, [initialStep, steps])
  const stepKey = steps[Math.min(step, steps.length - 1)]

  const stepsWithErrors = new Set(
    flattenErrors(form.formState.errors).map(({ path }) => stepForField(path, steps))
  )

  // ── Catalogs ───────────────────────────────────────────────────────────
  const [nationalities, setNationalities] = useState<string[]>([])
  useEffect(() => {
    loadCountries()
      .then((countries) => setNationalities(countries.map((c) => c.nationality)))
      .catch(() => {})
  }, [])

  const [subjects, setSubjects] = useState<Subject[]>([])
  const [grades, setGrades] = useState<GradeLevel[]>([])
  useEffect(() => {
    if (!isTeacher || subjects.length > 0) return
    Promise.all([
      apiFetch<Paginated<Subject>>("/subjects?per_page=100"),
      apiFetch<Paginated<GradeLevel>>("/grade-levels"),
    ])
      .then(([subjectsRes, gradesRes]) => {
        setSubjects(subjectsRes.data.filter((s) => s.is_active))
        setGrades(gradesRes.data)
      })
      .catch(() => {})
  }, [isTeacher, subjects.length])

  // Grades that have at least one teaching-capability row, for the picker.
  const [teachingGradeIds, setTeachingGradeIds] = useState<number[]>(() =>
    Array.from(new Set((employee?.teacher_subjects ?? []).map((ts) => ts.grade_level_id)))
  )

  // ── Portal-account policy (settings-gated, per target branch) ──────────
  const [policy, setPolicy] = useState<AccountPolicy | null>(null)
  const policyBranchId = isEdit ? employee?.branch_id : targetBranchId
  useEffect(() => {
    if (!branchReady) return
    const query = policyBranchId != null ? `?branch_id=${policyBranchId}` : ""
    apiFetch<{ data: AccountPolicy }>(`/employees/account-policy${query}`)
      .then((res) => setPolicy(res.data))
      .catch(() => setPolicy(null))
  }, [branchReady, policyBranchId])

  const hasAccount = isEdit && employee?.user_id != null
  const accountRequired = activeTitles.some((title) => ROLE_MAPPED_TITLES.includes(title))
  const accountEligible =
    accountRequired ||
    (policy != null && activeTitles.some((title) => policy.account_job_titles.includes(title)))
  // The checkbox only exists where there is a real choice.
  const showAccountChoice = !hasAccount && accountEligible && !accountRequired
  const willCreateAccount =
    !hasAccount && (accountRequired || (accountEligible && watched.create_user_account !== false))

  // ── Photo ──────────────────────────────────────────────────────────────
  const [photoFile, setPhotoFile] = useState<File | null>(null)
  const photoInputRef = useRef<HTMLInputElement>(null)
  const photoPreview = useMemo(
    () => (photoFile ? URL.createObjectURL(photoFile) : null),
    [photoFile]
  )
  useEffect(() => {
    return () => {
      if (photoPreview) URL.revokeObjectURL(photoPreview)
    }
  }, [photoPreview])

  // ── Attachments ────────────────────────────────────────────────────────
  const [attachments, setAttachments] = useState<EmployeeAttachment[]>(employee?.attachments ?? [])
  const [drafts, setDrafts] = useState<DraftFile[]>([])
  const [removingId, setRemovingId] = useState<number | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const pendingAnchorRef = useRef<string>("")

  // Set after a successful create so a retry (e.g. after a failed file
  // upload) updates the same record instead of creating a duplicate.
  const [savedId, setSavedId] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)

  /** Staged against the record the user attached them to (or "" = general). */
  function addFiles(files: File[], anchor: string) {
    const next: DraftFile[] = files.map((file) => ({
      name: file.name.replace(/\.[^.]+$/, ""),
      file,
      anchor,
    }))
    if (next.length > 0) setDrafts((prev) => [...prev, ...next])
  }

  // The picker is shared by every step, so its files belong to whichever
  // record asked for it last; dropped files carry their own anchor instead.
  const { takeFiles } = useFileDrop({
    accept: ACCEPTED_FILES,
    maxSize: MAX_FILE_BYTES,
    multiple: true,
    onFiles: (files) => addFiles(files, pendingAnchorRef.current),
  })

  function pickFilesFor(anchor: string) {
    pendingAnchorRef.current = anchor
    fileInputRef.current?.click()
  }

  async function removeExisting(attachment: EmployeeAttachment) {
    if (!employee) return
    setRemovingId(attachment.id)
    try {
      await apiFetch(`/employees/${employee.id}/attachments/${attachment.id}`, {
        method: "DELETE",
      })
      setAttachments((prev) => prev.filter((a) => a.id !== attachment.id))
      toast.success(t("attachments.removed"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("error"))
    } finally {
      setRemovingId(null)
    }
  }

  /** The attachment plumbing every file-bearing step needs. */
  const attachmentProps: AttachmentProps = {
    attachments,
    drafts,
    setDrafts,
    removingId,
    onRemoveExisting: removeExisting,
    onPickFiles: pickFilesFor,
    onDropFiles: addFiles,
    onPreview: openPreview,
    confirmDelete,
  }

  /**
   * Upload pending files one by one; on a failure, keep the failed file (and
   * the rest) as drafts so the user can retry from the still-open wizard.
   */
  async function uploadDrafts(employeeId: number, saved: Employee): Promise<boolean> {
    const values = form.getValues()
    const resolve = (anchor: string): { position?: number; qualification?: number } => {
      if (anchor.startsWith("p:")) return { position: Number(anchor.slice(2)) }
      if (anchor.startsWith("q:")) return { qualification: Number(anchor.slice(2)) }
      if (anchor.startsWith("pi:")) {
        const row = values.positions?.[Number(anchor.slice(3))]
        const match = (saved.positions ?? []).find((p) => p.job_title === row?.job_title)
        return match?.id ? { position: match.id } : {}
      }
      if (anchor.startsWith("qi:")) {
        const row = values.qualifications?.[Number(anchor.slice(3))]
        const match = (saved.qualifications ?? []).find(
          (q) =>
            q.education_level === row?.education_level &&
            (q.field_of_study ?? "") === (row?.field_of_study ?? "")
        )
        return match?.id ? { qualification: match.id } : {}
      }
      return {}
    }

    const pending = [...drafts]
    while (pending.length > 0) {
      const draft = pending[0]
      const body = new FormData()
      body.append("name", draft.name || draft.file.name)
      body.append("file", draft.file)
      const anchor = resolve(draft.anchor)
      if (anchor.position) body.append("employee_position_id", String(anchor.position))
      if (anchor.qualification)
        body.append("employee_qualification_id", String(anchor.qualification))
      try {
        await apiFetch(`/employees/${employeeId}/attachments`, {
          method: "POST",
          body,
        })
        pending.shift()
        setDrafts([...pending])
      } catch (error) {
        toast.error(
          error instanceof ApiError && error.message
            ? error.message
            : t("attachments.uploadFailed", { name: draft.name })
        )
        return false
      }
    }
    return true
  }

  // ── Navigation ─────────────────────────────────────────────────────────
  async function next() {
    const valid = await form.trigger(STEP_FIELDS[stepKey], {
      shouldFocus: true,
    })
    if (!valid) return
    setStep((s) => Math.min(s + 1, steps.length - 1))
    window.scrollTo({ top: 0 })
  }

  function back() {
    setStep((s) => Math.max(s - 1, 0))
    window.scrollTo({ top: 0 })
  }

  function goTo(index: number) {
    setStep(index)
    window.scrollTo({ top: 0 })
  }

  /** Jump to the FIRST broken step and toast the offending fields. */
  function reportErrors(errors: { path: string; message: string }[]) {
    if (errors.length === 0) return
    const first = errors.reduce(
      (min, e) => Math.min(min, stepForField(e.path, steps)),
      steps.length - 1
    )
    goTo(first)

    toast.error(t("wizard.checkErrors"), {
      description: (
        <ul className="mt-1 space-y-1">
          {errors.slice(0, 5).map(({ path, message }) => {
            const parts = path.split(".")
            const ordinal =
              parts.length > 1 && /^\d+$/.test(parts[1]) ? ` ${Number(parts[1]) + 1}` : ""
            const field = parts.filter((p) => !/^\d+$/.test(p)).pop() ?? path
            return (
              <li key={path}>
                <span className="font-medium">
                  {t(`wizard.steps.${steps[stepForField(path, steps)]}`)}
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

  // ── Submit ─────────────────────────────────────────────────────────────
  function buildBody(values: EmployeeFormValues): Record<string, unknown> {
    return {
      first_name: values.first_name,
      father_name: values.father_name || undefined,
      grandfather_name: values.grandfather_name || undefined,
      gender: values.gender || null,
      birth_date: values.birth_date || null,
      email: values.email || null,
      marital_status: values.marital_status || null,
      nationality: values.nationality || null,
      state: values.state || null,
      city: values.city || null,
      sub_city: values.sub_city || null,
      woreda: values.woreda || null,
      house_no: values.house_no || null,
      professional_level: values.professional_level || null,
      retirement_on: values.retirement_on || null,
      check_in: values.check_in || null,
      check_out: values.check_out || null,
      positions: values.positions.map((p) => ({
        id: p.id,
        job_title: p.job_title,
        employment_type: p.employment_type,
        salary: p.salary ? Number(p.salary) : null,
        salary_level: p.salary_level ? Number(p.salary_level) : null,
        hired_on: p.hired_on || null,
        last_promoted_on: p.last_promoted_on || null,
        ended_on: p.ended_on || null,
        is_primary: p.is_primary,
      })),
      qualifications: (values.qualifications ?? []).map((q) => ({
        id: q.id,
        education_level: q.education_level,
        field_of_study: q.field_of_study || null,
        institution: q.institution || null,
        graduation_year: q.graduation_year ? Number(q.graduation_year) : null,
      })),
      allowances: (values.allowances ?? []).map((a) => ({
        name: a.name,
        amount: Number(a.amount),
      })),
      deductions: (values.deductions ?? []).map((d) => ({
        name: d.name,
        amount: Number(d.amount),
      })),
      teacher_subjects: values.teacher_subjects ?? [],
      // Only sent where the person actually has the choice — role-mapped
      // titles always provision server-side, ineligible ones never do.
      ...(showAccountChoice ? { create_user_account: values.create_user_account } : {}),
    }
  }

  async function onSubmit(values: EmployeeFormValues) {
    // Only the review step may submit in create mode (Enter-key guard).
    if (!isEdit && stepKey !== "review") return

    const targetId = employee?.id ?? savedId
    const isUpdate = targetId != null

    setSubmitting(true)
    try {
      const body = isUpdate
        ? { ...buildBody(values), phone: values.phone }
        : {
            ...buildBody(values),
            phone: values.phone,
            ...(targetBranchId != null ? { branch_id: targetBranchId } : {}),
          }

      const res = await apiFetch<{ data: Employee }>(
        isUpdate ? `/employees/${targetId}` : "/employees",
        { method: isUpdate ? "PUT" : "POST", body }
      )
      if (!isEdit) setSavedId(res.data.id)

      let photoUploaded = true
      if (photoFile) {
        const photoBody = new FormData()
        photoBody.append("photo", photoFile)
        try {
          await apiFetch(`/employees/${res.data.id}/photo`, {
            method: "POST",
            body: photoBody,
          })
          setPhotoFile(null)
        } catch (error) {
          toast.error(
            error instanceof ApiError && error.message ? error.message : t("photo.uploadFailed")
          )
          photoUploaded = false
        }
      }

      const uploaded = drafts.length === 0 || (await uploadDrafts(res.data.id, res.data))

      // On an upload failure the wizard stays open with the failed files still
      // listed, so they can be retried by saving again.
      if (uploaded && photoUploaded) {
        toast.success(
          isEdit ? t("updated") : willCreateAccount ? t("createdWithAccount") : t("created")
        )
        router.push(`/employees/${res.data.id}`)
      }
    } catch (error) {
      if (error instanceof ApiError) {
        const fieldErrors = Object.entries(error.errors).map(([field, messages]) => {
          form.setError(field as keyof EmployeeFormValues, {
            type: "server",
            message: messages[0],
          })
          return { path: field, message: messages[0] }
        })
        if (fieldErrors.length > 0) reportErrors(fieldErrors)
        else toast.error(error.message)
      } else {
        toast.error(tc("errors.generic"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  // ── Position helpers ───────────────────────────────────────────────────
  function setPrimary(index: number) {
    watchedPositions.forEach((_, i) => {
      form.setValue(`positions.${i}.is_primary`, i === index, {
        shouldValidate: true,
      })
    })
  }

  function toggleTeacherSubject(subjectId: number, gradeLevelId: number) {
    const current = form.getValues("teacher_subjects") ?? []
    const exists = current.some(
      (ts) => ts.subject_id === subjectId && ts.grade_level_id === gradeLevelId
    )
    form.setValue(
      "teacher_subjects",
      exists
        ? current.filter(
            (ts) => !(ts.subject_id === subjectId && ts.grade_level_id === gradeLevelId)
          )
        : [...current, { subject_id: subjectId, grade_level_id: gradeLevelId }]
    )
  }

  // School-wide: pick the destination branch before the wizard starts.
  if (!isEdit && needsBranch && targetBranchId == null) {
    return (
      <div className="space-y-6">
        <PageHeader
          title={t("wizard.title")}
          description={t("wizard.subtitle")}
          backHref="/employees"
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

  const fullName = [watched.first_name, watched.father_name, watched.grandfather_name]
    .filter(Boolean)
    .join(" ")

  return (
    <div className="space-y-6 pb-6">
      {confirmDialog}
      {previewDialog}
      <PageHeader
        title={isEdit ? employee!.full_name : t("wizard.title")}
        description={isEdit ? t("wizard.editSubtitle") : t("wizard.subtitle")}
        backHref={isEdit ? `/employees/${employee!.id}` : "/employees"}
        backLabel={isEdit ? employee!.full_name : t("title")}
      />

      {/* Stepper */}
      <div className="page-gutter">
        <ol className="flex items-center gap-1.5 overflow-x-auto pb-1">
          {steps.map((key, index) => {
            const Icon = STEP_ICONS[key]
            return (
              <li key={key} className="flex shrink-0 items-center gap-1.5">
                <button
                  type="button"
                  onClick={() => (isEdit || index < step) && goTo(index)}
                  disabled={!isEdit && index > step}
                  className={cn(
                    "relative flex h-8 items-center gap-1.5 rounded-full px-3 text-xs font-medium transition-colors",
                    index === step
                      ? "bg-primary text-primary-foreground"
                      : isEdit || index < step
                        ? "bg-primary/10 text-primary"
                        : "bg-muted text-muted-foreground",
                    stepsWithErrors.has(index) && index !== step && "ring-2 ring-destructive/50"
                  )}
                >
                  {!isEdit && index < step ? (
                    <Check className="size-3" />
                  ) : (
                    <Icon className="size-3.5" />
                  )}
                  <span className={cn(index !== step && "hidden sm:inline")}>
                    {t(`wizard.steps.${key}`)}
                  </span>
                  {stepsWithErrors.has(index) ? (
                    <span
                      className="absolute -top-0.5 -right-0.5 size-2.5 rounded-full border-2 border-background bg-destructive"
                      aria-hidden
                    />
                  ) : null}
                </button>
                {index < steps.length - 1 ? (
                  <span className="h-px w-3 shrink-0 bg-border" aria-hidden />
                ) : null}
              </li>
            )
          })}
        </ol>
      </div>

      <div className="page-gutter">
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(onSubmit, (errors) => reportErrors(flattenErrors(errors)))}
            className="mx-auto flex min-h-[calc(100svh-21rem)] max-w-3xl flex-col gap-6 md:min-h-[calc(100svh-16rem)]"
            noValidate
          >
            {/* Shared picker for every attach button (positions,
                qualifications, general) — must stay mounted on every step. */}
            <input
              ref={fileInputRef}
              type="file"
              accept={ACCEPTED_FILES}
              multiple
              className="hidden"
              onChange={(e) => {
                takeFiles(e.target.files)
                e.target.value = ""
              }}
            />

            <IdentityStep
              active={stepKey === "identity"}
              form={form}
              employee={employee}
              photoFile={photoFile}
              setPhotoFile={setPhotoFile}
              photoPreview={photoPreview}
              photoInputRef={photoInputRef}
              nationalities={nationalities}
            />

            <AddressStep active={stepKey === "address"} />

            <PositionsStep
              active={stepKey === "positions"}
              form={form}
              positionArray={positionArray}
              watchedPositions={watchedPositions}
              isEdit={isEdit}
              employee={employee}
              account={{ hasAccount, accountRequired, showAccountChoice }}
              onSetPrimary={setPrimary}
              attachmentProps={attachmentProps}
            />

            {isTeacher && (
              <TeachingStep
                active={stepKey === "teaching"}
                form={form}
                subjects={subjects}
                grades={grades}
                teachingGradeIds={teachingGradeIds}
                setTeachingGradeIds={setTeachingGradeIds}
                teacherSubjects={teacherSubjects}
                onToggleSubject={toggleTeacherSubject}
              />
            )}

            <QualificationsStep
              active={stepKey === "qualifications"}
              form={form}
              qualificationArray={qualificationArray}
              watchedQualifications={watched.qualifications ?? []}
              attachmentProps={attachmentProps}
            />

            <CompensationStep
              active={stepKey === "compensation"}
              form={form}
              allowanceArray={allowanceArray}
              deductionArray={deductionArray}
            />

            <DocumentsStep active={stepKey === "documents"} {...attachmentProps} />

            {!isEdit && stepKey === "review" ? (
              <ReviewStep
                fullName={fullName}
                watched={watched}
                watchedPositions={watchedPositions}
                isTeacher={isTeacher}
                teacherSubjectCount={teacherSubjects.length}
                willCreateAccount={willCreateAccount}
                draftCount={drafts.length}
                onEdit={(key) => goTo(steps.indexOf(key))}
              />
            ) : null}

            {/* Sticky footer nav — floats inside the content column and
                clears the mobile bottom tab bar. */}
            <div className="sticky bottom-24 z-10 mt-auto md:bottom-4">
              <div className="flex items-center justify-between gap-3 rounded-2xl border bg-background/95 px-4 py-3 shadow-lg backdrop-blur supports-[backdrop-filter]:bg-background/85">
                {isEdit ? (
                  <>
                    <Button
                      type="button"
                      variant="outline"
                      className="h-11 rounded-full px-5"
                      onClick={() => router.push(`/employees/${employee!.id}`)}
                      disabled={submitting}
                    >
                      {tc("actions.cancel")}
                    </Button>
                    <Button
                      key="save"
                      type="submit"
                      className="h-11 flex-1 rounded-full sm:max-w-[280px]"
                      disabled={submitting}
                    >
                      {submitting ? (
                        <Loader2 className="size-4 animate-spin" />
                      ) : (
                        <Check className="size-4" />
                      )}
                      {tc("actions.save")}
                    </Button>
                  </>
                ) : (
                  <>
                    <Button
                      type="button"
                      variant="outline"
                      className="h-11 rounded-full px-5"
                      onClick={back}
                      loading={submitting}
                      disabled={step === 0}
                    >
                      <ArrowLeft className="size-4" />
                      {tc("actions.back")}
                    </Button>
                    {/* Distinct keys force a remount so React never flips the
                        same DOM button from type=button to type=submit
                        mid-click. */}
                    {step < steps.length - 1 ? (
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
                        {submitting ? (
                          <Loader2 className="size-4 animate-spin" />
                        ) : (
                          <Check className="size-4" />
                        )}
                        {t("wizard.submit")}
                      </Button>
                    )}
                  </>
                )}
              </div>
            </div>
          </form>
        </Form>
      </div>
    </div>
  )
}
