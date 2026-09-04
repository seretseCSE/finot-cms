"use client"

import { useParams, useSearchParams } from "next/navigation"
import {
  Archive,
  Cake,
  Droplets,
  FileText,
  Flag,
  GraduationCap,
  HeartPulse,
  IdCard,
  Languages,
  MapPin,
  MessageCircleMore,
  Pencil,
  Phone,
  Receipt,
  ScrollText,
  UserRound,
  Users,
} from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { AddressTab } from "@/components/students/tabs/address-tab"
import { DocumentsTab } from "@/components/students/tabs/documents-tab"
import { FeesTab } from "@/components/students/tabs/fees-tab"
import { GuardiansTab } from "@/components/students/tabs/guardians-tab"
import { HealthTab } from "@/components/students/tabs/health-tab"
import { OverviewTab } from "@/components/students/tabs/overview-tab"
import { PortalAccountSection } from "@/components/students/portal-account-section"
import { StudentPhotoAvatar } from "@/components/students/student-photo-avatar"
import { StudentSheet } from "@/components/students/student-sheet"
import { useChatLauncher } from "@/components/chat/chat-launcher"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type { Student } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtDate } from "@/lib/dates"

const TABS = [
  ["overview", UserRound],
  ["address", MapPin],
  ["guardians", Users],
  ["documents", FileText],
  ["health", HeartPulse],
  ["fees", Receipt],
] as const

type TabKey = (typeof TABS)[number][0]

function ageFrom(dateOfBirth: string): number | null {
  const dob = new Date(dateOfBirth)
  if (Number.isNaN(dob.getTime())) return null
  const now = new Date()
  let age = now.getFullYear() - dob.getFullYear()
  const beforeBirthday =
    now.getMonth() < dob.getMonth() ||
    (now.getMonth() === dob.getMonth() && now.getDate() < dob.getDate())
  if (beforeBirthday) age -= 1
  return age >= 0 ? age : null
}

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

export default function StudentDetailPage() {
  const params = useParams<{ id: string }>()
  const studentId = Number(params.id)
  const { t } = useTranslation("students")
  const { t: tChat } = useTranslation("chat")
  const permissions = useEffectivePermissions()
  const { openChat, available: chatAvailable } = useChatLauncher()

  const [student, setStudent] = useState<Student | null>(null)
  // Deep links (the ⌘K palette's invoice hits) may land on a specific tab via
  // ?tab=; unknown values fall back to the overview. useSearchParams (never
  // window.location) — during client-side navigation the URL bar only updates
  // AFTER the first render, so reading location here misses the param.
  const requestedTab = useSearchParams().get("tab")
  const [tab, setTab] = useState<TabKey>("overview")
  useEffect(() => {
    if (TABS.some(([key]) => key === requestedTab)) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- URL → state sync
      setTab(requestedTab as TabKey)
    }
  }, [requestedTab])
  const [editOpen, setEditOpen] = useState(false)

  // Archive mode: the student transferred to another school, so this school
  // keeps a read-only view of its own era — no edits, and the family/health/
  // document lanes are closed (the API refuses them anyway).
  const isArchive = student?.access === "archive"
  const canUpdate = permissions.includes("students.update") && !isArchive
  const canManageGuardians = permissions.includes("guardians.manage") && !isArchive
  const canViewFees = permissions.includes("fees.view")

  const load = useCallback(() => {
    apiFetch<{ data: Student }>(`/students/${studentId}`)
      .then((res) => setStudent(res.data))
      .catch((error) =>
        toast.error(error instanceof ApiError ? error.message : t("loadFailed")),
      )
  }, [studentId, t])

  useEffect(() => {
    load()
  }, [load])

  const tabs = TABS.filter(([key]) => key !== "fees" || canViewFees)
  const enrollment = student?.current_enrollment
  const age = student?.date_of_birth ? ageFrom(student.date_of_birth) : null
  const activeTab: TabKey = tabs.some(([key]) => key === tab) ? tab : "overview"

  // Archive mode reads the ERA SNAPSHOT (ADR-017): address/contact and health
  // exactly as they were when the student left this school — the tabs render
  // the frozen values, never the live record the new school now owns.
  const display: Student | null =
    student && isArchive && student.archive
      ? {
          ...student,
          ...(student.archive.profile ?? {}),
          blood_type: student.archive.health?.blood_type ?? null,
          health_notes: student.archive.health?.health_notes ?? null,
          health_conditions: student.archive.health?.conditions ?? [],
        }
      : student

  return (
    <div className="space-y-6">
      <PageHeader
        title={student ? student.full_name : <Skeleton className="h-8 w-48" />}
        description={t("detail.info")}
        backHref="/students"
        backLabel={t("title")}
        actions={
          student ? (
            <div className="flex flex-wrap items-center gap-2">
              {/* The family thread — live custody + active enrollment only. */}
              {chatAvailable && !isArchive && enrollment?.status === "active" && (
                <Button
                  className="h-10 rounded-full"
                  onClick={() =>
                    void openChat({ kind: "student", studentId: student.id, name: student.full_name })
                  }
                >
                  <MessageCircleMore className="size-4" />
                  {tChat("launcher.chatFamily")}
                </Button>
              )}
              {/* Multi-year transcript (frozen results) — opens the print view. */}
              <Button variant="outline" className="h-10 rounded-full" asChild>
                <a href={`/print/transcript/${student.id}`} target="_blank" rel="noreferrer">
                  <ScrollText className="size-4" />
                  {t("actions.transcript")}
                </a>
              </Button>
              {canUpdate && (
                <Button
                  variant="outline"
                  className="h-10 rounded-full"
                  onClick={() => setEditOpen(true)}
                >
                  <Pencil className="size-4" />
                  {t("editTitle")}
                </Button>
              )}
            </div>
          ) : undefined
        }
      />

      <div className="page-gutter">
        {student && isArchive ? (
          <div className="mb-4 flex items-start gap-3 rounded-2xl border border-warning/30 bg-warning/10 p-4">
            <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-warning/15 text-warning">
              <Archive className="size-4.5" />
            </span>
            <div className="min-w-0 space-y-0.5">
              <p className="text-sm font-semibold">{t("archive.title")}</p>
              <p className="text-sm text-muted-foreground">{t("archive.body")}</p>
              {student.archive?.captured_at ? (
                <p className="text-xs font-medium text-warning">
                  {t("archive.asOf", {
                    date: fmtDate(student.archive.captured_at),
                  })}
                </p>
              ) : null}
            </div>
          </div>
        ) : null}
        {student === null ? (
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
                  <StudentPhotoAvatar
                    studentId={student.id}
                    name={student.full_name}
                    photoUrl={student.photo_url ?? null}
                    canUpdate={canUpdate}
                    onUpdated={(photoUrl) =>
                      setStudent((prev) => (prev ? { ...prev, photo_url: photoUrl } : prev))
                    }
                  />
                  <div className="min-w-0 space-y-1">
                    <p className="truncate font-display text-lg font-semibold">{student.full_name}</p>
                    {student.public_id ? (
                      <p>
                        <CopyableId value={student.public_id} className="text-xs" />
                      </p>
                    ) : null}
                  </div>
                  <div className="flex flex-wrap items-center justify-center gap-1.5">
                    {isArchive ? (
                      <Badge
                        variant="secondary"
                        className="gap-1 bg-warning/10 text-warning"
                        title={t("archive.badgeHint")}
                      >
                        <Archive className="size-3" />
                        {t("archive.badge")}
                      </Badge>
                    ) : (
                      <Badge
                        variant="secondary"
                        className={cn(
                          student.is_active
                            ? "bg-success/10 text-success"
                            : "bg-muted text-muted-foreground",
                        )}
                      >
                        {student.is_active ? t("detail.active") : t("inactive")}
                      </Badge>
                    )}
                    {enrollment?.grade_level?.name ? (
                      <Badge variant="secondary" className="bg-primary/10 text-primary">
                        {enrollment.grade_level.name}
                        {enrollment.section_name ? ` — ${enrollment.section_name}` : ""}
                      </Badge>
                    ) : null}
                  </div>
                  {/* Registration is 4 fast steps — health/documents often
                      arrive later. Nudge staff to finish the file, one tap
                      to the right tab. */}
                  {canUpdate && (
                    <div className="flex flex-wrap items-center justify-center gap-1.5 empty:hidden">
                      {!student.blood_type &&
                        !student.health_notes &&
                        (student.health_conditions?.length ?? 0) === 0 && (
                          <button
                            type="button"
                            onClick={() => setTab("health")}
                            className="text-muted-foreground hover:border-warning/40 hover:text-warning inline-flex min-h-6 items-center gap-1 rounded-full border border-dashed px-2 py-0.5 text-[11px] transition-colors"
                          >
                            <HeartPulse className="size-3" />
                            {t("detail.missingHealth")}
                          </button>
                        )}
                      {(student.attachments?.length ?? 0) === 0 && (
                        <button
                          type="button"
                          onClick={() => setTab("documents")}
                          className="text-muted-foreground hover:border-warning/40 hover:text-warning inline-flex min-h-6 items-center gap-1 rounded-full border border-dashed px-2 py-0.5 text-[11px] transition-colors"
                        >
                          <FileText className="size-3" />
                          {t("detail.missingDocuments")}
                        </button>
                      )}
                    </div>
                  )}
                  {/* This year's homeroom teacher — tap for call/copy. */}
                  {enrollment?.homeroom_teacher ? (
                    enrollment.homeroom_teacher.phone ? (
                      <ContactActionCell
                        value={enrollment.homeroom_teacher.phone}
                        name={enrollment.homeroom_teacher.name}
                        chat={
                          enrollment.homeroom_teacher.user_id != null
                            ? {
                                kind: "user",
                                userId: enrollment.homeroom_teacher.user_id,
                                name: enrollment.homeroom_teacher.name,
                              }
                            : undefined
                        }
                      >
                        <span className="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                          <GraduationCap className="size-3.5" />
                          {t("detail.homeroomTeacher")}:{" "}
                          <span className="text-foreground font-medium">
                            {enrollment.homeroom_teacher.name}
                          </span>
                          <Phone className="size-3" />
                        </span>
                      </ContactActionCell>
                    ) : (
                      <p className="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                        <GraduationCap className="size-3.5" />
                        {t("detail.homeroomTeacher")}:{" "}
                        <span className="text-foreground font-medium">
                          {enrollment.homeroom_teacher.name}
                        </span>
                      </p>
                    )
                  ) : null}
                </div>

                {/* Portal account — "can this student log in?" on the record
                    itself, not buried in a users admin. Live custody only:
                    archive viewers never see (or provision) the login. */}
                {!isArchive ? (
                  <div className="mt-4 border-t pt-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                      {t("detail.portalAccount")}
                    </p>
                    <div className="mt-2">
                      <PortalAccountSection
                        kind="student"
                        personId={student.id}
                        personName={student.full_name}
                        account={student.account}
                        canManage={canUpdate}
                        defaultPhone={student.primary_phone}
                        onChanged={load}
                      />
                    </div>
                  </div>
                ) : null}

                {((display ?? student).primary_phone || (display ?? student).email) && (
                  <div className="mt-4 space-y-1 border-t pt-3 flex flex-col gap-2">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                      {t("detail.contact")}
                    </p>
                    {(display ?? student).primary_phone ? (
                      <ContactActionCell
                        value={(display ?? student).primary_phone!}
                        kind="phone"
                        name={student.full_name}
                        chat={
                          !isArchive
                            ? { kind: "student", studentId: student.id, name: student.full_name }
                            : undefined
                        }
                        triggerClassName="px-0"
                      />
                    ) : null}
                    {(display ?? student).email ? (
                      <ContactActionCell
                        value={(display ?? student).email!}
                        kind="email"
                        name={student.full_name}
                        chat={
                          !isArchive
                            ? { kind: "student", studentId: student.id, name: student.full_name }
                            : undefined
                        }
                        triggerClassName="px-0"
                      />
                    ) : null}
                  </div>
                )}

                <div className="mt-4 border-t pt-2">
                  <Fact icon={UserRound} label={t("fields.gender")} value={t(`fields.${student.gender}`)} />
                  <Fact
                    icon={Cake}
                    label={t("fields.dateOfBirth")}
                    value={
                      student.date_of_birth
                        ? `${student.date_of_birth}${age !== null ? ` · ${age} ${t("detail.years")}` : ""}`
                        : undefined
                    }
                  />
                  <Fact
                    icon={Droplets}
                    label={t("fields.bloodType")}
                    value={(display ?? student).blood_type}
                  />
                  <Fact
                    icon={Languages}
                    label={t("fields.languages")}
                    value={(student.languages ?? [])
                      .map((code) => t(`fields.languageNames.${code}`))
                      .join(", ")}
                  />
                  <Fact icon={Users} label={t("fields.motherName")} value={student.mother_name} />
                  <Fact icon={Flag} label={t("fields.citizenship")} value={student.citizenship} />
                  <Fact icon={IdCard} label={t("fields.nationalId")} value={student.national_student_id} />
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
                <OverviewTab
                  student={display ?? student}
                  canEditEnrollment={permissions.includes("enrollments.create") && !isArchive}
                  onChanged={load}
                />
              ) : null}
              {activeTab === "address" ? <AddressTab student={display ?? student} /> : null}
              {activeTab === "guardians" ? (
                <GuardiansTab studentId={studentId} canManage={canManageGuardians} />
              ) : null}
              {activeTab === "documents" ? (
                <DocumentsTab
                  student={display ?? student}
                  canUpdate={canUpdate}
                  onChanged={load}
                  transferFiles={student.transfer_files}
                />
              ) : null}
              {activeTab === "health" ? (
                <HealthTab student={display ?? student} canUpdate={canUpdate} onChanged={load} />
              ) : null}
              {activeTab === "fees" && canViewFees ? <FeesTab student={student} /> : null}
            </section>
          </div>
        )}
      </div>

      {student ? (
        <StudentSheet
          student={student}
          academicYears={[]}
          sections={[]}
          open={editOpen}
          onOpenChange={setEditOpen}
          onSaved={(updated) => {
            setStudent((prev) => (prev ? { ...prev, ...updated } : updated))
            load()
          }}
        />
      ) : null}
    </div>
  )
}
