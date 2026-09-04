import { z } from "zod"

/**
 * The one Ethiopian-phone standard for the whole frontend — mirrors the backend
 * `App\Support\PhoneNumber`. Accepts every Ethio Telecom / Safaricom Ethiopia
 * shape (local `09…`/`07…`, international `+2519…`/`+2517…`, bare `2519…`, with
 * or without spaces/dashes) and normalises to the canonical local form so the
 * value posted to the API always matches how the account is stored.
 *
 * School official lines additionally accept geographic landlines via the
 * `*ContactPhone` helpers (e.g. `+251 11 662 98 00` → `0116629800`).
 *
 * Safaricom (`07…`) acceptance is gated by NEXT_PUBLIC_ALLOW_SAFARICOM
 * (default OFF, mirrors the backend's `sms.allow_safaricom`): the SMS provider
 * only delivers to Ethio Telecom, so until the flag flips every phone field
 * rejects `07…` numbers.
 */
export const ALLOW_SAFARICOM = process.env.NEXT_PUBLIC_ALLOW_SAFARICOM === "true"

const ET_ANY_MOBILE_LOCAL_RE = /^0[79]\d{8}$/
const ET_ETHIO_TELECOM_LOCAL_RE = /^09\d{8}$/
const ET_PHONE_LOCAL_RE = ALLOW_SAFARICOM
  ? ET_ANY_MOBILE_LOCAL_RE
  : ET_ETHIO_TELECOM_LOCAL_RE
const ET_LANDLINE_LOCAL_RE = /^0[1-6]\d{8}$/

/**
 * The active-locale phone message, kept in sync by the I18nProvider via
 * `setPhoneValidationMessage` (source of truth: `common.validation.phone`).
 * Schemas are built at module load — before any locale is known — so the
 * message is resolved at VALIDATION time, never at schema-build time.
 */
let phoneValidationMessage = ALLOW_SAFARICOM
  ? "Enter a valid Ethiopian phone number (09… or 07…)"
  : "Enter an Ethio Telecom phone number (09…)"
let officePhoneValidationMessage = ALLOW_SAFARICOM
  ? "Enter a valid Ethiopian office or mobile number — 011…, 09…, 07… or +251…"
  : "Enter a valid office or Ethio Telecom mobile number — 011…, 09… or +251…"

export function setPhoneValidationMessage(message: string) {
  if (message) phoneValidationMessage = message
}

export function setOfficePhoneValidationMessage(message: string) {
  if (message) officePhoneValidationMessage = message
}

/** Strip formatting and map to 10 local digits (leading `0`), or null. */
function toLocalDigits(raw: string | null | undefined): string | null {
  if (raw == null) return null

  const trimmed = raw.trim()
  const hasPlus = trimmed.startsWith("+")
  const digits = trimmed.replace(/\D+/g, "")
  if (!digits) return null

  if (digits.startsWith("251")) {
    const rest = digits.slice(3)
    return rest.length === 9 ? "0" + rest : null
  }
  if (hasPlus) {
    // A '+' prefix that isn't +251 is another country — reject.
    return null
  }
  if (digits.length === 10 && digits[0] === "0") return digits
  if (digits.length === 9) return "0" + digits
  return null
}

/** Reduce any accepted shape to canonical local `09…`/`07…`, or null when invalid. */
export function normalizeEthPhone(
  raw: string | null | undefined
): string | null {
  const local = toLocalDigits(raw)
  return local && ET_PHONE_LOCAL_RE.test(local) ? local : null
}

/**
 * Mobile OR geographic landline — for school official contact lines only.
 * Canonical local form: `09…`/`07…` or `011…` (etc.).
 */
export function normalizeEthContactPhone(
  raw: string | null | undefined
): string | null {
  const local = toLocalDigits(raw)
  if (!local) return null
  if (ET_PHONE_LOCAL_RE.test(local) || ET_LANDLINE_LOCAL_RE.test(local)) {
    return local
  }
  return null
}

/** Whether the value is an acceptable Ethiopian mobile number in any shape. */
export function isEthPhone(raw: string | null | undefined): boolean {
  return normalizeEthPhone(raw) !== null
}

/** Whether the value is an acceptable Ethiopian mobile or office landline. */
export function isEthContactPhone(raw: string | null | undefined): boolean {
  return normalizeEthContactPhone(raw) !== null
}

/** Pretty grouping for display, e.g. `0911 234 567`. */
export function formatEthPhone(raw: string | null | undefined): string {
  const local = normalizeEthPhone(raw)
  if (!local) return raw ?? ""
  return `${local.slice(0, 4)} ${local.slice(4, 7)} ${local.slice(7)}`
}

/** Pretty grouping for contact lines: mobile or `011 662 98 00` landlines. */
export function formatEthContactPhone(raw: string | null | undefined): string {
  const local = normalizeEthContactPhone(raw)
  if (!local) return raw ?? ""
  if (ET_PHONE_LOCAL_RE.test(local)) return formatEthPhone(local)
  return `${local.slice(0, 3)} ${local.slice(3, 6)} ${local.slice(6, 8)} ${local.slice(8)}`
}

export function ethPhone(message?: string) {
  return z
    .string()
    .refine(
      (v) => isEthPhone(v),
      () => ({ message: message ?? phoneValidationMessage })
    )
    .transform((v) => normalizeEthPhone(v) ?? v)
}

export function optionalEthPhone(message?: string) {
  return z
    .string()
    .optional()
    .refine(
      (v) => !v || isEthPhone(v),
      () => ({ message: message ?? phoneValidationMessage })
    )
    .transform((v) => (v ? (normalizeEthPhone(v) ?? v) : v))
}

/** Optional school office line — mobile or geographic landline. */
export function optionalEthContactPhone(message?: string) {
  return z
    .string()
    .optional()
    .refine(
      (v) => !v || isEthContactPhone(v),
      () => ({ message: message ?? officePhoneValidationMessage })
    )
    .transform((v) => (v ? (normalizeEthContactPhone(v) ?? v) : v))
}

/**
 * Temari public person codes (students sign in with theirs): 6 chars from the
 * unambiguous alphabet — no 0/O/1/I. Mirrors backend `App\Support\PublicId`.
 */
const PUBLIC_ID_RE = /^[A-HJ-NP-Z2-9]{6}$/

export function isPublicId(raw: string | null | undefined): boolean {
  return raw != null && PUBLIC_ID_RE.test(raw.trim().toUpperCase())
}

/**
 * Whether a partially typed login identifier is heading towards a student ID
 * rather than a phone number (any letter can only be an ID). Drives the smart
 * login input's keyboard + casing.
 */
export function looksLikePublicId(raw: string): boolean {
  return /[a-z]/i.test(raw)
}

/** Locale-synced like the phone message (common.validation.loginIdentifier). */
let identifierValidationMessage = ALLOW_SAFARICOM
  ? "Enter your phone number (09…/07…) or your 6-character student ID"
  : "Enter your phone number (09…) or your 6-character student ID"

export function setIdentifierValidationMessage(message: string) {
  if (message) identifierValidationMessage = message
}

/**
 * The login `identifier`: an Ethiopian phone in any accepted shape OR a
 * Temari student ID. Normalises phones to canonical local form and IDs to
 * uppercase, matching the backend's NormalizesIdentifier.
 */
export function loginIdentifier(message?: string) {
  return z
    .string()
    .refine(
      (v) => isEthPhone(v) || isPublicId(v),
      () => ({ message: message ?? identifierValidationMessage })
    )
    .transform((v) => normalizeEthPhone(v) ?? v.trim().toUpperCase())
}
