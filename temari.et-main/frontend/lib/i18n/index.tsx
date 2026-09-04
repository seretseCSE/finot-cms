"use client"

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react"

import { apiFetch, getToken } from "@/lib/api"
import { setDateLocale } from "@/lib/dates"
import type { Locale } from "@/lib/types"
import { ALLOW_SAFARICOM, setIdentifierValidationMessage, setOfficePhoneValidationMessage, setPhoneValidationMessage } from "@/lib/validators"

import enCommon from "./en/common.json"
import enAuth from "./en/auth.json"
import enSchools from "./en/schools.json"
import enAcademic from "./en/academic.json"
import enStudents from "./en/students.json"
import enEmployees from "./en/employees.json"
import enAttendance from "./en/attendance.json"
import enDevices from "./en/devices.json"
import enFees from "./en/fees.json"
import enUsers from "./en/users.json"
import enPayroll from "./en/payroll.json"
import enHr from "./en/hr.json"
import enInventory from "./en/inventory.json"
import enDocs from "./en/docs.json"
import enMe from "./en/me.json"
import enPromotion from "./en/promotion.json"
import enTransfers from "./en/transfers.json"
import enTimetable from "./en/timetable.json"
import enCatalogs from "./en/catalogs.json"
import enGrading from "./en/grading.json"
import enLms from "./en/lms.json"
import enChat from "./en/chat.json"
import enLessonPlans from "./en/lesson-plans.json"
import enTutoring from "./en/tutoring.json"
import enAi from "./en/ai.json"
import amCommon from "./am/common.json"
import amAuth from "./am/auth.json"
import amSchools from "./am/schools.json"
import amAcademic from "./am/academic.json"
import amStudents from "./am/students.json"
import amEmployees from "./am/employees.json"
import amAttendance from "./am/attendance.json"
import amDevices from "./am/devices.json"
import amFees from "./am/fees.json"
import amUsers from "./am/users.json"
import amPayroll from "./am/payroll.json"
import amHr from "./am/hr.json"
import amInventory from "./am/inventory.json"
import amDocs from "./am/docs.json"
import amMe from "./am/me.json"
import amPromotion from "./am/promotion.json"
import amTransfers from "./am/transfers.json"
import amTimetable from "./am/timetable.json"
import amCatalogs from "./am/catalogs.json"
import amGrading from "./am/grading.json"
import amLms from "./am/lms.json"
import amChat from "./am/chat.json"
import amLessonPlans from "./am/lesson-plans.json"
import amTutoring from "./am/tutoring.json"
import amAi from "./am/ai.json"
import omCommon from "./om/common.json"
import omAuth from "./om/auth.json"
import omSchools from "./om/schools.json"
import omAcademic from "./om/academic.json"
import omStudents from "./om/students.json"
import omEmployees from "./om/employees.json"
import omAttendance from "./om/attendance.json"
import omDevices from "./om/devices.json"
import omFees from "./om/fees.json"
import omUsers from "./om/users.json"
import omPayroll from "./om/payroll.json"
import omHr from "./om/hr.json"
import omInventory from "./om/inventory.json"
import omDocs from "./om/docs.json"
import omMe from "./om/me.json"
import omPromotion from "./om/promotion.json"
import omTransfers from "./om/transfers.json"
import omTimetable from "./om/timetable.json"
import omCatalogs from "./om/catalogs.json"
import omGrading from "./om/grading.json"
import omLms from "./om/lms.json"
import omChat from "./om/chat.json"
import omLessonPlans from "./om/lesson-plans.json"
import omTutoring from "./om/tutoring.json"
import omAi from "./om/ai.json"

export type Domain =
  | "common"
  | "auth"
  | "schools"
  | "academic"
  | "students"
  | "employees"
  | "attendance"
  | "devices"
  | "fees"
  | "users"
  | "payroll"
  | "hr"
  | "inventory"
  | "docs"
  | "me"
  | "promotion"
  | "transfers"
  | "timetable"
  | "catalogs"
  | "grading"
  | "lms"
  | "chat"
  | "lessonPlans"
  | "tutoring"
  | "ai"

type Dictionary = Record<string, unknown>

const dictionaries: Record<Locale, Record<Domain, Dictionary>> = {
  en: {
    common: enCommon,
    auth: enAuth,
    schools: enSchools,
    academic: enAcademic,
    students: enStudents,
    employees: enEmployees,
    attendance: enAttendance,
    devices: enDevices,
    fees: enFees,
    users: enUsers,
    payroll: enPayroll,
    hr: enHr,
    inventory: enInventory,
    docs: enDocs,
    me: enMe,
    promotion: enPromotion,
    transfers: enTransfers,
    timetable: enTimetable,
    catalogs: enCatalogs,
    grading: enGrading,
    lms: enLms,
    chat: enChat,
    lessonPlans: enLessonPlans,
    tutoring: enTutoring,
    ai: enAi,
  },
  am: {
    common: amCommon,
    auth: amAuth,
    schools: amSchools,
    academic: amAcademic,
    students: amStudents,
    employees: amEmployees,
    attendance: amAttendance,
    devices: amDevices,
    fees: amFees,
    users: amUsers,
    payroll: amPayroll,
    hr: amHr,
    inventory: amInventory,
    docs: amDocs,
    me: amMe,
    promotion: amPromotion,
    transfers: amTransfers,
    timetable: amTimetable,
    catalogs: amCatalogs,
    grading: amGrading,
    lms: amLms,
    chat: amChat,
    lessonPlans: amLessonPlans,
    tutoring: amTutoring,
    ai: amAi,
  },
  om: {
    common: omCommon,
    auth: omAuth,
    schools: omSchools,
    academic: omAcademic,
    students: omStudents,
    employees: omEmployees,
    attendance: omAttendance,
    devices: omDevices,
    fees: omFees,
    users: omUsers,
    payroll: omPayroll,
    hr: omHr,
    inventory: omInventory,
    docs: omDocs,
    me: omMe,
    promotion: omPromotion,
    transfers: omTransfers,
    timetable: omTimetable,
    catalogs: omCatalogs,
    grading: omGrading,
    lms: omLms,
    chat: omChat,
    lessonPlans: omLessonPlans,
    tutoring: omTutoring,
    ai: omAi,
  },
}

export const LOCALES: Locale[] = ["en", "am", "om"]
const FALLBACK: Locale = "en"
const STORAGE_KEY = "temari_locale"

interface I18nContextValue {
  locale: Locale
  /** User-initiated language change: switches the UI AND persists to the
   *  account so school SMS/email arrive in the same language. */
  setLocale: (locale: Locale) => void
  /** Adopt the language stored on the account (e.g. after login) WITHOUT
   *  writing back — the account is the source of truth for a signed-in user. */
  adoptLocale: (locale: Locale) => void
}

const I18nContext = createContext<I18nContextValue | null>(null)

function resolve(dict: Dictionary, key: string): string | undefined {
  const value = key.split(".").reduce<unknown>((acc, part) => {
    if (acc && typeof acc === "object" && part in acc) {
      return (acc as Record<string, unknown>)[part]
    }
    return undefined
  }, dict)

  return typeof value === "string" ? value : undefined
}

function interpolate(
  template: string,
  vars?: Record<string, string | number>
): string {
  if (!vars) return template
  return template.replace(/\{(\w+)\}/g, (match, name) =>
    name in vars ? String(vars[name]) : match
  )
}

export function I18nProvider({
  children,
  initialLocale = FALLBACK,
}: {
  children: React.ReactNode
  initialLocale?: Locale
}) {
  const [locale, setLocaleState] = useState<Locale>(initialLocale)

  useEffect(() => {
    // Hydrate the locale from localStorage after mount. We deliberately start
    // from the SSR fallback and update client-side to avoid a hydration mismatch.
    const stored = window.localStorage.getItem(STORAGE_KEY) as Locale | null
    if (stored && LOCALES.includes(stored)) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- client-only storage hydration
      setLocaleState(stored)
    }
  }, [])

  useEffect(() => {
    document.documentElement.lang = locale
    // Keep the date formatters speaking the active language (month names,
    // day periods) — lib/dates.ts reads a module store, not this context.
    setDateLocale(locale)
    // Keep module-level zod schemas (built before any locale is known) speaking
    // the active language for the shared phone validator. The *EthioOnly copy
    // applies while Safaricom (07…) numbers are rejected — see ALLOW_SAFARICOM.
    const phoneKey = ALLOW_SAFARICOM ? "validation.phone" : "validation.phoneEthioOnly"
    const officePhoneKey = ALLOW_SAFARICOM
      ? "validation.officePhone"
      : "validation.officePhoneEthioOnly"
    const identifierKey = ALLOW_SAFARICOM
      ? "validation.loginIdentifier"
      : "validation.loginIdentifierEthioOnly"
    setPhoneValidationMessage(
      resolve(dictionaries[locale].common, phoneKey) ??
        resolve(dictionaries[FALLBACK].common, phoneKey) ??
        ""
    )
    setOfficePhoneValidationMessage(
      resolve(dictionaries[locale].common, officePhoneKey) ??
        resolve(dictionaries[FALLBACK].common, officePhoneKey) ??
        ""
    )
    setIdentifierValidationMessage(
      resolve(dictionaries[locale].common, identifierKey) ??
        resolve(dictionaries[FALLBACK].common, identifierKey) ??
        ""
    )
  }, [locale])

  // Switch the active UI language + remember it on this device. Shared by the
  // user-initiated setLocale and the account-driven adoptLocale.
  const applyLocale = useCallback((next: Locale) => {
    window.localStorage.setItem(STORAGE_KEY, next)
    setLocaleState(next)
  }, [])

  const setLocale = useCallback(
    (next: Locale) => {
      applyLocale(next)

      // Persist to the account (fire-and-forget) so school SMS/email arrive in
      // the same language the user reads the app in.
      if (getToken()) {
        apiFetch("/me/preferences", {
          method: "PUT",
          body: { preferred_language: next },
        }).catch(() => {})
      }
    },
    [applyLocale],
  )

  const value = useMemo(
    () => ({ locale, setLocale, adoptLocale: applyLocale }),
    [locale, setLocale, applyLocale],
  )

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
}

export function useLocale(): I18nContextValue {
  const context = useContext(I18nContext)
  if (!context) throw new Error("useLocale must be used within I18nProvider")
  return context
}

export function useTranslation(domain: Domain) {
  const { locale } = useLocale()

  const t = useCallback(
    (key: string, vars?: Record<string, string | number>): string => {
      const localized = resolve(dictionaries[locale][domain], key)
      const fallback = resolve(dictionaries[FALLBACK][domain], key)
      return interpolate(localized ?? fallback ?? key, vars)
    },
    [locale, domain]
  )

  return { t, locale }
}
