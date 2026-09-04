/**
 * Fixed catalog of salary allowance names. Mirrors the backend
 * `App\Support\AllowanceTypes` — keep the two in sync.
 */
export const ALLOWANCE_TYPES = [
  "Housing Allowance",
  "Transport Allowance",
  "Medical Allowance",
  "Education Allowance",
  "Meal Allowance",
  "Uniform Allowance",
  "Communication Allowance",
  "Overtime Allowance",
] as const

export type AllowanceType = (typeof ALLOWANCE_TYPES)[number]
