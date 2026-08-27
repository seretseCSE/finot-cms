"use client"

import {
  Award,
  BookOpen,
  Cake,
  CalendarOff,
  Clock,
  FileText,
  Flag,
  GraduationCap,
  Heart,
  MapPin,
  MessageCircleMore,
  Pencil,
  UserRound,
} from "lucide-react"
import { useParams, useRouter, useSearchParams } from "next/navigation"
import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { EmployeePhotoAvatar } from "@/components/employees/employee-photo-avatar"
import { EmployeeAvailabilityTab } from "@/components/employees/tabs/availability-tab"
import { EmployeeDocumentsTab } from "@/components/employees/tabs/documents-tab"
import { EmployeeOverviewTab } from "@/components/employees/tabs/overview-tab"
import { useChatLauncher } from "@/components/chat/chat-launcher"
import { EmployeeQualificationsTab } from "@/components/employees/tabs/qualifications-tab"
import { EmployeeTeachingTab } from "@/components/employees/tabs/teaching-tab"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type { Employee } from "@/lib/types"
import { cn } from "@/lib/utils"

function Fact({
  icon: Icon,
  label,
  value,
}: {
  icon: React.ComponentType<{ className?: string }>
  label: string
  value?: React.ReactNode
}) {
  if (!value) return null
  return (
    <div className="flex items-start gap-3 py-2">
      <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-muted-foreground">
        <Icon className="size-4" />
      </span>
      <span className="min-w-0">
        <span className="block text-xs text-muted-foreground">{label}</span>
        <span className="block truncate text-sm font-medium">{value}</span>
      </span>
    </div>
  )
}

/**
 * The staff member's file — restyled to match the student profile: a sticky
 * identity rail with the photo/facts, and a pill-tabbed content lane whose
 * heavy panels (teaching load, availability) fetch lazily only when opened.
 * On mobile the rail stacks above the tabs, app-style.
 */
export default function EmployeeProfilePage() {
  const params = useParams<{ id: string }>()
  const employeeId = Number(params.id)
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")
  const { t: tChat } = useTranslation("chat")
  const { openChat, canTarget } = useChatLauncher()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  const [employee, setEmployee] = useState<Employee | null>(null)

  const canSeePay = permissions.includes("payroll.view")
  const canManageTimetable = permissions.includes("timetable.manage")
  const canEdit = permissions.includes("employees.update")

  // The edit wizard is a full page; profile tabs deep-link into a section
  // (?step=positions | teaching | qualifications | documents).
  const openEditor = useCallback(
    (step?: string) =>
      router.push(`/employees/${employeeId}/edit${step ? `?step=${step}` : ""}`),
    [router, employeeId],
  )

  const load = useCallback(() => {
    apiFetch<{ data: Employee }>(`/employees/${employeeId}`)
      .then((res) => setEmployee(res.data))
      .catch((error) =>
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic")),
      )
  }, [employeeId, tc])

  useEffect(() => load(), [load])

  const activePositions = (employee?.positions ?? []).filter((p) => !p.ended_on)
  const isTeacher = activePositions.some((p) => p.job_title === "teacher")

  const teachingSubjectCount = useMemo(
    () => new Set((employee?.teacher_subjects ?? []).map((ts) => ts.subject_id)).size,
    [employee],
  )

  /** Year of the earliest hire — the "joined" stat. */
  const joinedYear = useMemo(() => {
    const dates = (employee?.positions ?? []).map((p) => p.hired_on).filter(Boolean) as string[]
    if (dates.length === 0) return null
    return [...dates].sort()[0].slice(0, 4)
  }, [employee])

  // Tabs are built after we know whether this person teaches — the teaching
  // and availability lanes only exist for teachers.
  const tabs = useMemo(
    () =>
      [
        ["overview", UserRound],
        ...(isTeacher ? ([["teaching", BookOpen]] as const) : []),
        ["qualifications", GraduationCap],
        ["documents", FileText],
        ...(isTeacher ? ([["availability", CalendarOff]] as const) : []),
      ] as const,
    [isTeacher],
  )
  type TabKey = (typeof tabs)[number][0]

  // Deep links (⌘K palette) may land on a specific tab via ?tab=; unknown
  // values fall back to the overview. useSearchParams (never window.location):
  // during client-side nav the URL bar updates only AFTER the first render.
  const requestedTab = useSearchParams().get("tab")
  const [tab, setTab] = useState<string>("overview")
  useEffect(() => {
    if (requestedTab) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- URL → state sync
      setTab(requestedTab)
    }
  }, [requestedTab])
  const activeTab = tabs.some(([key]) => key === tab) ? (tab as TabKey) : "overview"

  const stats = employee
    ? [
        { label: t("profile.stats.joined"), value: joinedYear ?? "—" },
        { label: t("profile.stats.positions"), value: String(activePositions.length) },
        { label: t("profile.stats.subjects"), value: String(teachingSubjectCount) },
        { label: t("profile.stats.documents"), value: String((employee.attachments ?? []).length) },
      ]
    : []

  const address = employee
    ? [employee.city, employee.sub_city, employee.woreda && `${t("fields.woreda")} ${employee.woreda}`]
        .filter(Boolean)
        .join(", ")
    : ""

  return (
    <div className="space-y-6">
      <PageHeader
        title={employee ? employee.full_name : <Skeleton className="h-8 w-48" />}
        description={
          employee
            ? [employee.school_name, employee.branch_name].filter(Boolean).join(" · ") ||
              t("detail.info")
            : undefined
        }
        backHref="/employees"
        backLabel={t("title")}
        actions={
          employee ? (
            <div className="flex flex-wrap items-center gap-2">
              {/* Staff↔staff direct — linked account required, never yourself. */}
              {employee.user_id != null && canTarget({ kind: "user", userId: employee.user_id }) && (
                <Button
                  className="h-10 rounded-full"
                  onClick={() =>
                    void openChat({ kind: "user", userId: employee.user_id!, name: employee.full_name })
                  }
                >
                  <MessageCircleMore className="size-4" />
                  {tChat("launcher.chatStaff")}
                </Button>
              )}
              {canEdit && (
                <Button variant="outline" className="h-10 rounded-full" onClick={() => openEditor()}>
                  <Pencil className="size-4" />
                  {tc("actions.edit")}
                </Button>
              )}
            </div>
          ) : undefined
        }
      />

      <div className="page-gutter">
        {employee === null ? (
          <div className="grid gap-4 lg:grid-cols-[300px_1fr]">
            <Skeleton className="h-80 w-full rounded-2xl" />
            <div className="space-y-3">
              <Skeleton className="h-10 w-2/3 rounded-full" />
              <Skeleton className="h-64 w-full rounded-2xl" />
            </div>
          </div>
        ) : (
          <div className="grid gap-4 lg:grid-cols-[300px_1fr] lg:items-start">
            {/* Identity rail */}
            <aside className="space-y-4 lg:sticky lg:top-20">
              <div className="rounded-2xl border bg-card p-5 shadow-xs">
                <div className="flex flex-col items-center gap-3 text-center">
                  <EmployeePhotoAvatar
                    employeeId={employee.id}
                    name={employee.full_name}
                    photoUrl={employee.photo_url}
                    canUpdate={canEdit}
                    onUpdated={(photoUrl) =>
                      setEmployee((prev) => (prev ? { ...prev, photo_url: photoUrl } : prev))
                    }
                  />
                  <div className="min-w-0 space-y-1">
                    <p className="truncate font-display text-lg font-semibold">
                      {employee.full_name}
                    </p>
                    {employee.user?.public_id ? (
                      <p>
                        <CopyableId value={employee.user.public_id} className="text-xs" />
                      </p>
                    ) : null}
                  </div>
                  <div className="flex flex-wrap items-center justify-center gap-1.5">
                    <Badge
                      variant="secondary"
                      className={cn(
                        employee.is_active
                          ? "bg-success/10 text-success"
                          : "bg-muted text-muted-foreground",
                      )}
                    >
                      {employee.is_active ? tc("states.active") : tc("states.inactive")}
                    </Badge>
                    {(employee.active_job_titles ?? []).map((d) => (
                      <Badge key={d} variant="secondary" className="bg-primary/10 text-primary">
                        {t(`jobTitles.${d}`)}
                      </Badge>
                    ))}
                    {employee.user_id == null ? (
                      <Badge variant="secondary" className="bg-muted text-muted-foreground">
                        {t("account.none")}
                      </Badge>
                    ) : null}
                  </div>
                </div>

                {(employee.phone || employee.email) && (
                  <div className="mt-4 flex flex-col gap-2 border-t pt-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                      {t("detail.contact")}
                    </p>
                    {employee.phone ? (
                      <ContactActionCell
                        value={employee.phone}
                        kind="phone"
                        name={employee.full_name}
                        chat={
                          employee.user_id != null
                            ? { kind: "user", userId: employee.user_id, name: employee.full_name }
                            : undefined
                        }
                        triggerClassName="px-0"
                      />
                    ) : null}
                    {employee.email ? (
                      <ContactActionCell
                        value={employee.email}
                        kind="email"
                        name={employee.full_name}
                        chat={
                          employee.user_id != null
                            ? { kind: "user", userId: employee.user_id, name: employee.full_name }
                            : undefined
                        }
                        triggerClassName="px-0"
                      />
                    ) : null}
                  </div>
                )}

                <div className="mt-4 border-t pt-2">
                  <Fact
                    icon={UserRound}
                    label={t("fields.gender")}
                    value={employee.gender ? t(`genders.${employee.gender}`) : undefined}
                  />
                  <Fact icon={Cake} label={t("fields.birthDate")} value={employee.birth_date} />
                  <Fact
                    icon={Heart}
                    label={t("fields.maritalStatus")}
                    value={
                      employee.marital_status
                        ? t(`maritalStatuses.${employee.marital_status}`)
                        : undefined
                    }
                  />
                  <Fact icon={Flag} label={t("fields.nationality")} value={employee.nationality} />
                  <Fact
                    icon={Award}
                    label={t("fields.professionalLevel")}
                    value={employee.professional_level}
                  />
                  <Fact
                    icon={Clock}
                    label={t("profile.workHours")}
                    value={
                      employee.check_in && employee.check_out
                        ? `${employee.check_in} – ${employee.check_out}`
                        : undefined
                    }
                  />
                  <Fact icon={MapPin} label={t("sections.address")} value={address || undefined} />
                </div>
              </div>
            </aside>

            {/* Content */}
            <section className="min-w-0 space-y-4">
              <div className="flex gap-1.5 overflow-x-auto pb-1">
                {tabs.map(([key, Icon]) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setTab(key)}
                    className={cn(
                      "flex h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-sm font-medium transition-colors",
                      activeTab === key
                        ? "bg-primary text-primary-foreground"
                        : "bg-muted text-muted-foreground hover:bg-muted/70",
                    )}
                  >
                    <Icon className="size-4" />
                    {t(`detail.tabs.${key}`)}
                  </button>
                ))}
              </div>

              {activeTab === "overview" ? (
                <EmployeeOverviewTab
                  employee={employee}
                  canSeePay={canSeePay}
                  stats={stats}
                  onEdit={canEdit ? () => openEditor("positions") : undefined}
                />
              ) : null}
              {activeTab === "teaching" ? (
                <EmployeeTeachingTab
                  employee={employee}
                  canManageTimetable={canManageTimetable}
                  onEdit={canEdit ? () => openEditor("teaching") : undefined}
                />
              ) : null}
              {activeTab === "qualifications" ? (
                <EmployeeQualificationsTab
                  employee={employee}
                  onEdit={canEdit ? () => openEditor("qualifications") : undefined}
                />
              ) : null}
              {activeTab === "documents" ? (
                <EmployeeDocumentsTab
                  employee={employee}
                  canUpdate={canEdit}
                  onChanged={load}
                />
              ) : null}
              {activeTab === "availability" ? (
                <EmployeeAvailabilityTab
                  employeeId={employee.id}
                  canManage={canManageTimetable}
                />
              ) : null}
            </section>
          </div>
        )}
      </div>

    </div>
  )
}
