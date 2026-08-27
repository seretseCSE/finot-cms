export const EDUCATION_LEVELS = [
  "Primary School Certificate",
  "Junior Secondary School Certificate",
  "Senior Secondary School Certificate",
  "Technical and Vocational Education and Training (TVET)",
  "Level 4 Certificate",
  "Certificate",
  "Diploma",
  "Bachelor's Degree",
  "Master's Degree",
  "Doctorate (PhD)",
  "Assistant Professor",
  "Associate Professor",
  "Professor",
] as const

export type EducationLevel = (typeof EDUCATION_LEVELS)[number]
