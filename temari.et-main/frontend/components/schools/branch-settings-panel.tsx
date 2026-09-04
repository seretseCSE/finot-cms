"use client"

import {
  ArrowRight,
  BadgePercent,
  BellRing,
  FileBadge,
  MessagesSquare,
  CalendarClock,
  DoorOpen,
  ScanLine,
  ShieldCheck,
  TrendingUp,
  Wallet,
} from "lucide-react"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import { JobTitleChips } from "@/components/employees/job-title-chips"
import Link from "next/link"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { TimePicker } from "@/components/ui/time-picker"
import { ReportCardSkillsEditor } from "@/components/schools/report-card-skills-editor"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { ReportCardSkill } from "@/lib/types"
import { cn } from "@/lib/utils"
import { setCalendarPrefs } from "@/lib/dates"

interface AttendancePolicy {
  attendance_sms_enabled: boolean
  attendance_sms_late: boolean
  device_auto_absent: boolean
  device_absent_cutoff: string
  device_late_grace: number
}

/** Draft override state for the attendance/device block (null = inherit). */
type AlertOverrides = { [K in keyof AttendancePolicy]: AttendancePolicy[K] | null }

const ALERT_KEYS: (keyof AttendancePolicy)[] = [
  "attendance_sms_enabled",
  "attendance_sms_late",
  "device_auto_absent",
  "device_absent_cutoff",
  "device_late_grace",
]

/** Concession policy knobs (sibling / employee-child suggestions). 0 = off. */
interface ConcessionPolicy {
  sibling_discount_percent: number
  sibling_min_children: number
  staff_child_discount_percent: number
}

type ConcessionOverrides = { [K in keyof ConcessionPolicy]: number | null }

const CONCESSION_KEYS: (keyof ConcessionPolicy)[] = [
  "sibling_discount_percent",
  "sibling_min_children",
  "staff_child_discount_percent",
]

/** Recurring billing + automated reminder-ladder knobs. */
interface BillingPolicy {
  fee_proration: "full" | "daily"
  fee_reminders_enabled: boolean
  fee_reminder_days_before: number
  fee_reminder_overdue_every: number
  fee_reminder_overdue_max: number
}

type BillingOverrides = { [K in keyof BillingPolicy]: BillingPolicy[K] | null }

const BILLING_KEYS: (keyof BillingPolicy)[] = [
  "fee_proration",
  "fee_reminders_enabled",
  "fee_reminder_days_before",
  "fee_reminder_overdue_every",
  "fee_reminder_overdue_max",
]

type ChatApprovalMode = "off" | "first" | "all"
type ChatTemplateMode = "suggested" | "required"

/** Report-card print policy (skills list + layout toggles). */
interface ReportCardPolicy {
  report_card_skills: ReportCardSkill[]
  report_card_per_page: 1 | 2 | 4
  report_card_subject_ranks: boolean
  report_card_grading_criteria: boolean
}

type ReportCardOverrides = { [K in keyof ReportCardPolicy]: ReportCardPolicy[K] | null }

const REPORT_CARD_KEYS: (keyof ReportCardPolicy)[] = [
  "report_card_skills",
  "report_card_per_page",
  "report_card_subject_ranks",
  "report_card_grading_criteria",
]

interface BranchSettings {
  branch_id: number
  branch_name: string
  school_name: string | null
  effective: {
    registration_gate: "soft" | "hard"
    calendar_mode: "ethiopian" | "gregorian"
    clock_mode: "standard" | "ethiopian"
    promotion_threshold: number
    teacher_assessments_enabled: boolean
    employee_account_job_titles: string[]
    chat_teacher_parent_approval: ChatApprovalMode
    chat_students_enabled: boolean
    chat_template_mode: ChatTemplateMode
  } & AttendancePolicy &
    ConcessionPolicy &
    BillingPolicy &
    ReportCardPolicy
  overrides: {
    registration_gate: "soft" | "hard" | null
    calendar_mode: "ethiopian" | "gregorian" | null
    clock_mode: "standard" | "ethiopian" | null
    promotion_threshold: number | null
    teacher_assessments_enabled: boolean | null
    employee_account_job_titles: string[] | null
    chat_teacher_parent_approval: ChatApprovalMode | null
    chat_students_enabled: boolean | null
    chat_template_mode: ChatTemplateMode | null
  } & AlertOverrides &
    ConcessionOverrides &
    BillingOverrides &
    ReportCardOverrides
  school_defaults: {
    registration_gate: "soft" | "hard"
    calendar_mode: "ethiopian" | "gregorian"
    clock_mode: "standard" | "ethiopian"
    promotion_threshold: number
    teacher_assessments_enabled: boolean
    employee_account_job_titles: string[]
    chat_teacher_parent_approval: ChatApprovalMode
    chat_students_enabled: boolean
    chat_template_mode: ChatTemplateMode
  } & AttendancePolicy &
    ConcessionPolicy &
    BillingPolicy &
    ReportCardPolicy
}

const THRESHOLD_PRESETS = [50, 60, 75]

const TAB_KEYS = ["academic", "attendance", "fees", "chat", "reportCards"] as const
type SettingsTab = (typeof TAB_KEYS)[number]

/**
 * The branch settings body — policy tabs + sticky save bar for ONE branch.
 * Everything starts INHERITED from the school default; flipping a setting to
 * "custom" pins an override for this branch only.
 *
 * Used in two places: the standalone /branch-settings page (directors) with
 * URL-synced tabs, and embedded under the Settings tab of /branches/[id]
 * (school managers) where the OUTER page owns `?tab=` — pass
 * `syncTabs={false}` there so the sub-tabs stay local state.
 */
export function BranchSettingsPanel({
  branchId,
  syncTabs = true,
}: {
  branchId: number
  syncTabs?: boolean
}) {
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()

  const [urlTab, setUrlTab] = useProfileTabs(TAB_KEYS, "academic")
  const [localTab, setLocalTab] = useState<SettingsTab>("academic")
  const tab = syncTabs ? urlTab : localTab
  const setTab = syncTabs ? setUrlTab : setLocalTab

  const [settings, setSettings] = useState<BranchSettings | null>(null)
  // Draft override state (null = inherit).
  const [gateOverride, setGateOverride] = useState<"soft" | "hard" | null>(null)
  const [calendarOverride, setCalendarOverride] = useState<"ethiopian" | "gregorian" | null>(null)
  const [clockOverride, setClockOverride] = useState<"standard" | "ethiopian" | null>(null)
  const [thresholdOverride, setThresholdOverride] = useState<number | null>(null)
  const [teacherAssessmentsOverride, setTeacherAssessmentsOverride] = useState<boolean | null>(null)
  const [accountTitlesOverride, setAccountTitlesOverride] = useState<string[] | null>(null)
  const [alerts, setAlerts] = useState<AlertOverrides>({
    attendance_sms_enabled: null,
    attendance_sms_late: null,
    device_auto_absent: null,
    device_absent_cutoff: null,
    device_late_grace: null,
  })
  const [concessions, setConcessions] = useState<ConcessionOverrides>({
    sibling_discount_percent: null,
    sibling_min_children: null,
    staff_child_discount_percent: null,
  })
  const [chatApprovalOverride, setChatApprovalOverride] = useState<ChatApprovalMode | null>(null)
  const [chatTemplateOverride, setChatTemplateOverride] = useState<ChatTemplateMode | null>(null)
  const [chatStudentsOverride, setChatStudentsOverride] = useState<boolean | null>(null)
  const [billing, setBilling] = useState<BillingOverrides>({
    fee_proration: null,
    fee_reminders_enabled: null,
    fee_reminder_days_before: null,
    fee_reminder_overdue_every: null,
    fee_reminder_overdue_max: null,
  })
  const [reportCard, setReportCard] = useState<ReportCardOverrides>({
    report_card_skills: null,
    report_card_per_page: null,
    report_card_subject_ranks: null,
    report_card_grading_criteria: null,
  })
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    setSettings(null)
    let cancelled = false
    apiFetch<{ data: BranchSettings }>(`/branches/${branchId}/settings`)
      .then((res) => {
        if (cancelled) return
        setSettings(res.data)
        setGateOverride(res.data.overrides.registration_gate)
        setCalendarOverride(res.data.overrides.calendar_mode)
        setClockOverride(res.data.overrides.clock_mode)
        setThresholdOverride(res.data.overrides.promotion_threshold)
        setTeacherAssessmentsOverride(res.data.overrides.teacher_assessments_enabled)
        setAccountTitlesOverride(res.data.overrides.employee_account_job_titles)
        setChatApprovalOverride(res.data.overrides.chat_teacher_parent_approval)
        setChatTemplateOverride(res.data.overrides.chat_template_mode)
        setChatStudentsOverride(res.data.overrides.chat_students_enabled)
        setAlerts(Object.fromEntries(
          ALERT_KEYS.map((k) => [k, res.data.overrides[k] ?? null])
        ) as AlertOverrides)
        setConcessions(Object.fromEntries(
          CONCESSION_KEYS.map((k) => [k, res.data.overrides[k] ?? null])
        ) as ConcessionOverrides)
        setBilling(Object.fromEntries(
          BILLING_KEYS.map((k) => [k, res.data.overrides[k] ?? null])
        ) as BillingOverrides)
        setReportCard(Object.fromEntries(
          REPORT_CARD_KEYS.map((k) => [k, res.data.overrides[k] ?? null])
        ) as ReportCardOverrides)
      })
      .catch((error) => {
        if (!cancelled) {
          toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        }
      })
    return () => {
      cancelled = true
    }
  }, [branchId, tc])

  // eslint-disable-next-line react-hooks/set-state-in-effect -- load resets to the loading state
  useEffect(() => load(), [load])

  const dirty =
    settings !== null &&
    (gateOverride !== settings.overrides.registration_gate ||
      (calendarOverride ?? null) !== (settings.overrides.calendar_mode ?? null) ||
      (clockOverride ?? null) !== (settings.overrides.clock_mode ?? null) ||
      (thresholdOverride ?? null) !== (settings.overrides.promotion_threshold ?? null) ||
      (teacherAssessmentsOverride ?? null) !== (settings.overrides.teacher_assessments_enabled ?? null) ||
      JSON.stringify(accountTitlesOverride?.slice().sort() ?? null) !==
        JSON.stringify(settings.overrides.employee_account_job_titles?.slice().sort() ?? null) ||
      (chatApprovalOverride ?? null) !== (settings.overrides.chat_teacher_parent_approval ?? null) ||
      (chatTemplateOverride ?? null) !== (settings.overrides.chat_template_mode ?? null) ||
      (chatStudentsOverride ?? null) !== (settings.overrides.chat_students_enabled ?? null) ||
      ALERT_KEYS.some((k) => (alerts[k] ?? null) !== (settings.overrides[k] ?? null)) ||
      CONCESSION_KEYS.some((k) => (concessions[k] ?? null) !== (settings.overrides[k] ?? null)) ||
      BILLING_KEYS.some((k) => (billing[k] ?? null) !== (settings.overrides[k] ?? null)) ||
      // The skills list compares structurally; the scalar knobs by value.
      JSON.stringify(reportCard.report_card_skills) !==
        JSON.stringify(settings.overrides.report_card_skills ?? null) ||
      (reportCard.report_card_per_page ?? null) !== (settings.overrides.report_card_per_page ?? null) ||
      (reportCard.report_card_subject_ranks ?? null) !==
        (settings.overrides.report_card_subject_ranks ?? null) ||
      (reportCard.report_card_grading_criteria ?? null) !==
        (settings.overrides.report_card_grading_criteria ?? null))

  async function save() {
    setSaving(true)
    try {
      const res = await apiFetch<{ data: BranchSettings }>(`/branches/${branchId}/settings`, {
        method: "PATCH",
        body: {
          registration_gate: gateOverride,
          calendar_mode: calendarOverride,
          clock_mode: clockOverride,
          promotion_threshold: thresholdOverride,
          teacher_assessments_enabled: teacherAssessmentsOverride,
          employee_account_job_titles: accountTitlesOverride,
          chat_teacher_parent_approval: chatApprovalOverride,
          chat_template_mode: chatTemplateOverride,
          chat_students_enabled: chatStudentsOverride,
          ...alerts,
          ...concessions,
          ...billing,
          ...reportCard,
          // Drop label-less rows; missing translations fall back to English
          // (the backend requires all three).
          report_card_skills:
            reportCard.report_card_skills === null
              ? null
              : reportCard.report_card_skills
                  .filter((s) => s.label.en.trim() !== "")
                  .map((s) => ({
                    ...s,
                    label: {
                      en: s.label.en.trim(),
                      am: s.label.am.trim() || s.label.en.trim(),
                      om: s.label.om.trim() || s.label.en.trim(),
                    },
                  })),
        },
      })
      setSettings(res.data)
      setGateOverride(res.data.overrides.registration_gate)
      setCalendarOverride(res.data.overrides.calendar_mode)
      setClockOverride(res.data.overrides.clock_mode)
      setThresholdOverride(res.data.overrides.promotion_threshold)
      setTeacherAssessmentsOverride(res.data.overrides.teacher_assessments_enabled)
      setAccountTitlesOverride(res.data.overrides.employee_account_job_titles)
      setChatApprovalOverride(res.data.overrides.chat_teacher_parent_approval)
      setChatStudentsOverride(res.data.overrides.chat_students_enabled)
      setAlerts(Object.fromEntries(
        ALERT_KEYS.map((k) => [k, res.data.overrides[k] ?? null])
      ) as AlertOverrides)
      setConcessions(Object.fromEntries(
        CONCESSION_KEYS.map((k) => [k, res.data.overrides[k] ?? null])
      ) as ConcessionOverrides)
      setBilling(Object.fromEntries(
        BILLING_KEYS.map((k) => [k, res.data.overrides[k] ?? null])
      ) as BillingOverrides)
      setReportCard(Object.fromEntries(
        REPORT_CARD_KEYS.map((k) => [k, res.data.overrides[k] ?? null])
      ) as ReportCardOverrides)
      if (active.branchId === branchId) {
        setCalendarPrefs({
          calendar: res.data.effective.calendar_mode,
          clock: res.data.effective.clock_mode,
        })
      }
      toast.success(t("branchSettings.saved"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  const effectiveGate = gateOverride ?? settings?.school_defaults.registration_gate ?? "soft"
  const effectiveCalendar = calendarOverride ?? settings?.school_defaults.calendar_mode ?? "ethiopian"
  const effectiveClock = clockOverride ?? settings?.school_defaults.clock_mode ?? "standard"
  const effectiveThreshold =
    thresholdOverride ?? settings?.school_defaults.promotion_threshold ?? 50

  if (settings === null) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-56 rounded-2xl" />
        <Skeleton className="h-40 rounded-2xl" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {/* Each policy family on its own tab — the single settings
          payload is already here, so switching is instant. */}
      <ProfileTabBar
        tabs={[
          { key: "academic", label: t("branchSettings.tabs.academic"), icon: ShieldCheck },
          { key: "attendance", label: t("branchSettings.tabs.attendance"), icon: BellRing },
          { key: "fees", label: t("branchSettings.tabs.fees"), icon: BadgePercent },
          { key: "chat", label: t("policy.tabs.chat"), icon: MessagesSquare },
          { key: "reportCards", label: t("policy.tabs.reportCards"), icon: FileBadge },
        ]}
        value={tab}
        onChange={setTab}
      />

      {tab === "academic" && (
      <>
      {/* ── Academic policy ── */}
      <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
        <div className="border-b bg-muted/30 px-5 py-4">
          <h2 className="flex items-center gap-2 text-sm font-semibold">
            <ShieldCheck className="size-4 text-primary" />
            {t("policy.title")}
            <Badge variant="outline" className="rounded-full text-[11px] text-muted-foreground">
              {settings.branch_name}
            </Badge>
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {t("branchSettings.policyHint", { school: settings.school_name ?? "" })}
          </p>
        </div>

        <div className="divide-y">
          {/* Registration gate */}
          <SettingRow
            title={t("policy.registrationGate")}
            inherited={gateOverride === null}
            inheritedLabel={t("branchSettings.inherited", {
              value:
                settings.school_defaults.registration_gate === "soft"
                  ? t("branchSettings.gateSoftShort")
                  : t("branchSettings.gateHardShort"),
            })}
            onInheritChange={(inherit) =>
              setGateOverride(inherit ? null : settings.effective.registration_gate)
            }
          >
            <div className="grid gap-2 sm:grid-cols-2">
              {(["soft", "hard"] as const).map((gate) => {
                const selected = effectiveGate === gate
                const disabled = gateOverride === null
                return (
                  <button
                    key={gate}
                    type="button"
                    disabled={disabled}
                    onClick={() => setGateOverride(gate)}
                    className={cn(
                      "rounded-xl border p-3 text-left transition-colors",
                      selected && !disabled
                        ? "border-primary bg-primary/5"
                        : "hover:bg-muted/50",
                      disabled && "opacity-50",
                      disabled && selected && "border-primary/40 bg-primary/5",
                    )}
                    aria-pressed={selected}
                  >
                    <p className="text-sm font-medium">
                      {gate === "soft"
                        ? t("branchSettings.gateSoftShort")
                        : t("branchSettings.gateHardShort")}
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                      {gate === "soft" ? t("policy.gateSoft") : t("policy.gateHard")}
                    </p>
                  </button>
                )
              })}
            </div>
          </SettingRow>

          {/* Calendar — how every date displays for this branch */}
          <SettingRow
            title={t("policy.calendarMode")}
            inherited={calendarOverride === null}
            inheritedLabel={t("branchSettings.inherited", {
              value:
                settings.school_defaults.calendar_mode === "ethiopian"
                  ? t("policy.calendarEthiopian")
                  : t("policy.calendarGregorian"),
            })}
            onInheritChange={(inherit) =>
              setCalendarOverride(inherit ? null : settings.effective.calendar_mode)
            }
          >
            <div className="grid gap-2 sm:grid-cols-2">
              {(["ethiopian", "gregorian"] as const).map((mode) => {
                const selected = effectiveCalendar === mode
                const disabled = calendarOverride === null
                return (
                  <button
                    key={mode}
                    type="button"
                    disabled={disabled}
                    onClick={() => setCalendarOverride(mode)}
                    className={cn(
                      "rounded-xl border p-3 text-left transition-colors",
                      selected && !disabled
                        ? "border-primary bg-primary/5"
                        : "hover:bg-muted/50",
                      disabled && "opacity-50",
                      disabled && selected && "border-primary/40 bg-primary/5",
                    )}
                    aria-pressed={selected}
                  >
                    <p className="text-sm font-medium">
                      {mode === "ethiopian"
                        ? t("policy.calendarEthiopian")
                        : t("policy.calendarGregorian")}
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                      {mode === "ethiopian"
                        ? t("policy.calendarEthiopianExample")
                        : t("policy.calendarGregorianExample")}
                    </p>
                  </button>
                )
              })}
            </div>
          </SettingRow>

          {/* Clock — how times of day are written */}
          <SettingRow
            title={t("policy.clockMode")}
            inherited={clockOverride === null}
            inheritedLabel={t("branchSettings.inherited", {
              value:
                settings.school_defaults.clock_mode === "ethiopian"
                  ? t("policy.clockEthiopian")
                  : t("policy.clockStandard"),
            })}
            onInheritChange={(inherit) =>
              setClockOverride(inherit ? null : settings.effective.clock_mode)
            }
          >
            <div className="grid gap-2 sm:grid-cols-2">
              {(["standard", "ethiopian"] as const).map((mode) => {
                const selected = effectiveClock === mode
                const disabled = clockOverride === null
                return (
                  <button
                    key={mode}
                    type="button"
                    disabled={disabled}
                    onClick={() => setClockOverride(mode)}
                    className={cn(
                      "rounded-xl border p-3 text-left transition-colors",
                      selected && !disabled
                        ? "border-primary bg-primary/5"
                        : "hover:bg-muted/50",
                      disabled && "opacity-50",
                      disabled && selected && "border-primary/40 bg-primary/5",
                    )}
                    aria-pressed={selected}
                  >
                    <p className="text-sm font-medium">
                      {mode === "standard"
                        ? t("policy.clockStandard")
                        : t("policy.clockEthiopian")}
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                      {mode === "standard"
                        ? t("policy.clockStandardExample")
                        : t("policy.clockEthiopianExample")}
                    </p>
                  </button>
                )
              })}
            </div>
          </SettingRow>

          {/* Promotion pass mark */}
          <SettingRow
            title={t("policy.threshold")}
            inherited={thresholdOverride === null}
            inheritedLabel={t("branchSettings.inherited", {
              value: `${settings.school_defaults.promotion_threshold}%`,
            })}
            onInheritChange={(inherit) =>
              setThresholdOverride(inherit ? null : settings.effective.promotion_threshold)
            }
          >
            <div className="flex flex-wrap items-center gap-2">
              <div className="relative">
                <Input
                  type="number"
                  min={0}
                  max={100}
                  disabled={thresholdOverride === null}
                  value={effectiveThreshold}
                  onChange={(e) => setThresholdOverride(Number(e.target.value))}
                  className="h-11 w-24 pr-7 text-base font-semibold tabular-nums"
                />
                <span className="absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground">
                  %
                </span>
              </div>
              {THRESHOLD_PRESETS.map((preset) => (
                <button
                  key={preset}
                  type="button"
                  disabled={thresholdOverride === null}
                  onClick={() => setThresholdOverride(preset)}
                  className={cn(
                    "pressable min-h-9 rounded-full border px-3 text-xs font-medium tabular-nums transition-colors",
                    effectiveThreshold === preset && thresholdOverride !== null
                      ? "border-primary/40 bg-primary/10 text-primary"
                      : "text-muted-foreground hover:bg-muted",
                    thresholdOverride === null && "opacity-50",
                  )}
                >
                  {preset}%
                </button>
              ))}
            </div>
            <p className="mt-2 text-xs text-muted-foreground">{t("policy.thresholdHelp")}</p>
          </SettingRow>

          {/* Teacher-defined assessments */}
          <BoolSettingRow
            title={t("policy.teacherAssessments")}
            help={t("policy.teacherAssessmentsHelp")}
            override={teacherAssessmentsOverride}
            schoolDefault={settings.school_defaults.teacher_assessments_enabled}
            onChange={setTeacherAssessmentsOverride}
          />

          {/* Staff portal-account policy (which job titles get a login) */}
          <SettingRow
            title={t("staffAccounts.title")}
            inherited={accountTitlesOverride === null}
            inheritedLabel={t("branchSettings.inherited", {
              value: String(settings.school_defaults.employee_account_job_titles.length),
            })}
            onInheritChange={(inherit) =>
              setAccountTitlesOverride(
                inherit ? null : settings.effective.employee_account_job_titles,
              )
            }
          >
            <p className="mb-2 text-xs text-muted-foreground">{t("staffAccounts.help")}</p>
            <JobTitleChips
              value={
                accountTitlesOverride ?? settings.school_defaults.employee_account_job_titles
              }
              onChange={setAccountTitlesOverride}
              disabled={accountTitlesOverride === null}
            />
            <p className="mt-2 text-xs text-muted-foreground">
              {t("staffAccounts.lockedHint")}
            </p>
          </SettingRow>
        </div>
      </section>

      {/* Everything else academic-shaped, one tap away. */}
      <section className="grid gap-3 sm:grid-cols-2">
        <HubLink
          href="/timetable"
          icon={CalendarClock}
          title={t("branchSettings.links.timetable")}
          description={t("branchSettings.links.timetableDesc")}
        />
        <HubLink
          href="/timetable"
          icon={DoorOpen}
          title={t("branchSettings.links.rooms")}
          description={t("branchSettings.links.roomsDesc")}
        />
      </section>
      </>
      )}

      {tab === "attendance" && (
      <>
      {/* ── Guardian alerts (SMS/email) ── */}
      <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
        <div className="border-b bg-muted/30 px-5 py-4">
          <h2 className="flex items-center gap-2 text-sm font-semibold">
            <BellRing className="size-4 text-primary" />
            {t("alerts.title")}
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">{t("alerts.hint")}</p>
        </div>
        <div className="divide-y">
          <BoolSettingRow
            title={t("alerts.enabled")}
            help={t("alerts.enabledHelp")}
            override={alerts.attendance_sms_enabled}
            schoolDefault={settings.school_defaults.attendance_sms_enabled}
            onChange={(v) => setAlerts({ ...alerts, attendance_sms_enabled: v })}
          />
          <BoolSettingRow
            title={t("alerts.late")}
            help={t("alerts.lateHelp")}
            override={alerts.attendance_sms_late}
            schoolDefault={settings.school_defaults.attendance_sms_late}
            onChange={(v) => setAlerts({ ...alerts, attendance_sms_late: v })}
          />
        </div>
      </section>

      {/* ── Device attendance (RFID gates) ── */}
      <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
        <div className="border-b bg-muted/30 px-5 py-4">
          <h2 className="flex items-center gap-2 text-sm font-semibold">
            <ScanLine className="size-4 text-primary" />
            {t("deviceMode.title")}
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">{t("deviceMode.hint")}</p>
        </div>
        <div className="divide-y">
          <BoolSettingRow
            title={t("deviceMode.autoAbsent")}
            help={t("deviceMode.autoAbsentHelp")}
            override={alerts.device_auto_absent}
            schoolDefault={settings.school_defaults.device_auto_absent}
            onChange={(v) => setAlerts({ ...alerts, device_auto_absent: v })}
          />
          <SettingRow
            title={t("deviceMode.cutoff")}
            inherited={alerts.device_absent_cutoff === null}
            inheritedLabel={t("branchSettings.inherited", {
              value: settings.school_defaults.device_absent_cutoff,
            })}
            onInheritChange={(inherit) =>
              setAlerts({
                ...alerts,
                device_absent_cutoff: inherit
                  ? null
                  : settings.effective.device_absent_cutoff,
              })
            }
          >
            <TimePicker
              value={
                alerts.device_absent_cutoff ??
                settings.school_defaults.device_absent_cutoff
              }
              onChange={(value) =>
                setAlerts({ ...alerts, device_absent_cutoff: value || null })
              }
              disabled={alerts.device_absent_cutoff === null}
              clearable={false}
              className="sm:w-44"
            />
            <p className="mt-2 text-xs text-muted-foreground">
              {t("deviceMode.cutoffHelp")}
            </p>
          </SettingRow>
          <SettingRow
            title={t("deviceMode.grace")}
            inherited={alerts.device_late_grace === null}
            inheritedLabel={t("branchSettings.inherited", {
              value: t("deviceMode.graceValue", {
                count: settings.school_defaults.device_late_grace,
              }),
            })}
            onInheritChange={(inherit) =>
              setAlerts({
                ...alerts,
                device_late_grace: inherit ? null : settings.effective.device_late_grace,
              })
            }
          >
            <div className="flex flex-wrap items-center gap-2">
              <Input
                type="number"
                min={0}
                max={120}
                disabled={alerts.device_late_grace === null}
                value={alerts.device_late_grace ?? settings.school_defaults.device_late_grace}
                onChange={(e) =>
                  setAlerts({ ...alerts, device_late_grace: Number(e.target.value) })
                }
                className="no-spinner h-11 w-24 text-base font-semibold tabular-nums"
              />
              {[10, 15, 30].map((preset) => (
                <button
                  key={preset}
                  type="button"
                  disabled={alerts.device_late_grace === null}
                  onClick={() => setAlerts({ ...alerts, device_late_grace: preset })}
                  className={cn(
                    "pressable min-h-9 rounded-full border px-3 text-xs font-medium tabular-nums transition-colors",
                    (alerts.device_late_grace ??
                      settings.school_defaults.device_late_grace) === preset &&
                      alerts.device_late_grace !== null
                      ? "border-primary/40 bg-primary/10 text-primary"
                      : "text-muted-foreground hover:bg-muted",
                    alerts.device_late_grace === null && "opacity-50",
                  )}
                >
                  {t("deviceMode.graceValue", { count: preset })}
                </button>
              ))}
            </div>
            <p className="mt-2 text-xs text-muted-foreground">{t("deviceMode.graceHelp")}</p>
          </SettingRow>
        </div>
      </section>
      </>
      )}

      {tab === "fees" && (
      <>
      {/* ── Discount policy (concession suggestions) ── */}
      <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
        <div className="border-b bg-muted/30 px-5 py-4">
          <h2 className="flex items-center gap-2 text-sm font-semibold">
            <BadgePercent className="size-4 text-primary" />
            {t("concessionPolicy.title")}
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {t("concessionPolicy.hint")}
          </p>
        </div>
        <div className="divide-y">
          <NumberSettingRow
            title={t("concessionPolicy.siblingPercent")}
            help={t("concessionPolicy.siblingPercentHelp")}
            override={concessions.sibling_discount_percent}
            schoolDefault={settings.school_defaults.sibling_discount_percent}
            min={0}
            max={100}
            suffix="%"
            offValue={0}
            onChange={(v) =>
              setConcessions({ ...concessions, sibling_discount_percent: v })
            }
          />
          <NumberSettingRow
            title={t("concessionPolicy.siblingMin")}
            help={t("concessionPolicy.siblingMinHelp")}
            override={concessions.sibling_min_children}
            schoolDefault={settings.school_defaults.sibling_min_children}
            min={2}
            max={10}
            onChange={(v) =>
              setConcessions({ ...concessions, sibling_min_children: v })
            }
          />
          <NumberSettingRow
            title={t("concessionPolicy.staffPercent")}
            help={t("concessionPolicy.staffPercentHelp")}
            override={concessions.staff_child_discount_percent}
            schoolDefault={settings.school_defaults.staff_child_discount_percent}
            min={0}
            max={100}
            suffix="%"
            offValue={0}
            onChange={(v) =>
              setConcessions({ ...concessions, staff_child_discount_percent: v })
            }
          />
        </div>
      </section>

      {/* ── Recurring billing + the automated reminder ladder ── */}
      <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
        <div className="border-b bg-muted/30 px-5 py-4">
          <h2 className="flex items-center gap-2 text-sm font-semibold">
            <BellRing className="size-4 text-primary" />
            {t("billingPolicy.title")}
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">{t("billingPolicy.hint")}</p>
        </div>
        <div className="divide-y">
          <SettingRow
            title={t("billingPolicy.proration")}
            inherited={billing.fee_proration === null}
            inheritedLabel={t("branchSettings.inherited", {
              value: t(
                settings.school_defaults.fee_proration === "daily"
                  ? "billingPolicy.prorationDaily"
                  : "billingPolicy.prorationFull",
              ),
            })}
            onInheritChange={(inherit) =>
              setBilling({
                ...billing,
                fee_proration: inherit ? null : settings.effective.fee_proration,
              })
            }
          >
            <div className="grid gap-2 sm:grid-cols-2">
              {(["full", "daily"] as const).map((mode) => {
                const selected =
                  (billing.fee_proration ?? settings.school_defaults.fee_proration) === mode
                const disabled = billing.fee_proration === null
                return (
                  <button
                    key={mode}
                    type="button"
                    disabled={disabled}
                    onClick={() => setBilling({ ...billing, fee_proration: mode })}
                    className={cn(
                      "rounded-xl border p-3 text-left transition-colors",
                      selected && !disabled
                        ? "border-primary bg-primary/5"
                        : "hover:bg-muted/50",
                      disabled && "opacity-50",
                      disabled && selected && "border-primary/40 bg-primary/5",
                    )}
                    aria-pressed={selected}
                  >
                    <p className="text-sm font-medium">
                      {t(
                        mode === "daily"
                          ? "billingPolicy.prorationDaily"
                          : "billingPolicy.prorationFull",
                      )}
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                      {t(
                        mode === "daily"
                          ? "billingPolicy.prorationDailyHelp"
                          : "billingPolicy.prorationFullHelp",
                      )}
                    </p>
                  </button>
                )
              })}
            </div>
          </SettingRow>
          <BoolSettingRow
            title={t("billingPolicy.remindersEnabled")}
            help={t("billingPolicy.remindersEnabledHelp")}
            override={billing.fee_reminders_enabled}
            schoolDefault={settings.school_defaults.fee_reminders_enabled}
            onChange={(v) => setBilling({ ...billing, fee_reminders_enabled: v })}
          />
          <NumberSettingRow
            title={t("billingPolicy.daysBefore")}
            help={t("billingPolicy.daysBeforeHelp")}
            override={billing.fee_reminder_days_before}
            schoolDefault={settings.school_defaults.fee_reminder_days_before}
            min={0}
            max={30}
            offValue={0}
            onChange={(v) => setBilling({ ...billing, fee_reminder_days_before: v })}
          />
          <NumberSettingRow
            title={t("billingPolicy.overdueEvery")}
            help={t("billingPolicy.overdueEveryHelp")}
            override={billing.fee_reminder_overdue_every}
            schoolDefault={settings.school_defaults.fee_reminder_overdue_every}
            min={1}
            max={60}
            onChange={(v) => setBilling({ ...billing, fee_reminder_overdue_every: v })}
          />
          <NumberSettingRow
            title={t("billingPolicy.overdueMax")}
            help={t("billingPolicy.overdueMaxHelp")}
            override={billing.fee_reminder_overdue_max}
            schoolDefault={settings.school_defaults.fee_reminder_overdue_max}
            min={0}
            max={10}
            offValue={0}
            onChange={(v) => setBilling({ ...billing, fee_reminder_overdue_max: v })}
          />
        </div>
      </section>

      {/* Fee definitions live in their own module, one tap away. */}
      <section className="grid gap-3 sm:grid-cols-2">
        <HubLink
          href="/fees"
          icon={Wallet}
          title={t("branchSettings.links.fees")}
          description={t("branchSettings.links.feesDesc")}
        />
      </section>
      </>
      )}

      {tab === "chat" && (
      <>
      {/* ── Chat policy (ADR-019) ── */}
      <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
        <div className="border-b bg-muted/30 px-5 py-4">
          <h2 className="flex items-center gap-2 text-sm font-semibold">
            <MessagesSquare className="size-4 text-primary" />
            {t("policy.tabs.chat")}
            <Badge variant="outline" className="rounded-full text-[11px] text-muted-foreground">
              {settings.branch_name}
            </Badge>
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {t("branchSettings.policyHint", { school: settings.school_name ?? "" })}
          </p>
        </div>

        <div className="divide-y">
          <SettingRow
            title={t("chatPolicy.approvalMode")}
            inherited={chatApprovalOverride === null}
            inheritedLabel={t("branchSettings.inherited", {
              value: t(
                `chatPolicy.approval${settings.school_defaults.chat_teacher_parent_approval === "off" ? "Off" : settings.school_defaults.chat_teacher_parent_approval === "first" ? "First" : "All"}`,
              ),
            })}
            onInheritChange={(inherit) =>
              setChatApprovalOverride(
                inherit ? null : settings.effective.chat_teacher_parent_approval,
              )
            }
          >
            <div className="grid gap-2 sm:grid-cols-3">
              {(["all", "first", "off"] as const).map((mode) => {
                const effective =
                  chatApprovalOverride ?? settings.school_defaults.chat_teacher_parent_approval
                const selected = effective === mode
                const disabled = chatApprovalOverride === null
                return (
                  <button
                    key={mode}
                    type="button"
                    disabled={disabled}
                    onClick={() => setChatApprovalOverride(mode)}
                    className={cn(
                      "rounded-xl border p-3 text-left transition-colors",
                      selected && !disabled
                        ? "border-primary bg-primary/5"
                        : "hover:bg-muted/50",
                      disabled && "opacity-50",
                      disabled && selected && "border-primary/40 bg-primary/5",
                    )}
                    aria-pressed={selected}
                  >
                    <p className="text-sm font-medium">
                      {t(`chatPolicy.approval${mode === "off" ? "Off" : mode === "first" ? "First" : "All"}`)}
                    </p>
                  </button>
                )
              })}
            </div>
            <p className="mt-2 text-xs text-muted-foreground">{t("chatPolicy.approvalHelp")}</p>
          </SettingRow>

          <SettingRow
            title={t("chatPolicy.templateMode")}
            inherited={chatTemplateOverride === null}
            inheritedLabel={t("branchSettings.inherited", {
              value: t(
                settings.school_defaults.chat_template_mode === "required"
                  ? "chatPolicy.templateRequired"
                  : "chatPolicy.templateSuggested",
              ),
            })}
            onInheritChange={(inherit) =>
              setChatTemplateOverride(inherit ? null : settings.effective.chat_template_mode)
            }
          >
            <div className="grid gap-2 sm:grid-cols-2">
              {(["suggested", "required"] as const).map((mode) => {
                const effective =
                  chatTemplateOverride ?? settings.school_defaults.chat_template_mode
                const selected = effective === mode
                const disabled = chatTemplateOverride === null
                return (
                  <button
                    key={mode}
                    type="button"
                    disabled={disabled}
                    onClick={() => setChatTemplateOverride(mode)}
                    className={cn(
                      "rounded-xl border p-3 text-left transition-colors",
                      selected && !disabled
                        ? "border-primary bg-primary/5"
                        : "hover:bg-muted/50",
                      disabled && "opacity-50",
                      disabled && selected && "border-primary/40 bg-primary/5",
                    )}
                    aria-pressed={selected}
                  >
                    <p className="text-sm font-medium">
                      {t(mode === "required" ? "chatPolicy.templateRequired" : "chatPolicy.templateSuggested")}
                    </p>
                  </button>
                )
              })}
            </div>
            <p className="mt-2 text-xs text-muted-foreground">{t("chatPolicy.templateHelp")}</p>
          </SettingRow>

          <BoolSettingRow
            title={t("chatPolicy.students")}
            help={t("chatPolicy.studentsHelp")}
            override={chatStudentsOverride}
            schoolDefault={settings.school_defaults.chat_students_enabled}
            onChange={setChatStudentsOverride}
          />
        </div>
      </section>
      </>
      )}

      {tab === "reportCards" && (
      <>
      {/* ── Report-card print policy ── */}
      <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
        <div className="border-b bg-muted/30 px-5 py-4">
          <h2 className="flex items-center gap-2 text-sm font-semibold">
            <FileBadge className="size-4 text-primary" />
            {t("policy.tabs.reportCards")}
            <Badge variant="outline" className="rounded-full text-[11px] text-muted-foreground">
              {settings.branch_name}
            </Badge>
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {t("branchSettings.policyHint", { school: settings.school_name ?? "" })}
          </p>
        </div>

        <div className="divide-y">
          <SettingRow
            title={t("reportCardPolicy.perPage")}
            inherited={reportCard.report_card_per_page === null}
            inheritedLabel={t("branchSettings.inherited", {
              value: t(
                settings.school_defaults.report_card_per_page === 4
                  ? "reportCardPolicy.perPage4"
                  : settings.school_defaults.report_card_per_page === 2
                    ? "reportCardPolicy.perPage2"
                    : "reportCardPolicy.perPage1",
              ),
            })}
            onInheritChange={(inherit) =>
              setReportCard({
                ...reportCard,
                report_card_per_page: inherit ? null : settings.effective.report_card_per_page,
              })
            }
          >
            <div className="grid gap-2 sm:grid-cols-3">
              {([1, 2, 4] as const).map((option) => {
                const effective =
                  reportCard.report_card_per_page ?? settings.school_defaults.report_card_per_page
                const selected = effective === option
                const disabled = reportCard.report_card_per_page === null
                return (
                  <button
                    key={option}
                    type="button"
                    disabled={disabled}
                    onClick={() =>
                      setReportCard({ ...reportCard, report_card_per_page: option })
                    }
                    className={cn(
                      "rounded-xl border p-3 text-left transition-colors",
                      selected && !disabled
                        ? "border-primary bg-primary/5"
                        : "hover:bg-muted/50",
                      disabled && "opacity-50",
                      disabled && selected && "border-primary/40 bg-primary/5",
                    )}
                    aria-pressed={selected}
                  >
                    <p className="text-sm font-medium">
                      {t(
                        option === 4
                          ? "reportCardPolicy.perPage4"
                          : option === 2
                            ? "reportCardPolicy.perPage2"
                            : "reportCardPolicy.perPage1",
                      )}
                    </p>
                  </button>
                )
              })}
            </div>
            <p className="mt-2 text-xs text-muted-foreground">
              {t("reportCardPolicy.perPageHelp")}
            </p>
          </SettingRow>

          <BoolSettingRow
            title={t("reportCardPolicy.subjectRanks")}
            help={t("reportCardPolicy.subjectRanksHelp")}
            override={reportCard.report_card_subject_ranks}
            schoolDefault={settings.school_defaults.report_card_subject_ranks}
            onChange={(v) => setReportCard({ ...reportCard, report_card_subject_ranks: v })}
          />

          <BoolSettingRow
            title={t("reportCardPolicy.gradingCriteria")}
            help={t("reportCardPolicy.gradingCriteriaHelp")}
            override={reportCard.report_card_grading_criteria}
            schoolDefault={settings.school_defaults.report_card_grading_criteria}
            onChange={(v) => setReportCard({ ...reportCard, report_card_grading_criteria: v })}
          />

          <SettingRow
            title={t("reportCardPolicy.skillsTitle")}
            inherited={reportCard.report_card_skills === null}
            inheritedLabel={t("branchSettings.inherited", {
              value: t("reportCardPolicy.skillsInherited", {
                count: settings.school_defaults.report_card_skills.length,
              }),
            })}
            onInheritChange={(inherit) =>
              setReportCard({
                ...reportCard,
                report_card_skills: inherit ? null : settings.effective.report_card_skills,
              })
            }
          >
            <p className="mb-3 text-xs text-muted-foreground">
              {t("reportCardPolicy.skillsHint")}
            </p>
            {reportCard.report_card_skills === null ? (
              // Inherited: show the school's list read-only for context.
              settings.school_defaults.report_card_skills.length > 0 ? (
                <div className="flex flex-wrap gap-1.5">
                  {settings.school_defaults.report_card_skills.map((skill) => (
                    <Badge key={skill.key} variant="outline" className="rounded-full text-xs font-normal">
                      {skill.label.en}
                    </Badge>
                  ))}
                </div>
              ) : (
                <p className="rounded-xl border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
                  {t("reportCardPolicy.noSchoolSkills")}
                </p>
              )
            ) : (
              <ReportCardSkillsEditor
                value={reportCard.report_card_skills}
                onChange={(skills) =>
                  setReportCard({ ...reportCard, report_card_skills: skills })
                }
              />
            )}
          </SettingRow>
        </div>
      </section>
      </>
      )}

      {/* Sticky save bar */}
      {dirty && (
        <div className="fixed inset-x-0 bottom-20 z-30 flex justify-center px-4 md:bottom-6">
          <div className="flex items-center gap-2 rounded-full border bg-background/95 p-1.5 pl-4 shadow-lg backdrop-blur-xl">
            <span className="text-xs font-medium text-muted-foreground">
              {t("branchSettings.unsaved")}
            </span>
            <Button size="sm" onClick={save} loading={saving}>
              {tc("actions.save")}
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}

/** A numeric policy row (percent / count) on the inherit-or-override pattern. */
function NumberSettingRow({
  title,
  help,
  override,
  schoolDefault,
  min,
  max,
  suffix,
  offValue,
  onChange,
}: {
  title: string
  help: string
  override: number | null
  schoolDefault: number
  min: number
  max: number
  suffix?: string
  /** When the school default equals this, the inherited badge reads "off". */
  offValue?: number
  onChange: (value: number | null) => void
}) {
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const effective = override ?? schoolDefault

  return (
    <SettingRow
      title={title}
      inherited={override === null}
      inheritedLabel={t("branchSettings.inherited", {
        value:
          offValue !== undefined && schoolDefault === offValue
            ? tc("toggles.off")
            : `${schoolDefault}${suffix ?? ""}`,
      })}
      onInheritChange={(inherit) => onChange(inherit ? null : effective)}
    >
      <div className="flex flex-wrap items-center gap-3">
        <div className="relative">
          <Input
            type="number"
            min={min}
            max={max}
            disabled={override === null}
            value={effective}
            onChange={(e) => onChange(Number(e.target.value))}
            className={cn(
              "no-spinner h-11 w-24 text-base font-semibold tabular-nums",
              suffix && "pr-7",
            )}
          />
          {suffix && (
            <span className="absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground">
              {suffix}
            </span>
          )}
        </div>
        <p className="min-w-0 flex-1 text-xs text-muted-foreground">{help}</p>
      </div>
    </SettingRow>
  )
}

/** An on/off policy row driven by the inherit-or-override pattern. */
function BoolSettingRow({
  title,
  help,
  override,
  schoolDefault,
  onChange,
}: {
  title: string
  help: string
  override: boolean | null
  schoolDefault: boolean
  onChange: (value: boolean | null) => void
}) {
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const effective = override ?? schoolDefault

  return (
    <SettingRow
      title={title}
      inherited={override === null}
      inheritedLabel={t("branchSettings.inherited", {
        value: schoolDefault ? tc("toggles.on") : tc("toggles.off"),
      })}
      onInheritChange={(inherit) => onChange(inherit ? null : effective)}
    >
      <div className="flex items-center justify-between gap-3 rounded-xl border px-4 py-3">
        <p className="text-xs text-muted-foreground">{help}</p>
        <Switch
          checked={effective}
          disabled={override === null}
          onCheckedChange={(v) => onChange(v)}
        />
      </div>
    </SettingRow>
  )
}

/** One policy row: inherit switch on the right, custom controls below. */
function SettingRow({
  title,
  inherited,
  inheritedLabel,
  onInheritChange,
  children,
}: {
  title: string
  inherited: boolean
  inheritedLabel: string
  onInheritChange: (inherit: boolean) => void
  children: React.ReactNode
}) {
  const { t } = useTranslation("schools")

  return (
    <div className="px-5 py-4">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <p className="text-sm font-medium">{title}</p>
          {inherited && (
            <Badge variant="outline" className="rounded-full border-info/30 bg-info/10 text-[11px] text-info">
              {inheritedLabel}
            </Badge>
          )}
        </div>
        <label className="flex items-center gap-2 text-xs text-muted-foreground">
          {t("branchSettings.customize")}
          <Switch checked={!inherited} onCheckedChange={(v) => onInheritChange(!v)} />
        </label>
      </div>
      {children}
    </div>
  )
}

function HubLink({
  href,
  icon: Icon,
  title,
  description,
}: {
  href: string
  icon: typeof TrendingUp
  title: string
  description: string
}) {
  return (
    <Link
      href={href}
      className="pressable group flex items-start gap-3 rounded-2xl border bg-card p-4 shadow-xs transition-colors hover:bg-accent/40"
    >
      <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent">
        <Icon className="size-4.5" strokeWidth={1.75} />
      </div>
      <div className="min-w-0 flex-1">
        <p className="flex items-center gap-1 text-sm font-medium">
          {title}
          <ArrowRight className="size-3.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
        </p>
        <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
      </div>
    </Link>
  )
}
