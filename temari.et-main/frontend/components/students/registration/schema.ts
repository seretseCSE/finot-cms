import { z } from "zod"

import { optionalEthPhone } from "@/lib/validators"

/** Home-language codes offered at registration (App\Support\Languages). */
export const LANGUAGES = [
  "am",
  "om",
  "ti",
  "so",
  "sid",
  "wal",
  "aa",
  "gur",
  "har",
  "gez",
  "en",
  "ar",
  "fr",
  "other",
] as const

export const BLOOD_TYPES = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"] as const

export const RELATIONSHIPS = [
  "father",
  "mother",
  "grandfather",
  "grandmother",
  "uncle",
  "aunt",
  "sibling",
  "legal_guardian",
  "other",
] as const

const healthConditionRow = z.object({
  health_condition_id: z.string().min(1, "Pick a condition"),
  severity: z.enum(["mild", "moderate", "severe"]).or(z.literal("")).optional(),
  notes: z.string().max(1000).optional(),
  medication: z.string().max(255).optional(),
})

const guardianRow = z
  .object({
    /** "search" attaches an existing Temari parent; "new" provisions one. */
    mode: z.enum(["search", "new"]),
    parent_id: z.string().optional(),
    /** Display label of the matched parent (search mode, UI only). */
    parent_label: z.string().optional(),
    first_name: z.string().max(255).optional(),
    father_name: z.string().max(255).optional(),
    grandfather_name: z.string().max(255).optional(),
    phone: optionalEthPhone(),
    email: z.string().email("Enter a valid email").max(255).or(z.literal("")).optional(),
    gender: z.enum(["male", "female"]).or(z.literal("")).optional(),
    occupation: z.string().max(255).optional(),
    relationship: z.enum(RELATIONSHIPS, { errorMap: () => ({ message: "Pick a relationship" }) }),
    is_primary: z.boolean(),
    emergency_contact: z.boolean(),
    can_view_grades: z.boolean(),
    can_view_attendance: z.boolean(),
    can_pay_fees: z.boolean(),
    can_receive_sms: z.boolean(),
    /** Staged profile photo — uploaded after save, once the parent_id is known. */
    photo: z.custom<File>().nullable(),
    /** Staged profile documents (ID, custody letters…) — uploaded after save. */
    documents: z.array(
      z.object({ id: z.string(), name: z.string(), category: z.string(), file: z.custom<File>() }),
    ),
  })
  .superRefine((row, ctx) => {
    if (row.mode === "search" && !row.parent_id) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ["parent_id"], message: "Search and pick a parent" })
    }
    if (row.mode === "new") {
      if (!row.first_name?.trim())
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ["first_name"], message: "First name is required" })
      if (!row.phone?.trim())
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ["phone"], message: "Phone is required" })
    }
  })

export const registrationSchema = z
  .object({
    // Identity
    first_name: z.string().min(1, "First name is required").max(255),
    father_name: z.string().min(1, "Father's name is required").max(255),
    grandfather_name: z.string().max(255).optional(),
    mother_name: z.string().max(255).optional(),
    gender: z.enum(["male", "female"]),
    date_of_birth: z.string().optional(),
    national_student_id: z.string().max(50).optional(),
    fayda_id: z.string().max(50).optional(),
    citizenship: z.string().max(100).optional(),
    marital_status: z.enum(["single", "married", "divorced", "widowed"]).or(z.literal("")).optional(),
    languages: z.array(z.enum(LANGUAGES)).min(1, "Pick at least one language"),
    /** The STUDENT's own contact — parent phones belong on the Guardians step. */
    student_has_phone: z.boolean(),
    primary_phone: optionalEthPhone(),
    email: z.string().email("Enter a valid email").max(255).or(z.literal("")).optional(),
    /** Always true — every student gets login credentials at registration.
     *  With a phone: SMS-keyed login. Without: ID login (student ID + PIN). */
    create_user_account: z.boolean(),

    // Addresses
    birth_state: z.string().max(100).optional(),
    birth_city: z.string().max(100).optional(),
    birth_sub_city: z.string().max(100).optional(),
    birth_woreda: z.string().max(100).optional(),
    state: z.string().max(100).optional(),
    city: z.string().max(100).optional(),
    sub_city: z.string().max(100).optional(),
    woreda: z.string().max(100).optional(),
    house_no: z.string().max(50).optional(),

    // Health
    blood_type: z.enum(BLOOD_TYPES).or(z.literal("")).optional(),
    health_notes: z.string().max(2000).optional(),
    health_conditions: z.array(healthConditionRow),

    // Guardians — every student must have at least one on file.
    guardians: z.array(guardianRow).min(1, "Add at least one parent or guardian"),

    // Enrollment
    enroll_now: z.boolean(),
    academic_year_id: z.string().optional(),
    grade_level_id: z.string().optional(),
    section_id: z.string().optional(),
    school_program_id: z.string().optional(),
    previous_school_id: z.string().optional(),
    previous_school_label: z.string().optional(),
    enrolled_on: z.string().optional(),
  })
  .superRefine((v, ctx) => {
    if (v.enroll_now) {
      if (!v.academic_year_id)
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ["academic_year_id"], message: "Pick an academic year" })
      if (!v.grade_level_id)
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ["grade_level_id"], message: "Pick a grade level" })
    }
    if (v.student_has_phone && !v.primary_phone?.trim()) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["primary_phone"],
        message: "Enter the student's phone, or turn the toggle off — they can sign in with their student ID instead",
      })
    }
    // The student's phone must be the STUDENT's — flag the clash on the
    // guardian row too, since that step is filled after this field.
    const studentPhone = v.primary_phone?.trim()
    if (studentPhone) {
      v.guardians.forEach((g, i) => {
        if (g.phone?.trim() && g.phone.trim() === studentPhone) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ["guardians", i, "phone"],
            message: "Same as the student's phone — a guardian's number must be their own",
          })
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ["primary_phone"],
            message: "This number is also on a guardian — if it's the parent's phone, remove it here and turn the toggle off",
          })
        }
      })
    }
  })

export type RegistrationValues = z.infer<typeof registrationSchema>
export type GuardianRowValues = RegistrationValues["guardians"][number]

export const emptyGuardianRow: GuardianRowValues = {
  mode: "new",
  parent_id: "",
  parent_label: "",
  first_name: "",
  father_name: "",
  grandfather_name: "",
  phone: "",
  email: "",
  gender: "",
  occupation: "",
  relationship: "father",
  is_primary: false,
  emergency_contact: false,
  can_view_grades: true,
  can_view_attendance: true,
  can_pay_fees: true,
  can_receive_sms: true,
  photo: null,
  documents: [],
}

export const registrationDefaults: RegistrationValues = {
  first_name: "",
  father_name: "",
  grandfather_name: "",
  mother_name: "",
  gender: "male",
  date_of_birth: "",
  national_student_id: "",
  fayda_id: "",
  citizenship: "Ethiopian",
  marital_status: "",
  languages: ["am"],
  student_has_phone: false,
  primary_phone: "",
  email: "",
  // Mandatory: every student leaves registration with a login (phone-keyed
  // or student-ID + PIN) — the identity step states it, no opt-out.
  create_user_account: true,
  // Addis Ababa is by far the most common answer — prefill, let others change.
  birth_state: "Addis Ababa",
  birth_city: "Addis Ababa",
  birth_sub_city: "",
  birth_woreda: "",
  state: "Addis Ababa",
  city: "Addis Ababa",
  sub_city: "",
  woreda: "",
  house_no: "",
  blood_type: "",
  health_notes: "",
  health_conditions: [],
  // Every student needs a guardian — open the first row by default so the
  // registrar fills it in rather than hunting for "Add guardian".
  guardians: [{ ...emptyGuardianRow, is_primary: true }],
  enroll_now: true,
  academic_year_id: "",
  grade_level_id: "",
  section_id: "",
  school_program_id: "",
  previous_school_id: "",
  previous_school_label: "",
  // Registered on defaults to today — the overwhelmingly common case.
  enrolled_on: new Date().toISOString().slice(0, 10),
}

/**
 * Per-step fields validated by the wizard's Next button.
 *
 * Four steps (registration-desk speed): the Student step folds identity,
 * address and the optional health/documents extras into one scroll;
 * documents are staged in local state (not RHF) — no fields to validate.
 */
export const STEP_FIELDS: (keyof RegistrationValues)[][] = [
  [
    "first_name",
    "father_name",
    "grandfather_name",
    "mother_name",
    "gender",
    "date_of_birth",
    "national_student_id",
    "fayda_id",
    "citizenship",
    "marital_status",
    "languages",
    "student_has_phone",
    "primary_phone",
    "email",
    "create_user_account",
    "birth_state",
    "birth_city",
    "birth_sub_city",
    "birth_woreda",
    "state",
    "city",
    "sub_city",
    "woreda",
    "house_no",
    "blood_type",
    "health_notes",
    "health_conditions",
  ],
  ["guardians"],
  ["enroll_now", "academic_year_id", "grade_level_id", "section_id", "school_program_id", "previous_school_id", "enrolled_on"],
  [],
]

/** Per-fee choice made on the fees step (outside RHF — dynamic catalog). */
export interface FeeSelection {
  selected: boolean
  action: "unpaid" | "pay_now" | "scholarship"
  method: "cash" | "wallet" | "bank_transfer" | "other"
  /** Collection account the money landed in (bank/wallet methods). */
  bank_account_id: string
  scholarship_reason: string
}

export const defaultFeeSelection: FeeSelection = {
  selected: false,
  action: "unpaid",
  method: "cash",
  bank_account_id: "",
  scholarship_reason: "",
}

/**
 * Standing concession filed together with the registration (fees.manage staff
 * only, outside RHF like FeeSelection). Created server-side BEFORE invoicing,
 * so the first bill is born discounted.
 */
export interface WizardConcession {
  enabled: boolean
  category: "sibling" | "staff_child" | "merit" | "hardship" | "scholarship" | "other"
  discount_type: "percentage" | "fixed" | "full_scholarship"
  discount_value: string
  reason: string
}

export const defaultWizardConcession: WizardConcession = {
  enabled: false,
  category: "sibling",
  discount_type: "percentage",
  discount_value: "",
  reason: "",
}

/** A staged document uploaded after the student is saved. */
export interface DraftDocument {
  id: string
  name: string
  /** Ethiopian document type (lib/document-categories.ts); "" = uncategorised. */
  category: string
  file: File
}
