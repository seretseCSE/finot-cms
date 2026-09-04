export type Locale = "en" | "am" | "om"

export interface Membership {
  id: number
  role: string
  role_label: string | null
  scope: "platform" | "school" | "branch"
  school_id: number | null
  branch_id: number | null
  is_active: boolean
  /** Director memberships only: whether the school grants directors finance authority. */
  director_finance_access?: boolean
}

// Note: "relationship" roles (student/parent/tutor/vendor) never appear in
// memberships — parent/student access flows through the /me endpoints.

export interface User {
  id: number
  /** Public-facing person code (e.g. H8R6WV) — never the DB id. */
  public_id?: string | null
  name: string
  phone: string
  email: string | null
  /** Signed URL — synced from the person's profile photo (ProfilePhotoSync). */
  avatar_url?: string | null
  preferred_language: Locale
  notify_via_sms?: boolean
  notify_via_email?: boolean
  notify_via_push?: boolean
  is_active: boolean
  /** Role names derived from active memberships (the only role record). */
  roles: string[]
  /** COARSE global union of permissions across membership roles — for
   * bootstrapping only; effective permissions are per-context. */
  permissions: string[]
  /** Each membership role → the permissions it grants (context-aware nav). */
  role_permissions: Record<string, string[]>
  memberships?: Membership[]
  /** Relationship-derived hats (never memberships): drive the /me lane. */
  is_parent?: boolean
  is_student?: boolean
  /** The tutor hat (ADR-012): owns a tutor_profiles row (any status). */
  is_tutor?: boolean
  tutor_status?:
    | "draft"
    | "pending"
    | "approved"
    | "declined"
    | "suspended"
    | null
}

export interface BranchAddress {
  country: string | null
  state: string | null
  city: string | null
  sub_city: string | null
  woreda: string | null
  house_no: string | null
}

/** A school/branch contact — the person holding a management role. */
export interface Contact {
  user_id: number | null
  membership_id: number
  name: string | null
  phone: string | null
  is_active: boolean
}

export interface Branch {
  id: number
  school_id: number
  school?: { id: number; name: string }
  name: string
  code: string
  /** Official branch phone for document mastheads (school/principal fallback). */
  phone?: string | null
  address: BranchAddress
  longitude?: string | null
  latitude?: string | null
  director?: Contact | null
  /** Education programs the branch runs (Regular always present). */
  programs?: ProgramRef[]
  is_active: boolean
  /** List-table vitals — present on management list/detail endpoints. */
  students_count?: number
  teachers_count?: number
  sections_count?: number
  /** Lowest/highest grade level served (grade level names), null when no sections. */
  grade_min?: string | null
  grade_max?: string | null
  created_at: string
  updated_at: string
}

export interface School {
  id: number
  name: string
  /** Official logo (signed URL) — set by Temari.et platform staff only. */
  logo_url?: string | null
  /** Official contact line for document mastheads (branch values win). */
  phone?: string | null
  address?: string | null
  is_active: boolean
  /** Registration-fee gate policy: soft allows provisional activation. */
  registration_gate?: "soft" | "hard"
  /** How every date DISPLAYS for this school (storage stays Gregorian). */
  calendar_mode?: CalendarMode
  /** How times of day are written (standard 8:00 AM vs Ethiopian dawn count). */
  clock_mode?: ClockMode
  /** Minimum annual average for a promotion suggestion (MoE default 50). */
  promotion_threshold?: number
  /** Teachers may define free-form assessments (default off, branch-overridable). */
  teacher_assessments_enabled?: boolean
  lesson_plan_department_review?: boolean
  /** Job titles whose employees get a portal account at hire (branch-overridable). */
  employee_account_job_titles?: string[]
  /** Guardian absence alerts (SMS/email) — school default, branch-overridable. */
  attendance_sms_enabled?: boolean
  /** Also alert guardians on late marks (absent-only by default). */
  attendance_sms_late?: boolean
  /** Device mode: auto-mark unscanned students absent after the cutoff. */
  device_auto_absent?: boolean
  /** Local H:i the auto-absent sweep runs. */
  device_absent_cutoff?: string
  /** Grace minutes after the expected start before a scan counts late. */
  device_late_grace?: number
  /** Concession policy — 0 = off; suggestions require finance approval. */
  sibling_discount_percent?: number
  sibling_min_children?: number
  staff_child_discount_percent?: number
  /** Recurring billing + reminder-ladder defaults (branch-overridable). */
  fee_proration?: "full" | "daily"
  fee_reminders_enabled?: boolean
  fee_reminder_days_before?: number
  fee_reminder_overdue_every?: number
  fee_reminder_overdue_max?: number
  /** One person may record AND approve an expense (four-eyes off). */
  finance_self_approval?: boolean
  /** Branch directors hold finance authority (books, payments, fee mgmt). */
  director_finance_access?: boolean
  /** Communication-book mode: teacher→family messages wait for a director. */
  chat_teacher_parent_approval?: "off" | "first" | "all"
  /** Students join classroom channels / DM their teachers. */
  chat_students_enabled?: boolean
  /** Preset chat templates: a convenience ('suggested') or the only allowed
   *  wording for family-reaching teacher messages ('required'). */
  chat_template_mode?: "suggested" | "required"
  /** Report-card policy (branch-overridable): the behavioral skill checklist
   *  printed on the yearly card (empty = no panel), compact 2-per-page
   *  semester printing, per-subject ranks, yearly grading-criteria legend. */
  report_card_skills?: ReportCardSkill[]
  report_card_per_page?: 1 | 2 | 4
  report_card_subject_ranks?: boolean
  report_card_grading_criteria?: boolean
  branches_count?: number
  branches?: Branch[]
  principal?: Contact | null
  school_admin?: Contact | null
  /** List-table vitals — present on management list endpoints. */
  students_count?: number
  teachers_count?: number
  /** Lowest/highest grade level served (grade level names), null when no sections. */
  grade_min?: string | null
  grade_max?: string | null
  created_at: string
  updated_at: string
}

/** One grade level's slice of a school/branch (profile stats). */
export interface OrgStatsGrade {
  id: number
  /** Compact axis tick (KG1, G4…). */
  code: string
  name: string
  students: number
  sections: number
}

/** Per-branch mini-summary inside the school profile stats. */
export interface OrgStatsBranch {
  id: number
  name: string
  code: string
  city: string | null
  is_active: boolean
  students: number
  teachers: number
  sections: number
  grade_min: string | null
  grade_max: string | null
}

/** Aggregated profile vitals for a school or branch (GET …/stats). */
export interface OrgStats {
  students: { active: number; pending: number; male: number; female: number }
  /** Distinct active guardians linked to the students enrolled here. */
  guardians: number
  employees: {
    total: number
    by_job_title: { job_title: string; total: number }[]
  }
  academics: {
    subjects_taught: number
    teachers_teaching: number
    sections: number
    capacity: number
  }
  grades: OrgStatsGrade[]
  /** School stats only. */
  branches?: OrgStatsBranch[]
}

export type Cycle =
  | "kindergarten"
  | "lower_primary"
  | "upper_primary"
  | "secondary"
  | "preparatory"

export interface GradeLevel {
  id: number
  code: string
  name: string
  cycle: Cycle
  sort_order: number
  has_national_exam: boolean
  /** Catalog studio only — how many sections (any school) sit on this level. */
  sections_count?: number
  /** Branch-scoped lists only: the branch's program ids offering this grade. */
  program_ids?: number[]
}

/** Education program a branch runs (catalog type slug + display name). */
export interface ProgramRef {
  id: number
  type: string
  name: string
  /** Grades this program is offered in — present on branch show/save payloads. */
  grade_level_ids?: number[] | null
}

/**
 * Live usage per grade at a branch (branch show `meta.grade_usage`): active
 * section count + live enrollment counts per program id — the branch editor
 * locks matrix cells this data says can't be unchecked.
 */
export type BranchGradeUsage = Record<
  string,
  { sections?: number; enrollments?: Record<string, number> }
>

export type TermStatus = "planned" | "active" | "closed"

export interface Term {
  id: number
  branch_id?: number
  academic_year_id: number
  academic_year_name?: string
  name: string
  sequence: number
  program?: ProgramRef | null
  /** Flat copy of program.type for client-side table filtering. */
  program_type?: string | null
  starts_on: string | null
  ends_on: string | null
  /** Daily class window (HH:mm) and period length in minutes. */
  class_starts_at: string | null
  class_ends_at: string | null
  period_minutes: number
  is_quarter: boolean
  /** Which semester a QUARTER belongs to (1|2); null for semester terms. */
  semester: number | null
  is_current: boolean
  status: TermStatus
  /** Today falls inside the term's calendar range. */
  in_progress: boolean
  is_active: boolean
  /** Present only in the global (no-branch) context for platform/school staff. */
  school_name?: string | null
  branch_name?: string | null
}

export type AcademicYearStatus = "planned" | "active" | "completed" | "archived"

export interface AcademicYear {
  id: number
  school_id: number
  branch_id: number
  name: string
  starts_on: string | null
  ends_on: string | null
  status: AcademicYearStatus
  status_label: string
  /** Derived: the ACTIVE year is the branch's operating year. */
  is_current: boolean
  is_active: boolean
  terms?: Term[]
  terms_count?: number
  fees?: FeeStructure[]
  fees_count?: number
  /** Present only in the global (no-branch) context for platform/school staff. */
  school_name?: string | null
  branch_name?: string | null
  created_at: string
  updated_at: string
}

export interface Section {
  id: number
  school_id: number
  branch_id: number
  grade_level_id: number
  grade_level?: GradeLevel
  name: string
  room_number: string | null
  capacity: number | null
  /** Year-scoped: the homeroom for the requested (or active) academic year. */
  homeroom_employee_id: number | null
  homeroom_name?: string | null
  homeroom_academic_year_id?: number | null
  is_active: boolean
  /** Present only in the global (no-branch) context for platform/school staff. */
  school_name?: string | null
  branch_name?: string | null
  created_at: string
  updated_at: string
}

export type Gender = "male" | "female"

export type EnrollmentStatus =
  | "pending"
  | "active"
  | "promoted"
  | "repeated"
  | "transferred_out"
  | "withdrawn"
  | "graduated"

export interface StudentEnrollment {
  id: number
  student_id: number
  /** Enrollment history spans schools (transfers) — each row names its own. */
  school_id?: number
  branch_id?: number
  school_name?: string | null
  branch_name?: string | null
  academic_year_id: number
  academic_year_name?: string
  /** Null when the section is assigned after registration. */
  section_id: number | null
  section_name?: string | null
  /** The homeroom teacher of THIS enrollment's year (detail payloads only). */
  homeroom_teacher?: {
    employee_id: number
    user_id: number | null
    name: string
    phone: string | null
  } | null
  grade_level_id: number
  grade_level?: GradeLevel
  school_program_id?: number | null
  school_program_name?: string | null
  previous_school_id?: number | null
  previous_school_name?: string | null
  status: EnrollmentStatus
  status_label: string
  enrolled_on: string | null
  exited_on: string | null
}

/** One row in the platform-wide Ethiopian school directory. */
export interface SchoolDirectoryEntry {
  id: number
  name: string
  region: string | null
  zone: string | null
  city: string | null
  /** Set when the school is hosted on Temari. */
  school_id: number | null
  is_verified: boolean
  school_name?: string | null
  /** Provenance: the school whose registrar added this row inline. */
  created_by_school_name?: string | null
  created_at?: string
}

export type HealthConditionCategory =
  | "allergy"
  | "chronic"
  | "neurological"
  | "physical"
  | "sensory"
  | "mental_health"
  | "blood"
  | "other"

export interface HealthCondition {
  id: number
  name: string
  category: HealthConditionCategory
  is_active?: boolean
  students_count?: number
  created_at?: string
}

export type HealthSeverity = "mild" | "moderate" | "severe"

/** A student's condition row (catalog pivot). */
export interface StudentHealthCondition {
  health_condition_id: number
  name?: string
  category?: HealthConditionCategory
  severity: HealthSeverity | null
  notes: string | null
  medication: string | null
}

export interface StudentAttachment {
  id: number
  name: string
  /** Ethiopian document type (lib/document-categories.ts); null = uncategorised. */
  category?: string | null
  /** Short-lived signed R2 URL. */
  url: string | null
  mime_type: string | null
  size: number | null
  /** Provenance: the school/branch that collected the document (documents travel with the student). */
  school_id?: number | null
  branch_name?: string | null
  uploaded_by_name?: string | null
  created_at: string
}

export type BloodType = "A+" | "A-" | "B+" | "B-" | "AB+" | "AB-" | "O+" | "O-"

/** Portal login status of a student's or parent's own account. */
export interface PortalAccount {
  status: AccountStatus
  status_label: string
  /** False = provisioned but the setup link was never used ("No login"). */
  has_password?: boolean
  last_login_at: string | null
  phone?: string | null
  /** "student_id" = phone-less account that signs in with student ID + PIN. */
  login_mode?: "phone" | "student_id"
}

export interface Student {
  id: number
  /** Public-facing code (e.g. H8R6WV) — students carry their own. */
  public_id?: string | null
  school_id: number
  branch_id: number
  first_name: string
  father_name: string
  grandfather_name: string | null
  mother_name: string | null
  full_name: string
  gender: Gender
  date_of_birth: string | null
  national_student_id: string | null
  primary_phone: string | null
  email?: string | null
  citizenship?: string | null
  marital_status?: MaritalStatus | null
  photo_url?: string | null
  /** Home language codes (am default). */
  languages?: string[]
  /** Health fields only arrive on the detail endpoint. */
  blood_type?: BloodType | null
  health_notes?: string | null
  health_conditions?: StudentHealthCondition[]
  attachments?: StudentAttachment[]
  birth_country?: string | null
  birth_state?: string | null
  birth_city?: string | null
  birth_sub_city?: string | null
  birth_woreda?: string | null
  country?: string | null
  state?: string | null
  city?: string | null
  sub_city?: string | null
  woreda?: string | null
  house_no?: string | null
  is_active: boolean
  /** Portal login status — present on the detail endpoint; null = no login. */
  account?: PortalAccount | null
  current_enrollment?: StudentEnrollment | null
  enrollments?: StudentEnrollment[]
  guardians?: Guardian[]
  /** Present only in the global (no-branch) context for platform/school staff. */
  school_name?: string | null
  branch_name?: string | null
  /**
   * 'archive' = the viewer's school no longer holds custody (student
   * transferred away): read-only, era-bounded — the file as the student LEFT
   * (see `archive`), and the enrollment shown is the viewer's own last one.
   */
  access?: "full" | "archive"
  /** ADR-017 handover snapshot — address/health frozen at transfer approval. */
  archive?: StudentArchiveSnapshot | null
  /** Transfer supporting documents — participant schools only. */
  transfer_files?: StudentTransferFileGroup[]
  created_at: string
  updated_at: string
  deleted_at?: string | null
}

/** The era snapshot a former school reads instead of the live record. */
export interface StudentArchiveSnapshot {
  captured_at: string | null
  profile: Partial<
    Pick<
      Student,
      | "primary_phone"
      | "email"
      | "country"
      | "state"
      | "city"
      | "sub_city"
      | "woreda"
      | "house_no"
      | "birth_country"
      | "birth_state"
      | "birth_city"
      | "birth_sub_city"
      | "birth_woreda"
    >
  > | null
  health: {
    blood_type: BloodType | null
    health_notes: string | null
    conditions: StudentHealthCondition[]
  } | null
}

/** One transfer request's supporting documents, shown on the student record. */
export interface StudentTransferFileGroup {
  id: number
  status: "requested" | "approved" | "rejected" | "cancelled"
  from_school_name: string | null
  to_school_name: string | null
  created_at: string
  files: {
    id: number
    name: string
    url: string | null
    mime_type: string | null
    size: number | null
    created_at: string
  }[]
}

export type EmploymentType =
  | "full_time"
  | "part_time"
  | "volunteer"
  | "substitute"
  | "contract"

export type MaritalStatus = "single" | "married" | "divorced" | "widowed"

/** One job an employee holds. `ended_on` null = current. A combined salary
 * covering several job_titles sits on the PRIMARY position. */
export interface EmployeePosition {
  id?: number
  job_title: string
  employment_type: EmploymentType | null
  employment_type_label?: string | null
  salary_level: number | null
  salary: string | null
  hired_on: string | null
  last_promoted_on: string | null
  ended_on: string | null
  is_primary: boolean
}

/** One academic credential (a person holds many). */
export interface EmployeeQualification {
  id?: number
  education_level: string
  field_of_study: string | null
  institution: string | null
  graduation_year: number | null
}

/** A recurring salary allowance line (name from the fixed catalog + ETB amount). */
export interface EmployeeAllowance {
  id?: number
  name: string
  amount: string
}

/** A recurring payroll deduction line (free-form name + ETB amount). */
export interface EmployeeDeduction {
  id?: number
  name: string
  amount: string
}

/** Teaching capability: a subject × grade this teacher can teach. */
export interface TeacherSubjectRef {
  id?: number
  subject_id: number
  subject_code?: string | null
  subject_name?: string | null
  grade_level_id: number
  grade_level_name?: string | null
  grade_level_sort?: number | null
}

/** A staff document stored privately on R2; `url` is a short-lived signed link. */
export interface EmployeeAttachment {
  id: number
  name: string
  url: string | null
  mime_type: string | null
  size: number | null
  employee_position_id?: number | null
  employee_qualification_id?: number | null
  created_at: string
}

export interface Employee {
  id: number
  user_id: number | null
  school_id: number
  branch_id: number | null
  first_name: string
  father_name: string | null
  grandfather_name: string | null
  full_name: string
  gender: "male" | "female" | null
  phone: string | null
  photo_url: string | null
  birth_date: string | null
  email: string | null
  marital_status: MaritalStatus | null
  nationality: string | null
  state: string | null
  city: string | null
  sub_city: string | null
  woreda: string | null
  house_no: string | null
  professional_level: string | null
  retirement_on: string | null
  /** Daily attendance window, HH:mm. */
  check_in: string | null
  check_out: string | null
  is_active: boolean
  positions?: EmployeePosition[]
  /** Convenience: current job_titles (from active positions). */
  active_job_titles?: string[]
  /** Convenience: the primary (salary-anchor) position. */
  primary_position?: Pick<
    EmployeePosition,
    "id" | "job_title" | "employment_type" | "salary" | "hired_on"
  > | null
  qualifications?: EmployeeQualification[]
  allowances?: EmployeeAllowance[]
  deductions?: EmployeeDeduction[]
  teacher_subjects?: TeacherSubjectRef[]
  attachments?: EmployeeAttachment[]
  /** Linked account with scope-filtered memberships — powers the Access column. */
  user?: AdminUser | null
  /** Present only in the global (no-branch) context for platform/school staff. */
  school_name?: string | null
  branch_name?: string | null
  created_at: string
}

// ─── HR: staff attendance, leave, holidays, reports ────────────────────────

/** A recorded staff mark; on-leave/holiday are roster overlays, not statuses. */
export type EmployeeAttendanceStatus =
  | "present"
  | "late"
  | "half_day"
  | "absent"
  | "excused"

export interface EmployeeAttendanceRosterEntry {
  /** Mirrors employee_id — DataTable rows need an `id`. */
  id?: number
  employee_id: number
  employee_name: string
  phone: string | null
  job_titles: string[]
  /** Client-side helper for the status filter: status, "unmarked" or "on_leave". */
  status_key?: string
  expected_check_in: string | null
  expected_check_out: string | null
  status: EmployeeAttendanceStatus | null
  /** Who produced the saved mark: register UI or an RFID scan. */
  source?: "manual" | "device" | null
  check_in: string | null
  check_out: string | null
  note: string | null
  on_leave: {
    leave_request_id: number
    leave_type_name: string | null
    is_half_day: boolean
    until: string
  } | null
}

/** One line of the school's leave policy (seeded from labour-law defaults). */
export interface LeaveType {
  id: number
  school_id: number
  code: string | null
  name: string
  days_per_year: number | null
  service_bonus_days: number
  service_bonus_every_years: number
  is_paid: boolean
  applicable_gender: "male" | "female" | null
  requires_note: boolean
  is_active: boolean
  sort_order: number
  requests_count?: number
}

export type LeaveRequestStatus =
  | "pending"
  | "approved"
  | "rejected"
  | "cancelled"

export interface LeaveRequest {
  id: number
  school_id: number
  branch_id: number
  employee_id: number
  employee_name?: string | null
  leave_type_id: number
  leave_type_name?: string | null
  leave_type_code?: string | null
  is_paid?: boolean
  start_date: string
  end_date: string
  days: number
  is_half_day: boolean
  reason: string | null
  status: LeaveRequestStatus
  requested_by_name?: string | null
  decided_by_name?: string | null
  decided_at: string | null
  decision_note: string | null
  created_at: string
}

/** Balance of one employee × leave type for the Ethiopian leave year. */
export interface LeaveBalanceLine {
  leave_type_id: number
  leave_type_name: string
  leave_type_code: string | null
  is_paid: boolean
  entitled: number | null
  taken: number
  pending: number
  remaining: number | null
}

export interface EmployeeLeaveBalances {
  employee_id: number
  employee_name: string
  balances: LeaveBalanceLine[]
}

export interface Holiday {
  id: number
  name: string
  date: string
  branch_id: number | null
  branch_name?: string | null
}

export interface HrOverviewReport {
  headcount: {
    total: number
    active: number
    inactive: number
    female: number
    male: number
  }
  by_job_title: Record<string, number>
  by_employment_type: Record<string, number>
  attendance: {
    recorded: number
    by_status: Partial<Record<EmployeeAttendanceStatus, number>>
    attendance_rate: number | null
  }
  leave: {
    pending_requests: number
    approved_days: number
    by_type: {
      name: string
      is_paid: boolean
      requests: number
      days: string
    }[]
  }
  payroll: {
    run_id: number
    name: string
    status: PayrollStatus
    period_end: string
    gross_total: string
    net_total: string
    employer_cost: number
  } | null
}

export interface HrAttendanceReportRow {
  /** Mirrors employee_id — DataTable rows need an `id`. */
  id?: number
  employee_id: number
  employee_name: string
  job_titles: string[]
  present: number
  late: number
  half_day: number
  absent: number
  excused: number
  recorded: number
  leave_days: number
  attendance_rate: number | null
}

/** Chart series for the HR reports dashboard (`/hr/reports/trends`). */
export interface HrTrendsReport {
  /** One row per marked register day inside the window. */
  daily: {
    date: string
    present: number
    late: number
    half_day: number
    absent: number
    excused: number
  }[]
  /** Approved leave days per calendar month — last 6, oldest first. */
  leave_monthly: { month: string; paid: number; unpaid: number }[]
  /** Closed (approved/paid) payroll runs — last 6, oldest first. */
  payroll_runs: {
    run_id: number
    name: string
    period_end: string
    net: number
    deductions: number
    employer_pension: number
    employer_cost: number
  }[]
  /** Active staff bucketed by years of service. */
  tenure: Record<HrTenureBucket, number>
}

export type HrTenureBucket =
  | "lt1"
  | "1to3"
  | "3to5"
  | "5to10"
  | "gte10"
  | "unknown"

export interface MyAttendanceRecord {
  date: string
  status: EmployeeAttendanceStatus
  check_in: string | null
  check_out: string | null
  note: string | null
}

export type PayrollStatus = "draft" | "approved" | "paid"

/** One employee's payslip inside a run; `breakdown` snapshots its source lines. */
export interface PayrollItem {
  id: number
  employee_id: number
  employee_name?: string | null
  basic_salary: string
  allowances_total: string
  gross_pay: string
  income_tax: string
  pension_employee: string
  pension_employer: string
  deductions_total: string
  net_pay: string
  breakdown?: {
    positions?: { job_title: string; salary: number; is_primary: boolean }[]
    allowances?: { name: string; amount: number }[]
    deductions?: { name: string; amount: number }[]
  } | null
}

export interface PayrollRun {
  id: number
  school_id: number
  branch_id: number
  branch_name?: string | null
  school_name?: string | null
  name: string
  period_start: string
  period_end: string
  status: PayrollStatus
  status_label: string
  notes: string | null
  gross_total: string
  tax_total: string
  pension_employee_total: string
  pension_employer_total: string
  deduction_total: string
  net_total: string
  employee_count?: number
  approved_at: string | null
  paid_at: string | null
  items?: PayrollItem[]
  created_at: string
}

export type FeeType =
  | "registration"
  | "one_time"
  | "daily"
  | "weekly"
  | "monthly"
  | "quarterly"
  | "semester"
  | "yearly"
export type PenaltyType = "fixed" | "incremental"
export type InvoiceStatus =
  | "unpaid"
  | "partial"
  | "paid"
  | "scholarship"
  | "void"

export type DiscountType = "none" | "percentage" | "fixed" | "full_scholarship"
export type PaymentMethod = "wallet" | "bank_transfer" | "cash" | "other"

/** One collection account attached to a fee (payments may land in it). */
export interface FeeBankAccount {
  id: number
  account_name: string
  account_number: string
  bank_name: string | null
  bank_code: string | null
  bank_logo: string | null
  bank_type: "bank" | "wallet" | null
}

export interface FeeStructure {
  id: number
  branch_id: number
  academic_year_id: number
  academic_year_name?: string
  name: string
  type: FeeType
  type_label: string
  amount: string
  /** Collection accounts payments on this fee may land in (0..n). */
  bank_accounts?: FeeBankAccount[]
  /** Applicable grades — empty means every grade. */
  grade_levels?: GradeLevel[]
  starts_on: string | null
  due_on: string | null
  notify_parents: boolean
  notify_students: boolean
  penalty_type: PenaltyType | null
  penalty_amount: string | null
  penalty_increment_days: number | null
  /** Recurring types: Ethiopian day-of-month invoices fall due (null = 10). */
  billing_day: number | null
  /** Recurring types: the daily engine auto-issues each period's invoices. */
  auto_generate: boolean
  is_active: boolean
  /** Present only in the global (no-branch) context for platform/school staff. */
  school_name?: string | null
  branch_name?: string | null
  invoices_count?: number
  created_at: string
}

export interface Payment {
  id: number
  invoice_id: number
  student_id: number
  amount: string
  method: PaymentMethod
  method_label: string
  reference: string | null
  /** Official receipt number (RCT-{branch}-{seq}) — printable + QR-verified. */
  receipt_number?: string
  paid_at: string | null
  note: string | null
  /** Collection-account snapshot at payment time (bank/wallet methods). */
  bank_account_id?: number | null
  bank_account?: FeeBankAccount | null
  recorded_by_name?: string | null
  created_at: string
}

export interface Invoice {
  id: number
  /** Human-facing invoice number (INV-000123) — always shown tap-to-copy. */
  number: string
  student_id: number
  branch_id?: number
  student_name?: string | null
  student_public_id?: string | null
  academic_year_id: number
  academic_year_name?: string | null
  term_id: number | null
  term_name?: string | null
  fee_structure_id: number | null
  title: string
  amount: string
  amount_paid: string
  /** Payable amount after any discount/scholarship. */
  net_amount?: string
  /** Accrued late penalty (fees:apply-penalties) — part of the balance. */
  penalty_amount?: string
  penalty_waived?: boolean
  /** net_amount + penalty — what the family actually owes in total. */
  total_due?: string
  /** Recurring billing period (Ethiopian month) when engine-issued. */
  billing_year?: number | null
  billing_month?: number | null
  discount_type?: DiscountType
  discount_value?: string
  scholarship_reason?: string | null
  /** Provenance when the discount came from a standing concession. */
  fee_concession_id?: number | null
  concession_category?: ConcessionCategory | null
  balance: string
  status: InvoiceStatus
  status_label: string
  due_date: string | null
  is_overdue?: boolean
  /** Present only in the global (no-branch) context for platform/school staff. */
  school_name?: string | null
  branch_name?: string | null
  payments?: Payment[]
  /** Where the fee expects money to land (payment-sheet default list). */
  collection_accounts?: FeeBankAccount[]
  /** Where recorded payments actually landed (distinct). */
  paid_accounts?: FeeBankAccount[]
  /** Parent payment submissions awaiting finance review (list badge). */
  pending_verifications_count?: number
  created_at: string
}

/** The books (finance.books.*): categories, expenses, income, cashbook. */
export interface FinanceCategory {
  id: number
  kind: "expense" | "income"
  name: string
  is_active: boolean
}

export type ExpenseMethod = "cash" | "bank_transfer" | "wallet" | "other"

export interface Expense {
  id: number
  branch_id: number
  branch_name?: string | null
  finance_category_id: number
  category_name?: string
  title: string
  amount: string
  expense_date: string | null
  method: ExpenseMethod
  bank_account_id: number | null
  bank_account?: {
    id: number
    account_name: string
    account_number: string
    bank_name: string | null
    bank_logo: string | null
  } | null
  payee: string | null
  reference: string | null
  note: string | null
  status: "pending" | "approved" | "rejected"
  /** Recorder's user id — the UI hides Approve on your own rows (four-eyes). */
  recorded_by: number | null
  recorded_by_name?: string | null
  approved_by_name?: string | null
  approved_at: string | null
  review_note: string | null
  created_at: string
}

export interface OtherIncome {
  id: number
  branch_id: number
  branch_name?: string | null
  finance_category_id: number
  category_name?: string
  title: string
  amount: string
  received_on: string | null
  method: ExpenseMethod
  bank_account_id: number | null
  bank_account?: Expense["bank_account"]
  source: string | null
  reference: string | null
  note: string | null
  recorded_by_name?: string | null
  created_at: string
}

// ── Inventory & school property ──────────────────────────────────────────

export type StockMovementType = "receive" | "issue" | "return" | "adjustment" | "write_off"

export type RequisitionStatus = "pending" | "approved" | "declined" | "issued" | "cancelled"

export type PurchaseOrderStatus = "pending" | "approved" | "declined" | "received" | "cancelled"

export type StockTakeStatus = "in_progress" | "posted" | "cancelled"

/** Units of measure — mirrors backend App\Support\InventoryUnits::ALL. */
export const INVENTORY_UNITS = [
  "piece", "set", "pair", "box", "pack", "ream", "dozen", "roll",
  "bottle", "carton", "kg", "g", "litre", "ml", "meter",
] as const

export type InventoryUnit = (typeof INVENTORY_UNITS)[number]

export interface InventoryCategory {
  id: number
  school_id: number | null
  name: string
  icon: string | null
  is_active: boolean
  /** Platform seed rows are read-only school-side. */
  is_platform: boolean
  items_count?: number
}

export interface InventoryItem {
  id: number
  school_id: number
  inventory_category_id: number
  category_name?: string
  category_icon?: string | null
  name: string
  code: string | null
  unit: string
  is_asset: boolean
  reorder_level: string | null
  description: string | null
  is_active: boolean
  /** Aggregated for the active scope by the list endpoint. */
  quantity_on_hand?: string
}

export interface InventoryStats {
  item_count: number
  low_stock_count: number
  pending_requisitions: number
  open_purchase_orders: number
}

export interface StockMovement {
  id: number
  branch_id: number
  branch_name?: string | null
  inventory_item_id: number
  item_name?: string
  item_unit?: string
  type: StockMovementType
  quantity: string
  quantity_change: string
  /** Running balance after this movement — the bin-card column. */
  quantity_after: string
  unit_cost: string | null
  requisition_id: number | null
  purchase_order_id: number | null
  stock_take_id: number | null
  supplier_name: string | null
  recipient: string | null
  reference: string | null
  note: string | null
  created_by_name?: string | null
  created_at: string
}

export interface RequisitionItem {
  id: number
  inventory_item_id: number
  item_name?: string
  item_unit?: string
  quantity_requested: string
  quantity_approved: string | null
  quantity_issued: string
}

export interface Requisition {
  id: number
  branch_id: number
  branch_name?: string | null
  status: RequisitionStatus
  /** Requester's user id — the UI hides Approve on your own rows (four-eyes). */
  requested_by: number
  requested_by_name?: string | null
  purpose: string | null
  decided_by_name?: string | null
  decided_at: string | null
  decline_reason: string | null
  fulfilled_at: string | null
  items_count?: number
  items?: RequisitionItem[]
  created_at: string
}

export interface PurchaseOrderItem {
  id: number
  inventory_item_id: number
  item_name?: string
  item_unit?: string
  quantity: string
  unit_cost: string | null
  received_quantity: string
}

export interface PurchaseOrder {
  id: number
  branch_id: number
  branch_name?: string | null
  supplier_name: string
  supplier_phone: string | null
  status: PurchaseOrderStatus
  expected_on: string | null
  note: string | null
  total_cost: string
  /** Orderer's user id — the UI hides Approve on your own rows (four-eyes). */
  ordered_by: number
  ordered_by_name?: string | null
  decided_by_name?: string | null
  decided_at: string | null
  decline_reason: string | null
  items_count?: number
  items?: PurchaseOrderItem[]
  created_at: string
}

export type AssetStatus = "in_store" | "assigned" | "under_repair" | "lost" | "disposed"

export type AssetCondition = "new" | "good" | "fair" | "poor" | "damaged"

export type AssetHolderType = "employee" | "student" | "room" | "section"

export interface AssetUnit {
  id: number
  branch_id: number
  branch_name?: string | null
  inventory_item_id: number
  item_name?: string
  /** Public code written on the unit itself — the property-register handle. */
  tag: string
  serial_number: string | null
  condition: AssetCondition
  status: AssetStatus
  acquired_on: string | null
  unit_cost: string | null
  note: string | null
  holder?: {
    type: AssetHolderType
    label: string | null
    since: string | null
    note: string | null
  } | null
  created_at: string
}

/** id + label row from the scoped /inventory/holders picker. */
export interface AssetHolderOption {
  id: number
  label: string
  sublabel?: string | null
}

export type TextbookLoanStatus = "out" | "returned" | "lost"

export interface TextbookLoan {
  id: number
  branch_id: number
  branch_name?: string | null
  academic_year_id: number
  inventory_item_id: number
  item_name?: string
  student_id: number
  student_name?: string | null
  student_public_id?: string | null
  section_id: number | null
  section_name?: string | null
  quantity: number
  status: TextbookLoanStatus
  returned_at: string | null
  lost_at: string | null
  note: string | null
  created_at: string
}

export interface StockTakeLine {
  id: number
  inventory_item_id: number
  item_name?: string
  item_unit?: string
  expected_quantity: string
  counted_quantity: string | null
}

export interface StockTake {
  id: number
  branch_id: number
  branch_name?: string | null
  inventory_category_id: number | null
  category_name?: string | null
  status: StockTakeStatus
  note: string | null
  started_by_name?: string | null
  posted_at: string | null
  lines_count?: number
  counted_count?: number
  lines?: StockTakeLine[]
  created_at: string
}

export interface CashbookEntry {
  /** Not sent by the API — present to satisfy generic table constraints. */
  id?: number
  entry_date: string
  entry_id: number
  source: "fee_payment" | "other_income" | "expense" | "payroll"
  direction: "in" | "out"
  description: string
  method: string | null
  bank_account_id: number | null
  finance_category_id: number | null
  category: string | null
  branch_name: string | null
  amount: string
}

export interface FinanceStatement {
  income: {
    school_fees: string
    other: { category: string; amount: string }[]
    total: string
  }
  expenses: {
    payroll: string
    categories: { category: string; amount: string }[]
    total: string
  }
  net: string
}

export interface BudgetRow {
  finance_category_id: number
  category: string
  budget: string | null
  actual: string
  /** Recorded but not yet approved — not counted in `actual`. */
  pending: string
}

/** Receivables analytics payloads (/fee-reports/*). */
export interface FeeReportOverview {
  invoices: number
  invoiced: string
  collected: string
  outstanding: string
  overdue_count: number
  overdue_amount: string
  penalties_accrued: string
  students_owing: number
  collection_rate: number | null
  aging: {
    bucket: "current" | "1-30" | "31-60" | "61-90" | "90+"
    amount: string
    count: number
  }[]
  methods: { method: PaymentMethod; amount: string; count: number }[]
}

export interface FeeDefaulterRow {
  student_id: number
  student_name: string | null
  student_public_id: string | null
  open_invoices: number
  balance: string
  overdue_amount: string
  oldest_due: string | null
  guardians: { name: string; phone: string | null; email: string | null }[]
}

export interface FeeDailyCollections {
  days: {
    date: string
    total: string
    count: number
    methods: { method: PaymentMethod; amount: string; count: number }[]
  }[]
  methods: { method: PaymentMethod; amount: string; count: number }[]
  cashiers: {
    user_id: number | null
    name: string
    amount: string
    count: number
  }[]
  total: string
  count: number
}

/** Printable official receipt for one payment (staff print + public QR page). */
export interface PaymentReceipt {
  receipt_number: string
  public_token: string
  school: string | null
  branch: string | null
  student: { full_name: string | null; public_id: string | null }
  invoice_id: number
  invoice_number: string
  invoice_title: string | null
  amount: string
  method: PaymentMethod
  method_label: string
  reference: string | null
  bank_account: {
    account_name: string
    account_number: string
    bank_name: string | null
  } | null
  paid_at: string | null
  recorded_by: string | null
  invoice_total_due: string | null
  invoice_amount_paid: string | null
  invoice_status: InvoiceStatus | null
  issued_at: string | null
  /** Official PDF (public page only) — download saves it, view prints it. */
  download_url?: string | null
  view_url?: string | null
}

/** One parent payment-proof submission on an invoice (finance review lane). */
export interface InvoiceVerification {
  id: number
  status: "verified" | "failed" | "needs_review"
  status_label: string
  failure_reason: string | null
  method: "reference" | "link" | "file" | string | null
  bank_code: string | null
  transaction_number: string | null
  receipt_url: string | null
  receipt_file_url: string | null
  submitted_by: string | null
  submitted_by_phone?: string | null
  payment_id: number | null
  created_at: string
  /** What check.et actually saw in bank records (immutable snapshot). */
  evidence?: {
    bank_code: string | null
    bank_name: string | null
    verification_method: string | null
    receipt_status: string | null
    amount: string | null
    currency: string | null
    transaction_date: string | null
    payer_name: string | null
    receiver_name: string | null
    receiver_account: string | null
    provider_message: string | null
    unavailable: boolean
  }
  /** Fraud radar: the same transaction number claimed/recorded elsewhere. */
  duplicate_claims?: number
  duplicate_other_invoices?: string[]
  already_paid_with?: boolean
  /** Manual review resolution trail. */
  reviewed_by?: string | null
  reviewed_at?: string | null
  review_note?: string | null
}

/** Billing vitals for the current invoices view (same scope + filters). */
export interface InvoiceStats {
  invoiced: string
  collected: string
  outstanding: string
  overdue_count: number
  overdue_amount: string
}

export type ConcessionCategory =
  | "sibling"
  | "staff_child"
  | "merit"
  | "hardship"
  | "scholarship"
  | "other"

export type ConcessionStatus = "pending" | "active" | "rejected" | "revoked"

/**
 * A standing discount/scholarship policy for a student or a guardian (all
 * their children). Applied to NEW invoices only — best single concession wins.
 */
export interface FeeConcession {
  id: number
  school_id: number
  branch_id: number | null
  branch_name?: string | null
  student_id: number | null
  student_name?: string | null
  student_public_id?: string | null
  parent_id: number | null
  parent_name?: string | null
  category: ConcessionCategory
  category_label: string
  discount_type: Exclude<DiscountType, "none">
  discount_value: string
  /** Fee types it touches — null means every fee. */
  fee_types: FeeType[] | null
  academic_year_id: number | null
  academic_year_name?: string | null
  term_id: number | null
  term_name?: string | null
  status: ConcessionStatus
  status_label: string
  source: "manual" | "auto_sibling" | "auto_staff"
  reason: string | null
  approved_by_name?: string | null
  approved_at: string | null
  revoked_at: string | null
  invoices_count?: number
  created_at: string
}

export interface ConcessionStats {
  pending_count: number
  active_count: number
  granted_value: string
  granted_invoices: number
}

export type AttendanceStatus = "present" | "absent" | "late" | "excused"

export interface AttendanceRosterEntry {
  /** Mirrors student_id — DataTable rows need an `id`. */
  id?: number
  student_id: number
  student_name: string
  /** Client-side helper for the status filter: status or "unmarked". */
  status_key?: string
  status: AttendanceStatus | null
  /** Who produced the saved mark: register UI or an RFID scan. */
  source: "manual" | "device" | null
  check_in: string | null
  check_out: string | null
  note: string | null
}

// ─── Attendance reports (analytics) ─────────────────────────────────────────

export interface AttendanceReportDeviceRow {
  id: number
  name: string
  location: string | null
  marks: number
  late: number
}

export interface AttendanceReportDeviceOption {
  id: number
  name: string
  location: string | null
}

export interface AttendanceReportOverview {
  window: { from: string; to: string; school_days: number }
  totals: {
    marks: number
    students: number
    by_status: Partial<Record<AttendanceStatus, number>>
    attendance_rate: number | null
    previous_rate: number | null
  }
  coverage: { recorded: number; expected: number; rate: number | null }
  punctuality: {
    late: number
    on_time_rate: number | null
    average_check_in: string | null
    average_late_check_in: string | null
  }
  absences: {
    total: number
    by_gender: { female: number; male: number }
    chronic_students: number
    perfect_students: number
  }
  sources: {
    manual: number
    device: number
    devices: AttendanceReportDeviceRow[]
  }
}

export interface AttendanceDailyPoint {
  date: string
  present: number
  late: number
  absent: number
  excused: number
  device: number
  manual: number
}

export interface AttendanceArrivalBucket {
  time: string
  total: number
  late: number
}

export type AttendanceBreakdownGroup = "school" | "branch" | "grade" | "section"

export interface AttendanceBreakdownRow {
  id: number
  name: string
  students: number
  marks: number
  present: number
  late: number
  absent: number
  excused: number
  rate: number | null
}

export interface AttendanceReportTrends {
  daily: AttendanceDailyPoint[]
  arrivals: AttendanceArrivalBucket[]
  breakdown: { group: AttendanceBreakdownGroup; rows: AttendanceBreakdownRow[] }
}

export type AttendanceStudentFlag = "chronic" | "perfect" | "frequent_late"

export interface AttendanceReportStudentRow {
  /** Mirrors student_id — DataTable rows need an `id`. */
  id?: number
  student_id: number
  public_id: string | null
  name: string
  gender: string | null
  section: string | null
  grade: string | null
  recorded: number
  present: number
  late: number
  absent: number
  excused: number
  attendance_rate: number | null
  absent_streak: number
  last_marks: { date: string; status: AttendanceStatus }[]
}

// ─── RFID devices, ID cards, guardian alerts ────────────────────────────────

export type DeviceAudience = "students" | "employees" | "both"

export interface Device {
  id: number
  school_id: number
  school_name?: string | null
  branch_id: number
  branch_name: string | null
  name: string
  location: string | null
  serial_no: string | null
  audience: DeviceAudience
  is_active: boolean
  last_seen_at: string | null
  last_event_at: string | null
  last_roster_at: string | null
  online: boolean
  events_today: number
  pending_events: number
  created_at: string
}

export type IdCardStatus = "active" | "lost" | "revoked" | "replaced"

export interface IdCardRow {
  id: number
  school_id: number
  school_name?: string | null
  branch_id: number
  branch_name?: string | null
  card_uid: string
  holder_type: "student" | "employee"
  holder_id: number
  holder_name: string | null
  status: IdCardStatus
  issued_on: string | null
  note?: string | null
  deactivated_at: string | null
  replaced_by_id: number | null
  issued_by_name: string | null
  created_at: string
}

export type DeviceEventStatus =
  | "pending"
  | "processed"
  | "unknown_card"
  | "inactive_card"
  | "no_enrollment"
  | "closed_term"

export interface DeviceEventRow {
  id: number
  device_id: number
  device_name: string | null
  card_uid: string
  holder_type: "student" | "employee" | null
  holder_name: string | null
  scanned_at: string
  received_at: string
  status: DeviceEventStatus
}

export type CardRequestStatus =
  | "requested"
  | "accepted"
  | "preparing"
  | "delivering"
  | "delivered"
  | "rejected"

/** One school → Temari.et card fulfilment request. */
export interface CardRequestRow {
  id: number
  school_id: number
  school_name: string | null
  branch_id: number
  branch_name: string | null
  holder_type: "student" | "employee"
  holder_name: string | null
  reason: "lost" | "damaged" | "new"
  note: string | null
  status: CardRequestStatus
  lost_card_uid: string | null
  new_card_uid: string | null
  requested_by_name: string | null
  created_at: string
  updated_at: string
}

/** One row of the bulk-issue worklist (people without an active card). */
export interface CardCandidate {
  id: number
  name: string
  detail: string
  /** Students only — the class as filterable fields (employees carry job titles in detail). */
  grade?: string | null
  grade_sort?: number | null
  section?: string | null
}

export interface AttendanceNotificationRow {
  id: number
  date: string
  student_id: number
  student_name: string | null
  guardian_name: string | null
  status: "absent" | "late"
  channel: "sms" | "email"
  recipient: string
  result: "sent" | "failed"
  sent_at: string | null
}

/** One day of a student's own register (parent/student /me lane). */
export interface MyAttendanceDay {
  date: string
  status: AttendanceStatus
  check_in: string | null
  check_out: string | null
  note: string | null
}

/** `meta.summary` on the /me attendance endpoints — year-to-date vitals. */
export interface MyAttendanceSummary {
  from: string | null
  total: number
  present: number
  late: number
  absent: number
  excused: number
  rate: number | null
  streak: number
}

/** Profile-centric parent row for the staff Parents register. */
export interface ParentRow {
  id: number
  public_id: string | null
  name: string | null
  first_name: string | null
  father_name: string | null
  grandfather_name: string | null
  phone: string | null
  email: string | null
  secondary_phone: string | null
  gender: Gender | null
  occupation: string | null
  employer: string | null
  photo_url: string | null
  country?: string | null
  state?: string | null
  city?: string | null
  sub_city?: string | null
  woreda?: string | null
  house_no?: string | null
  is_verified: boolean
  children_count?: number
  account?: PortalAccount | null
  attachments?: StudentAttachment[]
  children?: {
    student_id: number
    full_name: string
    public_id: string | null
    relationship: GuardianRelationship
    is_primary: boolean
    grade_level: string | null
    school: string | null
    branch: string | null
  }[]
  created_at: string
}

/** Data-minimal cross-school parent lookup result (guardians/search). */
export interface GuardianSearchResult {
  parent_id: number
  name: string | null
  public_id: string | null
  /** Masked (e.g. 0911•••45) — full numbers are never exposed in search. */
  phone: string | null
  children_count: number
  /** The parent's most recent link — seeds the new link's form. */
  defaults?: GuardianLinkDefaults | null
}

export interface GuardianLinkDefaults {
  relationship: GuardianRelationship
  can_view_grades: boolean
  can_view_attendance: boolean
  can_pay_fees: boolean
  can_receive_sms: boolean
  emergency_contact: boolean
}

export type GuardianRelationship =
  | "father"
  | "mother"
  | "grandfather"
  | "grandmother"
  | "uncle"
  | "aunt"
  | "sibling"
  | "legal_guardian"
  | "other"

export interface Guardian {
  id: number
  student_id: number
  parent_id: number
  /** The parent's public person code (from their user account). */
  public_id?: string | null
  name: string | null
  first_name?: string | null
  father_name?: string | null
  grandfather_name?: string | null
  phone: string | null
  email?: string | null
  secondary_phone: string | null
  gender?: Gender | null
  occupation?: string | null
  employer?: string | null
  photo_url?: string | null
  country?: string | null
  state?: string | null
  city?: string | null
  sub_city?: string | null
  woreda?: string | null
  house_no?: string | null
  attachments?: StudentAttachment[]
  /** The parent's portal login status (parents always have an account). */
  account?: PortalAccount | null
  relationship: GuardianRelationship
  relationship_label: string
  can_view_grades: boolean
  can_view_attendance: boolean
  can_pay_fees: boolean
  can_receive_sms: boolean
  is_primary: boolean
  emergency_contact: boolean
  priority_order: number
  is_active: boolean
  notes: string | null
  created_at: string
}

export type AccountStatus = "active" | "inactive" | "banned"

/** A school a user is affiliated with (derived from memberships). */
export interface UserSchoolRef {
  id: number
  name: string
}

/** A branch membership as surfaced on an admin user row. */
export interface UserBranchRef {
  id: number
  name: string
  school_id: number | null
  membership_id: number
  membership_active: boolean
  role: string
  /**
   * Whether the current actor may administer this membership (activate/deactivate/
   * remove) in their active context. When false the row is shown read-only — e.g. a
   * peer/higher role, or a branch outside the actor's current scope.
   */
  can_manage: boolean
}

/** A branch inside a user's School → Branch → Role affiliation tree. */
export interface UserAffiliationBranch {
  id: number
  name: string
  /** True when at least one membership in this branch is active. */
  active: boolean
  roles: string[]
}

/** One school a user is affiliated with, with its branch/role breakdown. */
export interface UserAffiliation {
  school_id: number
  school_name: string
  /** School-wide roles (principal / school_admin) not tied to a branch. */
  roles: string[]
  branches: UserAffiliationBranch[]
}

/** Lightweight user record returned by the admin users list endpoint. */
export interface AdminUser {
  id: number
  /** Public-facing person code (e.g. H8R6WV) — never the DB id. */
  public_id: string | null
  name: string
  phone: string
  email: string | null
  preferred_language: Locale
  avatar_url: string | null
  status: AccountStatus
  status_label: string
  roles: string[]
  type: "affiliated" | "independent"
  schools: UserSchoolRef[]
  branches: UserBranchRef[]
  /** Any visible membership is active — drives the scoped status column
   * (school-level memberships have no branch row to check). */
  has_active_membership: boolean
  /** School → Branch → Role tree; the source for the unified "Access" column. */
  affiliations: UserAffiliation[]
  /** Platform-scoped roles (Temari staff), not tied to any school. */
  platform_roles: string[]
  /** Roles the user holds that aren't anchored to a school/branch/platform. */
  other_roles: string[]
  /** Relationship-lane hats (ADR-012): derived from enrollment/guardianship,
   * never memberships. Scoped admins only get the parts inside their scope. */
  relationships: {
    student: {
      student_id: number
      school_name: string | null
      branch_name: string | null
      grade: string | null
    } | null
    parent: {
      children_count: number
      children: string[]
      schools: string[]
    } | null
  }
  last_login_at: string | null
  created_at: string
  deleted_at: string | null
}

export type CalendarMode = "ethiopian" | "gregorian"
export type ClockMode = "standard" | "ethiopian"

export interface ContextBranch {
  id: number
  name: string
  calendar_mode?: CalendarMode
  clock_mode?: ClockMode
}

export interface ContextSchool {
  id: number
  name: string
  logo_url?: string | null
  can_manage: boolean
  calendar_mode?: CalendarMode
  clock_mode?: ClockMode
  branches: ContextBranch[]
}

export interface ContextsResponse {
  is_platform: boolean
  schools: ContextSchool[]
}

/** A single selectable context in the switcher (platform, school, or branch). */
export interface ContextOption {
  id: string
  schoolId: number | null
  branchId: number | null
  schoolName: string | null
  branchName: string | null
  schoolLogoUrl?: string | null
  /** How this workspace writes dates/times (branch override → school → defaults). */
  calendarMode?: CalendarMode
  clockMode?: ClockMode
}

/** Platform catalog subject (Ethiopian curriculum) or school-custom subject. */
export interface Subject {
  id: number
  code: string
  name: string
  school_id: number | null
  school_name?: string | null
  category: string | null
  /** Explicit grade set (grade_level ids). Empty = taught in every grade. */
  grade_level_ids?: number[]
  /** The same set as grade_levels.sort_order values, ascending. */
  grade_sorts?: number[]
  /** Cognitive load 1 (light) … 5 (heavy) — drives the timetable solver. */
  weight?: number
  /** Special room this subject needs (lab/ict/gym/…); null = own classroom. */
  room_type?: string | null
  is_active: boolean
  assignments_count?: number
  created_at: string
}

/** An Ethiopian bank or mobile wallet from the platform catalog. */
export interface Bank {
  id: number
  code: string
  name: string
  type: "bank" | "wallet"
  logo: string | null
  is_active?: boolean
  accounts_count?: number
  created_at?: string
}

/** Counts + attention flags for the platform catalog studio hub. */
export interface CatalogOverview {
  subjects: {
    total: number
    platform: number
    custom: number
    inactive: number
  }
  grade_levels: { total: number }
  banks: { total: number; wallets: number; inactive: number }
  health_conditions: { total: number; inactive: number }
  school_directory: { total: number; unverified: number; on_platform: number }
  notification_events: { total: number; sms_enabled: number }
}

/** One in-app feed row — title/body arrive pre-localized by the backend. */
export interface AppNotification {
  id: number
  event: string
  category: NotificationCategory
  title: string
  body: string
  link: string | null
  school_id: number | null
  branch_id: number | null
  /** Digest fold size ("3 new submissions") when the event dedupes. */
  count: number | null
  read_at: string | null
  created_at: string
}

export type NotificationCategory =
  | "security"
  | "finance"
  | "attendance"
  | "academics"
  | "lms"
  | "chat"
  | "movement"
  | "approvals"
  | "hr"
  | "family"
  | "tutoring"
  | "system"

/** A catalog event row on the platform SMS whitelist screen. */
export interface NotificationEventRow {
  event: string
  category: NotificationCategory
  severity: "critical" | "important" | "info"
  email: boolean
  sms_default: boolean
  sms_enabled: boolean
}

/** A school-owned payment collection account, shared across branches. */
export interface BankAccount {
  id: number
  school_id: number
  bank: Bank | null
  account_name: string
  account_number: string
  /** School-level switch. */
  is_active: boolean
  branches: { id: number; name: string; is_active: boolean }[]
  /** Whether the account is attached to the ACTIVE branch, and its switch. */
  attached_to_branch: boolean
  branch_active: boolean | null
  /** Collection vitals — present on the payment-accounts page. */
  payments_count?: number | null
  collected_sum?: string | null
  fee_structures_count?: number | null
  last_payment_at?: string | null
  created_at: string
}

/** Collection analytics for one payment account (detail page). */
export interface BankAccountStats {
  collected: string
  transactions: number
  last_paid_at: string | null
  by_method: {
    method: PaymentMethod
    method_label: string
    total: string
    count: number
  }[]
  monthly: { month: string; total: string; count: number }[]
  by_fee: { fee: string; total: string; count: number }[]
}

/** One payment row on the account detail page. */
export interface BankAccountPayment {
  id: number
  invoice_id: number
  invoice_number: string
  invoice_title: string | null
  student_name: string | null
  student_public_id: string | null
  amount: string
  method: PaymentMethod
  method_label: string
  reference: string | null
  paid_at: string | null
  recorded_by_name: string | null
}

/** One row of the semester teaching grid (section × subject × teacher). */
export interface SubjectAssignmentRow {
  id: number
  section_id: number
  section_name?: string | null
  grade_level_id?: number | null
  grade_level_name?: string | null
  grade_level_sort?: number | null
  subject_id: number
  subject_name?: string | null
  subject_code?: string | null
  term_id: number
  employee_id: number | null
  employee_name?: string | null
  periods_per_week: number
  is_active: boolean
}

/** The matrix endpoint's picker metadata. */
export interface AssignmentMatrixMeta {
  teachers: { id: number; name: string }[]
  capabilities: {
    employee_id: number
    subject_id: number
    grade_level_id: number
  }[]
  sections: {
    id: number
    name: string
    grade_level_id: number
    grade_level_name: string | null
    grade_level_sort: number | null
  }[]
  subjects: {
    id: number
    code: string
    name: string
    category: string | null
    /** grade_levels.sort_order values; empty = taught in every grade. */
    grade_sorts: number[]
  }[]
}

export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// ─────────────────────────── Academic operations ───────────────────────────

export type PromotionDecision =
  | "promoted"
  | "repeated"
  | "graduated"
  | "withdrawn"
  | "transferred"

/** One row of the year-end promotion board. */
export interface PromotionBoardRow {
  enrollment_id: number
  student: {
    id: number
    public_id: string | null
    full_name: string
    gender: Gender
    photo_url: string | null
  }
  grade_level_id: number
  grade_level_name: string
  section_name: string | null
  enrollment_status: EnrollmentStatus
  term_averages: {
    term_id: number
    average: number | null
    rank: number | null
    rank_of: number | null
  }[]
  annual_average: number | null
  attendance_rate: number | null
  suggestion: PromotionDecision | null
  decision: {
    value: PromotionDecision
    notes: string | null
    average: number | null
    decided_at: string | null
    executed_at: string | null
  } | null
}

export interface PromotionBoardMeta {
  terms: { id: number; name: string; sequence: number; status: TermStatus }[]
  threshold: number
  top_grade_sort: number | null
}

export interface RolloverResult {
  executed: number
  skipped: number
  errors: { enrollment_id: number; student: string; message: string }[]
}

export interface RevertResult {
  reverted: number
  skipped: number
  errors: { enrollment_id: number; student: string; message: string }[]
}

// Section assignment board
export interface AssignBoardSection {
  id: number
  name: string
  capacity: number | null
  room_number: string | null
}

export interface AssignBoardStudent {
  enrollment_id: number
  student_id: number
  full_name: string
  public_id: string | null
  gender: Gender
  photo_url: string | null
  section_id: number | null
  enrollment_status: EnrollmentStatus
  last_average: number | null
}

// Transfers
export type TransferRequestStatus =
  | "requested"
  | "approved"
  | "rejected"
  | "cancelled"

export interface TransferRequest {
  id: number
  status: TransferRequestStatus
  status_label: string
  reason: string | null
  decision_note: string | null
  student?: {
    id: number
    public_id: string | null
    full_name: string
    gender: Gender
    photo_url: string | null
  }
  from_school_id: number
  from_school_name?: string
  from_branch_id: number
  from_branch_name?: string
  to_school_id: number
  to_school_name?: string
  to_branch_id: number
  to_branch_name?: string
  to_academic_year_id: number
  to_academic_year_name?: string
  to_grade_level_id: number
  to_grade_level_name?: string
  from_enrollment?: {
    grade_level_name: string | null
    section_name: string | null
    academic_year_name: string | null
    status: EnrollmentStatus
  }
  attachments?: TransferRequestAttachment[]
  requested_by_name?: string | null
  decided_by_name?: string | null
  decided_at: string | null
  created_at: string
}

export interface TransferRequestAttachment {
  id: number
  name: string
  url: string | null
  mime_type: string | null
  size: number | null
}

export interface TransferCandidate {
  student_id: number
  public_id: string | null
  full_name: string
  gender: Gender
  photo_url: string | null
  enrollment_id: number
  school_name: string
  branch_name: string
  branch_id: number
  grade_level_name: string
  academic_year_name: string
}

export interface TransferLetter {
  reference: string
  /** Unguessable token behind the public verification URL (QR target). */
  public_token: string | null
  student: {
    full_name: string
    public_id: string | null
    gender: Gender
    date_of_birth: string | null
    photo_url: string | null
  }
  from_school: string
  from_branch: string
  to_school: string
  to_branch: string
  last_grade: string | null
  last_section: string | null
  last_academic_year: string | null
  new_grade: string
  new_academic_year: string
  reason: string | null
  approved_by: string | null
  approved_at: string | null
  /** Official PDF (public page only) — download saves it, view prints it. */
  download_url?: string | null
  view_url?: string | null
}

/** Payload of the printable QR-verified withdrawal clearance letter. */
export interface WithdrawalLetter {
  /** Withdrawal row id — the PDF document lane's subject. */
  id: number
  reference: string
  /** Unguessable token behind the public verification URL (QR target). */
  public_token: string | null
  student: {
    full_name: string
    public_id: string | null
    gender: Gender
    date_of_birth: string | null
    photo_url: string | null
  }
  school: string
  branch: string
  last_grade: string | null
  last_section: string | null
  last_academic_year: string | null
  destination: string | null
  reason: string
  withdrawn_on: string
  outstanding_amount: string
  issued_by: string | null
  /** Official PDF (public page only) — download saves it, view prints it. */
  download_url?: string | null
  view_url?: string | null
}

// Timetable
export interface TermPeriod {
  id?: number
  sequence: number
  type: "class" | "break" | "lunch" | "flag"
  period_number: number | null
  label: string | null
  starts_at: string
  ends_at: string
}

export type RoomType =
  | "classroom"
  | "lab"
  | "library"
  | "ict"
  | "gym"
  | "music"
  | "art"
  | "hall"
  | "other"

export interface Room {
  id: number
  branch_id: number
  name: string
  type: RoomType
  capacity: number | null
  is_active: boolean
}

export type TimetableVersionStatus =
  | "draft"
  | "generating"
  | "published"
  | "archived"

export interface TimetableVersion {
  id: number
  term_id: number
  name: string
  status: TimetableVersionStatus
  status_label: string
  score: number | null
  conflicts: Record<string, unknown>[]
  days: number[]
  generated_at: string | null
  published_at: string | null
  slots_count: number
}

export interface TimetableGridAssignment {
  id: number
  section_id: number
  subject: {
    id: number
    code: string
    name: string
    weight: number
    /** Special room required (lab/gym/ict…); null = own classroom. */
    room_type: RoomType | null
  }
  teacher_id: number | null
  teacher_name: string | null
  periods_per_week: number
  block_size: number
  placed: number
}

export interface TimetableGridSlot {
  id: number
  subject_assignment_id: number
  day_of_week: number
  period_number: number
  room_id: number | null
  is_locked: boolean
}

export interface TimetableGrid {
  version: TimetableVersion
  periods: TermPeriod[]
  sections: {
    id: number
    name: string
    grade_level_id: number
    grade_level_name: string | null
  }[]
  assignments: TimetableGridAssignment[]
  slots: TimetableGridSlot[]
  rooms: { id: number; name: string; type: RoomType }[]
}

/** Published weekly timetable for one student (/me lanes). */
export interface StudentTimetable {
  term_id: number
  term_name: string
  section: string | null
  days: number[]
  periods: TermPeriod[]
  slots: {
    day_of_week: number
    period_number: number
    subject: string | null
    teacher: string | null
    room: string | null
  }[]
}

/* ───────────────────────── Grading module ───────────────────────── */

/** One score range of a grading scale (display + value + judgement). */
export interface GradingScaleBand {
  id?: number
  min_score: number
  max_score: number
  letter: string | null
  label: string
  grade_points: number | null
  is_passing: boolean
}

/** Numeric→grade mapping. Platform-seeded (school_id null) or school-custom. */
export interface GradingScale {
  id: number
  school_id: number | null
  code: string
  name: string
  description: string | null
  is_active: boolean
  is_platform: boolean
  sort_order: number
  bands: GradingScaleBand[]
  policies_count?: number
}

export type GradingDisplay = "numeric" | "letter" | "both"

/** Which scale + display applies to a grade window (school or branch layer). */
export interface GradingPolicy {
  id: number
  school_id: number
  branch_id: number | null
  branch_name?: string | null
  grading_scale_id: number
  scale?: GradingScale
  display: GradingDisplay
  min_grade_sort: number | null
  max_grade_sort: number | null
}

export type ContinuousAssessmentItemType =
  | "quiz"
  | "test"
  | "assignment"
  | "project"
  | "mid_exam"
  | "final_exam"

/** One assessment slot of a grade book template. */
export interface ContinuousAssessmentItem {
  id?: number
  type: ContinuousAssessmentItemType
  name: string
  weight: number
  max_score: number
  due_on: string | null
  sort_order?: number
}

/**
 * One targeting row of a grade book: a grade (null = all grades) optionally
 * narrowed to some of that grade's sections and/or subjects. Empty id-arrays
 * mean "all" on that axis.
 */
export interface ContinuousAssessmentTarget {
  grade_level_id: number | null
  grade_name?: string | null
  section_ids: number[]
  section_names: string[]
  subject_ids: number[]
  subject_names: string[]
}

/** Principal/director-defined assessment plan per branch + term, with targeting. */
export interface ContinuousAssessment {
  id: number
  school_id: number
  branch_id: number
  branch_name?: string | null
  term_id: number
  term_name?: string | null
  name: string
  targets: ContinuousAssessmentTarget[]
  is_active: boolean
  created_by_name?: string | null
  total_weight?: number
  items: ContinuousAssessmentItem[]
  created_at: string
}

export type MarklistStatus = "draft" | "submitted" | "approved"

/** Sign-off workflow state of one subject assignment's marklist. */
export interface Marklist {
  id: number
  subject_assignment_id: number
  status: MarklistStatus
  is_locked: boolean
  submitted_at: string | null
  submitted_by_name?: string | null
  approved_at: string | null
  approved_by_name?: string | null
  remarks: string | null
  /** Supervisor who declared on-behalf mark entry on this draft (trust rule). */
  assisted_by: number | null
  assisted_at: string | null
  assisted_by_name?: string | null
  assist_reason: string | null
}

/** One row of the marklist register (teacher's list / approval queue). */
export interface MarklistRegisterRow {
  /** Mirrors subject_assignment_id — DataTable keys rows by `id`. */
  id?: number
  subject_assignment_id: number
  subject: { id: number; code: string; name: string }
  section: { id: number; name: string; grade_level: string | null }
  teacher_name: string | null
  is_own: boolean
  assessments_count: number
  marklist: Marklist | null
}

/** One appraisal row of the teacher-evaluation register. */
export interface TeacherEvaluationRow {
  id: number
  employee: { id: number | null; name: string | null; photo_url: string | null }
  term: { id: number | null; name: string | null }
  evaluator_name: string | null
  status: "draft" | "submitted" | "acknowledged"
  overall_score: number | null
  submitted_at: string | null
  acknowledged_at: string | null
  updated_at: string | null
}

/** One snapshotted criterion line of an appraisal. */
export interface EvaluationScoreLine {
  id: number
  domain: string
  label: string
  weight: number
  max_score: number
  score: number | null
  note: string | null
}

/** Full appraisal detail (register row + narratives + score lines). */
export interface TeacherEvaluationDetail extends TeacherEvaluationRow {
  strengths: string | null
  improvements: string | null
  teacher_comment: string | null
  scores: EvaluationScoreLine[]
  signals?: {
    classes: number
    marklists_approved: number
    lesson_plans_total: number
    lesson_plans_approved: number
  }
}

/** The school's appraisal rubric. */
export interface EvaluationTemplateData {
  id: number
  name: string
  description: string | null
  criteria: { id?: number; domain: string; label: string; weight: number; max_score: number }[]
}

/** One row of the director's marklist submission monitor. */
export interface MarklistStatusRow {
  /** Mirrors subject_assignment_id — DataTable keys rows by `id`. */
  id?: number
  subject_assignment_id: number
  subject: { id: number | null; code: string | null; name: string | null }
  section: { id: number | null; name: string; grade_sort: number }
  teacher: {
    employee_id: number | null
    name: string | null
    photo_url: string | null
    has_account: boolean
  }
  status: MarklistStatus | "not_started"
  submitted_at: string | null
  approved_at: string | null
  /** Marks-grid completeness: recorded cells over roster × columns. */
  entry: { students: number; columns: number; percent: number | null }
}

/** Meta block of the submission monitor payload. */
export interface MarklistStatusMeta {
  term: { id: number; name: string; status: string; ends_on: string | null }
  total: number
  not_started: number
  draft: number
  submitted: number
  approved: number
}

/** A column of the marks grid. */
export interface MarklistAssessment {
  id: number
  type: string
  name: string
  max_score: number
  weight: number
  conducted_on: string | null
  is_planned: boolean
}

/** A student row of the marks grid. */
export interface MarklistStudent {
  student_id: number
  public_id: string | null
  full_name: string
  gender: string | null
  scores: {
    assessment_id: number
    score: number | null
    is_absent: boolean
    /** Employee who recorded this cell — the per-mark audit trail. */
    recorded_by?: number | null
  }[]
}

/** A non-owner who recorded score cells — powers the "entered by" badges. */
export interface MarklistRecorder {
  employee_id: number
  name: string
  cells: number
}

/** The full marks grid payload for one subject assignment. */
export interface MarklistGrid {
  subject_assignment_id: number
  subject: { id: number; code: string; name: string }
  section: { id: number; name: string; grade_level: string | null }
  term: { id: number; name: string; is_closed: boolean }
  teacher_name: string | null
  is_own: boolean
  can_approve: boolean
  /** Server-decided trust rule: may the viewer type score cells right now? */
  can_edit_marks: boolean
  /** Supervisor on a teacher-owned draft: may declare on-behalf entry. */
  can_request_assist: boolean
  /** Viewer entered/submitted marks here — someone else must approve. */
  four_eyes_blocked: boolean
  /** May the viewer add/edit free-form assessments (no plan + branch policy)? */
  can_define_assessments: boolean
  continuous_assessment: { id: number; name: string } | null
  marklist: Marklist
  assessments: MarklistAssessment[]
  students: MarklistStudent[]
  recorders: MarklistRecorder[]
}

/** Snapshotted grading of a frozen term result. */
export interface ResultGrading {
  scale: { id: number; code: string; name: string }
  display: GradingDisplay
  overall: {
    letter: string | null
    label: string
    grade_points: number | null
    is_passing: boolean
  } | null
}

/** Per-subject line of a frozen term result / report card. */
export interface ResultSubjectLine {
  subject_id: number
  code: string
  name: string
  total: number | null
  letter?: string | null
  band_label?: string | null
  is_passing?: boolean | null
}

/** The official (frozen) report card of one student for one term. */
export interface ReportCard {
  student: {
    id: number
    public_id: string | null
    full_name: string
    gender: string | null
    photo_url: string | null
  }
  school_name: string | null
  school_logo_url?: string | null
  branch_name: string | null
  academic_year: string | null
  term_id: number
  term_name: string | null
  grade_level: string | null
  section_name: string | null
  subjects: ResultSubjectLine[]
  total: number | null
  average: number | null
  rank: number | null
  rank_of: number | null
  subject_count: number
  grading: ResultGrading | null
  conduct: string | null
  absence_days: number | null
  comment: string | null
  computed_at: string | null
}

/** One academic year on the transcript grid (a column group). */
export interface TranscriptYear {
  academic_year_id: number
  academic_year: string | null
  grade_level: string | null
  school_name: string | null
  branch_name: string | null
  annual_average: number | null
  /** Year-end outcome from the promotion board ("Promoted to Grade 10"). */
  outcome: {
    decision: string
    label: string
    to_grade_level: string | null
  } | null
  terms: {
    term_id: number
    term_name: string | null
    section_name: string | null
    total: number | null
    average: number | null
    rank: number | null
    rank_of: number | null
    conduct: string | null
    absence_days: number | null
    subjects: ResultSubjectLine[]
    grading: ResultGrading | null
  }[]
}

/** Multi-year transcript aggregated from frozen term results. */
export interface Transcript {
  student: {
    id: number
    public_id: string | null
    full_name: string
    gender: string | null
    date_of_birth: string | null
    photo_url: string | null
  }
  /** The CURRENT custodian of the record — whose letterhead the sheet wears. */
  issued_by: {
    school_name: string
    logo_url: string | null
    branch_name: string | null
    /** Branch address, falling back to the school's own line. */
    address: string | null
    /** Branch phone → school phone → principal's phone. */
    phone: string | null
  } | null
  years: TranscriptYear[]
  /** The full frozen history — drives the year picker and the partial stamp. */
  available_years: {
    academic_year_id: number
    academic_year: string | null
    grade_level: string | null
    terms_count: number
  }[]
  /** True when the sheet covers a subset of the record (stamped as such). */
  is_partial: boolean
  generated_at: string
}

/** The public token page behind a transcript's QR (live, revocable). */
export interface PublicTranscript {
  transcript: Transcript
  download_url: string | null
  /** Inline URL — Print opens the PDF in the viewer, never a download. */
  view_url: string | null
  issued_on: string | null
}

/** One student line on the transcripts register (per-year enrollment view). */
export interface TranscriptRegisterRow {
  student_id: number
  public_id: string | null
  full_name: string | null
  gender: string | null
  photo_url: string | null
  section_id: number | null
  section_name: string | null
  grade_level_name: string | null
  /** Frozen history across ALL years — 0 terms = transcript not ready. */
  years_count: number
  terms_count: number
  last_computed_at: string | null
}

/** Grading analytics payload for one term (frozen-rows aggregates). */
export interface GradingReport {
  term: { id: number; name: string; status: TermStatus }
  totals: {
    students: number
    with_results: number
    average: number | null
    pass_rate: number | null
    avg_absence_days: number | null
  }
  bands: {
    label: string
    letter: string | null
    is_passing: boolean
    count: number
  }[]
  sections: {
    section_id: number
    name: string
    students: number
    average: number
    pass_rate: number
  }[]
  subjects: {
    subject_id: number
    code: string | null
    name: string
    students: number
    average: number
    pass_rate: number
  }[]
  marklists: {
    total: number
    draft: number
    submitted: number
    approved: number
  }
  /** Same-scope stats of the previous semester in the year (trend), if frozen. */
  previous: {
    term: { id: number; name: string }
    average: number | null
    pass_rate: number | null
  } | null
  gender: {
    gender: "male" | "female"
    students: number
    average: number | null
    pass_rate: number | null
  }[]
  top_students: {
    student_id: number
    public_id: string | null
    full_name: string | null
    photo_url: string | null
    section: string | null
    average: number
    rank: number | null
    letter: string | null
  }[]
  /** Students below the pass line, weakest first. */
  at_risk: {
    student_id: number
    public_id: string | null
    full_name: string | null
    photo_url: string | null
    section: string | null
    average: number
    absence_days: number | null
    letter: string | null
  }[]
}

/** One school-defined behavioral skill row on the report card. */
export interface ReportCardSkill {
  key: string
  group: "habits" | "character"
  label: { en: string; am: string; om: string }
}

/** The fixed rating codes every skill is marked on. */
export type SkillRating = "E" | "VG" | "S" | "NI"

/** Branch-effective report-card policy, carried in the roster meta. */
export interface ReportCardMeta {
  skills: ReportCardSkill[]
  per_page: 1 | 2 | 4
  subject_ranks: boolean
  grading_criteria: boolean
}

/** One subject column of a roster sheet (server-built union). */
export interface RosterColumn {
  subject_id: number
  code: string | null
  name: string
}

/** One frozen subject mark cell on a roster sheet. */
export interface RosterScoreCell {
  total: number | null
  letter: string | null
  is_passing: boolean | null
}

/** Identity cells shared by both roster sheets. */
export interface RosterStudentCells {
  student_id: number
  public_id: string | null
  full_name: string | null
  photo_url: string | null
  gender: string | null
  section_id: number | null
  section_name: string | null
}

/** One student line on the term (semester/quarter) roster. */
export interface TermRosterRow extends RosterStudentCells {
  student_enrollment_id: number
  /** subject_id → frozen mark cell. */
  scores: Record<string, RosterScoreCell>
  total: number | null
  average: number | null
  rank: number | null
  rank_of: number | null
  absence_days: number | null
  conduct: string | null
  comment: string | null
  /** skill key → rating code, per the branch's configured checklist. */
  skills: Record<string, string> | null
}

/** GET terms/{id}/roster payload. */
export interface TermRoster {
  columns: RosterColumn[]
  rows: TermRosterRow[]
}

export interface TermRosterMeta {
  term: {
    id: number
    name: string
    status: TermStatus
    is_quarter: boolean
    semester: number | null
  }
  students: number
  computed_at: string | null
  report_card: ReportCardMeta
}

/** One term line of a yearly-roster student. */
export interface YearRosterTermLine {
  term_id: number
  student_enrollment_id: number
  scores: Record<string, RosterScoreCell>
  total: number | null
  average: number | null
  rank: number | null
  rank_of: number | null
  absence_days: number | null
  conduct: string | null
  comment: string | null
  skills: Record<string, string> | null
}

/** One student on the yearly roster: terms, semester sub-averages, year row. */
export interface YearRosterStudent extends RosterStudentCells {
  terms: YearRosterTermLine[]
  semesters: { semester: number; average: number }[]
  year: { average: number | null; rank: number | null; rank_of: number | null }
}

/** GET academic-years/{id}/roster payload. */
export interface YearRoster {
  columns: RosterColumn[]
  students: YearRosterStudent[]
}

export interface YearRosterMeta {
  year: { id: number; name: string; status: AcademicYearStatus }
  terms: {
    id: number
    name: string
    sequence: number
    is_quarter: boolean
    semester: number | null
    status: TermStatus
  }[]
  has_semester_groups: boolean
  students: number
  computed_at: string | null
  report_card: ReportCardMeta
}

/** One editable performance range on the marklist-analysis tab. */
export interface ScoreRange {
  min: number
  max: number
  label: string
  letter?: string | null
  is_passing?: boolean
}

/** One scored student row of the marklist analysis. */
export interface MarklistAnalysisStudent {
  student_id: number
  public_id: string | null
  full_name: string | null
  photo_url: string | null
  gender: string | null
  section_id: number | null
  section_name: string | null
  score: number
  letter: string | null
  is_passing: boolean | null
  rank: number | null
}

/** GET terms/{id}/marklist-analysis payload. */
export interface MarklistAnalysis {
  term: { id: number; name: string; status: TermStatus }
  subject: { id: number; code: string | null; name: string } | null
  students: MarklistAnalysisStudent[]
  summary: {
    count: number
    male: number
    female: number
    average: number | null
    min: number | null
    max: number | null
  }
  default_ranges: ScoreRange[] | null
}

// ─────────────────────────── LMS (ADR-016) ───────────────────────────

export type QuestionType =
  | "mcq_single"
  | "mcq_multi"
  | "true_false"
  | "short_answer"
  | "numeric"
  | "fill_blank"
  | "matching"
  | "essay"
  | "group"

export type QuizKind = "quiz" | "exam" | "mock"
export type QuizStatus = "draft" | "published" | "closed" | "archived"
export type QuizAttemptStatus =
  | "in_progress"
  | "submitted"
  | "graded"
  | "invalidated"

export interface QuestionBank {
  id: number
  name: string
  description: string | null
  school_id: number | null
  branch_id: number | null
  branch_name?: string | null
  is_platform: boolean
  subject_id: number | null
  subject_name?: string | null
  school_name?: string | null
  grade_level_id: number | null
  grade_level_name?: string | null
  topics: string[]
  is_active: boolean
  questions_count?: number
  created_by: number | null
  created_by_name?: string | null
  created_at: string
  can_edit: boolean
  can_delete: boolean
}

export interface QuestionOption {
  id: string
  text: string
}

export interface QuestionAttachment {
  kind: "file" | "link" | "youtube"
  name?: string | null
  url?: string | null
  path?: string | null
  mime_type?: string | null
  size?: number | null
}

export interface QuestionBody {
  stem: string
  options?: QuestionOption[]
  left?: QuestionOption[]
  right?: QuestionOption[]
  blanks_text?: string
  attachments?: QuestionAttachment[]
  [key: string]: unknown
}

export interface QuestionAnswerKey {
  correct?: string | string[] | boolean
  accepted?: string[]
  value?: number
  tolerance?: number
  blanks?: string[][]
  pairs?: Record<string, string>
  rubric?: string
  [key: string]: unknown
}

export interface Question {
  id: number
  question_bank_id: number
  bank_name?: string | null
  /** Set on a sub-question filed under a `group` container. */
  parent_id?: number | null
  position?: number | null
  /** Groups only: how many sub-questions the container holds. */
  children_count?: number
  type: QuestionType
  body: QuestionBody
  answer_key: QuestionAnswerKey | null
  points: number
  difficulty: "easy" | "medium" | "hard" | null
  topic: string | null
  tags: string[]
  source: string | null
  explanation: string | null
  status: "draft" | "published" | "retired"
  created_by_name?: string | null
  created_at: string
  can_edit: boolean
  can_delete: boolean
}

export interface QuizSettings {
  duration_minutes?: number | null
  opens_at?: string | null
  closes_at?: string | null
  attempts_allowed?: number | null
  shuffle_questions?: boolean
  shuffle_options?: boolean
  navigation?: "free" | "sequential"
  results_policy?: "immediately" | "after_close" | "manual"
  results_released?: boolean
  reveal_answers?: boolean
  grade_attempt?: "best" | "last" | "first"
}

export interface QuizDrawRule {
  question_bank_id: number
  count: number
  difficulty?: "easy" | "medium" | "hard" | null
  tags?: string[]
}

/** One paper part ("Part I — Multiple Choice…"), referenced by index. */
export interface QuizPart {
  title: string
  instructions?: string | null
}

export interface Quiz {
  id: number
  kind: QuizKind
  title: string
  instructions: string | null
  is_platform: boolean
  subject_assignment_id: number | null
  subject_assignment_ids?: number[]
  sections?: { id: number; name: string }[]
  section_names?: string[]
  section_name?: string | null
  expected_takers?: number
  takers_count?: number
  subject_id: number | null
  subject_name?: string | null
  grade_level_id: number | null
  grade_level_name?: string | null
  exam_kind?: "national_past" | "mock" | "practice" | null
  exam_year_ec?: number | null
  stream?: "natural" | "social" | null
  language: string
  status: QuizStatus
  total_points: number
  settings: QuizSettings
  draw: QuizDrawRule[] | null
  parts: QuizPart[] | null
  has_access_code: boolean
  question_count?: number
  attempts_count?: number
  assessment_id: number | null
  assessment_name?: string | null
  published_at: string | null
  closed_at: string | null
  created_by_name?: string | null
  created_at: string
  can_edit: boolean
  can_delete: boolean
}

/** A question group's passage/introduction, sent once per paper. */
export interface QuestionGroupStem {
  id: number
  stem: string
  attachments?: QuestionAttachment[]
}

export interface QuizDetail extends Quiz {
  questions: (Question & {
    quiz_points: number
    sort_order: number
    part_index: number | null
  })[]
  /** Passages of every group referenced by the questions, keyed by group id. */
  groups?: Record<number, QuestionGroupStem>
}

export interface QuizAttemptRow {
  id: number
  quiz_id: number
  user_id: number
  student_id: number | null
  taker_name: string | null
  student_public_id?: string | null
  attempt_number: number
  status: QuizAttemptStatus
  started_at: string
  deadline_at: string | null
  submitted_at: string | null
  graded_at: string | null
  score: number | null
  max_score: number
  pending_manual: boolean
  flag_count: number
  integrity_log?: { type: string; at: string }[]
}

export interface AttemptGradingQuestion {
  question_id: number
  number: number
  part?: number | null
  group_id?: number | null
  type: QuestionType
  points: number
  body: QuestionBody
  answer_key: QuestionAnswerKey | null
  explanation: string | null
  answer: unknown
  auto_score: number | null
  manual_score: number | null
  feedback: string | null
  needs_manual: boolean
}

export interface LmsFileMeta {
  name: string
  path?: string | null
  size: number | null
  mime_type?: string | null
  url: string | null
}

export type LmsSubmissionType = "text" | "file" | "photo" | "audio" | "link"

export type AssignmentKind = "standard" | "quiz"

export interface RubricCriterion {
  criterion: string
  max_points: number
}

export interface LmsAssignment {
  id: number
  kind: AssignmentKind
  quiz_id: number | null
  quiz_title?: string | null
  /**
   * Quiz-kind completion (present only for `kind === "quiz"`): the linked
   * quiz's attempts are the real turn-ins, so the teacher screen bridges to
   * the exam grading lane instead of the (empty) submissions queue.
   */
  quiz_stats?: {
    status: QuizStatus
    expected_takers: number
    takers_count: number
  } | null
  title: string
  instructions: string | null
  subject_assignment_id: number
  section_name?: string | null
  grade_level_name?: string | null
  subject_name?: string | null
  submission_types: LmsSubmissionType[]
  rubric: RubricCriterion[] | null
  target_student_ids: number[] | null
  resubmission_policy: "until_graded" | "once" | "never"
  attachments: LmsFileMeta[]
  max_score: number | null
  available_from: string | null
  due_at: string | null
  late_policy: "accept" | "reject"
  late_penalty_percent: number | null
  status: "draft" | "published" | "closed"
  published_at: string | null
  assessment_id: number | null
  assessment_name?: string | null
  submissions_count?: number
  created_by_name?: string | null
  created_at: string
}

/** One student's message thread on an assignment (teacher inbox row). */
export interface AssignmentThread {
  conversation_id: number
  student_id: number
  student_name: string
  student_public_id: string | null
  student_photo_url: string | null
  messages_count: number
  last_body: string
  last_is_staff: boolean
  last_at: string
  awaiting_reply: boolean
}

export interface LmsSubmission {
  id: number
  assignment_id: number
  student_id: number
  student_name?: string | null
  student_public_id?: string | null
  student_photo_url?: string | null
  body: string | null
  files: LmsFileMeta[]
  link_url: string | null
  attempt_count: number
  submitted_at: string
  is_late: boolean
  status: "submitted" | "graded" | "returned"
  score: number | null
  rubric_scores: number[] | null
  feedback: string | null
  graded_at: string | null
  graded_by_name?: string | null
}

export interface CourseMaterial {
  id: number
  title: string
  description: string | null
  type: "file" | "link" | "youtube" | "text"
  content: {
    url?: string | null
    video_id?: string
    body?: string
    name?: string
    size?: number | null
    mime_type?: string | null
  }
  school_id: number | null
  branch_id: number | null
  branch_name?: string | null
  subject_id: number | null
  subject_name?: string | null
  min_grade_sort: number | null
  max_grade_sort: number | null
  is_pinned: boolean
  is_active: boolean
  targets?: { subject_assignment_id: number; section_name: string | null }[]
  created_by: number | null
  created_by_name?: string | null
  created_at: string
  can_edit: boolean
  can_delete: boolean
}

// ── courses (modules → lessons → progress) ──

export type CourseLessonType = "video" | "reading" | "file" | "quiz"

export interface CourseLessonRow {
  id: number
  course_module_id?: number
  type: CourseLessonType
  title: string
  duration_minutes: number | null
  is_preview?: boolean
  sort_order?: number
  quiz_id: number | null
  quiz_title?: string | null
  content?: {
    url?: string | null
    body?: string
    name?: string
    size?: number | null
    mime_type?: string | null
  }
}

export interface CourseModuleRow {
  id: number
  title: string
  description: string | null
  sort_order?: number
  lessons: CourseLessonRow[]
}

export interface Course {
  id: number
  title: string
  description: string | null
  is_platform: boolean
  school_id?: number | null
  branch_id?: number | null
  branch_name?: string | null
  subject_assignment_id?: number | null
  section_name?: string | null
  targets?: { subject_assignment_id: number; section_name: string | null }[]
  subject_id?: number | null
  subject_name?: string | null
  min_grade_sort?: number | null
  max_grade_sort?: number | null
  stream: "natural" | "social" | null
  language: string
  cover_url: string | null
  is_sequential: boolean
  status: "draft" | "published" | "archived"
  published_at?: string | null
  modules_count?: number
  lessons_count?: number
  created_by_name?: string | null
  created_at?: string
  can_edit?: boolean
  can_delete?: boolean
  modules?: CourseModuleRow[]
}

export interface MeCourse {
  id: number
  title: string
  description: string | null
  is_platform: boolean
  subject_name: string | null
  stream: "natural" | "social" | null
  language: string
  cover_url: string | null
  lessons_count: number
  completed_count: number
  progress_percent: number
  last_activity_at?: string | null
}

export interface MeCourseDetail extends MeCourse {
  is_sequential: boolean
  continue_lesson_id: number | null
  modules: {
    id: number
    title: string
    description: string | null
    lessons: {
      id: number
      type: CourseLessonType
      title: string
      duration_minutes: number | null
      status: "started" | "completed" | null
      is_locked: boolean
      quiz_id: number | null
    }[]
  }[]
}

export interface MeLessonContent {
  id: number
  course_id: number
  type: CourseLessonType
  title: string
  duration_minutes: number | null
  quiz_id: number | null
  content: {
    url?: string | null
    body?: string
    name?: string
    size?: number | null
    mime_type?: string | null
  }
}

/** One of the teacher's (or branch's) classes, from GET /marklists. */
export interface LmsClassOption {
  subject_assignment_id: number
  subject: { id: number | null; code: string | null; name: string | null }
  section: {
    id: number | null
    name: string | null
    grade_level: string | null
  }
  teacher_name?: string | null
}

// ── /me lane shapes ──

export interface MeAssignment {
  id: number
  kind: AssignmentKind
  quiz_id: number | null
  title: string
  subject_name: string | null
  section_name?: string | null
  submission_types: LmsSubmissionType[]
  resubmission_policy: "until_graded" | "once" | "never"
  max_score: number | null
  due_at: string | null
  late_policy: "accept" | "reject"
  late_penalty_percent?: number | null
  status: "published" | "closed"
  instructions?: string | null
  rubric?: RubricCriterion[] | null
  attachments?: LmsFileMeta[]
  quiz?: MeExam | null
  /**
   * Quiz-kind assignments carry no `submission` — the work is a quiz attempt.
   * This is the taker's progress on the linked quiz: present once they've made
   * at least one (non-invalidated) attempt. `score` is only filled when the
   * quiz's results policy releases it.
   */
  quiz_progress?: {
    status: QuizAttemptStatus
    submitted_at: string | null
    score: number | null
    max_score: number
    attempts_used: number
  } | null
  submission: {
    id: number
    status: "submitted" | "graded" | "returned"
    submitted_at: string
    is_late: boolean
    attempt_count: number
    body: string | null
    link_url: string | null
    files: LmsFileMeta[]
    score: number | null
    rubric_scores: number[] | null
    feedback: string | null
    graded_at: string | null
  } | null
}

export interface MeExam {
  id: number
  kind: QuizKind
  exam_kind?: "national_past" | "mock" | "practice" | null
  exam_year_ec?: number | null
  stream?: "natural" | "social" | null
  /** How the sitting behaves: practice = instant answers, mock = real-exam simulation. */
  mode?: "practice" | "mock"
  title: string
  instructions?: string | null
  subject_name: string | null
  section_name?: string | null
  grade_level_name?: string | null
  language?: string
  status: QuizStatus
  duration_minutes: number | null
  opens_at: string | null
  closes_at: string | null
  attempts_allowed: number
  attempts_used: number
  requires_access_code: boolean
  window_open: boolean
  can_start: boolean
  live_attempt_id: number | null
  /** Most recent finished sitting — links the card's "View result". */
  result_attempt_id?: number | null
  question_count: number
  best_score?: number | null
  /** Max score of the finished sitting (only when results are visible). */
  best_max_score?: number | null
}

export interface MeMaterial {
  id: number
  title: string
  description: string | null
  type: "file" | "link" | "youtube" | "text"
  subject_name: string | null
  is_pinned: boolean
  posted_at: string
  content: CourseMaterial["content"]
}

export interface PlayerQuestion {
  question_id: number
  number: number
  part: number | null
  group_id?: number | null
  type: QuestionType
  points: number
  body: QuestionBody
  answer: unknown
}

export interface AttemptState {
  attempt_id: number
  quiz_id: number
  quiz_title: string | null
  kind: QuizKind
  status: QuizAttemptStatus
  attempt_number: number
  started_at: string
  deadline_at: string | null
  remaining_seconds: number | null
  navigation: "free" | "sequential"
  max_score: number
  question_count: number
  results_policy: string
  instructions?: string | null
  parts?: QuizPart[] | null
  questions?: PlayerQuestion[]
  groups?: Record<number, QuestionGroupStem>
}

export interface AttemptResultQuestion {
  number: number
  question_id: number
  part?: number | null
  group_id?: number | null
  type: QuestionType
  points: number
  body: QuestionBody
  answer: unknown
  earned: number | null
  pending: boolean
  feedback: string | null
  answer_key?: QuestionAnswerKey | null
  explanation?: string | null
}

export interface AttemptResult {
  visible: boolean
  message?: string
  quiz_title?: string | null
  question_count?: number
  answered_count?: number
  results_policy?: string
  expected_release_at?: string | null
  parts?: QuizPart[] | null
  status: QuizAttemptStatus
  pending_manual?: boolean
  score?: number | null
  max_score?: number
  submitted_at: string | null
  reveal_answers?: boolean
  questions?: AttemptResultQuestion[]
  groups?: Record<number, QuestionGroupStem>
}

export interface MyAttemptRow {
  id: number
  quiz_id: number
  quiz_title: string | null
  kind: QuizKind
  is_platform: boolean
  subject_name: string | null
  grade_level_name: string | null
  status: QuizAttemptStatus
  submitted_at: string | null
  score: number | null
  max_score: number
  results_visible: boolean
}

/* ── Staff dashboard (GET /dashboard) ──────────────────────────────────── */

/** The current-term strip on the dashboard header. */
export interface DashboardTerm {
  id: number
  name: string
  year_name: string | null
  status: string
  starts_on: string | null
  ends_on: string | null
  week: number | null
  days_left: number | null
  /** 0–100 through the term, null before dates are set. */
  progress: number | null
}

export interface DashboardAttendanceToday {
  marked: number
  enrolled: number
  present: number
  late: number
  absent: number
  excused: number
  rate: number | null
}

export interface DashboardAttendanceDay {
  date: string
  present: number
  late: number
  absent: number
  excused: number
}

export interface DashboardFinanceMonth {
  ec_year: number
  ec_month: number
  label: string
  collected: string
  payments: number
}

export type DashboardQueueKey =
  | "pending_enrollments"
  | "payment_verifications"
  | "expenses_pending"
  | "leave_pending"
  | "transfers_incoming"
  | "marklists_submitted"
  | "concessions_pending"

export interface DashboardQueueItem {
  key: DashboardQueueKey
  count: number
}

export interface DashboardBranchRow {
  id: number
  name: string
  code: string
  students: number
  attendance_rate: number | null
  attendance_marked: number
  collected_month: string
}

export interface DashboardTeacherPeriod {
  period: number
  starts_at: string | null
  ends_at: string | null
  subject: string | null
  subject_code: string | null
  section: string | null
  section_id: number | null
  room: string | null
}

export interface DashboardTeacher {
  today: DashboardTeacherPeriod[]
  homerooms: {
    section_id: number
    section: string
    students: number
    marked_today: number
  }[]
  marklists: { draft: number; submitted: number; approved: number }
  lms: { to_grade: number; open_assignments: number }
}

/** One aggregated, permission-adaptive payload — absent key = no authority. */
export interface DashboardData {
  context: {
    today: string
    ethiopian: { year: number; month: number; day: number; label: string }
    term: DashboardTerm | null
  }
  org?: OrgStats
  attendance?: {
    today: DashboardAttendanceToday
    week: DashboardAttendanceDay[]
  }
  finance?: {
    month: DashboardFinanceMonth
    receivables: { balance: string; overdue: string; students: number }
    trend: DashboardFinanceMonth[]
  }
  staff_today?: {
    total: number
    present: number
    late: number
    absent: number
    on_leave: number
    marked: number
  }
  queue?: DashboardQueueItem[]
  branches?: DashboardBranchRow[]
  platform?: {
    schools: number
    branches: number
    students: number
    employees: number
    recent_schools: {
      id: number
      name: string
      branches: number
      created_at: string | null
    }[]
  }
  teacher?: DashboardTeacher | null
}

/* ── Chat (ADR-019) ─────────────────────────────────────────────────── */

export type ConversationKind = "direct" | "group" | "channel" | "context"

export type ChatSystemChannel =
  | "staff_room"
  | "branch_announcements"
  | "school_announcements"
  | "classroom"

export interface ChatAttachment {
  name: string
  size?: number | null
  mime_type?: string | null
  duration?: number | null
  url?: string | null
  /** Present only while composing (before send). */
  path?: string
}

export interface ChatReaction {
  emoji: string
  count: number
  user_ids: number[]
}

export interface ChatMessage {
  id: number
  conversation_id: number
  kind: "text" | "voice" | "system"
  body: string | null
  attachments: ChatAttachment[]
  meta: Record<string, unknown> | null
  status: "sent" | "pending" | "rejected"
  review_note: string | null
  author: { id: number; name: string; avatar_url: string | null } | null
  reply_to: {
    id: number
    body: string
    author_name: string | null
    kind: string
  } | null
  reactions: ChatReaction[]
  removed: boolean
  pinned?: boolean
  edited_at: string | null
  client_uuid: string | null
  created_at: string
  /** Client-only optimistic-send marker. */
  sending?: boolean
  failed?: boolean
}

/** A preset message as the composer picker receives it — already resolved. */
export interface ChatTemplatePick {
  id: number
  name: string
  category: string
  resolved_body: string
}

/** A preset message in the management studio (raw tri-language body). */
export interface ChatTemplate {
  id: number
  name: string
  category: string
  body: { en?: string; am?: string; om?: string }
  is_active: boolean
  branch_id: number | null
  branch_name: string | null
  updated_at: string | null
}

export interface ChatConversation {
  id: number
  kind: ConversationKind
  title: string | null
  system: ChatSystemChannel | null
  posting: "all" | "admins"
  school_id: number
  branch_id: number | null
  branch_name?: string | null
  section_id: number | null
  student: { id: number; name: string } | null
  context_type: string | null
  context_id: number | null
  archived: boolean
  last_message_at: string | null
  created_at: string
  display: {
    title: string | null
    subtitle: string | null
    avatar_url: string | null
  }
  unread?: number
  muted?: boolean
  pinned?: boolean
  last_message?: {
    body: string
    kind: string
    meta: Record<string, unknown> | null
    author_name: string | null
    author_id: number | null
    has_attachments: boolean
    created_at: string
  } | null
  /** Detail-only fields. */
  access?: "member" | "audit"
  can_post?: boolean
  needs_approval?: boolean
  can_moderate?: boolean
  can_pin?: boolean
  pinned_messages?: ChatMessage[]
  members?: {
    id: number
    name: string
    avatar_url: string | null
    role: string
    left?: boolean
  }[]
  targets?: {
    audience: "staff" | "parents" | "students"
    branch_id: number | null
    branch_name?: string | null
    grade_level_id: number | null
    grade_name?: string | null
    section_id: number | null
    section_name?: string | null
    job_title: string | null
  }[]
}

export interface ChatChannelOptions {
  /** Staff job titles that carry a login (the roles a staff channel can target). */
  roles: string[]
  grades: { id: number; name: string; sort: number }[]
  sections: { id: number; name: string; grade_level_id: number; branch_id: number | null }[]
  branches: { id: number; name: string }[]
  /** True in the school-wide workspace — the composer must name the target branch. */
  needs_branch: boolean
}

export interface ChatPartnerStaff {
  user_id: number
  name: string
  avatar_url: string | null
}

export interface ChatPartnerStudent {
  student_id: number
  name: string
  guardians: number
}

export interface ChatFamilyPartnerCard {
  student_id: number
  student_name: string | null
  branch_name: string | null
  is_self: boolean
  partners: {
    user_id: number
    name: string
    avatar_url: string | null
    role: "teacher" | "homeroom" | "office"
    subject: string | null
  }[]
}

export interface ChatSearchHit {
  id: number
  conversation_id: number
  body: string
  author_name: string | null
  created_at: string
  conversation: ChatConversation
}

// ── Lesson planning ─────────────────────────────────────────────────────────

export type LessonPlanStatus = "draft" | "submitted" | "approved" | "declined"
export type LessonCoverage = "pending" | "covered" | "partial" | "missed"

export interface LessonPlanPacingSummary {
  planned_periods: number
  covered_periods: number
  expected_periods: number
  lag_periods: number
  units_total: number
  units_done: number
  progress_percent: number
}

export interface AnnualPlanUnit {
  id: number
  sequence: number
  title: string
  objectives: string | null
  methods: string | null
  rationale: string | null
  prerequisite_knowledge: string | null
  teaching_aids: string | null
  assessment_techniques: string | null
  page_from: number | null
  page_to: number | null
  term: { id: number; name?: string; semester?: number | null } | null
  starts_on: string | null
  ends_on: string | null
  planned_periods: number
  lessons_count: number | null
}

export type LessonStageKey = "intro" | "main" | "conclusion"

export interface DailyPlanStageRow {
  stage: LessonStageKey
  learning_contents: string | null
  page: string | null
  teacher_activity: string | null
  student_activity: string | null
  assessment_techniques: string | null
  teaching_aids: string | null
  remark: string | null
}

export interface DailyPlanDeliveryRow {
  id: number
  section: { id: number | null; name: string | null }
  teaches_on: string
  period_number: number | null
  coverage: LessonCoverage
  coverage_note: string | null
}

/** The compact day row inside a week payload. */
export interface DailyPlanSummary {
  id: number
  teaches_on: string
  topic: string
  subtopic: string | null
  sequence: number
  unit_id: number | null
  unit_title: string | null
  homework: string | null
  deliveries: DailyPlanDeliveryRow[]
}

/** The full daily plan the studio edits. */
export interface DailyPlanRow {
  id: number
  weekly_lesson_plan_id: number
  week_starts_on: string | null
  week_status: LessonPlanStatus | null
  teaches_on: string
  topic: string
  subtopic: string | null
  unit_id: number | null
  unit_title: string | null
  rationale: string | null
  prerequisite_knowledge: string | null
  objectives: string | null
  support_slow: string | null
  support_medium: string | null
  support_fast: string | null
  homework: string | null
  sequence: number
  stages: DailyPlanStageRow[]
  deliveries: DailyPlanDeliveryRow[]
  is_own: boolean
  can_review: boolean
  editable: boolean
  plan?: {
    id: number
    status: LessonPlanStatus
    subject: { id: number | null; code: string | null; name: string | null }
    grade_level: { id: number | null; name: string | null }
    teacher_name: string | null
  }
  units?: {
    id: number
    sequence: number
    title: string
    page_from: number | null
    page_to: number | null
  }[]
  sections?: { id: number; name: string }[]
}

export interface WeeklyLessonPlanRow {
  id: number
  annual_lesson_plan_id: number
  week_starts_on: string
  status: LessonPlanStatus
  notes: string | null
  lag_justification: string | null
  decline_reason: string | null
  submitted_at: string | null
  submitted_by_name: string | null
  decided_at: string | null
  decided_by_name: string | null
  is_own: boolean
  can_review: boolean
  days: DailyPlanSummary[] | null
}

/** One row of the teacher's My Day timeline. */
export interface MyDayItem {
  period_number: number | null
  starts_at: string | null
  ends_at: string | null
  subject: { id: number | null; code: string | null; name: string | null }
  section: { id: number | null; name: string | null }
  grade_level: { id: number | null; name: string | null }
  plan: { id: number; status: LessonPlanStatus } | null
  week: { id: number; status: LessonPlanStatus } | null
  daily: {
    id: number
    topic: string
    subtopic: string | null
    delivery_id: number
    coverage: LessonCoverage
    coverage_note: string | null
  } | null
  suggested_unit: {
    id: number
    sequence: number
    title: string
    page_from: number | null
    page_to: number | null
  } | null
}

export interface MyDayPayload {
  date: string
  week_starts_on?: string
  has_timetable: boolean
  term?: { id: number; name: string; status: string } | null
  periods: { period_number: number; starts_at: string; ends_at: string }[]
  items: MyDayItem[]
}

export interface AnnualLessonPlanRow {
  id: number
  school_id: number
  branch_id: number
  academic_year: { id: number; name?: string; status?: string }
  subject: { id: number | null; code: string | null; name: string | null }
  grade_level: { id: number | null; name: string | null }
  teacher_name: string | null
  goals: string | null
  methods: string | null
  periods_per_week: number | null
  total_periods: number | null
  status: LessonPlanStatus
  submitted_at: string | null
  submitted_by_name: string | null
  decided_at: string | null
  decided_by_name: string | null
  decline_reason: string | null
  is_own: boolean
  can_review: boolean
  units_count?: number
  weekly_plans_count?: number
  pacing?: LessonPlanPacingSummary | null
  sections?: { id: number; name: string }[]
  units?: AnnualPlanUnit[]
  weekly_plans?: {
    id: number
    week_starts_on: string
    status: LessonPlanStatus
    lag_justified: boolean
    decline_reason: string | null
    lessons_count: number
    covered_count: number
  }[]
}

export interface LessonPlanOption {
  academic_year_id: number
  academic_year_name: string
  academic_year_status?: string
  subject: { id: number; code: string | null; name: string }
  grade_level: { id: number; name: string }
  grade_sort: number
  plan_id: number | null
}

export interface LessonPlanPacingRow {
  id: number
  subject: { id: number | null; code: string | null; name: string | null }
  grade_level: { id: number | null; name: string | null }
  teacher_name: string | null
  pacing: LessonPlanPacingSummary | null
  weeks_total: number
  weeks_approved: number
  weeks_declined: number
  weeks_justified: number
  last_week_starts_on: string | null
  last_week_status: LessonPlanStatus | null
}

export interface WeeklyPrefill {
  week_starts_on: string
  existing_id: number | null
  units: {
    id: number
    sequence: number
    title: string
    objectives: string | null
    planned_periods: number
    page_from: number | null
    page_to: number | null
  }[]
  needs_justification: boolean
  carryover: {
    week_starts_on: string | null
    lessons: {
      id: number
      topic: string
      teaches_on: string
      unit_id: number | null
      unit_title: string | null
      uncovered_sections: string[]
    }[]
  }
}

export interface FamilyLessonPlans {
  week_starts_on?: string
  subjects: {
    subject: { id: number | null; code: string | null; name: string | null }
    teacher_name: string | null
    has_plan: boolean
    progress_percent: number | null
    units_total: number | null
    units_done: number | null
    units:
      | {
          sequence: number
          title: string
          starts_on: string | null
          ends_on: string | null
          is_current: boolean
          is_past: boolean
        }[]
      | null
    current_week: {
      week_starts_on: string
      lessons: {
        teaches_on?: string
        day_of_week: number
        topic: string
        subtopic?: string | null
        objectives: string | null
        homework: string | null
        coverage: LessonCoverage
      }[]
    } | null
  }[]
}

// ───────────────────────────── Student import ──────────────────────────────

export type StudentImportStatus = "draft" | "importing" | "completed" | "failed"

export type StudentImportRowStatus =
  | "ready"
  | "duplicate"
  | "error"
  | "imported"
  | "skipped"
  | "failed"

export interface StudentImportIssue {
  field: string
  level: "error" | "warning" | "info"
  code: string
  /** English fallback for codes the client doesn't translate. */
  message: string
}

/** One guardian block of a canonical import row payload. */
export interface ImportGuardianPayload {
  first_name?: string | null
  father_name?: string | null
  grandfather_name?: string | null
  phone?: string | null
  relationship?: string | null
  is_primary?: boolean
}

/** The canonical mapped payload of one spreadsheet row. */
export interface ImportRowPayload {
  first_name?: string | null
  father_name?: string | null
  grandfather_name?: string | null
  mother_name?: string | null
  gender?: string | null
  date_of_birth?: string | null
  national_student_id?: string | null
  fayda_id?: string | null
  primary_phone?: string | null
  email?: string | null
  state?: string | null
  city?: string | null
  sub_city?: string | null
  woreda?: string | null
  house_no?: string | null
  grade_level_id?: number | null
  section_id?: number | null
  school_program_id?: number | null
  guardians?: ImportGuardianPayload[]
}

export interface StudentImportRow {
  id: number
  row_number: number
  payload: ImportRowPayload
  status: StudentImportRowStatus
  issues: StudentImportIssue[]
  resolution: "skip" | "create" | "enroll_existing" | null
  duplicate_student_id: number | null
  duplicate_student?: {
    id: number
    public_id: string
    full_name: string
  } | null
  student_id: number | null
  student?: { id: number; public_id: string; full_name: string } | null
  error: string | null
}

export interface StudentImport {
  id: number
  file_name: string
  status: StudentImportStatus
  branch_id: number
  branch?: { id: number; name: string }
  academic_year_id: number
  academic_year?: { id: number; name: string }
  grade_level_id: number | null
  section_id: number | null
  school_program_id: number | null
  column_map: Record<string, string> | null
  options: { send_sms: boolean; create_student_accounts: boolean }
  total_rows: number
  imported_count: number
  skipped_count: number
  failed_count: number
  /** Pre-commit status breakdown, present on show. */
  row_stats?: Partial<Record<StudentImportRowStatus, number>>
  /** Rows "Start import" will write (ready + resolved duplicates); on show. */
  importable_count?: number
  created_by: number
  creator?: { id: number; name: string }
  committed_at: string | null
  finished_at: string | null
  created_at: string
}

// ── Temari AI ──────────────────────────────────────────────────────────────

export type AiLane =
  | "student"
  | "parent"
  | "teacher"
  | "leadership"
  | "registrar"
  | "finance"
  | "platform"

export type AiPlanTier =
  | "free"
  | "premium"
  | "school"
  | "staff_free"
  | "platform"

export interface AiEntitlement {
  plan: AiPlanTier
  daily_limit: number
  used_today: number
  /** null = unlimited */
  remaining: number | null
  subscription_ends_at: string | null
  school_plan_until: string | null
}

/** The assistant surface — derived from the workspace, never user-picked. */
export type AiSurface = "school" | "family" | "platform"

export interface AiAssistantInfo {
  surface: AiSurface
  /** The capability lanes composing this assistant (priority-ordered). */
  lanes: AiLane[]
  entitlement: AiEntitlement
}

export interface AiContextInfo {
  /** First entry = the workspace's default assistant. */
  assistants: AiAssistantInfo[]
  limits: { max_prompt_length: number; max_attachments: number }
}

export interface AiConversationSummary {
  id: number
  uuid: string
  lane: AiLane
  surface: AiSurface
  title: string
  pinned: boolean
  student_id: number | null
  school_id: number | null
  branch_id: number | null
  last_message_at: string | null
  created_at: string
}

export interface AiMessageAttachment {
  /** Position within the message — addresses the bytes endpoint. */
  index: number
  name: string | null
  mime: string | null
  kind: "image" | "file"
  /** Local-echo only: an object URL for the just-picked file. */
  localUrl?: string
}

export interface AiChatMessage {
  /** SDK message uuid; local temp ids while streaming. */
  id: string
  role: "user" | "assistant"
  content: string
  attachments?: AiMessageAttachment[]
  created_at?: string
  /** True while tokens are still arriving. */
  streaming?: boolean
}

export interface AiSubscriptionPlans {
  plan: string
  price_etb: number
  days: number
  gateways: string[]
}
