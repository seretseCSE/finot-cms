"use client"

import {
  Award,
  BookOpen,
  DoorOpen,
  GraduationCap,
  Percent,
  Phone,
  Users,
} from "lucide-react"
import { useParams } from "next/navigation"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { TermSelect } from "@/components/academic/term-select"
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Paginated, Section, Term } from "@/lib/types"
import { cn } from "@/lib/utils"

/** One roster row as /sections/{id}/roster returns it. */
interface RosterStudent {
  enrollment_id: number
  student_id: number
  public_id: string | null
  full_name: string
  gender: "male" | "female" | null
  date_of_birth: string | null
  photo_url: string | null
  enrolled_on: string | null
  result: {
    average: number | null
    rank: number | null
    rank_of: number | null
    letter: string | null
    is_passing: boolean | null
    conduct: string | null
    absence_days: number | null
  } | null
}

interface SectionRoster {
  section: {
    id: number
    name: string
    grade_level: string | null
    room_number: string | null
    capacity: number | null
    is_active: boolean
  }
  term: {
    id: number
    name: string
    status: string
    academic_year_id: number
    academic_year_name: string | null
  }
  homeroom: { employee_id: number; user_id: number | null; name: string; phone: string | null } | null
  subjects_count: number
  can_view_marks: boolean
  summary: {
    students: number
    male: number
    female: number
    with_results: number
    average: number | null
    pass_rate: number | null
  }
  students: RosterStudent[]
}

/** Roster row + flat keys for the table's search/filters. */
type RosterRow = RosterStudent & {
  id: number
  age: number | null
  average_key: number | null
}

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

export default function SectionDetailPage() {
  const params = useParams<{ id: string }>()
  const sectionId = Number(params.id)
  const { t } = useTranslation("academic")
  const { t: ts } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const router = useRouter()

  const [section, setSection] = useState<Section | null>(null)
  const [terms, setTerms] = useState<Term[] | null>(null)
  const [termId, setTermId] = useState<string>("")
  const [roster, setRoster] = useState<SectionRoster | null>(null)

  // The section first (it names the branch), then its branch's semesters.
  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: Section }>(`/sections/${sectionId}`)
      .then((res) => !cancelled && setSection(res.data))
      .catch((error) => {
        if (!cancelled)
          toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [sectionId])

  useEffect(() => {
    if (section === null) return
    let cancelled = false
    apiFetch<Paginated<Term>>(`/terms?per_page=100&branch_id=${section.branch_id}`)
      .then((res) => {
        if (cancelled) return
        setTerms(res.data)
        const current = res.data.find((x) => x.status === "active")?.id ?? res.data[0]?.id
        if (current) setTermId((prev) => prev || String(current))
      })
      .catch(() => setTerms([]))
    return () => {
      cancelled = true
    }
  }, [section])

  useEffect(() => {
    if (!termId) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset for the new query
    setRoster(null)
    apiFetch<{ data: SectionRoster }>(`/sections/${sectionId}/roster?term_id=${termId}`)
      .then((res) => !cancelled && setRoster(res.data))
      .catch((error) => {
        if (!cancelled)
          toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [sectionId, termId])

  const rows: RosterRow[] | null = useMemo(
    () =>
      roster === null
        ? null
        : roster.students.map((s) => ({
            ...s,
            id: s.enrollment_id,
            age: s.date_of_birth ? ageFrom(s.date_of_birth) : null,
            average_key: s.result?.average ?? null,
          })),
    [roster],
  )

  const showMarks = roster?.can_view_marks ?? false

  const columns: DataTableColumn<RosterRow>[] = useMemo(
    () => [
      {
        key: "full_name",
        label: t("sections.profile.student"),
        primary: true,
        sortable: true,
        render: (row) => (
          <div className="flex min-w-0 items-center gap-2.5">
            <PersonAvatar name={row.full_name} photoUrl={row.photo_url} className="size-8" />
            <div className="min-w-0">
              <p className="truncate font-medium">{row.full_name}</p>
              {row.public_id ? (
                <p className="text-muted-foreground text-xs">{row.public_id}</p>
              ) : null}
            </div>
          </div>
        ),
        exportValue: (row) => row.full_name,
      },
      {
        key: "gender",
        label: ts("fields.gender"),
        render: (row) => (row.gender ? ts(`fields.${row.gender}`) : "—"),
        exportValue: (row) => row.gender ?? "",
      },
      {
        key: "age",
        label: t("sections.profile.age"),
        sortable: true,
        render: (row) => <span className="tabular-nums">{row.age ?? "—"}</span>,
        exportValue: (row) => (row.age !== null ? String(row.age) : ""),
      },
      ...(showMarks
        ? ([
            {
              key: "average_key",
              label: t("sections.profile.average"),
              sortable: true,
              render: (row: RosterRow) =>
                row.result?.average != null ? (
                  <span
                    className={cn(
                      "font-semibold tabular-nums",
                      row.result.is_passing === false && "text-destructive",
                    )}
                  >
                    {row.result.average}
                  </span>
                ) : (
                  <span className="text-muted-foreground">—</span>
                ),
              exportValue: (row: RosterRow) =>
                row.result?.average != null ? String(row.result.average) : "",
            },
            {
              key: "rank",
              label: t("sections.profile.rank"),
              render: (row: RosterRow) =>
                row.result?.rank != null && row.result.rank_of != null ? (
                  <span className="tabular-nums">
                    {row.result.rank}
                    <span className="text-muted-foreground"> / {row.result.rank_of}</span>
                  </span>
                ) : (
                  "—"
                ),
              exportValue: (row: RosterRow) =>
                row.result?.rank != null ? String(row.result.rank) : "",
            },
            {
              key: "letter",
              label: t("sections.profile.grade"),
              render: (row: RosterRow) =>
                row.result?.letter ? (
                  <Badge
                    variant="outline"
                    className={cn(
                      row.result.is_passing === false
                        ? "border-destructive/30 bg-destructive/10 text-destructive"
                        : "border-success/30 bg-success/10 text-success",
                    )}
                  >
                    {row.result.letter}
                  </Badge>
                ) : (
                  "—"
                ),
              exportValue: (row: RosterRow) => row.result?.letter ?? "",
            },
            {
              key: "conduct",
              label: t("sections.profile.conduct"),
              mobileHidden: true,
              render: (row: RosterRow) => row.result?.conduct ?? "—",
              exportValue: (row: RosterRow) => row.result?.conduct ?? "",
            },
            {
              key: "absence_days",
              label: t("sections.profile.absences"),
              mobileHidden: true,
              render: (row: RosterRow) => (
                <span className="tabular-nums">{row.result?.absence_days ?? "—"}</span>
              ),
              exportValue: (row: RosterRow) =>
                row.result?.absence_days != null ? String(row.result.absence_days) : "",
            },
          ] as DataTableColumn<RosterRow>[])
        : []),
    ],
    [t, ts, showMarks],
  )

  const title = roster
    ? `${roster.section.grade_level ?? ""} — ${roster.section.name}`.trim()
    : section
      ? `${section.grade_level?.name ?? ""} — ${section.name}`.trim()
      : null

  return (
    <div className="space-y-6 pb-10">
      <PageHeader
        title={title ?? <Skeleton className="h-8 w-40" />}
        description={
          roster
            ? [
                roster.term.academic_year_name,
                roster.section.room_number
                  ? `${t("sections.roomNumber")} ${roster.section.room_number}`
                  : null,
                roster.section.capacity
                  ? `${t("sections.capacity")} ${roster.section.capacity}`
                  : null,
              ]
                .filter(Boolean)
                .join(" · ")
            : t("sections.profile.subtitle")
        }
        backHref="/sections"
        backLabel={t("sections.title")}
        actions={
          <TermSelect
            terms={terms ?? []}
            value={termId}
            onValueChange={setTermId}
            placeholder={t("sections.profile.term")}
            aria-label={t("sections.profile.term")}
            className="h-9 w-full md:w-64"
          />
        }
      />

      {/* Vitals: composition + performance of the class this semester. */}
      <div className="page-gutter grid grid-cols-2 gap-3 lg:grid-cols-4">
        {roster === null ? (
          [0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-24 rounded-2xl" />)
        ) : (
          <>
            <StatCard
              label={t("sections.profile.students")}
              value={roster.summary.students}
              icon={Users}
              hint={`${roster.summary.male} ${t("sections.profile.boys")} · ${roster.summary.female} ${t("sections.profile.girls")}`}
            />
            <StatCard
              label={t("sections.profile.classAverage")}
              value={roster.summary.average ?? "—"}
              icon={Award}
              hint={
                roster.summary.with_results > 0
                  ? t("sections.profile.withResults", { count: roster.summary.with_results })
                  : undefined
              }
            />
            <StatCard
              label={t("sections.profile.passRate")}
              value={roster.summary.pass_rate !== null ? `${roster.summary.pass_rate}%` : "—"}
              icon={Percent}
            />
            <StatCard
              label={t("sections.profile.subjects")}
              value={roster.subjects_count}
              icon={BookOpen}
              hint={roster.term.name}
            />
          </>
        )}
      </div>

      {/* Homeroom teacher — tap for call/copy, per the contact convention. */}
      {roster !== null && (
        <div className="page-gutter">
          <div className="bg-card flex flex-wrap items-center gap-3 rounded-2xl border p-4 shadow-xs">
            <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
              <GraduationCap className="size-[18px]" strokeWidth={1.75} />
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-muted-foreground text-[13px]">
                {t("sections.homeroomName")}
              </p>
              {roster.homeroom?.phone ? (
                <ContactActionCell
                  value={roster.homeroom.phone}
                  name={roster.homeroom.name}
                  chat={
                    roster.homeroom.user_id != null
                      ? { kind: "user", userId: roster.homeroom.user_id, name: roster.homeroom.name }
                      : undefined
                  }
                >
                  <span className="flex items-center gap-1.5 text-sm font-semibold">
                    {roster.homeroom.name}
                    <Phone className="text-muted-foreground size-3.5" />
                  </span>
                </ContactActionCell>
              ) : (
                <p className="text-sm font-semibold">
                  {roster.homeroom?.name ?? t("sections.noHomeroom")}
                </p>
              )}
            </div>
            {roster.section.room_number ? (
              <span className="text-muted-foreground inline-flex items-center gap-1.5 text-sm">
                <DoorOpen className="size-4" />
                {roster.section.room_number}
              </span>
            ) : null}
          </div>
        </div>
      )}

      <DataTable
        columns={columns}
        data={rows ?? []}
        loading={rows === null}
        dense
        searchKeys={["full_name", "public_id"]}
        searchPlaceholder={tc("actions.search")}
        filters={[
          {
            key: "gender",
            label: ts("fields.gender"),
            options: [
              { label: ts("fields.male"), value: "male" },
              { label: ts("fields.female"), value: "female" },
            ],
          },
        ]}
        onRowClick={(row) => router.push(`/students/${row.student_id}`)}
        emptyMessage={t("sections.profile.empty")}
        exportFilename={`section-${roster?.section.grade_level ?? ""}-${roster?.section.name ?? sectionId}`}
      />
    </div>
  )
}
