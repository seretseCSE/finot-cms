import { fromEthiopian } from "@/lib/ethiopian-date"
import type { GradeLevel, ImportRowPayload, Section } from "@/lib/types"

/**
 * The import studio's target-field catalog + all the fuzzy parsing that turns
 * a messy Ethiopian school spreadsheet into canonical registration payloads.
 * Everything here runs in the BROWSER — the server only ever sees resolved
 * ids and normalized values (the .xlsx never uploads).
 */

export type TargetFieldKey =
  | "full_name"
  | "first_name"
  | "father_name"
  | "grandfather_name"
  | "mother_name"
  | "gender"
  | "date_of_birth"
  | "national_student_id"
  | "fayda_id"
  | "primary_phone"
  | "email"
  | "state"
  | "city"
  | "sub_city"
  | "woreda"
  | "house_no"
  | "grade"
  | "section"
  | "g1_name"
  | "g1_phone"
  | "g1_relationship"
  | "g2_name"
  | "g2_phone"
  | "g2_relationship"

export interface TargetField {
  key: TargetFieldKey
  /** i18n key under students:import.fields */
  labelKey: string
  group: "student" | "enrollment" | "guardians"
  /** Lower-cased header names (any language) that auto-map to this field. */
  synonyms: string[]
}

export const TARGET_FIELDS: TargetField[] = [
  { key: "full_name", labelKey: "fullName", group: "student", synonyms: ["full name", "student name", "name", "student full name", "ስም", "ሙሉ ስም", "የተማሪ ስም", "maqaa", "maqaa guutuu"] },
  { key: "first_name", labelKey: "firstName", group: "student", synonyms: ["first name", "given name", "የመጀመሪያ ስም", "maqaa duraa"] },
  { key: "father_name", labelKey: "fatherName", group: "student", synonyms: ["father name", "father", "fathers name", "father's name", "የአባት ስም", "maqaa abbaa"] },
  { key: "grandfather_name", labelKey: "grandfatherName", group: "student", synonyms: ["grandfather name", "grand father name", "grandfather", "የአያት ስም", "maqaa akaakayyuu"] },
  { key: "mother_name", labelKey: "motherName", group: "student", synonyms: ["mother name", "mother", "mothers name", "mother's name", "የእናት ስም", "maqaa haadhaa"] },
  { key: "gender", labelKey: "gender", group: "student", synonyms: ["gender", "sex", "ጾታ", "saala", "m/f"] },
  { key: "date_of_birth", labelKey: "dateOfBirth", group: "student", synonyms: ["date of birth", "dob", "birth date", "birthdate", "birthday", "የትውልድ ቀን", "guyyaa dhalootaa"] },
  { key: "national_student_id", labelKey: "nationalStudentId", group: "student", synonyms: ["national student id", "student id", "nemis", "nemis id", "id number", "id no"] },
  { key: "fayda_id", labelKey: "faydaId", group: "student", synonyms: ["fayda", "fayda id", "fin", "ፋይዳ"] },
  { key: "primary_phone", labelKey: "primaryPhone", group: "student", synonyms: ["student phone", "phone", "mobile", "phone number", "የተማሪ ስልክ", "bilbila barataa"] },
  { key: "email", labelKey: "email", group: "student", synonyms: ["email", "e-mail", "ኢሜይል"] },
  { key: "state", labelKey: "state", group: "student", synonyms: ["region", "state", "ክልል", "naannoo"] },
  { key: "city", labelKey: "city", group: "student", synonyms: ["city", "town", "ከተማ", "magaalaa"] },
  { key: "sub_city", labelKey: "subCity", group: "student", synonyms: ["sub city", "subcity", "sub-city", "ክፍለ ከተማ"] },
  { key: "woreda", labelKey: "woreda", group: "student", synonyms: ["woreda", "wereda", "ወረዳ", "aanaa"] },
  { key: "house_no", labelKey: "houseNo", group: "student", synonyms: ["house no", "house number", "የቤት ቁጥር"] },
  { key: "grade", labelKey: "grade", group: "enrollment", synonyms: ["grade", "grade level", "class", "level", "ክፍል", "kutaa"] },
  { key: "section", labelKey: "section", group: "enrollment", synonyms: ["section", "ሴክሽን", "kutaa xiqqaa"] },
  { key: "g1_name", labelKey: "g1Name", group: "guardians", synonyms: ["guardian name", "parent name", "guardian", "parent", "guardian 1 name", "guardian 1", "parent/guardian", "የወላጅ ስም", "maqaa maatii"] },
  { key: "g1_phone", labelKey: "g1Phone", group: "guardians", synonyms: ["guardian phone", "parent phone", "guardian 1 phone", "parent mobile", "family phone", "contact phone", "የወላጅ ስልክ", "bilbila maatii"] },
  { key: "g1_relationship", labelKey: "g1Relationship", group: "guardians", synonyms: ["relationship", "relation", "guardian relationship", "ዝምድና", "firooma"] },
  { key: "g2_name", labelKey: "g2Name", group: "guardians", synonyms: ["guardian 2 name", "guardian 2", "second guardian", "second guardian name"] },
  { key: "g2_phone", labelKey: "g2Phone", group: "guardians", synonyms: ["guardian 2 phone", "second guardian phone"] },
  { key: "g2_relationship", labelKey: "g2Relationship", group: "guardians", synonyms: ["guardian 2 relationship", "second guardian relationship"] },
]

const FIELD_BY_KEY = new Map(TARGET_FIELDS.map((field) => [field.key, field]))

export function targetField(key: TargetFieldKey): TargetField {
  return FIELD_BY_KEY.get(key)!
}

function normalizeHeader(header: string): string {
  return header
    .toLowerCase()
    .replace(/[_\-./]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
}

/**
 * Auto-map spreadsheet headers to target fields: exact synonym match first,
 * then a contains pass. Each target field maps at most once (first column
 * wins), and a previously saved mapping (same header text) takes precedence.
 */
export function autoMapColumns(
  headers: string[],
  savedMap: Record<string, string> | null,
): (TargetFieldKey | null)[] {
  const taken = new Set<TargetFieldKey>()
  const result: (TargetFieldKey | null)[] = headers.map(() => null)

  const claim = (index: number, key: TargetFieldKey | null) => {
    if (key === null || taken.has(key)) return
    // full_name and the explicit trio are alternatives — never map both.
    if (key === "full_name" && (taken.has("first_name") || taken.has("father_name"))) return
    if ((key === "first_name" || key === "father_name") && taken.has("full_name")) return
    taken.add(key)
    result[index] = key
  }

  // 1. The school's remembered mapping (exact header text).
  if (savedMap) {
    headers.forEach((header, index) => {
      const saved = savedMap[normalizeHeader(header)]
      if (saved && FIELD_BY_KEY.has(saved as TargetFieldKey)) {
        claim(index, saved as TargetFieldKey)
      }
    })
  }

  // 2. Exact synonym match.
  headers.forEach((header, index) => {
    if (result[index]) return
    const normalized = normalizeHeader(header)
    const field = TARGET_FIELDS.find((f) => f.synonyms.includes(normalized))
    claim(index, field?.key ?? null)
  })

  // 3. Loose contains match (longest synonym first, so "father name" beats "name").
  const bySynonymLength = TARGET_FIELDS.flatMap((field) =>
    field.synonyms.map((synonym) => ({ field, synonym })),
  ).sort((a, b) => b.synonym.length - a.synonym.length)

  headers.forEach((header, index) => {
    if (result[index]) return
    const normalized = normalizeHeader(header)
    if (!normalized) return
    const hit = bySynonymLength.find(({ synonym }) => synonym.length > 2 && normalized.includes(synonym))
    claim(index, hit?.field.key ?? null)
  })

  return result
}

/** Serialize the chosen mapping for reuse next time (header text → field). */
export function mappingToSave(headers: string[], mapping: (TargetFieldKey | null)[]): Record<string, string> {
  const map: Record<string, string> = {}
  headers.forEach((header, index) => {
    const key = mapping[index]
    if (key) map[normalizeHeader(header)] = key
  })
  return map
}

// ── Value converters ────────────────────────────────────────────────────────

const MALE_VALUES = new Set(["m", "male", "boy", "ወ", "ወንድ", "dhiira", "dh"])
const FEMALE_VALUES = new Set(["f", "female", "girl", "ሴ", "ሴት", "durba", "dubara", "shamarree"])

export function parseGender(raw: string): string | null {
  const value = raw.trim().toLowerCase()
  if (MALE_VALUES.has(value)) return "male"
  if (FEMALE_VALUES.has(value)) return "female"
  return null
}

const RELATIONSHIPS: Record<string, string> = {
  father: "father", dad: "father", "አባት": "father", abbaa: "father",
  mother: "mother", mom: "mother", "እናት": "mother", haadha: "mother",
  grandfather: "grandfather", "አያት": "grandfather",
  grandmother: "grandmother", "ሴት አያት": "grandmother", akkoo: "grandmother",
  uncle: "uncle", "አጎት": "uncle", eessuma: "uncle",
  aunt: "aunt", "አክስት": "aunt",
  brother: "sibling", sister: "sibling", sibling: "sibling", "ወንድም": "sibling", "እህት": "sibling",
  guardian: "legal_guardian", "legal guardian": "legal_guardian", "ሞግዚት": "legal_guardian",
}

export function parseRelationship(raw: string): string {
  return RELATIONSHIPS[raw.trim().toLowerCase()] ?? "other"
}

export type DateInterpretation = "auto" | "gc-dmy" | "gc-mdy" | "gc-ymd" | "ec-dmy" | "ec-ymd"

/** Days between the Gregorian and Excel-serial epochs (1900 date system). */
function excelSerialToIso(serial: number): string | null {
  if (serial < 60 || serial > 80000) return null
  const ms = Math.round((serial - 25569) * 86_400_000)
  const date = new Date(ms)
  if (Number.isNaN(date.getTime())) return null
  return date.toISOString().slice(0, 10)
}

function toParts(raw: string): [number, number, number] | null {
  const nums = raw.split(/[^\d]+/).filter(Boolean).map(Number)
  if (nums.length !== 3 || nums.some((n) => !Number.isFinite(n))) return null
  return [nums[0], nums[1], nums[2]]
}

/**
 * Parse one date cell under the chosen interpretation. `auto` handles Excel
 * serials, ISO strings, and unambiguous D/M/Y — the studio's calendar picker
 * exists exactly because "12/03/2009" alone can't tell GC from EC.
 */
export function parseDateCell(raw: string | number, mode: DateInterpretation): string | null {
  if (typeof raw === "number") return excelSerialToIso(raw)

  const text = raw.trim()
  if (!text) return null

  // ISO-ish YYYY-MM-DD is unambiguous in GC modes and `auto`.
  const parts = toParts(text)
  if (!parts) return null

  const build = (y: number, m: number, d: number, ethiopian: boolean): string | null => {
    if (ethiopian) return fromEthiopian({ year: y, month: m, day: d })
    if (y < 1900 || y > 2100 || m < 1 || m > 12 || d < 1 || d > 31) return null
    const iso = `${y}-${String(m).padStart(2, "0")}-${String(d).padStart(2, "0")}`
    return Number.isNaN(new Date(iso).getTime()) ? null : iso
  }

  const [a, b, c] = parts

  switch (mode) {
    case "gc-ymd":
      return build(a, b, c, false)
    case "gc-dmy":
      return a > 31 ? build(a, b, c, false) : build(c, b, a, false)
    case "gc-mdy":
      return a > 31 ? build(a, b, c, false) : build(c, a, b, false)
    case "ec-ymd":
      return build(a, b, c, true)
    case "ec-dmy":
      return a > 1000 ? build(a, b, c, true) : build(c, b, a, true)
    case "auto": {
      if (a > 1000) return build(a, b, c, false) // YYYY-first ⇒ ISO
      if (c > 1000) {
        // D/M/Y vs M/D/Y: prefer day-first (Ethiopian convention); swap only
        // when day-first is impossible.
        if (b <= 12) return build(c, b, a, false)
        if (a <= 12) return build(c, a, b, false)
      }
      return null
    }
  }
}

// ── Grade & section resolution ──────────────────────────────────────────────

/**
 * "Grade 1" / "G1" / "1" / "ክፍል 1" / "KG 1" / "kg-2" → the matching grade
 * level of the branch's offering.
 */
export function resolveGrade(raw: string, gradeLevels: GradeLevel[]): GradeLevel | null {
  const text = raw.trim().toLowerCase()
  if (!text) return null

  const exact = gradeLevels.find(
    (g) => g.name.toLowerCase() === text || g.code.toLowerCase() === text,
  )
  if (exact) return exact

  const kg = text.match(/(?:kg|k\.g|kindergarten|nursery)\s*[-.]?\s*(\d)?/)
  if (kg) {
    const code = `KG-${kg[1] ?? "1"}`
    return gradeLevels.find((g) => g.code.toLowerCase() === code.toLowerCase()) ?? null
  }

  const digits = text.match(/\d{1,2}/)
  if (digits) {
    const code = `G${Number(digits[0])}`
    return gradeLevels.find((g) => g.code.toLowerCase() === code.toLowerCase()) ?? null
  }

  return null
}

/** Section by name within the resolved grade ("A", "1A", "Blue"…). */
export function resolveSection(raw: string, gradeLevelId: number | null, sections: Section[]): Section | null {
  const text = raw.trim().toLowerCase()
  if (!text || gradeLevelId === null) return null

  const inGrade = sections.filter((s) => s.grade_level_id === gradeLevelId)
  return (
    inGrade.find((s) => s.name.toLowerCase() === text) ??
    // "1A" written where the section is just "A".
    inGrade.find((s) => text.endsWith(s.name.toLowerCase())) ??
    null
  )
}

// ── Row assembly ────────────────────────────────────────────────────────────

/** Patronymic split: "Abel Tesfaye Lemma" → first/father/grandfather. */
export function splitFullName(raw: string): { first?: string; father?: string; grandfather?: string } {
  const parts = raw.trim().split(/\s+/).filter(Boolean)
  return { first: parts[0], father: parts[1], grandfather: parts.slice(2).join(" ") || undefined }
}

export interface RowBuildContext {
  mapping: (TargetFieldKey | null)[]
  dateMode: DateInterpretation
  gradeLevels: GradeLevel[]
  sections: Section[]
  /** Manual value-mapping for grade texts resolveGrade couldn't place. */
  gradeOverrides: Record<string, number>
}

function cellText(value: unknown): string {
  if (value === null || value === undefined) return ""
  return String(value).trim()
}

/** Whether a parsed sheet row has any usable content under the mapping. */
export function rowHasContent(cells: unknown[], mapping: (TargetFieldKey | null)[]): boolean {
  return mapping.some((key, index) => key !== null && cellText(cells[index]) !== "")
}

/** Distinct grade texts in the sheet that resolveGrade cannot place. */
export function unresolvedGradeTexts(rows: unknown[][], context: RowBuildContext): string[] {
  const gradeIndex = context.mapping.indexOf("grade")
  if (gradeIndex === -1) return []

  const unresolved = new Set<string>()
  for (const cells of rows) {
    const text = cellText(cells[gradeIndex])
    if (!text) continue
    const key = text.toLowerCase()
    if (context.gradeOverrides[key] == null && resolveGrade(text, context.gradeLevels) === null) {
      unresolved.add(text)
    }
  }
  return [...unresolved]
}

/** Build the canonical payload for one sheet row. */
export function buildRowPayload(cells: unknown[], context: RowBuildContext): ImportRowPayload {
  const { mapping, dateMode, gradeLevels, sections, gradeOverrides } = context

  const valueOf = (key: TargetFieldKey): string => {
    const index = mapping.indexOf(key)
    return index === -1 ? "" : cellText(cells[index])
  }
  const rawOf = (key: TargetFieldKey): unknown => {
    const index = mapping.indexOf(key)
    return index === -1 ? "" : cells[index]
  }
  const orNull = (value: string): string | null => (value === "" ? null : value)

  let first = orNull(valueOf("first_name"))
  let father = orNull(valueOf("father_name"))
  let grandfather = orNull(valueOf("grandfather_name"))

  const fullName = valueOf("full_name")
  if (fullName && (!first || !father)) {
    const split = splitFullName(fullName)
    first = first ?? split.first ?? null
    father = father ?? split.father ?? null
    grandfather = grandfather ?? split.grandfather ?? null
  }

  const genderText = valueOf("gender")
  const dobRaw = rawOf("date_of_birth")
  const dob =
    typeof dobRaw === "number"
      ? parseDateCell(dobRaw, dateMode)
      : cellText(dobRaw)
        ? parseDateCell(cellText(dobRaw), dateMode)
        : null

  const gradeText = valueOf("grade")
  const overrideId = gradeText ? gradeOverrides[gradeText.toLowerCase()] : undefined
  const grade = overrideId != null
    ? gradeLevels.find((g) => g.id === overrideId) ?? null
    : gradeText
      ? resolveGrade(gradeText, gradeLevels)
      : null

  const section = resolveSection(valueOf("section"), grade?.id ?? null, sections)

  const guardians = (["g1", "g2"] as const).flatMap((prefix) => {
    const name = valueOf(`${prefix}_name` as TargetFieldKey)
    const phone = valueOf(`${prefix}_phone` as TargetFieldKey)
    if (!name && !phone) return []

    const split = splitFullName(name)
    const relationshipText = valueOf(`${prefix}_relationship` as TargetFieldKey)

    return [{
      first_name: split.first ?? null,
      father_name: split.father ?? null,
      grandfather_name: split.grandfather ?? null,
      phone: orNull(phone),
      relationship: relationshipText ? parseRelationship(relationshipText) : "legal_guardian",
    }]
  })

  return {
    first_name: first,
    father_name: father,
    grandfather_name: grandfather,
    mother_name: orNull(valueOf("mother_name")),
    gender: genderText ? parseGender(genderText) ?? genderText : null,
    date_of_birth: dob,
    national_student_id: orNull(valueOf("national_student_id")),
    fayda_id: orNull(valueOf("fayda_id")),
    primary_phone: orNull(valueOf("primary_phone")),
    email: orNull(valueOf("email")),
    state: orNull(valueOf("state")),
    city: orNull(valueOf("city")),
    sub_city: orNull(valueOf("sub_city")),
    woreda: orNull(valueOf("woreda")),
    house_no: orNull(valueOf("house_no")),
    grade_level_id: grade?.id ?? null,
    section_id: section?.id ?? null,
    guardians,
  }
}
