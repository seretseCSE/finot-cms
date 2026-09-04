"use client"

import { Ban, CalendarRange, Check, GitBranch, KeyRound, Pencil, Trash2, Undo2, UserCog, UserX } from "lucide-react"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import {
  AffiliationsCell,
  affiliationExport,
} from "@/components/users/affiliations-cell"
import { BulkBranchSheet } from "@/components/users/bulk-branch-sheet"
import { reportBulkResult, type BulkSkip } from "@/components/ui/bulk-actions"
import { ManageBranchesSheet } from "@/components/users/manage-branches-sheet"
import { UserSheet } from "@/components/users/user-sheet"
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
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import {
  DataTable,
  exportCSV,
  exportExcel,
  type DataTableAction,
  type DataTableBulkAction,
  type DataTableColumn,
  type DataTableExportOptions,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { ApiError, apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useContextsResponse, useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import { ROLE_OPTIONS } from "@/lib/roles"
import { fmtDate, fmtDateTime } from "@/lib/dates"
import {
  initialPageSize,
  normalizePageSize,
  setStoredPageSize,
} from "@/lib/use-page-size"
import type {
  AccountStatus,
  AdminUser,
  Paginated,
} from "@/lib/types"


const STATUS_VARIANT: Record<
  AccountStatus,
  "default" | "secondary" | "destructive"
> = {
  active: "default",
  inactive: "secondary",
  banned: "destructive",
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? "")
    .join("")
}

function Avatar({ user }: { user: AdminUser }) {
  return (
    <div className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted text-xs font-semibold text-muted-foreground">
      {user.avatar_url ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={user.avatar_url} alt="" className="size-full object-cover" />
      ) : (
        initials(user.name)
      )}
    </div>
  )
}

interface DateFilters {
  registered_from: string
  registered_to: string
  last_login_from: string
  last_login_to: string
}

const EMPTY_DATES: DateFilters = {
  registered_from: "",
  registered_to: "",
  last_login_from: "",
  last_login_to: "",
}

type PendingAction =
  | { kind: "deactivate" | "ban" | "restore" | "delete"; user: AdminUser }
  | { kind: "bulk"; status: AccountStatus; users: AdminUser[] }
  | { kind: "bulkDelete" | "bulkReset" | "bulkRestore"; users: AdminUser[] }
  | null

/** Confirmed actions that undo or notify rather than destroy — no red button. */
const NON_DESTRUCTIVE = new Set(["bulkReset", "restore", "bulkRestore"])

/**
 * Pure relationship-lane row (ADR-012): a student/parent with no staff
 * memberships anywhere visible. School staff get no branch-management
 * affordances on these — their access follows the enrollment, not a role.
 */
function isRelationshipOnly(row: AdminUser): boolean {
  return (
    row.affiliations.length === 0 &&
    row.branches.length === 0 &&
    Boolean(row.relationships?.student || row.relationships?.parent)
  )
}

export default function UsersPage() {
  const { user: currentUser } = useAuth()
  const { active } = useSchoolContext()
  const { t } = useTranslation("users")
  // Skip reasons are shared platform vocabulary (common.bulkSkip.*), so every
  // bulk report on every table speaks the same language.
  const { t: tc } = useTranslation("common")

  // Context-scoped: the permissions effective in the ACTIVE school/branch, not the
  // global union of every role the user holds elsewhere (see useEffectivePermissions).
  const perms = useEffectivePermissions()
  const canCreate = perms.includes("users.create")
  const canEdit = perms.includes("users.update")
  const canSetStatus = perms.includes("users.status")
  const canReset = perms.includes("users.reset_password")
  const canDelete = perms.includes("users.delete")
  const canImpersonate = perms.includes("users.impersonate")
  const canAssignBranch = perms.includes("users.assign_branch")
  const canManageBranchAccess = perms.includes("users.manage_branch_access")
  // Anyone who can assign to or manage access within a branch opens the branch sheet.
  const canOpenBranchSheet = canAssignBranch || canManageBranchAccess
  const isPlatform = canSetStatus // global status permission is platform-only

  // ── Data + query state ──────────────────────────────────────────────────────
  const [rows, setRows] = useState<AdminUser[] | null>(null)
  const [meta, setMeta] = useState({ total: 0, last_page: 1, current_page: 1 })
  const [loading, setLoading] = useState(true)

  const [searchInput, setSearchInput] = useState("")
  const [search, setSearch] = useState("")
  const [filters, setFilters] = useState<Record<string, string>>({})
  const [dates, setDates] = useState<DateFilters>(EMPTY_DATES)
  const [sort, setSort] = useState<{ key: string; dir: "asc" | "desc" }>({
    key: "created_at",
    dir: "desc",
  })
  const [page, setPage] = useState(1)
  // Rows per page is the app-wide preference (shared with every DataTable
  // footer); seeded synchronously so the first fetch already asks for the
  // right number of rows.
  const [perPage, setPerPageState] = useState(() => initialPageSize())
  const setPerPage = useCallback((size: number) => {
    const next = normalizePageSize(size)
    setStoredPageSize(next)
    setPerPageState(next)
    setPage(1)
  }, [])

  // Context-derived filter options (schools / branches) + role tier — from
  // the shared, auto-refreshing contexts source (new branches appear live).
  const { data: contextsData } = useContextsResponse()
  const { schoolOptions, branchOptions, tier } = useMemo(() => {
    if (!contextsData) {
      return {
        schoolOptions: [] as { label: string; value: string }[],
        branchOptions: [] as {
          id: number
          name: string
          schoolId: number | null
          schoolName: string | null
        }[],
        tier: "platform" as "platform" | "principal" | "director",
      }
    }
    const schools: { label: string; value: string }[] = []
    const branches: {
      id: number
      name: string
      schoolId: number | null
      schoolName: string | null
    }[] = []
    for (const s of contextsData.schools) {
      schools.push({ label: s.name, value: String(s.id) })
      for (const b of s.branches)
        branches.push({ id: b.id, name: b.name, schoolId: s.id, schoolName: s.name })
    }
    return {
      schoolOptions: schools,
      branchOptions: branches,
      tier: contextsData.is_platform
        ? ("platform" as const)
        : contextsData.schools.some((s) => s.can_manage)
          ? ("principal" as const)
          : ("director" as const),
    }
  }, [contextsData])

  // Sheets + confirmations
  const [editUser, setEditUser] = useState<AdminUser | null>(null)
  const [editOpen, setEditOpen] = useState(false)
  const [manageUser, setManageUser] = useState<AdminUser | null>(null)
  const [manageOpen, setManageOpen] = useState(false)
  const [bulkBranchUsers, setBulkBranchUsers] = useState<AdminUser[]>([])
  const [bulkBranchOpen, setBulkBranchOpen] = useState(false)
  const [pending, setPending] = useState<PendingAction>(null)
  const [working, setWorking] = useState(false)

  // Debounce the search box.
  useEffect(() => {
    const id = setTimeout(() => setSearch(searchInput), 300)
    return () => clearTimeout(id)
  }, [searchInput])

  const buildParams = useCallback(
    (withPaging: boolean) => {
      const p = new URLSearchParams()
      if (search) p.set("search", search)
      for (const [k, v] of Object.entries(filters)) if (v) p.set(k, v)
      for (const [k, v] of Object.entries(dates)) if (v) p.set(k, v)
      p.set("sort", sort.key)
      p.set("dir", sort.dir)
      if (withPaging) {
        p.set("page", String(page))
        p.set("per_page", String(perPage))
      }
      return p
    },
    [search, filters, dates, sort, page, perPage]
  )

  const fetchUsers = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiFetch<Paginated<AdminUser>>(
        `/users?${buildParams(true).toString()}`
      )
      setRows(res.data)
      setMeta({
        total: res.meta.total,
        last_page: res.meta.last_page,
        current_page: res.meta.current_page,
      })
    } catch (err) {
      if (!(err instanceof ApiError) || err.code === null) {
        toast.error(
          err instanceof ApiError ? err.message : t("toast.loadFailed")
        )
      }
      setRows([])
    } finally {
      setLoading(false)
    }
  }, [buildParams, t])

  // Refetch on the active school/branch context too — apiFetch reads it from
  // storage and sends X-School-Id/X-Branch-Id, so a switch must re-query.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- syncing list state with the API
    fetchUsers()
  }, [fetchUsers, active.schoolId, active.branchId])

  // Reset to page 1 whenever the query or active context (not the page) changes.
  const queryKey = JSON.stringify({ search, filters, dates, sort, active })
  const firstRender = useRef(true)
  useEffect(() => {
    if (firstRender.current) {
      firstRender.current = false
      return
    }
    setPage(1)
  }, [queryKey])

  // ── Mutations ───────────────────────────────────────────────────────────────
  async function changeStatus(user: AdminUser, status: AccountStatus) {
    setWorking(true)
    try {
      await apiFetch(`/users/${user.id}/status`, {
        method: "PATCH",
        body: { status },
      })
      toast.success(t("toast.statusUpdated"))
      await fetchUsers()
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  async function deleteUser(user: AdminUser) {
    setWorking(true)
    try {
      // Confirmed, but not by the shared hook: this page routes delete / ban / deactivate /
      // bulk through ONE AlertDialog (the `pending` state below), and useConfirmDelete only
      // models delete. deleteUser is unreachable except via pending.kind === "delete".
      // eslint-disable-next-line temari/require-delete-confirmation -- see above
      await apiFetch(`/users/${user.id}`, { method: "DELETE" })
      toast.success(t("toast.deleted"))
      await fetchUsers()
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  async function restoreUser(user: AdminUser) {
    setWorking(true)
    try {
      await apiFetch(`/users/${user.id}/restore`, { method: "POST" })
      toast.success(t("toast.restored"))
      await fetchUsers()
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  async function bulkRestore(users: AdminUser[]) {
    setWorking(true)
    try {
      const res = await apiFetch<{
        meta: { restored: number; skipped: BulkSkip[] }
      }>("/users/bulk/restore", {
        method: "POST",
        body: { ids: users.map((u) => u.id) },
      })
      reportBulkResult(
        res.meta.restored,
        res.meta.skipped,
        t("toast.bulkRestored", { count: res.meta.restored }),
        tc
      )
      await fetchUsers()
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  async function bulkStatus(status: AccountStatus, users: AdminUser[]) {
    setWorking(true)
    try {
      const res = await apiFetch<{
        meta: { updated: number; skipped: BulkSkip[] }
      }>("/users/bulk/status", {
        method: "POST",
        body: { ids: users.map((u) => u.id), status },
      })
      reportBulkResult(
        res.meta.updated,
        res.meta.skipped,
        t("toast.bulkUpdated", { count: res.meta.updated }),
        tc
      )
      await fetchUsers()
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  async function bulkDelete(users: AdminUser[]) {
    setWorking(true)
    try {
      // Confirmed through this page's shared AlertDialog (`pending`), not the
      // useConfirmDelete hook — see deleteUser above for why.
      const res = await apiFetch<{
        meta: { deleted: number; skipped: BulkSkip[] }
      }>("/users/bulk/delete", {
        method: "POST",
        body: { ids: users.map((u) => u.id) },
      })
      reportBulkResult(
        res.meta.deleted,
        res.meta.skipped,
        t("toast.bulkDeleted", { count: res.meta.deleted }),
        tc
      )
      await fetchUsers()
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  async function bulkReset(users: AdminUser[]) {
    setWorking(true)
    try {
      const res = await apiFetch<{
        meta: { sent: number; skipped: BulkSkip[] }
      }>("/users/bulk/reset-password", {
        method: "POST",
        body: { ids: users.map((u) => u.id) },
      })
      reportBulkResult(
        res.meta.sent,
        res.meta.skipped,
        t("toast.bulkResetSent", { count: res.meta.sent }),
        tc
      )
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  async function resetPassword(user: AdminUser) {
    try {
      await apiFetch(`/users/${user.id}/reset-password`, { method: "POST" })
      toast.success(t("toast.resetSent"))
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    }
  }

  const [impersonating, setImpersonating] = useState<number | null>(null)
  async function impersonate(user: AdminUser) {
    if (impersonating !== null) return
    setImpersonating(user.id)
    try {
      const res = await apiFetch<{ data: { url: string } }>(
        `/users/${user.id}/impersonate`,
        { method: "POST" }
      )
      await navigator.clipboard.writeText(res.data.url)
      toast.success(t("toast.impersonateCopied"))
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      setImpersonating(null)
    }
  }

  async function handleExport({
    format,
    filename,
    columns: exportColumns,
  }: DataTableExportOptions<AdminUser>) {
    try {
      const res = await apiFetch<{ data: AdminUser[] }>(
        `/users/export?${buildParams(false).toString()}`
      )
      if (res.data.length === 0) {
        toast.info(t("toast.exportEmpty"))
        return
      }
      if (format === "csv") exportCSV(exportColumns, res.data, filename)
      else exportExcel(exportColumns, res.data, filename)
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    }
  }

  // ── Columns ─────────────────────────────────────────────────────────────────
  const columns: DataTableColumn<AdminUser>[] = [
    {
      key: "name",
      label: t("columns.user"),
      sortable: true,
      primary: true,
      render: (row) => (
        <div className="flex items-center gap-2.5">
          <Avatar user={row} />
          <div className="min-w-0">
            <p className="truncate leading-tight font-medium">{row.name}</p>
          </div>
        </div>
      ),
      exportValue: (row) => row.name,
    },
    {
      key: "public_id",
      label: t("columns.id"),
      sortable: true,
      render: (row) => <CopyableId value={row.public_id} fallback="—" />,
      exportValue: (row) => row.public_id ?? "",
    },
    {
      key: "phone",
      label: t("columns.phone"),
      sortable: true,
      render: (row) => (
        <ContactActionCell value={row.phone} name={row.name} />
      ),
      exportValue: (row) => row.phone,
    },
    {
      key: "email",
      label: t("columns.email"),
      sortable: true,
      mobileHidden: true,
      render: (row) => (
        <ContactActionCell value={row.email} kind="email" name={row.name} />
      ),
      exportValue: (row) => row.email ?? "",
    },
    {
      key: "access",
      label: t("columns.access"),
      // Unified School → Branch → Role tree. Replaces the old roles/schools/
      // branches columns, which showed the three flat and disconnected so you
      // couldn't tell which role a user held in which branch.
      mobileBlock: true,
      render: (row) => <AffiliationsCell user={row} />,
      exportValue: affiliationExport,
    },
    {
      key: "status",
      label: t("columns.status"),
      sortable: isPlatform,
      // Platform staff manage the GLOBAL account status (users.status), so they see it here.
      // Scoped principals/directors never touch that global flag — for them this column instead
      // reflects branch access (memberships), which is what they actually just changed and what
      // the "assign to branch" modal shows. Otherwise this column looks stuck on "Active" right
      // after they deactivate someone's branch membership, contradicting the modal.
      render: (row) => {
        if (row.deleted_at)
          return <Badge variant="destructive">{t("status.deleted")}</Badge>
        // Relationship-lane rows (students/parents) have no memberships —
        // their access follows the GLOBAL account status for every viewer.
        if (isPlatform || isRelationshipOnly(row))
          return (
            <Badge variant={STATUS_VARIANT[row.status]}>
              {row.status_label}
            </Badge>
          )
        // has_active_membership covers school-level memberships (principal /
        // school_admin) that have no branch row to check.
        const active = row.has_active_membership
        return (
          <Badge variant={active ? "default" : "secondary"}>
            {active ? t("status.active") : t("status.inactive")}
          </Badge>
        )
      },
      exportValue: (row) => {
        if (row.deleted_at) return t("status.deleted")
        if (isPlatform || isRelationshipOnly(row)) return row.status_label
        return row.has_active_membership
          ? t("status.active")
          : t("status.inactive")
      },
    },
    {
      key: "last_login_at",
      label: t("columns.lastLogin"),
      sortable: true,
      render: (row) =>
        row.last_login_at ? (
          fmtDate(row.last_login_at)
        ) : (
          <span className="text-muted-foreground">{t("neverLoggedIn")}</span>
        ),
      exportValue: (row) =>
        row.last_login_at ? fmtDateTime(row.last_login_at) : "",
    },
    {
      key: "created_at",
      label: t("columns.created"),
      sortable: true,
      render: (row) => fmtDate(row.created_at),
      exportValue: (row) => fmtDate(row.created_at),
    },
  ]

  // ── Filter definitions (server-driven) ──────────────────────────────────────
  // The "status" filter queries the GLOBAL account status, which only platform staff manage —
  // for scoped principals/directors it would silently disagree with the branch-access status
  // column above, so it's hidden for them.
  const filterDefs: DataTableFilter[] = []
  if (isPlatform)
    filterDefs.push({
      key: "status",
      label: t("filters.status"),
      options: [
        { label: t("status.active"), value: "active" },
        { label: t("status.inactive"), value: "inactive" },
        { label: t("status.banned"), value: "banned" },
      ],
    })
  // Platform staff filter across the whole system; scoped principals/directors
  // only ever see school-relevant people, so the role filter offers just the
  // school/branch job roles (+ student/parent), never platform or tutor/vendor
  // roles they can neither see nor manage.
  const roleFilterOptions = isPlatform
    ? ROLE_OPTIONS
    : ROLE_OPTIONS.filter(
        (r) =>
          r.scope === "school" ||
          r.scope === "branch" ||
          r.value === "student" ||
          r.value === "parent"
      )
  filterDefs.push({
    key: "role",
    label: t("filters.role"),
    options: roleFilterOptions.map((r) => ({ label: r.label, value: r.value })),
  })
  // Affiliated vs. independent (self-registered / B2C) only makes sense platform-
  // wide — a principal's list is already all school-affiliated people.
  if (isPlatform)
    filterDefs.push({
      key: "type",
      label: t("filters.type"),
      options: [
        { label: t("type.affiliated"), value: "affiliated" },
        { label: t("type.independent"), value: "independent" },
      ],
    })
  if (schoolOptions.length > 1)
    filterDefs.push({
      key: "school_id",
      label: t("filters.school"),
      options: schoolOptions,
    })
  // Cascade: the branch step appears once a school is unambiguous — either
  // picked in the school filter, or the only school the user can see.
  const cascadeSchoolId =
    schoolOptions.length === 1
      ? Number(schoolOptions[0].value)
      : filters["school_id"]
        ? Number(filters["school_id"])
        : null
  const cascadeBranches =
    cascadeSchoolId !== null
      ? branchOptions.filter((b) => b.schoolId === cascadeSchoolId)
      : []
  if (cascadeBranches.length > 1)
    filterDefs.push({
      key: "branch_id",
      label: t("filters.branch"),
      options: cascadeBranches.map((b) => ({
        label: b.name,
        value: String(b.id),
      })),
    })
  if (canDelete)
    filterDefs.push({
      key: "trashed",
      label: t("filters.deleted"),
      options: [{ label: t("filters.showDeleted"), value: "with" }],
    })

  // ── Row actions ─────────────────────────────────────────────────────────────
  const rowActions: DataTableAction<AdminUser>[] = []

  if (canEdit)
    rowActions.push({
      label: t("actions.edit"),
      icon: Pencil,
      onClick: (row) => {
        setEditUser(row)
        setEditOpen(true)
      },
    })
  if (canOpenBranchSheet && !isPlatform)
    rowActions.push({
      // Directors can only manage access within their branch, not assign to one —
      // so the label reflects what they can actually do.
      label: canAssignBranch
        ? t("actions.assignToBranch")
        : t("actions.manageBranches"),
      icon: GitBranch,
      onClick: (row) => {
        setManageUser(row)
        setManageOpen(true)
      },
      // Students/parents aren't staff — their access follows the enrollment,
      // so branch-membership management doesn't apply to them.
      hidden: isRelationshipOnly,
    })
  if (canImpersonate)
    rowActions.push({
      label: t("actions.impersonate"),
      icon: UserCog,
      onClick: impersonate,
      disabled: (row) => impersonating === row.id,
      hidden: (row) => row.id === currentUser?.id,
    })
  if (canReset)
    rowActions.push({
      label: t("actions.resetPassword"),
      icon: KeyRound,
      onClick: resetPassword,
    })

  if (canSetStatus) {
    rowActions.push({
      label: t("actions.activate"),
      icon: Check,
      onClick: (row) => changeStatus(row, "active"),
      hidden: (row) => row.status === "active" || row.id === currentUser?.id,
    })
    rowActions.push({
      label: t("actions.deactivate"),
      icon: UserX,
      onClick: (row) => setPending({ kind: "deactivate", user: row }),
      hidden: (row) => row.status !== "active" || row.id === currentUser?.id,
    })
    rowActions.push({
      label: t("actions.ban"),
      icon: Ban,
      destructive: true,
      onClick: (row) => setPending({ kind: "ban", user: row }),
      hidden: (row) => row.status === "banned" || row.id === currentUser?.id,
    })
  }

  if (canDelete) {
    rowActions.push({
      label: t("actions.delete"),
      icon: Trash2,
      destructive: true,
      onClick: (row) => setPending({ kind: "delete", user: row }),
      hidden: (row) => row.id === currentUser?.id || !!row.deleted_at,
    })
    // The way back out of the bin. Only reachable on trashed rows, which only
    // appear once the "Show deleted accounts" filter is on — same permission
    // gates seeing them and undeleting them.
    rowActions.push({
      label: t("actions.restore"),
      icon: Undo2,
      onClick: (row) => setPending({ kind: "restore", user: row }),
      hidden: (row) => !row.deleted_at,
    })
  }

  // ── Bulk actions ────────────────────────────────────────────────────────────
  // Mirrors the row actions: whatever an admin can do to one user in their scope,
  // they can do to a selection. Everything is authorized PER ROW server-side, and
  // rows out of reach come back as reported skips instead of a failed batch.
  const bulkActions: DataTableBulkAction<AdminUser>[] = []

  if (canSetStatus) {
    bulkActions.push({
      label: t("bulk.activate"),
      icon: Check,
      onClick: (r) => bulkStatus("active", r),
    })
    bulkActions.push({
      label: t("bulk.deactivate"),
      icon: UserX,
      onClick: (r) => setPending({ kind: "bulk", status: "inactive", users: r }),
    })
    bulkActions.push({
      label: t("bulk.ban"),
      icon: Ban,
      destructive: true,
      onClick: (r) => setPending({ kind: "bulk", status: "banned", users: r }),
    })
  }

  // Branch access for a whole selection — the school-side counterpart of the
  // per-row branch sheet, so it follows the same "not for platform staff" rule.
  if (canOpenBranchSheet && !isPlatform)
    bulkActions.push({
      label: t("bulk.branchAccess"),
      icon: GitBranch,
      onClick: (r) => {
        // Students/parents have no memberships — branch access means nothing
        // for them, so they are dropped from the selection up front.
        const staff = r.filter((row) => !isRelationshipOnly(row))
        if (staff.length === 0) {
          toast.info(t("bulk.noStaffSelected"))
          return
        }
        setBulkBranchUsers(staff)
        setBulkBranchOpen(true)
      },
    })

  if (canReset)
    bulkActions.push({
      label: t("bulk.resetPassword"),
      icon: KeyRound,
      onClick: (r) => setPending({ kind: "bulkReset", users: r }),
    })

  if (canDelete) {
    bulkActions.push({
      label: t("bulk.delete"),
      icon: Trash2,
      destructive: true,
      onClick: (r) => setPending({ kind: "bulkDelete", users: r }),
    })
    bulkActions.push({
      label: t("bulk.restore"),
      icon: Undo2,
      onClick: (r) => setPending({ kind: "bulkRestore", users: r }),
    })
  }

  const rangeFrom =
    meta.total === 0 ? 0 : (meta.current_page - 1) * perPage + 1
  const rangeTo = Math.min(meta.current_page * perPage, meta.total)
  const activeDateCount = Object.values(dates).filter(Boolean).length

  // One dialog serves every confirmed action on this page — single rows and
  // whole selections — so the copy is resolved here rather than nested in JSX.
  function confirmTitle(p: PendingAction): string {
    switch (p?.kind) {
      case "delete":
        return t("confirm.deleteTitle")
      case "bulkDelete":
        return t("confirm.bulkDeleteTitle", { count: p.users.length })
      case "bulkReset":
        return t("confirm.bulkResetTitle", { count: p.users.length })
      case "restore":
        return t("confirm.restoreTitle")
      case "bulkRestore":
        return t("confirm.bulkRestoreTitle", { count: p.users.length })
      case "ban":
        return t("confirm.banTitle")
      case "bulk":
        return p.status === "banned"
          ? t("confirm.banTitle")
          : t("confirm.deactivateTitle")
      default:
        return t("confirm.deactivateTitle")
    }
  }

  function confirmDesc(p: PendingAction): string {
    switch (p?.kind) {
      case "bulk":
        return t("bulk.selected", { count: p.users.length })
      case "bulkDelete":
        return t("confirm.bulkDeleteDesc", { count: p.users.length })
      case "bulkReset":
        return t("confirm.bulkResetDesc", { count: p.users.length })
      case "restore":
        return t("confirm.restoreDesc", { name: p.user.name })
      case "bulkRestore":
        return t("confirm.bulkRestoreDesc", { count: p.users.length })
      case "delete":
        return t("confirm.deleteDesc", { name: p.user.name })
      case "ban":
        return t("confirm.banDesc", { name: p.user.name })
      case "deactivate":
        return t("confirm.deactivateDesc", { name: p.user.name })
      default:
        return ""
    }
  }

  function runPending(p: PendingAction) {
    switch (p?.kind) {
      case "bulk":
        return bulkStatus(p.status, p.users)
      case "bulkDelete":
        return bulkDelete(p.users)
      case "bulkReset":
        return bulkReset(p.users)
      case "restore":
        return restoreUser(p.user)
      case "bulkRestore":
        return bulkRestore(p.users)
      case "delete":
        return deleteUser(p.user)
      case "ban":
        return changeStatus(p.user, "banned")
      case "deactivate":
        return changeStatus(p.user, "inactive")
    }
  }

  function handleRowClick(row: AdminUser) {
    if (isPlatform && canEdit) {
      setEditUser(row)
      setEditOpen(true)
    } else if (canOpenBranchSheet && !isRelationshipOnly(row)) {
      setManageUser(row)
      setManageOpen(true)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 px-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6 md:px-8">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">
            {t("title")}
          </h1>
          <p className="text-sm text-muted-foreground">
            {t(`subtitle.${tier}`)}
          </p>
        </div>
        <div className="flex items-center gap-2">
          {canCreate && <UserSheet showTrigger onSaved={fetchUsers} />}
        </div>
      </div>

      <DataTable
        columns={columns}
        data={rows ?? []}
        loading={rows === null || loading}
        serverMode
        searchable
        searchValue={searchInput}
        onSearchChange={setSearchInput}
        searchPlaceholder={t("search")}
        filters={filterDefs}
        filterValues={filters}
        onFilterChange={(key, value) =>
          setFilters((f) => ({ ...f, [key]: value }))
        }
        toolbarSlot={
          // Registration / last-login date ranges are a platform-audit tool;
          // scoped principals/directors don't get them.
          !isPlatform ? undefined : (
          <Popover>
            <PopoverTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                className={`h-9 gap-1.5 rounded-full ${activeDateCount > 0 ? "border-primary/40 bg-primary/10" : ""}`}
              >
                <CalendarRange className="size-3.5" />
                {t("filters.dates")}
                {activeDateCount > 0 && (
                  <Badge
                    variant="secondary"
                    className="px-1.5 py-0 text-xs font-normal"
                  >
                    {activeDateCount}
                  </Badge>
                )}
              </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-72 space-y-3">
              {(
                [
                  ["registered_from", t("filters.registeredFrom")],
                  ["registered_to", t("filters.registeredTo")],
                  ["last_login_from", t("filters.lastLoginFrom")],
                  ["last_login_to", t("filters.lastLoginTo")],
                ] as const
              ).map(([key, label]) => (
                <div key={key} className="space-y-1">
                  <label className="text-xs text-muted-foreground">
                    {label}
                  </label>
                  <DatePicker
                    value={dates[key]}
                    onChange={(value) =>
                      setDates((d) => ({ ...d, [key]: value }))
                    }
                    className="h-9 rounded-lg"
                  />
                </div>
              ))}
              {activeDateCount > 0 && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-7 w-full text-xs"
                  onClick={() => setDates(EMPTY_DATES)}
                >
                  {t("filters.clear")}
                </Button>
              )}
            </PopoverContent>
          </Popover>
          )
        }
        onSortChange={(key, dir) => setSort({ key: key ?? "created_at", dir })}
        onExport={handleExport}
        actions={rowActions.length > 0 ? rowActions : undefined}
        bulkActions={bulkActions.length > 0 ? bulkActions : undefined}
        onRowClick={canEdit || canOpenBranchSheet ? handleRowClick : undefined}
        emptyMessage={t("empty")}
        exportFilename="users"
        pagination={{
          page: meta.current_page,
          pageCount: meta.last_page,
          total: meta.total,
          from: rangeFrom,
          to: rangeTo,
          onPageChange: setPage,
          pageSize: perPage,
          onPageSizeChange: setPerPage,
        }}
      />

      {/* Edit / create sheet (platform) */}
      <UserSheet
        user={editUser}
        open={editOpen}
        onOpenChange={(v) => {
          setEditOpen(v)
          if (!v) setEditUser(null)
        }}
        onSaved={fetchUsers}
      />

      {/* Branch management sheet (principal / director) */}
      <ManageBranchesSheet
        user={manageUser}
        open={manageOpen}
        onOpenChange={(v) => {
          setManageOpen(v)
          if (!v) setManageUser(null)
        }}
        branchOptions={branchOptions}
        canAssign={canAssignBranch}
        onUpdated={fetchUsers}
      />

      {/* Branch access for a whole selection (principal / director) */}
      <BulkBranchSheet
        users={bulkBranchUsers}
        open={bulkBranchOpen}
        onOpenChange={(v) => {
          setBulkBranchOpen(v)
          if (!v) setBulkBranchUsers([])
        }}
        branchOptions={branchOptions}
        canAssign={canAssignBranch}
        canManageAccess={canManageBranchAccess}
        onDone={fetchUsers}
      />

      {/* Destructive confirmation */}
      <AlertDialog
        open={pending !== null}
        onOpenChange={(open) => !open && setPending(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{confirmTitle(pending)}</AlertDialogTitle>
            <AlertDialogDescription>
              {confirmDesc(pending)}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={working}>
              {t("confirm.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              loading={working}
              // Sending reset links and undeleting are not destructive — only
              // the status/delete actions wear the red button.
              variant={NON_DESTRUCTIVE.has(pending?.kind ?? "") ? "default" : "destructive"}
              onClick={(e) => {
                e.preventDefault()
                void runPending(pending)
              }}
            >
              {pending?.kind === "bulkReset"
                ? t("confirm.send")
                : pending?.kind === "restore" || pending?.kind === "bulkRestore"
                  ? t("actions.restore")
                  : t("confirm.confirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
