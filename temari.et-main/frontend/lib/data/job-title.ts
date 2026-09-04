/**
 * Fixed job title catalog (job titles). Mirrors the backend
 * `App\Support\JobTitles` — keep the two in sync. Values are stable machine
 * codes; display labels come from the staff i18n domain
 * (`jobTitles.<code>`). The five role-mapped codes (director / teacher /
 * registrar / finance_officer / storekeeper) derive branch memberships on
 * the backend.
 */
export const JOB_TITLES = [
  "principal",
  "school_admin",
  "director",
  "vice_director",
  "teacher",
  "registrar",
  "finance_officer",
  "department_head",
  "unit_leader",
  "hr_officer",
  "accountant",
  "cashier",
  "secretary",
  "librarian",
  "counselor",
  "lab_assistant",
  "ict_officer",
  "nurse",
  "storekeeper",
  "security_guard",
  "janitor",
  "driver",
  "cook",
  "other",
] as const

export type JobTitle = (typeof JOB_TITLES)[number]

/** Qualification education levels — mirrors EmployeeQualification::EDUCATION_LEVELS. */
export const QUALIFICATION_LEVELS = [
  "certificate",
  "diploma",
  "bachelor",
  "master",
  "phd",
  "pgdt",
  "other",
] as const

export type QualificationLevel = (typeof QUALIFICATION_LEVELS)[number]
