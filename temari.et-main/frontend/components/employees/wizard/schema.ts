import { z } from "zod"

import type { Employee } from "@/lib/types"
import { ethPhone } from "@/lib/validators"

export const EMPLOYMENT_TYPES = [
  "full_time",
  "part_time",
  "volunteer",
  "substitute",
  "contract",
] as const

export const MARITAL_STATUSES = ["single", "married", "divorced", "widowed"] as const

/** Salary levels are stored 1–10 but always displayed as roman numerals. */
export const ROMAN_LEVELS = ["I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X"]

/** Job titles whose kernel role REQUIRES a user account (JobTitles::ROLE_MAP). */
export const ROLE_MAPPED_TITLES = ["director", "teacher", "registrar", "finance_officer", "storekeeper"]

export const ACCEPTED_FILES = ".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
export const MAX_FILE_BYTES = 10 * 1024 * 1024

const positionSchema = z.object({
  id: z.number().optional(),
  job_title: z.string().min(1, "Pick a job title"),
  employment_type: z.enum(EMPLOYMENT_TYPES),
  salary: z
    .string()
    .optional()
    .refine((v) => !v || Number(v) >= 0, "Salary cannot be negative"),
  salary_level: z.string().optional(),
  // Required: leave entitlement grows with service (Art. 77), which cannot
  // be computed without a hire date.
  hired_on: z.string().min(1, "Hire date is required"),
  last_promoted_on: z.string().optional(),
  ended_on: z.string().optional(),
  is_primary: z.boolean(),
})

export const employeeSchema = z
  .object({
    first_name: z.string().min(1, "First name is required").max(255),
    father_name: z.string().max(255).optional(),
    grandfather_name: z.string().max(255).optional(),
    phone: ethPhone(),
    // Jobs — at least one current position
    positions: z.array(positionSchema).min(1, "Add at least one position"),
    /** Portal account at hire — only sent when the policy shows the choice. */
    create_user_account: z.boolean(),
    // Personal
    gender: z.string().optional(),
    birth_date: z.string().optional(),
    email: z.string().email("Enter a valid email").optional().or(z.literal("")),
    marital_status: z.string().optional(),
    nationality: z.string().optional(),
    // Address
    state: z.string().optional(),
    city: z.string().optional(),
    sub_city: z.string().optional(),
    woreda: z.string().optional(),
    house_no: z.string().optional(),
    // Person-level career facts
    professional_level: z.string().optional(),
    retirement_on: z.string().optional(),
    // Qualifications (many)
    qualifications: z
      .array(
        z.object({
          id: z.number().optional(),
          education_level: z.string().min(1, "Pick a level"),
          field_of_study: z.string().optional(),
          institution: z.string().optional(),
          graduation_year: z
            .string()
            .optional()
            .refine((v) => !v || (Number(v) >= 1950 && Number(v) <= 2100), "Enter a valid year"),
        }),
      )
      .optional(),
    // Compensation lines
    allowances: z
      .array(
        z.object({
          name: z.string().min(1, "Pick an allowance"),
          amount: z.string().min(1, "Amount is required"),
        }),
      )
      .optional(),
    deductions: z
      .array(
        z.object({
          name: z.string().min(1, "Name is required"),
          amount: z.string().min(1, "Amount is required"),
        }),
      )
      .optional(),
    // Teaching capability rows (teachers only)
    teacher_subjects: z
      .array(z.object({ subject_id: z.number(), grade_level_id: z.number() }))
      .optional(),
    // Attendance window
    check_in: z.string().optional(),
    check_out: z.string().optional(),
  })
  .superRefine((values, ctx) => {
    const active = values.positions.filter((p) => !p.ended_on)
    if (active.length === 0) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["positions"],
        message: "At least one position must be current (no end date)",
      })
      return
    }
    const jobTitles = active.map((p) => p.job_title)
    if (new Set(jobTitles).size !== jobTitles.length) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["positions"],
        message: "Each job title may only be held once at a time",
      })
    }
    if (active.filter((p) => p.is_primary).length !== 1) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["positions"],
        message: "Mark exactly one current position as primary",
      })
    }
  })

export type EmployeeFormValues = z.infer<typeof employeeSchema>
export type PositionValue = z.infer<typeof positionSchema>

export const emptyPosition: PositionValue = {
  job_title: "",
  employment_type: "full_time",
  salary: "",
  salary_level: "",
  hired_on: "",
  last_promoted_on: "",
  ended_on: "",
  is_primary: true,
}

export const employeeDefaults: EmployeeFormValues = {
  first_name: "",
  father_name: "",
  grandfather_name: "",
  phone: "",
  positions: [{ ...emptyPosition }],
  create_user_account: true,
  gender: "",
  birth_date: "",
  email: "",
  marital_status: "",
  nationality: "Ethiopian",
  // Addis Ababa is by far the most common answer — prefill, let others change
  // (same default as student registration).
  state: "Addis Ababa",
  city: "Addis Ababa",
  sub_city: "",
  woreda: "",
  house_no: "",
  professional_level: "",
  retirement_on: "",
  qualifications: [],
  allowances: [],
  deductions: [],
  teacher_subjects: [],
  check_in: "",
  check_out: "",
}

export function toFormValues(employee: Employee): EmployeeFormValues {
  return {
    ...employeeDefaults,
    first_name: employee.first_name,
    father_name: employee.father_name ?? "",
    grandfather_name: employee.grandfather_name ?? "",
    phone: employee.phone ?? "",
    positions:
      (employee.positions ?? []).length > 0
        ? (employee.positions ?? []).map((p) => ({
            id: p.id,
            job_title: p.job_title,
            employment_type: p.employment_type ?? "full_time",
            salary: p.salary ? String(p.salary) : "",
            salary_level: p.salary_level ? String(p.salary_level) : "",
            hired_on: p.hired_on ?? "",
            last_promoted_on: p.last_promoted_on ?? "",
            ended_on: p.ended_on ?? "",
            is_primary: p.is_primary,
          }))
        : [{ ...emptyPosition }],
    gender: employee.gender ?? "",
    birth_date: employee.birth_date ?? "",
    email: employee.email ?? "",
    marital_status: employee.marital_status ?? "",
    nationality: employee.nationality ?? "Ethiopian",
    state: employee.state ?? "",
    city: employee.city ?? "",
    sub_city: employee.sub_city ?? "",
    woreda: employee.woreda ?? "",
    house_no: employee.house_no ?? "",
    professional_level: employee.professional_level ?? "",
    retirement_on: employee.retirement_on ?? "",
    qualifications: (employee.qualifications ?? []).map((q) => ({
      id: q.id,
      education_level: q.education_level,
      field_of_study: q.field_of_study ?? "",
      institution: q.institution ?? "",
      graduation_year: q.graduation_year ? String(q.graduation_year) : "",
    })),
    allowances: (employee.allowances ?? []).map((a) => ({
      name: a.name,
      amount: String(a.amount),
    })),
    deductions: (employee.deductions ?? []).map((d) => ({
      name: d.name,
      amount: String(d.amount),
    })),
    teacher_subjects: (employee.teacher_subjects ?? []).map((ts) => ({
      subject_id: ts.subject_id,
      grade_level_id: ts.grade_level_id,
    })),
    check_in: employee.check_in ?? "",
    check_out: employee.check_out ?? "",
  }
}

/** The wizard's steps, in order. `teaching` only renders for teachers. */
export const WIZARD_STEPS = [
  "identity",
  "address",
  "positions",
  "teaching",
  "qualifications",
  "compensation",
  "documents",
  "review",
] as const

export type WizardStepKey = (typeof WIZARD_STEPS)[number]

/** Which top-level fields each step owns — drives per-step validation and
 * the "jump to the first broken step" behaviour on a failed submit. */
export const STEP_FIELDS: Record<WizardStepKey, (keyof EmployeeFormValues)[]> = {
  identity: [
    "first_name",
    "father_name",
    "grandfather_name",
    "phone",
    "gender",
    "birth_date",
    "email",
    "marital_status",
    "nationality",
  ],
  address: ["state", "city", "sub_city", "woreda", "house_no"],
  positions: ["positions", "create_user_account"],
  teaching: ["teacher_subjects"],
  qualifications: ["qualifications"],
  compensation: [
    "professional_level",
    "retirement_on",
    "allowances",
    "deductions",
    "check_in",
    "check_out",
  ],
  // Documents are staged in local state (not RHF) — nothing to validate.
  documents: [],
  review: [],
}

/** Which step owns a (possibly nested) field path — "positions.2.salary" → positions. */
export function stepForField(path: string, steps: readonly WizardStepKey[]): number {
  const root = path.split(".")[0] as keyof EmployeeFormValues
  const index = steps.findIndex((step) => (STEP_FIELDS[step] as string[]).includes(root as string))
  return index === -1 ? 0 : index
}

/** Flatten RHF's nested errors object into dotted paths with messages. */
export function flattenErrors(node: unknown, prefix = ""): { path: string; message: string }[] {
  if (node === null || typeof node !== "object") return []
  const record = node as Record<string, unknown>
  if (typeof record.message === "string" && record.message) {
    return [{ path: prefix, message: record.message }]
  }
  return Object.entries(record).flatMap(([key, value]) =>
    key === "ref" ? [] : flattenErrors(value, prefix ? `${prefix}.${key}` : key),
  )
}
