"use client"

import { Copy, KeyRound, Pencil, Plus, Trash2 } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn, type DataTableFilter } from "@/components/ui/data-table"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { Device, DeviceAudience } from "@/lib/types"
import { cn } from "@/lib/utils"

import { ScopeSelects, type ScopeValue } from "./scope-selects"

const AUDIENCES: DeviceAudience[] = ["students", "employees", "both"]

interface DraftDevice {
  id?: number
  name: string
  location: string
  serial_no: string
  audience: DeviceAudience
  scope: ScopeValue
}

const EMPTY_DRAFT: DraftDevice = {
  name: "",
  location: "",
  serial_no: "",
  audience: "both",
  scope: { schoolId: null, branchId: null },
}

/** Relative "x min ago" without a library — coarse on purpose. */
function since(iso: string | null, t: (k: string, v?: Record<string, unknown>) => string): string {
  if (!iso) return t("device.neverSeen")
  const minutes = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000))
  if (minutes < 1) return t("device.justNow")
  if (minutes < 60) return t("device.minutesAgo", { count: minutes })
  const hours = Math.round(minutes / 60)
  if (hours < 48) return t("device.hoursAgo", { count: hours })
  return t("device.daysAgo", { count: Math.round(hours / 24) })
}

/**
 * The terminal fleet. Hardware is Temari.et territory: only platform staff
 * (devices.manage) register, edit, rotate tokens, toggle or remove devices —
 * schools see their fleet's health (online, scans today) read-only.
 */
export function DevicesTab() {
  const { t } = useTranslation("devices")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const canManage = permissions.includes("devices.manage")
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [devices, setDevices] = useState<Device[] | null>(null)
  const [draft, setDraft] = useState<DraftDevice | null>(null)
  const [saving, setSaving] = useState(false)
  const [token, setToken] = useState<{ name: string; token: string } | null>(null)
  // The kill-switch confirmation: which device, flipping to what.
  const [toggling, setToggling] = useState<{ device: Device; next: boolean } | null>(null)

  const load = useCallback(() => {
    let cancelled = false
    apiFetch<{ data: Device[] }>("/devices")
      .then((res) => !cancelled && setDevices(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setDevices([])
      })
    return () => {
      cancelled = true
    }
  }, [tc])

  useEffect(() => load(), [load, active.branchId, active.schoolId])

  async function save() {
    if (!draft) return
    setSaving(true)
    try {
      if (draft.id) {
        await apiFetch(`/devices/${draft.id}`, {
          method: "PATCH",
          body: {
            name: draft.name,
            location: draft.location || null,
            serial_no: draft.serial_no || null,
            audience: draft.audience,
          },
        })
        toast.success(t("device.updated"))
      } else {
        const res = await apiFetch<{ data: Device; meta: { token: string } }>("/devices", {
          method: "POST",
          body: {
            name: draft.name,
            location: draft.location || null,
            serial_no: draft.serial_no || null,
            audience: draft.audience,
            ...(draft.scope.branchId != null ? { branch_id: draft.scope.branchId } : {}),
          },
        })
        setToken({ name: res.data.name, token: res.meta.token })
      }
      setDraft(null)
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  async function rotate(device: Device) {
    try {
      const res = await apiFetch<{ meta: { token: string } }>(
        `/devices/${device.id}/rotate-token`,
        { method: "POST" },
      )
      setToken({ name: device.name, token: res.meta.token })
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  async function applyToggle() {
    if (!toggling) return
    try {
      await apiFetch(`/devices/${toggling.device.id}`, {
        method: "PATCH",
        body: { is_active: toggling.next },
      })
      toast.success(toggling.next ? t("device.enabledDone") : t("device.disabledDone"))
      setToggling(null)
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  function remove(device: Device) {
    confirmDelete(async () => {
      await apiFetch(`/devices/${device.id}`, { method: "DELETE" })
      toast.success(t("device.removed"))
      load()
    }, t("device.removeWarning", { name: device.name }))
  }

  const showScope = isPlatform || active.branchId == null

  const columns: DataTableColumn<Device>[] = [
    {
      key: "name",
      label: t("device.columns.device"),
      sortable: true,
      primary: true,
      render: (row) => (
        <div className="leading-tight">
          <span className="block text-sm font-medium">{row.name}</span>
          <span className="block text-xs text-muted-foreground">
            {[row.location, row.serial_no].filter(Boolean).join(" · ") || "—"}
          </span>
        </div>
      ),
      exportValue: (row) => row.name,
    },
    ...(showScope
      ? [
          {
            key: "scope_name",
            label: isPlatform ? tc("filters.school") : tc("filters.branch"),
            mobileHidden: true,
            render: (row: Device) => (
              <div className="text-xs leading-tight">
                {isPlatform && <span className="block">{row.school_name ?? "—"}</span>}
                <span className={cn("block", isPlatform && "text-muted-foreground")}>
                  {row.branch_name ?? "—"}
                </span>
              </div>
            ),
            exportValue: (row: Device) =>
              [row.school_name, row.branch_name].filter(Boolean).join(" · "),
          } satisfies DataTableColumn<Device>,
        ]
      : []),
    {
      key: "audience",
      label: t("device.columns.audience"),
      mobileHidden: true,
      render: (row) => (
        <Badge variant="secondary" className="text-[11px]">
          {t(`device.audiences.${row.audience}`)}
        </Badge>
      ),
      exportValue: (row) => t(`device.audiences.${row.audience}`),
    },
    {
      // Connectivity — telemetry from heartbeats, distinct from the
      // enabled/disabled kill-switch in the next column.
      key: "online",
      label: t("device.columns.status"),
      sortable: true,
      render: (row) => (
        <span className="flex items-center gap-2 text-xs whitespace-nowrap">
          <span className="relative flex size-2.5">
            {row.online && row.is_active && (
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-success opacity-60" />
            )}
            <span
              className={cn(
                "relative inline-flex size-2.5 rounded-full",
                row.online && row.is_active ? "bg-success" : "bg-muted-foreground/40",
              )}
            />
          </span>
          <span
            className={cn(
              row.online && row.is_active ? "text-success font-medium" : "text-muted-foreground",
            )}
          >
            {row.online && row.is_active
              ? t("device.online")
              : since(row.last_seen_at, t as (k: string, v?: Record<string, unknown>) => string)}
          </span>
        </span>
      ),
      exportValue: (row) => (row.online ? "online" : (row.last_seen_at ?? "never")),
    },
    {
      key: "is_active",
      label: t("device.columns.enabled"),
      render: (row) =>
        canManage ? (
          <Switch
            checked={row.is_active}
            onCheckedChange={(next) => setToggling({ device: row, next })}
            onClick={(e) => e.stopPropagation()}
            aria-label={t("device.columns.enabled")}
          />
        ) : row.is_active ? (
          <Badge className="border-transparent bg-success/10 text-[11px] text-success">
            {t("device.enabledShort")}
          </Badge>
        ) : (
          <Badge className="border-transparent bg-muted text-[11px] text-muted-foreground">
            {t("device.disabled")}
          </Badge>
        ),
      exportValue: (row) => (row.is_active ? t("device.enabledShort") : t("device.disabled")),
    },
    {
      key: "events_today",
      label: t("device.columns.scansToday"),
      sortable: true,
      render: (row) => (
        <span className="text-sm tabular-nums">
          {row.events_today}
          {row.pending_events > 0 && (
            <Badge className="ml-2 border-transparent bg-warning/10 text-[10px] text-warning">
              {t("device.pending", { count: row.pending_events })}
            </Badge>
          )}
        </span>
      ),
      exportValue: (row) => String(row.events_today),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            label: "",
            render: (row: Device) => (
              <div className="flex justify-end gap-1">
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label={tc("actions.edit")}
                  onClick={(e) => {
                    e.stopPropagation()
                    setDraft({
                      id: row.id,
                      name: row.name,
                      location: row.location ?? "",
                      serial_no: row.serial_no ?? "",
                      audience: row.audience,
                      scope: { schoolId: row.school_id, branchId: row.branch_id },
                    })
                  }}
                >
                  <Pencil className="size-4" strokeWidth={1.75} />
                </Button>
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label={t("device.rotate")}
                  title={t("device.rotate")}
                  onClick={(e) => {
                    e.stopPropagation()
                    rotate(row)
                  }}
                >
                  <KeyRound className="size-4" strokeWidth={1.75} />
                </Button>
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label={tc("actions.delete")}
                  className="text-destructive"
                  onClick={(e) => {
                    e.stopPropagation()
                    remove(row)
                  }}
                >
                  <Trash2 className="size-4" strokeWidth={1.75} />
                </Button>
              </div>
            ),
          } satisfies DataTableColumn<Device>,
        ]
      : []),
  ]

  // Client-side scope filters built from the loaded fleet (small by nature).
  const scopeFilterDefs: DataTableFilter[] = useMemo(() => {
    if (!showScope || !devices) return []
    const defs: DataTableFilter[] = []
    if (isPlatform) {
      const schools = [...new Set(devices.map((d) => d.school_name).filter(Boolean))] as string[]
      if (schools.length > 1) {
        defs.push({
          key: "school_name",
          label: tc("filters.school"),
          options: schools.map((s) => ({ label: s, value: s })),
        })
      }
    }
    const hasSchoolFilter = defs.some((d) => d.key === "school_name")
    const branches = [...new Set(devices.map((d) => d.branch_name).filter(Boolean))] as string[]
    if (branches.length > 1) {
      // For platform staff the branch step cascades from the school filter.
      defs.push({
        key: "branch_name",
        label: tc("filters.branch"),
        ...(hasSchoolFilter
          ? {
              dependsOn: "school_name",
              options: (schoolValue: string) => {
                const schools = schoolValue.split(",").filter(Boolean)
                const inSchool = devices.filter((d) =>
                  schools.includes(d.school_name ?? "")
                )
                return [...new Set(inSchool.map((d) => d.branch_name).filter(Boolean))].map(
                  (b) => ({ label: b as string, value: b as string })
                )
              },
            }
          : { options: branches.map((b) => ({ label: b, value: b })) }),
      })
    }
    return defs
  }, [showScope, isPlatform, devices, tc])

  const filterDefs: DataTableFilter[] = [
    ...scopeFilterDefs,
    {
      key: "audience",
      label: t("device.columns.audience"),
      options: AUDIENCES.map((a) => ({ label: t(`device.audiences.${a}`), value: a })),
    },
  ]

  return (
    <>
      {canManage && (
        <div className="page-gutter flex justify-end">
          <Button size="sm" onClick={() => setDraft({ ...EMPTY_DRAFT })}>
            <Plus className="size-4" /> {t("device.add")}
          </Button>
        </div>
      )}

      <DataTable
        columns={columns}
        data={(devices ?? []).map((d) => ({ ...d }))}
        loading={devices === null}
        searchKeys={["name", "location", "serial_no", "school_name", "branch_name"]}
        searchPlaceholder={tc("actions.search")}
        filters={filterDefs}
        emptyMessage={t("device.empty")}
        exportFilename="devices"
      />

      {/* Create / edit sheet — platform staff only */}
      <ResponsiveSheet open={draft !== null} onOpenChange={(open) => !open && setDraft(null)}>
        <ResponsiveSheetContent className="data-[side=right]:sm:max-w-md">
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>
              {draft?.id ? t("device.edit") : t("device.add")}
            </ResponsiveSheetTitle>
            <ResponsiveSheetDescription>{t("device.sheetHint")}</ResponsiveSheetDescription>
          </ResponsiveSheetHeader>
          {draft && (
            <ResponsiveSheetBody className="space-y-4">
              {!draft.id && (
                <ScopeSelects
                  value={draft.scope}
                  onChange={(scope) => setDraft({ ...draft, scope })}
                  required
                  layout="stack"
                />
              )}
              <div className="space-y-2">
                <Label>
                  {t("device.fields.name")} <span className="text-destructive">*</span>
                </Label>
                <Input
                  value={draft.name}
                  onChange={(e) => setDraft({ ...draft, name: e.target.value })}
                  placeholder={t("device.fields.namePlaceholder")}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("device.fields.location")}</Label>
                <Input
                  value={draft.location}
                  onChange={(e) => setDraft({ ...draft, location: e.target.value })}
                  placeholder={t("device.fields.locationPlaceholder")}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("device.fields.serial")}</Label>
                <Input
                  value={draft.serial_no}
                  onChange={(e) => setDraft({ ...draft, serial_no: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("device.fields.audience")}</Label>
                <Select
                  value={draft.audience}
                  onValueChange={(v) => setDraft({ ...draft, audience: v as DeviceAudience })}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {AUDIENCES.map((a) => (
                      <SelectItem key={a} value={a}>
                        {t(`device.audiences.${a}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">{t("device.fields.audienceHelp")}</p>
              </div>
            </ResponsiveSheetBody>
          )}
          <ResponsiveSheetFooter>
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setDraft(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              onClick={save}
              loading={saving} disabled={!draft?.name.trim() || (!draft?.id && draft?.scope.branchId == null)}
            >
              {draft?.id ? tc("actions.save") : t("device.register")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>

      {/* Token reveal — shown exactly once */}
      <Dialog open={token !== null} onOpenChange={(open) => !open && setToken(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t("device.tokenTitle", { name: token?.name ?? "" })}</DialogTitle>
            <DialogDescription>{t("device.tokenHint")}</DialogDescription>
          </DialogHeader>
          <button
            type="button"
            onClick={() => {
              if (!token) return
              navigator.clipboard.writeText(token.token)
              toast.success(tc("actions.copied"))
            }}
            className="flex w-full items-center justify-between gap-2 rounded-xl border bg-muted/30 px-3.5 py-3 text-left font-mono text-sm break-all hover:bg-muted/60"
          >
            {token?.token}
            <Copy className="size-4 shrink-0 text-muted-foreground" />
          </button>
          <DialogFooter>
            <Button className="h-11 w-full" onClick={() => setToken(null)}>
              {t("device.tokenDone")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Kill-switch confirmation — disabling interrupts a school's gate */}
      <Dialog open={toggling !== null} onOpenChange={(open) => !open && setToggling(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {toggling?.next
                ? t("device.enableTitle", { name: toggling?.device.name ?? "" })
                : t("device.disableTitle", { name: toggling?.device.name ?? "" })}
            </DialogTitle>
            <DialogDescription>
              {toggling?.next ? t("device.enableHint") : t("device.disableHint")}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setToggling(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              variant={toggling?.next ? "default" : "destructive"}
              onClick={applyToggle}
            >
              {tc("actions.confirm")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {confirmDialog}
    </>
  )
}
