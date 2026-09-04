"use client"

import { MessageCircleMore, Send } from "lucide-react"
import { useRouter } from "next/navigation"

import { useChatLauncher } from "@/components/chat/chat-launcher"
import { PortalAccountChip } from "@/components/students/portal-account-chip"
import { runBulk, useBulkConfirm } from "@/components/ui/bulk-actions"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import {
  DataTable,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type { ParentRow } from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"
import { useScopeFilters } from "@/lib/use-scope-filters"

export default function ParentsPage() {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const { t: tChat } = useTranslation("chat")
  const { confirmBulk, bulkDialog } = useBulkConfirm()
  // Re-sending a guardian's portal setup link is guardian-management authority
  // (re-checked per row against each guardian's own scopes server-side).
  const canManageGuardians = useEffectivePermissions().includes("guardians.manage")
  const { active, isPlatform } = useSchoolContext()
  const { openChat, available: chatAvailable } = useChatLauncher()
  const router = useRouter()

  const hasBranch = active.branchId != null
  const isGlobal = !hasBranch && (isPlatform || active.schoolId != null)

  const table = useServerTable<ParentRow>({
    endpoint: "/parents",
    exportEndpoint: "/parents/export",
    defaultSort: { key: "created_at", dir: "desc" },
    enabled: hasBranch || isGlobal,
    refreshKey: `${active.schoolId ?? ""}-${active.branchId ?? ""}`,
    loadFailedMessage: t("parents.loadFailed"),
  })

  const columns: DataTableColumn<ParentRow>[] = [
    {
      key: "first_name",
      label: t("columns.name"),
      sortable: true,
      primary: true,
      render: (row) => (
        <span className="flex items-center gap-2.5 font-medium">
          <PersonAvatar name={row.name ?? "?"} photoUrl={row.photo_url} />
          {row.name}
        </span>
      ),
      exportValue: (row) => row.name ?? "",
    },
    {
      key: "public_id",
      label: t("columns.publicId"),
      render: (row) => <CopyableId value={row.public_id} fallback="—" />,
      exportValue: (row) => row.public_id ?? "",
    },
    {
      key: "phone",
      label: t("guardians.phone"),
      render: (row) => (
        <ContactActionCell
          value={row.phone}
          name={row.name ?? ""}
          chat={{ kind: "parent", parentId: row.id, name: row.name }}
        />
      ),
      exportValue: (row) => row.phone ?? "",
    },
    {
      key: "email",
      label: t("fields.email"),
      mobileHidden: true,
      render: (row) => row.email ?? "—",
      exportValue: (row) => row.email ?? "",
    },
    {
      key: "children_count",
      label: t("parents.childrenCount"),
      sortable: true,
      render: (row) => row.children_count ?? 0,
      exportValue: (row) => String(row.children_count ?? 0),
    },
    {
      key: "occupation",
      label: t("wizard.occupation"),
      sortable: true,
      mobileHidden: true,
      render: (row) => row.occupation ?? "—",
      exportValue: (row) => row.occupation ?? "",
    },
    {
      // Portal login state ("can this parent sign in?"), not the dormant
      // is_verified KYC flag — that read "Unverified" for everyone and
      // answered a question nobody was asking.
      key: "account",
      label: t("columns.login"),
      render: (row) => <PortalAccountChip account={row.account} />,
      exportValue: (row) =>
        row.account == null || row.account.has_password === false
          ? t("detail.noLogin")
          : row.account.last_login_at == null
            ? t("detail.neverLoggedIn")
            : t("detail.active"),
    },
  ]

  const scopeFilters = useScopeFilters(table.filters)

  const filterDefs: DataTableFilter[] = [
    {
      key: "has_login",
      label: t("columns.login"),
      options: [
        { label: t("parents.hasLogin"), value: "true" },
        { label: t("detail.noLogin"), value: "false" },
      ],
    },
  ]

  return (
    <div className="space-y-6">
      <PageHeader title={t("parents.title")} description={t("parents.subtitle")} />

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
          searchPlaceholder={t("parents.searchPlaceholder")}
          filters={[...scopeFilters, ...filterDefs]}
          filterValues={table.filters}
          onFilterChange={table.setFilter}
          onSortChange={table.onSortChange}
          onExport={table.handleExport}
          onRowClick={(row) => router.push(`/parents/${row.id}`)}
          bulkActions={
            canManageGuardians
              ? [
                  {
                    // Onboarding a grade's families at once instead of tapping
                    // through one guardian at a time.
                    label: t("parents.bulk.invite"),
                    icon: Send,
                    onClick: (rows: ParentRow[]) =>
                      confirmBulk({
                        title: t("parents.bulk.inviteTitle", { count: rows.length }),
                        description: t("parents.bulk.inviteDesc"),
                        confirmLabel: t("parents.bulk.invite"),
                        action: async () => {
                          await runBulk({
                            url: "/parents/bulk/invite",
                            ids: rows.map((r) => r.id),
                            countKey: "sent",
                            success: (count) => t("parents.bulk.invited", { count }),
                            tc,
                          })
                        },
                      }),
                  },
                ]
              : undefined
          }
          actions={
            chatAvailable
              ? [
                  {
                    // Messaging a parent = the child's family thread; the
                    // launcher resolves (and asks) which child.
                    label: tChat("launcher.chatFamily"),
                    icon: MessageCircleMore,
                    onClick: (row: ParentRow) =>
                      void openChat({ kind: "parent", parentId: row.id, name: row.name }),
                    hidden: (row: ParentRow) => (row.children_count ?? 0) === 0,
                  },
                ]
              : undefined
          }
          emptyMessage={t("parents.empty")}
          exportFilename="parents"
          pagination={table.pagination}
        />
      )}
      {bulkDialog}
    </div>
  )
}
