/**
 * Ethiopian document types collected on students and guardians — mirrors
 * App\Enums\DocumentCategory on the backend. Labels come from the students
 * i18n namespace: `documents.categories.<value>`.
 */
export const DOCUMENT_CATEGORIES = [
  "birth_certificate",
  "vaccination_card",
  "kebele_id",
  "national_id",
  "passport",
  "transfer_certificate",
  "report_card",
  "medical_certificate",
  "custody_letter",
  "photograph",
  "other",
] as const

export type DocumentCategory = (typeof DOCUMENT_CATEGORIES)[number]
