"use client"

import {
  BadgePercent,
  BellRing,
  FileBadge,
  KeyRound,
  Landmark,
  MessagesSquare,
  ShieldCheck,
} from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { JobTitleChips } from "@/components/employees/job-title-chips"
import { Button } from "@/components/ui/button"
import { ProfileTabBar } from "@/components/ui/profile-tabs"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { TimePicker } from "@/components/ui/time-picker"
import { ReportCardSkillsEditor } from "@/components/schools/report-card-skills-editor"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { ReportCardSkill, School } from "@/lib/types"
import { useSchoolContext } from "@/lib/auth/school-context"
import { setCalendarPrefs } from "@/lib/dates"

/** The policy families, each its own sub-tab (mirrors Branch settings). */
type SettingsTab =
  | "academic"
  | "staff"
  | "attendance"
  | "fees"
  | "finance"
  | "chat"
  | "reportCards"

/** Order-insensitive equality for the account-titles list. */
function sameTitles(a: string[], b: string[]): boolean {
  return [...a].sort().join(",") === [...b].sort().join(",")
}

/**
 * School-wide default policy: the academic gate/pass-mark, attendance &
 * device alerts, fees & discounts, and finance controls. Owned by the school's
 * managers — backend-enforced via SchoolPolicy@updateSettings. The many knobs
 * are grouped into sub-tabs; one Save persists them all at once.
 */
export function PolicyCard({
  school,
  canEdit,
  onSaved,
}: {
  school: School | null
  canEdit: boolean
  onSaved: (school: School) => void
}) {
  const { t } = useTranslation("schools")
  const { active } = useSchoolContext()

  const [gate, setGate] = useState<"soft" | "hard">("soft")
  const [calendarMode, setCalendarMode] = useState<"ethiopian" | "gregorian">("ethiopian")
  const [clockMode, setClockMode] = useState<"standard" | "ethiopian">("ethiopian")
  const [threshold, setThreshold] = useState("50")
  const [teacherAssessments, setTeacherAssessments] = useState(false)
  const [deptReview, setDeptReview] = useState(false)
  const [accountTitles, setAccountTitles] = useState<string[]>([])
  const [smsEnabled, setSmsEnabled] = useState(true)
  const [smsLate, setSmsLate] = useState(false)
  const [autoAbsent, setAutoAbsent] = useState(false)
  const [cutoff, setCutoff] = useState("09:30")
  const [grace, setGrace] = useState("15")
  const [siblingPercent, setSiblingPercent] = useState("0")
  const [siblingMin, setSiblingMin] = useState("2")
  const [staffPercent, setStaffPercent] = useState("0")
  const [proration, setProration] = useState<"full" | "daily">("full")
  const [remindersEnabled, setRemindersEnabled] = useState(true)
  const [daysBefore, setDaysBefore] = useState("3")
  const [overdueEvery, setOverdueEvery] = useState("7")
  const [overdueMax, setOverdueMax] = useState("3")
  const [selfApproval, setSelfApproval] = useState(false)
  const [directorFinance, setDirectorFinance] = useState(false)
  const [chatApproval, setChatApproval] = useState<"off" | "first" | "all">(
    "all"
  )
  const [chatStudents, setChatStudents] = useState(false)
  const [chatTemplateMode, setChatTemplateMode] = useState<"suggested" | "required">("suggested")
  const [rcSkills, setRcSkills] = useState<ReportCardSkill[]>([])
  const [rcPerPage, setRcPerPage] = useState<1 | 2 | 4>(1)
  const [rcSubjectRanks, setRcSubjectRanks] = useState(false)
  const [rcGradingCriteria, setRcGradingCriteria] = useState(false)
  const [saving, setSaving] = useState(false)
  // Local (not URL-synced) — the school profile already owns the ?tab= param
  // for its Overview/Branches/Settings tabs.
  const [tab, setTab] = useState<SettingsTab>("academic")

  useEffect(() => {
    if (!school) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- sync form from loaded school
    setGate(school.registration_gate ?? "soft")
    setCalendarMode(school.calendar_mode ?? "ethiopian")
    setClockMode(school.clock_mode ?? "ethiopian")
    setThreshold(String(school.promotion_threshold ?? 50))
    setTeacherAssessments(school.teacher_assessments_enabled ?? false)
    setDeptReview(school.lesson_plan_department_review ?? false)
    setAccountTitles(school.employee_account_job_titles ?? [])
    setSmsEnabled(school.attendance_sms_enabled ?? true)
    setSmsLate(school.attendance_sms_late ?? false)
    setAutoAbsent(school.device_auto_absent ?? false)
    setCutoff(school.device_absent_cutoff ?? "09:30")
    setGrace(String(school.device_late_grace ?? 15))
    setSiblingPercent(String(school.sibling_discount_percent ?? 0))
    setSiblingMin(String(school.sibling_min_children ?? 2))
    setStaffPercent(String(school.staff_child_discount_percent ?? 0))
    setProration(school.fee_proration ?? "full")
    setRemindersEnabled(school.fee_reminders_enabled ?? true)
    setDaysBefore(String(school.fee_reminder_days_before ?? 3))
    setOverdueEvery(String(school.fee_reminder_overdue_every ?? 7))
    setOverdueMax(String(school.fee_reminder_overdue_max ?? 3))
    setSelfApproval(school.finance_self_approval ?? false)
    setDirectorFinance(school.director_finance_access ?? false)
    setChatApproval(school.chat_teacher_parent_approval ?? "all")
    setChatStudents(school.chat_students_enabled ?? false)
    setChatTemplateMode(school.chat_template_mode ?? "suggested")
    setRcSkills(school.report_card_skills ?? [])
    setRcPerPage(school.report_card_per_page ?? 1)
    setRcSubjectRanks(school.report_card_subject_ranks ?? false)
    setRcGradingCriteria(school.report_card_grading_criteria ?? false)
  }, [school])

  if (!school) {
    return <Skeleton className="h-40 rounded-2xl" />
  }

  const dirty =
    gate !== (school.registration_gate ?? "soft") ||
    calendarMode !== (school.calendar_mode ?? "ethiopian") ||
    clockMode !== (school.clock_mode ?? "ethiopian") ||
    Number(threshold) !== (school.promotion_threshold ?? 50) ||
    teacherAssessments !== (school.teacher_assessments_enabled ?? false) ||
    deptReview !== (school.lesson_plan_department_review ?? false) ||
    !sameTitles(accountTitles, school.employee_account_job_titles ?? []) ||
    smsEnabled !== (school.attendance_sms_enabled ?? true) ||
    smsLate !== (school.attendance_sms_late ?? false) ||
    autoAbsent !== (school.device_auto_absent ?? false) ||
    cutoff !== (school.device_absent_cutoff ?? "09:30") ||
    Number(grace) !== (school.device_late_grace ?? 15) ||
    Number(siblingPercent) !== (school.sibling_discount_percent ?? 0) ||
    Number(siblingMin) !== (school.sibling_min_children ?? 2) ||
    Number(staffPercent) !== (school.staff_child_discount_percent ?? 0) ||
    proration !== (school.fee_proration ?? "full") ||
    remindersEnabled !== (school.fee_reminders_enabled ?? true) ||
    Number(daysBefore) !== (school.fee_reminder_days_before ?? 3) ||
    Number(overdueEvery) !== (school.fee_reminder_overdue_every ?? 7) ||
    Number(overdueMax) !== (school.fee_reminder_overdue_max ?? 3) ||
    selfApproval !== (school.finance_self_approval ?? false) ||
    directorFinance !== (school.director_finance_access ?? false) ||
    chatApproval !== (school.chat_teacher_parent_approval ?? "all") ||
    chatStudents !== (school.chat_students_enabled ?? false) ||
    chatTemplateMode !== (school.chat_template_mode ?? "suggested") ||
    JSON.stringify(rcSkills) !== JSON.stringify(school.report_card_skills ?? []) ||
    rcPerPage !== (school.report_card_per_page ?? 1) ||
    rcSubjectRanks !== (school.report_card_subject_ranks ?? false) ||
    rcGradingCriteria !== (school.report_card_grading_criteria ?? false)

  async function save() {
    if (!school) return
    setSaving(true)
    try {
      const res = await apiFetch<{ data: School }>(
        `/schools/${school.id}/settings`,
        {
          method: "PATCH",
          body: {
            registration_gate: gate,
            calendar_mode: calendarMode,
            clock_mode: clockMode,
            promotion_threshold: Number(threshold),
            teacher_assessments_enabled: teacherAssessments,
            lesson_plan_department_review: deptReview,
            employee_account_job_titles: accountTitles,
            attendance_sms_enabled: smsEnabled,
            attendance_sms_late: smsLate,
            device_auto_absent: autoAbsent,
            device_absent_cutoff: cutoff,
            device_late_grace: Number(grace),
            sibling_discount_percent: Number(siblingPercent),
            sibling_min_children: Number(siblingMin) || 2,
            staff_child_discount_percent: Number(staffPercent),
            fee_proration: proration,
            fee_reminders_enabled: remindersEnabled,
            fee_reminder_days_before: Number(daysBefore) || 0,
            fee_reminder_overdue_every: Number(overdueEvery) || 7,
            fee_reminder_overdue_max: Number(overdueMax) || 0,
            finance_self_approval: selfApproval,
            director_finance_access: directorFinance,
            chat_teacher_parent_approval: chatApproval,
            chat_students_enabled: chatStudents,
            chat_template_mode: chatTemplateMode,
            // Empty labels would print blank rows — drop label-less rows and
            // fall back to English where a translation wasn't typed.
            report_card_skills: rcSkills
              .filter((s) => s.label.en.trim() !== "")
              .map((s) => ({
                ...s,
                label: {
                  en: s.label.en.trim(),
                  am: s.label.am.trim() || s.label.en.trim(),
                  om: s.label.om.trim() || s.label.en.trim(),
                },
              })),
            report_card_per_page: rcPerPage,
            report_card_subject_ranks: rcSubjectRanks,
            report_card_grading_criteria: rcGradingCriteria,
          },
        }
      )
      onSaved(res.data)
      // The workspace's date/time display follows immediately — the contexts
      // payload is only refetched on reload.
      if (active.schoolId === school.id) {
        setCalendarPrefs({ calendar: calendarMode, clock: clockMode })
      }
      toast.success(t("policy.saved"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("policy.title"))
    } finally {
      setSaving(false)
    }
  }

  // The header tracks the active sub-tab. Finance controls are school-scope
  // only, so they don't carry the "branches inherit these" hint.
  const TAB_META: Record<
    SettingsTab,
    { icon: typeof ShieldCheck; hint: string }
  > = {
    academic: { icon: ShieldCheck, hint: t("policy.defaultsHint") },
    staff: { icon: KeyRound, hint: t("policy.defaultsHint") },
    attendance: { icon: BellRing, hint: t("policy.defaultsHint") },
    fees: { icon: BadgePercent, hint: t("policy.defaultsHint") },
    finance: { icon: Landmark, hint: t("financeControls.hint") },
    chat: { icon: MessagesSquare, hint: t("policy.defaultsHint") },
    reportCards: { icon: FileBadge, hint: t("policy.defaultsHint") },
  }
  const HeaderIcon = TAB_META[tab].icon

  return (
    <section className="rounded-2xl border bg-card p-5 shadow-xs">
      <div className="flex items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <HeaderIcon className="size-4.5" strokeWidth={1.75} />
          </div>
          <div>
            <h2 className="text-sm font-semibold">{t(`policy.tabs.${tab}`)}</h2>
            <p className="text-xs text-muted-foreground">
              {TAB_META[tab].hint}
            </p>
          </div>
        </div>
        {canEdit && (
          <Button size="sm" onClick={save} loading={saving} disabled={!dirty}>
            {t("policy.save")}
          </Button>
        )}
      </div>

      {/* Each policy family on its own sub-tab — one Save persists them all. */}
      <ProfileTabBar
        className="mt-5"
        tabs={[
          {
            key: "academic",
            label: t("policy.tabs.academic"),
            icon: ShieldCheck,
          },
          { key: "staff", label: t("policy.tabs.staff"), icon: KeyRound },
          {
            key: "attendance",
            label: t("policy.tabs.attendance"),
            icon: BellRing,
          },
          { key: "fees", label: t("policy.tabs.fees"), icon: BadgePercent },
          { key: "finance", label: t("policy.tabs.finance"), icon: Landmark },
          { key: "chat", label: t("policy.tabs.chat"), icon: MessagesSquare },
          { key: "reportCards", label: t("policy.tabs.reportCards"), icon: FileBadge },
        ]}
        value={tab}
        onChange={setTab}
      />

      {tab === "academic" && (
        <>
          {/* Calendar & clock: how every date/time in the app, SMS and
              documents is written for this school. Display-only — official
              PDFs always print both calendars. */}
          <div className="mt-5 grid gap-5 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t("policy.calendarMode")}</Label>
              <Select
                value={calendarMode}
                onValueChange={(v) => setCalendarMode(v as "ethiopian" | "gregorian")}
                disabled={!canEdit}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="ethiopian">{t("policy.calendarEthiopian")}</SelectItem>
                  <SelectItem value="gregorian">{t("policy.calendarGregorian")}</SelectItem>
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                {t("policy.calendarHelp")}
              </p>
            </div>
            <div className="space-y-2">
              <Label>{t("policy.clockMode")}</Label>
              <Select
                value={clockMode}
                onValueChange={(v) => setClockMode(v as "standard" | "ethiopian")}
                disabled={!canEdit}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="standard">{t("policy.clockStandard")}</SelectItem>
                  <SelectItem value="ethiopian">{t("policy.clockEthiopian")}</SelectItem>
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                {t("policy.clockHelp")}
              </p>
            </div>
          </div>

          <div className="mt-5 grid gap-5 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t("policy.registrationGate")}</Label>
              <Select
                value={gate}
                onValueChange={(v) => setGate(v as "soft" | "hard")}
                disabled={!canEdit}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="soft">{t("policy.gateSoft")}</SelectItem>
                  <SelectItem value="hard">{t("policy.gateHard")}</SelectItem>
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                {t("policy.gateHelp")}
              </p>
            </div>
            <div className="space-y-2">
              <Label>{t("policy.threshold")}</Label>
              <Input
                type="number"
                min={0}
                max={100}
                value={threshold}
                onChange={(e) => setThreshold(e.target.value)}
                disabled={!canEdit}
                className="max-w-32 tabular-nums"
              />
              <p className="text-xs text-muted-foreground">
                {t("policy.thresholdHelp")}
              </p>
            </div>
          </div>

          {/* Grading policy default (branch-overridable). */}
          <div className="mt-5 grid gap-3 border-t pt-5">
            <label className="flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
              <span>
                <span className="block text-sm font-medium">
                  {t("policy.teacherAssessments")}
                </span>
                <span className="block text-xs text-muted-foreground">
                  {t("policy.teacherAssessmentsHelp")}
                </span>
              </span>
              <Switch
                checked={teacherAssessments}
                onCheckedChange={setTeacherAssessments}
                disabled={!canEdit}
              />
            </label>
            <label className="flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
              <span>
                <span className="block text-sm font-medium">
                  {t("policy.lessonPlanDeptReview")}
                </span>
                <span className="block text-xs text-muted-foreground">
                  {t("policy.lessonPlanDeptReviewHelp")}
                </span>
              </span>
              <Switch
                checked={deptReview}
                onCheckedChange={setDeptReview}
                disabled={!canEdit}
              />
            </label>
          </div>
        </>
      )}

      {tab === "staff" && (
        <div className="mt-5 space-y-3">
          <div>
            <p className="text-sm font-medium">{t("staffAccounts.title")}</p>
            <p className="text-xs text-muted-foreground">
              {t("staffAccounts.help")}
            </p>
          </div>
          <JobTitleChips
            value={accountTitles}
            onChange={setAccountTitles}
            disabled={!canEdit}
          />
          <p className="text-xs text-muted-foreground">
            {t("staffAccounts.lockedHint")}
          </p>
        </div>
      )}

      {tab === "attendance" && (
        <>
          {/* Attendance alerts + device attendance defaults (branch-overridable). */}
          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            <label className="flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
              <span>
                <span className="block text-sm font-medium">
                  {t("alerts.enabled")}
                </span>
                <span className="block text-xs text-muted-foreground">
                  {t("alerts.enabledHelp")}
                </span>
              </span>
              <Switch
                checked={smsEnabled}
                onCheckedChange={setSmsEnabled}
                disabled={!canEdit}
              />
            </label>
            <label className="flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
              <span>
                <span className="block text-sm font-medium">
                  {t("alerts.late")}
                </span>
                <span className="block text-xs text-muted-foreground">
                  {t("alerts.lateHelp")}
                </span>
              </span>
              <Switch
                checked={smsLate}
                onCheckedChange={setSmsLate}
                disabled={!canEdit}
              />
            </label>
            <label className="flex items-start justify-between gap-3 rounded-xl border px-4 py-3 sm:col-span-2">
              <span>
                <span className="block text-sm font-medium">
                  {t("deviceMode.autoAbsent")}
                </span>
                <span className="block text-xs text-muted-foreground">
                  {t("deviceMode.autoAbsentHelp")}
                </span>
              </span>
              <Switch
                checked={autoAbsent}
                onCheckedChange={setAutoAbsent}
                disabled={!canEdit}
              />
            </label>
            <div className="space-y-2">
              <Label>{t("deviceMode.cutoff")}</Label>
              <TimePicker
                value={cutoff}
                onChange={(value) => value && setCutoff(value)}
                disabled={!canEdit}
                clearable={false}
              />
              <p className="text-xs text-muted-foreground">
                {t("deviceMode.cutoffHelp")}
              </p>
            </div>
            <div className="space-y-2">
              <Label>{t("deviceMode.grace")}</Label>
              <Input
                type="number"
                min={0}
                max={120}
                value={grace}
                onChange={(e) => setGrace(e.target.value)}
                disabled={!canEdit}
                className="max-w-32 tabular-nums"
              />
              <p className="text-xs text-muted-foreground">
                {t("deviceMode.graceHelp")}
              </p>
            </div>
          </div>
        </>
      )}

      {tab === "fees" && (
        <>
          {/* Concession policy — files PENDING suggestions for finance to
          approve, never silent discounts. 0% = policy off. */}
          <div className="mt-5">
            <h3 className="text-sm font-semibold">
              {t("concessionPolicy.title")}
            </h3>
            <p className="mt-0.5 text-xs text-muted-foreground">
              {t("concessionPolicy.hint")}
            </p>
            <div className="mt-4 grid gap-5 sm:grid-cols-3">
              <div className="space-y-2">
                <Label>{t("concessionPolicy.siblingPercent")}</Label>
                <Input
                  type="number"
                  min={0}
                  max={100}
                  value={siblingPercent}
                  onChange={(e) => setSiblingPercent(e.target.value)}
                  disabled={!canEdit}
                  className="max-w-32 tabular-nums"
                />
                <p className="text-xs text-muted-foreground">
                  {t("concessionPolicy.siblingPercentHelp")}
                </p>
              </div>
              <div className="space-y-2">
                <Label>{t("concessionPolicy.siblingMin")}</Label>
                <Input
                  type="number"
                  min={2}
                  max={10}
                  value={siblingMin}
                  onChange={(e) => setSiblingMin(e.target.value)}
                  disabled={!canEdit || Number(siblingPercent) <= 0}
                  className="max-w-32 tabular-nums"
                />
                <p className="text-xs text-muted-foreground">
                  {t("concessionPolicy.siblingMinHelp")}
                </p>
              </div>
              <div className="space-y-2">
                <Label>{t("concessionPolicy.staffPercent")}</Label>
                <Input
                  type="number"
                  min={0}
                  max={100}
                  value={staffPercent}
                  onChange={(e) => setStaffPercent(e.target.value)}
                  disabled={!canEdit}
                  className="max-w-32 tabular-nums"
                />
                <p className="text-xs text-muted-foreground">
                  {t("concessionPolicy.staffPercentHelp")}
                </p>
              </div>
            </div>
          </div>

          {/* Billing + the automated payment-reminder ladder (branch-overridable). */}
          <div className="mt-5 border-t pt-5">
            <h3 className="text-sm font-semibold">
              {t("billingPolicy.title")}
            </h3>
            <p className="mt-0.5 text-xs text-muted-foreground">
              {t("billingPolicy.hint")}
            </p>
            <div className="mt-4 grid gap-5 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>{t("billingPolicy.proration")}</Label>
                <Select
                  value={proration}
                  onValueChange={(v) => setProration(v as "full" | "daily")}
                  disabled={!canEdit}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="full">
                      {t("billingPolicy.prorationFull")}
                    </SelectItem>
                    <SelectItem value="daily">
                      {t("billingPolicy.prorationDaily")}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  {t(
                    proration === "daily"
                      ? "billingPolicy.prorationDailyHelp"
                      : "billingPolicy.prorationFullHelp"
                  )}
                </p>
              </div>
              <label className="flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
                <span>
                  <span className="block text-sm font-medium">
                    {t("billingPolicy.remindersEnabled")}
                  </span>
                  <span className="block text-xs text-muted-foreground">
                    {t("billingPolicy.remindersEnabledHelp")}
                  </span>
                </span>
                <Switch
                  checked={remindersEnabled}
                  onCheckedChange={setRemindersEnabled}
                  disabled={!canEdit}
                />
              </label>
            </div>
            <div className="mt-4 grid gap-5 sm:grid-cols-3">
              <div className="space-y-2">
                <Label>{t("billingPolicy.daysBefore")}</Label>
                <Input
                  type="number"
                  min={0}
                  max={30}
                  value={daysBefore}
                  onChange={(e) => setDaysBefore(e.target.value)}
                  disabled={!canEdit || !remindersEnabled}
                  className="max-w-32 tabular-nums"
                />
                <p className="text-xs text-muted-foreground">
                  {t("billingPolicy.daysBeforeHelp")}
                </p>
              </div>
              <div className="space-y-2">
                <Label>{t("billingPolicy.overdueEvery")}</Label>
                <Input
                  type="number"
                  min={1}
                  max={60}
                  value={overdueEvery}
                  onChange={(e) => setOverdueEvery(e.target.value)}
                  disabled={!canEdit || !remindersEnabled}
                  className="max-w-32 tabular-nums"
                />
                <p className="text-xs text-muted-foreground">
                  {t("billingPolicy.overdueEveryHelp")}
                </p>
              </div>
              <div className="space-y-2">
                <Label>{t("billingPolicy.overdueMax")}</Label>
                <Input
                  type="number"
                  min={0}
                  max={10}
                  value={overdueMax}
                  onChange={(e) => setOverdueMax(e.target.value)}
                  disabled={!canEdit || !remindersEnabled}
                  className="max-w-32 tabular-nums"
                />
                <p className="text-xs text-muted-foreground">
                  {t("billingPolicy.overdueMaxHelp")}
                </p>
              </div>
            </div>
          </div>
        </>
      )}

      {tab === "finance" && (
        <>
          {/* Finance controls — school-scope only; a branch director can never
          reach this card (SchoolPolicy@updateSettings requires managesSchool).
          The card header already names this section. */}
          <div className="mt-5">
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
                <span>
                  <span className="block text-sm font-medium">
                    {t("financeControls.selfApproval")}
                  </span>
                  <span className="block text-xs text-muted-foreground">
                    {t("financeControls.selfApprovalHelp")}
                  </span>
                </span>
                <Switch
                  checked={selfApproval}
                  onCheckedChange={setSelfApproval}
                  disabled={!canEdit}
                />
              </label>
              <label className="flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
                <span>
                  <span className="block text-sm font-medium">
                    {t("financeControls.directorAccess")}
                  </span>
                  <span className="block text-xs text-muted-foreground">
                    {t("financeControls.directorAccessHelp")}
                  </span>
                </span>
                <Switch
                  checked={directorFinance}
                  onCheckedChange={setDirectorFinance}
                  disabled={!canEdit}
                />
              </label>
            </div>
          </div>
        </>
      )}
      {tab === "chat" && (
        <div className="mt-5 grid gap-5 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>{t("chatPolicy.approvalMode")}</Label>
            <Select
              value={chatApproval}
              onValueChange={(v) =>
                setChatApproval(v as "off" | "first" | "all")
              }
              disabled={!canEdit}
            >
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">
                  {t("chatPolicy.approvalAll")}
                </SelectItem>
                <SelectItem value="first">
                  {t("chatPolicy.approvalFirst")}
                </SelectItem>
                <SelectItem value="off">
                  {t("chatPolicy.approvalOff")}
                </SelectItem>
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
              {t("chatPolicy.approvalHelp")}
            </p>
          </div>
          <div className="space-y-2">
            <Label>{t("chatPolicy.templateMode")}</Label>
            <Select
              value={chatTemplateMode}
              onValueChange={(v) =>
                setChatTemplateMode(v as "suggested" | "required")
              }
              disabled={!canEdit}
            >
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="suggested">
                  {t("chatPolicy.templateSuggested")}
                </SelectItem>
                <SelectItem value="required">
                  {t("chatPolicy.templateRequired")}
                </SelectItem>
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
              {t("chatPolicy.templateHelp")}
            </p>
          </div>
          <label className="flex h-fit items-start justify-between gap-3 rounded-xl border px-4 py-3">
            <span>
              <span className="block text-sm font-medium">
                {t("chatPolicy.students")}
              </span>
              <span className="block text-xs text-muted-foreground">
                {t("chatPolicy.studentsHelp")}
              </span>
            </span>
            <Switch
              checked={chatStudents}
              onCheckedChange={setChatStudents}
              disabled={!canEdit}
            />
          </label>
        </div>
      )}

      {tab === "reportCards" && (
        <div className="mt-5 space-y-5">
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t("reportCardPolicy.perPage")}</Label>
              <Select
                value={String(rcPerPage)}
                onValueChange={(v) => setRcPerPage(v === "4" ? 4 : v === "2" ? 2 : 1)}
                disabled={!canEdit}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="1">{t("reportCardPolicy.perPage1")}</SelectItem>
                  <SelectItem value="2">{t("reportCardPolicy.perPage2")}</SelectItem>
                  <SelectItem value="4">{t("reportCardPolicy.perPage4")}</SelectItem>
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                {t("reportCardPolicy.perPageHelp")}
              </p>
            </div>
            <div className="grid gap-3">
              <label className="flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
                <span>
                  <span className="block text-sm font-medium">
                    {t("reportCardPolicy.subjectRanks")}
                  </span>
                  <span className="block text-xs text-muted-foreground">
                    {t("reportCardPolicy.subjectRanksHelp")}
                  </span>
                </span>
                <Switch
                  checked={rcSubjectRanks}
                  onCheckedChange={setRcSubjectRanks}
                  disabled={!canEdit}
                />
              </label>
              <label className="flex items-start justify-between gap-3 rounded-xl border px-4 py-3">
                <span>
                  <span className="block text-sm font-medium">
                    {t("reportCardPolicy.gradingCriteria")}
                  </span>
                  <span className="block text-xs text-muted-foreground">
                    {t("reportCardPolicy.gradingCriteriaHelp")}
                  </span>
                </span>
                <Switch
                  checked={rcGradingCriteria}
                  onCheckedChange={setRcGradingCriteria}
                  disabled={!canEdit}
                />
              </label>
            </div>
          </div>

          <div className="border-t pt-5">
            <h3 className="text-sm font-semibold">{t("reportCardPolicy.skillsTitle")}</h3>
            <p className="mt-0.5 mb-3 text-xs text-muted-foreground">
              {t("reportCardPolicy.skillsHint")}
            </p>
            <ReportCardSkillsEditor value={rcSkills} onChange={setRcSkills} disabled={!canEdit} />
          </div>
        </div>
      )}
    </section>
  )
}
