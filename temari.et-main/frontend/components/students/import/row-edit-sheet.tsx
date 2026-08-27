"use client"

import { AlertTriangle, Info, Loader2 } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { PhoneInput } from "@/components/ui/phone-input"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type {
  GradeLevel,
  ImportGuardianPayload,
  ImportRowPayload,
  Section,
  StudentImportRow,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const RELATIONSHIP_VALUES = [
  "father", "mother", "grandfather", "grandmother", "uncle", "aunt",
  "sibling", "legal_guardian", "other",
] as const

/** Payload field → its `import.fields.*` label key (shared with the banner). */
const FIELD_LABEL_KEYS: Record<string, string> = {
  first_name: "firstName",
  father_name: "fatherName",
  grandfather_name: "grandfatherName",
  mother_name: "motherName",
  gender: "gender",
  date_of_birth: "dateOfBirth",
  national_student_id: "nationalStudentId",
  fayda_id: "faydaId",
  primary_phone: "primaryPhone",
  email: "email",
  state: "state",
  city: "city",
  sub_city: "subCity",
  woreda: "woreda",
  house_no: "houseNo",
  grade_level_id: "grade",
  section_id: "section",
}

/** Fields the form renders inputs for — their errors show inline, not in the banner. */
const INLINE_FIELDS = new Set([
  "first_name", "father_name", "grandfather_name", "mother_name", "gender",
  "date_of_birth", "grade_level_id", "section_id", "primary_phone", "email",
])
const INLINE_GUARDIAN_FIELDS = new Set(["first_name", "father_name", "phone", "relationship"])

const GUARDIAN_FIELD = /^guardians\.(\d+)\.(.+)$/

function isInlineField(field: string | null | undefined): boolean {
  if (!field) return false
  const guardian = GUARDIAN_FIELD.exec(field)
  if (guardian) return INLINE_GUARDIAN_FIELDS.has(guardian[2])
  return INLINE_FIELDS.has(field)
}

interface RowEditSheetProps {
  importId: number
  row: StudentImportRow | null
  gradeLevels: GradeLevel[]
  sections: Section[]
  /** Draft imports allow editing; committed ones open read-only. */
  editable: boolean
  onOpenChange: (open: boolean) => void
  onSaved: (row: StudentImportRow) => void
}

/**
 * The validation grid's row editor: every issue the server raised, the
 * duplicate resolution when one was matched, and the row's payload as a
 * plain editable form. Saving revalidates server-side — the row returns
 * with its fresh verdict. Keyed by row id so each opening starts from the
 * row's current payload.
 */
export function RowEditSheet(props: RowEditSheetProps) {
  const { row, onOpenChange } = props

  return (
    <ResponsiveSheet open={row !== null} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        {row ? <RowEditForm key={row.id} {...props} row={row} /> : null}
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}

function RowEditForm({
  importId,
  row,
  gradeLevels,
  sections,
  editable,
  onOpenChange,
  onSaved,
}: RowEditSheetProps & { row: StudentImportRow }) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")

  const [payload, setPayload] = useState<ImportRowPayload>(() => structuredClone(row.payload))
  const [resolution, setResolution] = useState<string>(row.resolution ?? "skip")
  const [saving, setSaving] = useState(false)
  // The freshest server verdict — updated in place after each save, so a row
  // that is STILL broken shows its new errors right here instead of closing.
  const [verdict, setVerdict] = useState<StudentImportRow>(row)
  // Issues reflect the LAST saved verdict — once the operator edits a field,
  // its stale error stops showing (saving revalidates and brings fresh ones).
  const [touched, setTouched] = useState<Set<string>>(() => new Set())

  const set = (field: keyof ImportRowPayload, value: unknown) => {
    setTouched((prev) => new Set(prev).add(field))
    setPayload((prev) => ({ ...prev, [field]: value }))
  }

  const setGuardian = (index: number, field: keyof ImportGuardianPayload, value: unknown) => {
    setTouched((prev) => new Set(prev).add(`guardians.${index}.${field}`))
    setPayload((prev) => {
      const guardians = [...(prev.guardians ?? [])]
      guardians[index] = { ...(guardians[index] ?? {}), [field]: value }
      return { ...prev, guardians }
    })
  }

  const issueFor = (field: string) =>
    touched.has(field) ? [] : verdict.issues.filter((issue) => issue.field === field)

  const issueText = (issue: StudentImportRow["issues"][number]) => {
    const key = `import.issues.${issue.code}`
    const translated = t(key)
    return translated === key ? issue.message : translated
  }

  /** Human label for an issue's field — "Guardian 1 — phone", "Grade"… */
  const fieldLabel = (field: string | null | undefined): string | null => {
    if (!field) return null
    const guardian = GUARDIAN_FIELD.exec(field)
    if (guardian) {
      // Guardian subfields reuse the labels their inputs already carry.
      const subKeys: Record<string, string> = {
        first_name: "import.fields.firstName",
        father_name: "import.fields.fatherName",
        grandfather_name: "import.fields.grandfatherName",
        phone: "guardians.phone",
        secondary_phone: "guardians.secondaryPhone",
        email: "import.fields.email",
        relationship: "guardians.relationship",
        occupation: "wizard.occupation",
      }
      const subKey = subKeys[guardian[2]]
      const sub = subKey ? t(subKey) : guardian[2]
      return `${t("import.row.guardian", { number: Number(guardian[1]) + 1 })} — ${sub === subKey ? guardian[2] : sub}`
    }
    const key = FIELD_LABEL_KEYS[field]
    return key ? t(`import.fields.${key}`) : field
  }

  /** Inline messages under a field — the red border alone says nothing. */
  const fieldIssues = (field: string) => {
    const issues = issueFor(field)
    if (issues.length === 0) return null
    return (
      <div className="space-y-0.5">
        {issues.map((issue, index) => (
          <p
            key={index}
            className={cn(
              "text-xs font-medium",
              issue.level === "error" && "text-destructive",
              issue.level === "warning" && "text-amber-700 dark:text-amber-400",
              issue.level === "info" && "text-muted-foreground",
            )}
          >
            {issueText(issue)}
          </p>
        ))}
      </div>
    )
  }

  // Field-anchored errors render inline under their inputs; the banner keeps
  // only row-level issues and fields the form doesn't show (addresses…),
  // each prefixed with the field's name so nothing reads as a mystery.
  const bannerIssues = verdict.issues.filter((issue) => !isInlineField(issue.field))

  async function save() {
    setSaving(true)
    try {
      const res = await apiFetch<{ data: StudentImportRow }>(
        `/student-imports/${importId}/rows/${row.id}`,
        { method: "PATCH", body: { data: payload } },
      )
      let saved = res.data
      // A still-duplicate row keeps the chosen resolution.
      if (saved.status === "duplicate" && resolution !== (saved.resolution ?? "skip")) {
        const withResolution = await apiFetch<{ data: StudentImportRow }>(
          `/student-imports/${importId}/rows/${row.id}`,
          { method: "PATCH", body: { resolution } },
        )
        saved = withResolution.data
      }
      onSaved(saved)

      // Still broken after revalidation? Stay open and show the fresh
      // verdict where the operator can act on it — closing would strand
      // the errors on the grid.
      const stillBroken =
        saved.status === "error" || saved.issues.some((issue) => issue.level === "error")
      if (stillBroken) {
        setVerdict(saved)
        setTouched(new Set())
        toast.error(t("import.row.savedWithIssues"))
      } else {
        onOpenChange(false)
      }
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  const guardians: ImportGuardianPayload[] =
    (payload.guardians?.length ?? 0) > 0 ? payload.guardians! : [{}]

  return (
    <>
      <ResponsiveSheetHeader>
        <ResponsiveSheetTitle>{t("import.row.title", { number: row.row_number })}</ResponsiveSheetTitle>
      </ResponsiveSheetHeader>

      <ResponsiveSheetBody className="space-y-5">
        {/* Row-level issues — field-anchored ones render under their inputs. */}
        {bannerIssues.length > 0 || verdict.error ? (
          <div className="space-y-1.5">
            {verdict.error ? (
              <p className="flex items-start gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive">
                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                {verdict.error}
              </p>
            ) : null}
            {bannerIssues.map((issue, index) => {
              const label = fieldLabel(issue.field)
              return (
                <p
                  key={index}
                  className={cn(
                    "flex items-start gap-2 rounded-lg px-3 py-2 text-sm",
                    issue.level === "error" && "bg-destructive/10 text-destructive",
                    issue.level === "warning" && "bg-amber-500/10 text-amber-700 dark:text-amber-400",
                    issue.level === "info" && "bg-muted text-muted-foreground",
                  )}
                >
                  {issue.level === "info" ? (
                    <Info className="mt-0.5 size-4 shrink-0" />
                  ) : (
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                  )}
                  <span>
                    {label ? <span className="font-semibold">{label}: </span> : null}
                    {issueText(issue)}
                  </span>
                </p>
              )
            })}
          </div>
        ) : null}

        {/* Duplicate resolution */}
        {verdict.status === "duplicate" && verdict.duplicate_student ? (
          <div className="space-y-2 rounded-xl border p-3">
            <p className="text-sm font-medium">
              {t("import.row.duplicateOf", {
                name: verdict.duplicate_student.full_name,
                id: verdict.duplicate_student.public_id,
              })}
            </p>
            <div className="grid gap-2">
              {(["skip", "enroll_existing", "create"] as const).map((option) => (
                <label
                  key={option}
                  className={cn(
                    "flex cursor-pointer items-start gap-2.5 rounded-lg border p-2.5 text-sm",
                    resolution === option && "border-primary bg-primary/5",
                    !editable && "pointer-events-none opacity-70",
                  )}
                >
                  <input
                    type="radio"
                    name="resolution"
                    className="mt-1"
                    checked={resolution === option}
                    onChange={() => setResolution(option)}
                  />
                  <span>
                    <span className="font-medium">{t(`import.row.resolutions.${option}`)}</span>
                    <span className="block text-xs text-muted-foreground">
                      {t(`import.row.resolutions.${option}Hint`)}
                    </span>
                  </span>
                </label>
              ))}
            </div>
          </div>
        ) : null}

        {/* Student fields */}
        <div className="grid gap-3 sm:grid-cols-2">
          {(
            [
              ["first_name", "firstName"],
              ["father_name", "fatherName"],
              ["grandfather_name", "grandfatherName"],
              ["mother_name", "motherName"],
            ] as const
          ).map(([field, labelKey]) => (
            <div key={field} className="space-y-1.5">
              <Label>{t(`import.fields.${labelKey}`)}</Label>
              <Input
                value={(payload[field] as string | null) ?? ""}
                onChange={(event) => set(field, event.target.value || null)}
                disabled={!editable}
                className={cn(issueFor(field).length > 0 && "border-destructive")}
              />
              {fieldIssues(field)}
            </div>
          ))}

          <div className="space-y-1.5">
            <Label>{t("import.fields.gender")}</Label>
            <Select
              value={payload.gender ?? ""}
              onValueChange={(value) => set("gender", value)}
              disabled={!editable}
            >
              <SelectTrigger className={cn("w-full", issueFor("gender").length > 0 && "border-destructive")}>
                <SelectValue placeholder="—" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="male">{t("import.row.male")}</SelectItem>
                <SelectItem value="female">{t("import.row.female")}</SelectItem>
              </SelectContent>
            </Select>
            {fieldIssues("gender")}
          </div>

          <div className="space-y-1.5">
            <Label>{t("import.fields.dateOfBirth")}</Label>
            <DatePicker
              value={payload.date_of_birth ?? ""}
              onChange={(value) => set("date_of_birth", value || null)}
            />
            {fieldIssues("date_of_birth")}
          </div>

          <div className="space-y-1.5">
            <Label>{t("import.fields.grade")}</Label>
            <Select
              value={payload.grade_level_id != null ? String(payload.grade_level_id) : ""}
              onValueChange={(value) => {
                set("grade_level_id", Number(value))
                set("section_id", null)
              }}
              disabled={!editable}
            >
              <SelectTrigger
                className={cn("w-full", issueFor("grade_level_id").length > 0 && "border-destructive")}
              >
                <SelectValue placeholder="—" />
              </SelectTrigger>
              <SelectContent>
                {gradeLevels.map((grade) => (
                  <SelectItem key={grade.id} value={String(grade.id)}>
                    {grade.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {fieldIssues("grade_level_id")}
          </div>

          <div className="space-y-1.5">
            <Label>{t("import.fields.section")}</Label>
            <Select
              value={payload.section_id != null ? String(payload.section_id) : "none"}
              onValueChange={(value) => set("section_id", value === "none" ? null : Number(value))}
              disabled={!editable || payload.grade_level_id == null}
            >
              <SelectTrigger
                className={cn("w-full", issueFor("section_id").length > 0 && "border-destructive")}
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">{t("import.target.noSection")}</SelectItem>
                {sections
                  .filter((section) => section.grade_level_id === payload.grade_level_id)
                  .map((section) => (
                    <SelectItem key={section.id} value={String(section.id)}>
                      {section.name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
            {fieldIssues("section_id")}
          </div>

          <div className="space-y-1.5">
            <Label>{t("import.fields.primaryPhone")}</Label>
            <PhoneInput
              value={payload.primary_phone ?? ""}
              onChange={(value) => set("primary_phone", value || null)}
              disabled={!editable}
              className={cn(issueFor("primary_phone").length > 0 && "border-destructive")}
            />
            {fieldIssues("primary_phone")}
          </div>

          <div className="space-y-1.5">
            <Label>{t("import.fields.email")}</Label>
            <Input
              type="email"
              value={payload.email ?? ""}
              onChange={(event) => set("email", event.target.value || null)}
              disabled={!editable}
              className={cn(issueFor("email").length > 0 && "border-destructive")}
            />
            {fieldIssues("email")}
          </div>
        </div>

        {/* Guardians */}
        {guardians.map((guardian, index) => (
          <div key={index} className="space-y-3 rounded-xl border p-3">
            <p className="text-sm font-semibold">{t("import.row.guardian", { number: index + 1 })}</p>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>{t("import.fields.firstName")}</Label>
                <Input
                  value={guardian.first_name ?? ""}
                  onChange={(event) => setGuardian(index, "first_name", event.target.value || null)}
                  disabled={!editable}
                  className={cn(
                    issueFor(`guardians.${index}.first_name`).length > 0 && "border-destructive",
                  )}
                />
                {fieldIssues(`guardians.${index}.first_name`)}
              </div>
              <div className="space-y-1.5">
                <Label>{t("import.fields.fatherName")}</Label>
                <Input
                  value={guardian.father_name ?? ""}
                  onChange={(event) => setGuardian(index, "father_name", event.target.value || null)}
                  disabled={!editable}
                />
                {fieldIssues(`guardians.${index}.father_name`)}
              </div>
              <div className="space-y-1.5">
                <Label>{t("import.fields.g1Phone")}</Label>
                <PhoneInput
                  value={guardian.phone ?? ""}
                  onChange={(value) => setGuardian(index, "phone", value || null)}
                  disabled={!editable}
                  className={cn(issueFor(`guardians.${index}.phone`).length > 0 && "border-destructive")}
                />
                {fieldIssues(`guardians.${index}.phone`)}
              </div>
              <div className="space-y-1.5">
                <Label>{t("import.fields.g1Relationship")}</Label>
                <Select
                  // No cosmetic fallback: a missing relationship must LOOK
                  // missing, or the required error makes no sense.
                  value={guardian.relationship ?? ""}
                  onValueChange={(value) => setGuardian(index, "relationship", value)}
                  disabled={!editable}
                >
                  <SelectTrigger
                    className={cn(
                      "w-full",
                      issueFor(`guardians.${index}.relationship`).length > 0 && "border-destructive",
                    )}
                  >
                    <SelectValue placeholder="—" />
                  </SelectTrigger>
                  <SelectContent>
                    {RELATIONSHIP_VALUES.map((value) => (
                      <SelectItem key={value} value={value}>
                        {t(`import.row.relationships.${value}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {fieldIssues(`guardians.${index}.relationship`)}
              </div>
            </div>
          </div>
        ))}
      </ResponsiveSheetBody>

      <ResponsiveSheetFooter>
        <Button type="button" variant="outline" className="h-11 flex-1" onClick={() => onOpenChange(false)}>
          {tc("actions.cancel")}
        </Button>
        {editable ? (
          <Button type="button" className="h-11 flex-1" disabled={saving} onClick={() => void save()}>
            {saving ? <Loader2 className="size-4 animate-spin" /> : null}
            {tc("actions.save")}
          </Button>
        ) : null}
      </ResponsiveSheetFooter>
    </>
  )
}
