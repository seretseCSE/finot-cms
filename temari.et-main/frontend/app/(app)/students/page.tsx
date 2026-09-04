"use client"

import { Archive, ArrowRightLeft, FileText, FileUp, MessageCircleMore, Plus, Send, Trash2, Undo2, UserCheck, UserMinus, UserPlus } from "lucide-react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { useChatLauncher } from "@/components/chat/chat-launcher"
import { BulkSectionSheet } from "@/components/students/bulk-section-sheet"
import { EnrollStudentSheet } from "@/components/students/enroll-student-sheet"
import { LoginClaimsButton } from "@/components/students/login-claims"
import { WithdrawStudentDialog } from "@/components/students/withdraw-dialog"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { CopyableId } from "@/components/ui/copyable-id"
import {
  DataTable,
  type DataTableBulkAction,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { DateRangeFilter } from "@/components/ui/date-range-filter"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { ApiError, apiFetch } from "@/lib/api"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { runBulk, useBulkConfirm } from "@/components/ui/bulk-actions"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  AcademicYear,
  Paginated,
  Section,
  Student,
} from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"
import { useScopeFilters } from "@/lib/use-scope-filters"

function gradeOf(student: Student): string {
  return student.current_enrollment?.grade_level?.name ?? "—"
}

export default function StudentsPage() {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const { t: tChat } = useTranslation("chat")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { confirmBulk, bulkDialog } = useBulkConfirm()
  const { openChat, available: chatAvailable } = useChatLauncher()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  const [academicYears, setAcademicYears] = useState<AcademicYear[]>([])
  // Branch-scoped grade offering, session-cached across pages.
  const { grades: gradeLevels } = useGradeLevels()
  const [sections, setSections] = useState<Section[]>([])

  const [enrolling, setEnrolling] = useState<Student | null>(null)
  const [enrollOpen, setEnrollOpen] = useState(false)
  const [bulkStudents, setBulkStudents] = useState<Student[]>([])
  const [bulkOpen, setBulkOpen] = useState(false)
  const [activating, setActivating] = useState<Student | null>(null)
  const [withdrawing, setWithdrawing] = useState<Student | null>(null)
  const [activateWorking, setActivateWorking] = useState(false)

  const canCreate = permissions.includes("students.create")
  const canAssignSections = permissions.includes("sections.update")
  const canDelete = permissions.includes("students.delete")
  const canEnroll = permissions.includes("enrollments.create")
  // Re-sending a portal setup link is the same authority as editing the student
  // (PortalAccountController checks StudentPolicy@update per row).
  const canUpdate = permissions.includes("students.update")
  const canWithdraw = permissions.includes("transfers.manage")
  const hasBranch = active.branchId != null
  /** True when the user is in an all-branches context (platform or school-level). */
  const isGlobal = !hasBranch && (isPlatform || active.schoolId != null)
  // School managers register from the school-wide workspace too — the wizard
  // asks for the target branch first.
  const canTargetBranch = hasBranch || (!isPlatform && active.schoolId != null)

  const table = useServerTable<Student>({
    endpoint: "/students",
    exportEndpoint: "/students/export",
    defaultSort: { key: "created_at", dir: "desc" },
    enabled: hasBranch || isGlobal,
    refreshKey: `${active.schoolId ?? ""}-${active.branchId ?? ""}`,
    loadFailedMessage: t("loadFailed"),
  })

  useEffect(() => {
    if (!hasBranch && !isGlobal) return
    let cancelled = false

    apiFetch<Paginated<AcademicYear>>("/academic-years")
      .then((res) => !cancelled && setAcademicYears(res.data))
      .catch(() => {})
    apiFetch<Paginated<Section>>("/sections")
      .then((res) => !cancelled && setSections(res.data))
      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [hasBranch, isGlobal, active.branchId, active.schoolId])

  const selectedGradeId = table.filters.grade_level_id

  // The section filter only lists sections under the selected grade(s) —
  // multi-select grades come through comma-joined.
  const sectionOptions = useMemo(() => {
    const gradeIds = (selectedGradeId ?? "").split(",").filter(Boolean)
    return gradeIds.length > 0
      ? sections.filter((section) => gradeIds.includes(String(section.grade_level?.id ?? "")))
      : sections
  }, [sections, selectedGradeId])

  // Grade change invalidates a section picked under another grade.
  useEffect(() => {
    if (!selectedGradeId) return
    const current = table.filters.section_id
    if (current && !sectionOptions.some((s) => String(s.id) === current)) {
      table.setFilter("section_id", "")
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reset the dependent filter only when grade/options change
  }, [selectedGradeId, sectionOptions])

  async function handleDelete(student: Student) {
    try {
      await apiFetch(`/students/${student.id}`, { method: "DELETE" })
      toast.success(t("deleted"))
      await table.refetch()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const columns: DataTableColumn<Student>[] = [
    ...(isGlobal
      ? [
          {
            key: "branch_name",
            label: tc("columns.branch"),
            render: (row: Student) => (
              <span className="text-xs text-muted-foreground">
                {row.school_name} · {row.branch_name}
              </span>
            ),
            exportValue: (row: Student) =>
              [row.school_name, row.branch_name].filter(Boolean).join(" · "),
          } as DataTableColumn<Student>,
        ]
      : []),
    {
      key: "first_name",
      label: t("columns.name"),
      sortable: true,
      primary: true,
      render: (row) => (
        <span className="flex items-center gap-2.5 font-medium">
          <PersonAvatar name={row.full_name} photoUrl={row.photo_url} />
          {row.full_name}
        </span>
      ),
      exportValue: (row) => row.full_name,
    },
    {
      key: "public_id",
      label: t("columns.publicId"),
      sortable: true,
      render: (row) => <CopyableId value={row.public_id} fallback="—" />,
      exportValue: (row) => row.public_id ?? "",
    },
    {
      key: "gender",
      label: t("columns.gender"),
      sortable: true,
      mobileHidden: true,
      render: (row) => t(`fields.${row.gender}`),
      exportValue: (row) => row.gender,
    },
    {
      key: "grade",
      label: t("columns.grade"),
      render: (row) => gradeOf(row),
      exportValue: (row) => gradeOf(row),
    },
    {
      key: "section",
      label: t("columns.section"),
      mobileHidden: true,
      render: (row) => row.current_enrollment?.section_name ?? "—",
      exportValue: (row) => row.current_enrollment?.section_name ?? "",
    },
    {
      key: "status",
      label: t("columns.status"),
      render: (row) => {
        // Archive rows: the student moved to another school — the enrollment
        // shown is this school's own closed one, flagged distinctly.
        if (row.access === "archive") {
          return (
            <Badge
              variant="outline"
              className="gap-1 border-warning/30 bg-warning/10 text-warning"
              title={t("archive.badgeHint")}
            >
              <Archive className="size-3" />
              {row.current_enrollment?.status_label ?? t("archive.badge")}
            </Badge>
          )
        }
        const status = row.current_enrollment?.status
        if (!status) return <Badge variant="secondary">{t("columns.notEnrolled")}</Badge>
        if (status === "pending") {
          return (
            <Badge
              variant="outline"
              className="border-warning/30 bg-warning/10 text-warning"
              title={t("enrollment.pendingHint")}
            >
              {t("enrollment.pending")}
            </Badge>
          )
        }
        return (
          <Badge
            variant="outline"
            className={
              status === "active"
                ? "border-success/30 bg-success/10 text-success"
                : "border-border bg-muted text-muted-foreground"
            }
          >
            {row.current_enrollment?.status_label}
          </Badge>
        )
      },
      exportValue: (row) =>
        row.current_enrollment ? row.current_enrollment.status_label : t("columns.notEnrolled"),
    },
  ]

  const scopeFilters = useScopeFilters(table.filters)

  const filterDefs: DataTableFilter[] = [
    {
      key: "gender",
      label: t("columns.gender"),
      options: [
        { label: t("fields.male"), value: "male" },
        { label: t("fields.female"), value: "female" },
      ],
    },
    ...(gradeLevels.length > 0
      ? [
          {
            key: "grade_level_id",
            label: t("columns.grade"),
            options: gradeLevels.map((grade) => ({
              label: grade.name,
              value: String(grade.id),
            })),
          },
        ]
      : []),
    ...(sectionOptions.length > 0
      ? [
          {
            key: "section_id",
            label: t("columns.section"),
            // Cascades from grade: hidden until a grade is picked.
            dependsOn: "grade_level_id",
            options: sectionOptions.map((section) => ({
              label: section.grade_level?.name
                ? `${section.grade_level.name} — ${section.name}`
                : section.name,
              value: String(section.id),
            })),
          },
        ]
      : []),
    {
      key: "enrollment_status",
      label: t("filters.enrollmentStatus"),
      options: [
        { label: t("enrollment.pending"), value: "pending" },
        { label: tc("states.active"), value: "active" },
      ],
    },
    {
      key: "is_active",
      label: t("filters.recordStatus"),
      options: [
        { label: tc("states.active"), value: "true" },
        { label: tc("states.inactive"), value: "false" },
      ],
    },
  ]
  // Deleted records are a Temari.et platform view — school admins never see them.
  if (isPlatform)
    filterDefs.push({
      key: "trashed",
      label: tc("filters.deleted"),
      options: [{ label: tc("filters.showDeleted"), value: "with" }],
    })

  const rowActions = [
    // Family thread (ADR-019) — the conversation is with the student's
    // guardians; the kernel decides reach (own sections vs students.view).
    ...(chatAvailable
      ? [
          {
            label: tChat("launcher.chatFamily"),
            icon: MessageCircleMore,
            onClick: (row: Student) => void openChat({ kind: "student", studentId: row.id, name: row.full_name }),
            hidden: (row: Student) =>
              row.access === "archive" || row.current_enrollment?.status !== "active",
          },
        ]
      : []),
    // The multi-year transcript (frozen results) — opens the print view.
    {
      label: t("actions.transcript"),
      icon: FileText,
      onClick: (row: Student) => window.open(`/print/transcript/${row.id}`, "_blank"),
    },
    ...(canEnroll
      ? [
          {
            label: t("enroll.action"),
            icon: UserPlus,
            onClick: (row: Student) => {
              setEnrolling(row)
              setEnrollOpen(true)
            },
            // Custody moved with the transfer — re-enrolling here would need
            // a transfer request back, not a plain enrollment.
            hidden: (row: Student) => row.access === "archive",
          },
          {
            label: t("enrollment.activate"),
            icon: UserCheck,
            onClick: (row: Student) => setActivating(row),
            hidden: (row: Student) => row.current_enrollment?.status !== "pending",
          },
        ]
      : []),
    // Mid-year withdrawal (leaving school / moving outside Temari) — the same
    // student-movement authority as transfers.
    ...(canWithdraw
      ? [
          {
            label: t("enrollment.withdraw"),
            icon: UserMinus,
            destructive: true,
            onClick: (row: Student) => setWithdrawing(row),
            hidden: (row: Student) =>
              !["pending", "active"].includes(row.current_enrollment?.status ?? ""),
          },
        ]
      : []),
    ...(canDelete
      ? [
          {
            label: tc("actions.delete"),
            icon: Trash2,
            destructive: true,
            onClick: (row: Student) =>
              confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.full_name })),
            // Archive rows belong to the student's new school now.
            hidden: (row: Student) => row.access === "archive",
          },
        ]
      : []),
  ]

  // Mirrors the row actions: whatever the register lets you do to one student,
  // a selection can do to many. Section assignment keeps its own sheet.
  const studentBulkActions: DataTableBulkAction<Student>[] = []

  if (canAssignSections)
    studentBulkActions.push({
      label: t("bulkSection.action"),
      icon: ArrowRightLeft,
      onClick: (rows) => {
        setBulkStudents(rows)
        setBulkOpen(true)
      },
    })

  if (canEnroll)
    studentBulkActions.push({
      label: t("enrollment.activate"),
      icon: UserCheck,
      onClick: (rows) => {
        // Intake week: activate the pending enrollments once the registration
        // fees are in. Only rows that actually have one are sent.
        const pending = rows.filter((r) => r.current_enrollment?.status === "pending")
        if (pending.length === 0) {
          toast.info(t("bulk.noPendingEnrollments"))
          return
        }
        confirmBulk({
          title: t("bulk.activateTitle", { count: pending.length }),
          description: t("bulk.activateDesc"),
          confirmLabel: t("enrollment.activate"),
          action: async () => {
            await runBulk({
              url: "/enrollments/bulk/activate",
              ids: pending.map((r) => r.current_enrollment!.id),
              countKey: "activated",
              success: (count) => t("bulk.activated", { count }),
              tc,
            })
            await table.refetch()
          },
        })
      },
    })

  if (canUpdate)
    studentBulkActions.push({
      label: t("bulk.invite"),
      icon: Send,
      onClick: (rows) =>
        confirmBulk({
          title: t("bulk.inviteTitle", { count: rows.length }),
          description: t("bulk.inviteDesc"),
          confirmLabel: t("bulk.invite"),
          action: async () => {
            await runBulk({
              url: "/students/bulk/invite",
              ids: rows.map((r) => r.id),
              countKey: "sent",
              success: (count) => t("bulk.invited", { count }),
              tc,
            })
          },
        }),
    })

  if (canDelete) {
    studentBulkActions.push({
      label: tc("actions.delete"),
      icon: Trash2,
      destructive: true,
      onClick: (rows) =>
        confirmBulk({
          title: t("bulk.deleteTitle", { count: rows.length }),
          description: t("bulk.deleteDesc"),
          confirmLabel: tc("actions.delete"),
          destructive: true,
          action: async () => {
            await runBulk({
              url: "/students/bulk/delete",
              ids: rows.map((r) => r.id),
              countKey: "deleted",
              success: (count) => t("bulk.deleted", { count }),
              tc,
            })
            await table.refetch()
          },
        }),
    })
    studentBulkActions.push({
      label: tc("actions.restore"),
      icon: Undo2,
      onClick: (rows) =>
        confirmBulk({
          title: t("bulk.restoreTitle", { count: rows.length }),
          description: t("bulk.restoreDesc"),
          confirmLabel: tc("actions.restore"),
          action: async () => {
            await runBulk({
              url: "/students/bulk/restore",
              ids: rows.map((r) => r.id),
              countKey: "restored",
              success: (count) => t("bulk.restored", { count }),
              tc,
            })
            await table.refetch()
          },
        }),
    })
  }

  return (
    <div className="space-y-6">
      {confirmDialog}
      {bulkDialog}
      <PageHeader
        title={t("title")}
        description={t("subtitle")}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {/* Pending self-signup ID claims — renders nothing when empty. */}
            {permissions.includes("students.update") ? (
              <LoginClaimsButton refreshKey={`${active.schoolId ?? ""}-${active.branchId ?? ""}`} />
            ) : null}
            {canCreate && canTargetBranch ? (
              <Button asChild variant="outline" className="h-11">
                <Link href="/students/import">
                  <FileUp className="size-4" />
                  {t("import.button")}
                </Link>
              </Button>
            ) : null}
            {canCreate && canTargetBranch ? (
              <Button asChild className="h-11">
                <Link href="/students/new">
                  <Plus className="size-4" />
                  {t("create")}
                </Link>
              </Button>
            ) : null}
          </div>
        }
      />

      {!hasBranch && !isGlobal ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("noBranch")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={table.rows}
          loading={table.loading}
          serverMode
          searchable
          searchValue={table.searchInput}
          onSearchChange={table.setSearchInput}
          searchPlaceholder={t("searchPlaceholder")}
          filters={[...scopeFilters, ...filterDefs]}
          filterValues={table.filters}
          onFilterChange={table.setFilter}
          toolbarSlot={
            <DateRangeFilter
              fields={[
                { key: "registered_from", label: t("filters.registeredFrom") },
                { key: "registered_to", label: t("filters.registeredTo") },
              ]}
              values={table.dates}
              onChange={table.setDate}
              onClear={table.clearDates}
            />
          }
          onSortChange={table.onSortChange}
          onExport={table.handleExport}
          onRowClick={(row) => router.push(`/students/${row.id}`)}
          actions={rowActions.length > 0 ? rowActions : undefined}
          emptyMessage={t("empty")}
          exportFilename="students"
          pagination={table.pagination}
          bulkActions={studentBulkActions}
        />
      )}

      {/* Bulk assign/move/unassign the checked students to a section. */}
      <BulkSectionSheet
        students={bulkStudents}
        academicYears={academicYears}
        sections={sections}
        open={bulkOpen}
        onOpenChange={(v) => {
          setBulkOpen(v)
          if (!v) setBulkStudents([])
        }}
        onDone={() => table.refetch()}
      />

      {/* Activate a pending enrollment (registration-fee gate, soft mode). */}
      <AlertDialog open={activating !== null} onOpenChange={(open) => !open && setActivating(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("enrollment.activateTitle")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("enrollment.activateDesc", { name: activating?.full_name ?? "" })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={activateWorking}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={activateWorking}
              onClick={async (e) => {
                e.preventDefault()
                if (!activating?.current_enrollment) return
                setActivateWorking(true)
                try {
                  await apiFetch(`/enrollments/${activating.current_enrollment.id}/activate`, {
                    method: "POST",
                  })
                  toast.success(t("enrollment.activated"))
                  table.refetch()
                } catch (error) {
                  toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
                } finally {
                  setActivateWorking(false)
                  setActivating(null)
                }
              }}
            >
              {t("enrollment.activate")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Mid-year withdrawal — leaving school / moving outside Temari. */}
      <WithdrawStudentDialog
        student={withdrawing}
        onOpenChange={(open) => !open && setWithdrawing(null)}
        onDone={() => table.refetch()}
      />

      <EnrollStudentSheet
        student={enrolling}
        academicYears={academicYears}
        gradeLevels={gradeLevels}
        sections={sections}
        open={enrollOpen}
        onOpenChange={(v) => {
          setEnrollOpen(v)
          if (!v) setEnrolling(null)
        }}
        onEnrolled={() => table.refetch()}
      />
    </div>
  )
}
