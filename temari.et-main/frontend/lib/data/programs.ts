/**
 * Education program catalog (type slugs). Mirrors the backend
 * `App\Models\SchoolProgram::CATALOG` — keep the two in sync. Display labels
 * come from the `common:programs.*` i18n keys.
 */
export const PROGRAM_TYPES = [
  "regular",
  "night",
  "extension",
  "distance",
  "summer",
  "tutorial",
  "special_needs",
] as const

export type ProgramType = (typeof PROGRAM_TYPES)[number]
