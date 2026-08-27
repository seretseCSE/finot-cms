"use client"

import { PenLine, ScanLine, StickyNote, UserCheck } from "lucide-react"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { BranchScopePicker, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import {
  DataTable,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { DatePicker } from "@/components/ui/date-picker"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { TimePicker } from "@/components/ui/time-picker"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  AttendanceRosterEntry,
  AttendanceStatus,
  Paginated,
  Section,
} from "@/lib/types"
import { cn } from "@/lib/utils"
import { addisToday } from "@/lib/dates"

const STATUSES: AttendanceStatus[] = ["present", "late", "absent", "excused"]

// Status conventions per DESIGN.md §3: green = present, amber = late,
// red = absent, blue = informational (excused).
const STATUS_STYLES: Record<AttendanceStatus, string> = {
  present: "bg-success text-white border-success",
  late: "bg-warning text-white border-warning",
  absent: "bg-destructive text-white border-destructive",
  excused: "bg-info text-white border-info",
}

function today(): string {
  // "Today" on the Addis wall clock — UTC is a day behind between 21:00 and
  // midnight local, and the register must line up with device scans.
  return addisToday()
}

function sectionLabel(section: Section): string {
  const grade = section.grade_level?.name ?? ""
  return grade ? `${grade} — ${section.name}` : section.name
}

export default function AttendancePage() {
  const { t } = useTranslation("attendance")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const [sections, setSections] = useState<Section[]>([])
  const [sectionId, setSectionId] = useState<string>("")
  const [date, setDate] = useState<string>(today())
  const [roster, setRoster] = useState<AttendanceRosterEntry[] | null>(null)
  const [saving, setSaving] = useState(false)
  // Unsaved local edits pause live refresh so a device sync never clobbers them.
  const dirtyRef = useRef(false)

  // Supervisory staff record anywhere in scope; teachers homeroom-only —
  // the ownership check itself is enforced server-side (ADR-010 lanes).
  const canRecord =
    permissions.includes("attendance.record") ||
    permissions.includes("attendance.record_own")
  // Teachers (own-lane only) get just their homeroom sections in the picker.
  const homeroomOnly = !permissions.includes("attendance.view")
  const hasBranch = active.branchId != null

  // School-wide workspace: the register is one-section-at-a-time, so school
  // managers first pick the branch whose sections to offer.
  const { needsBranch } = useBranchScope()
  const [pickedBranchId, setPickedBranchId] = useState<number | null>(null)
  const branchReady = hasBranch || (needsBranch && pickedBranchId != null)

  useEffect(() => {
    if (!branchReady) return
    let cancelled = false
    const params = new URLSearchParams()
    if (!hasBranch && pickedBranchId != null) params.set("branch_id", String(pickedBranchId))
    if (homeroomOnly) params.set("homeroom_only", "1")
    const query = params.toString()
    // eslint-disable-next-line react-hooks/set-state-in-effect -- stale section from the previous branch
    setSectionId("")
    apiFetch<Paginated<Section>>(`/sections${query ? `?${query}` : ""}`)
      .then((res) => !cancelled && setSections(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [branchReady, hasBranch, pickedBranchId, active.branchId, homeroomOnly])

  useEffect(() => {
    if (!sectionId || !date) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on section/date change
    setRoster(null)
    dirtyRef.current = false
    apiFetch<{ data: AttendanceRosterEntry[] }>(
      `/sections/${sectionId}/attendance?date=${date}`
    )
      .then((res) => !cancelled && setRoster(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(
          error instanceof ApiError ? error.message : t("loadFailed")
        )
        setRoster([])
      })
    return () => {
      cancelled = true
    }
  }, [sectionId, date, t])

  // Today's register refreshes itself so RFID-gate marks stream in while the
  // page is open — paused the moment the teacher has unsaved edits.
  const isToday = date === today()
  useEffect(() => {
    if (!sectionId || !isToday) return
    const id = setInterval(() => {
      if (dirtyRef.current) return
      apiFetch<{ data: AttendanceRosterEntry[] }>(
        `/sections/${sectionId}/attendance?date=${date}`
      )
        .then((res) => {
          if (!dirtyRef.current) setRoster(res.data)
        })
        .catch(() => {})
    }, 45000)
    return () => clearInterval(id)
  }, [sectionId, date, isToday])

  function patchEntry(studentId: number, patch: Partial<AttendanceRosterEntry>) {
    dirtyRef.current = true
    setRoster((prev) =>
      (prev ?? []).map((r) =>
        r.student_id === studentId ? { ...r, ...patch } : r
      )
    )
  }

  function markAllPresent() {
    dirtyRef.current = true
    setRoster((prev) =>
      (prev ?? []).map((r) => ({ ...r, status: r.status ?? "present" }))
    )
  }

  async function save() {
    const marked = (roster ?? []).filter((r) => r.status != null)
    if (marked.length === 0) return
    setSaving(true)
    try {
      await apiFetch(`/sections/${sectionId}/attendance`, {
        method: "POST",
        body: {
          date,
          records: marked.map((r) => ({
            student_id: r.student_id,
            status: r.status,
            check_in: r.check_in || undefined,
            check_out: r.check_out || undefined,
            note: r.note || undefined,
          })),
        },
      })
      dirtyRef.current = false
      toast.success(t("saved"))
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : t("loadFailed")
      )
    } finally {
      setSaving(false)
    }
  }

  const markedCount = (roster ?? []).filter((r) => r.status != null).length

  // DataTable rows: id + the scalar the status filter matches against.
  const rows = useMemo(
    () =>
      (roster ?? []).map((r) => ({
        ...r,
        id: r.student_id,
        status_key: r.status ?? "unmarked",
      })),
    [roster]
  )

  const columns: DataTableColumn<AttendanceRosterEntry>[] = [
    {
      key: "student_name",
      label: t("columns.student"),
      sortable: true,
      primary: true,
      render: (row) => (
        <span className="text-sm font-medium">{row.student_name}</span>
      ),
      exportValue: (row) => row.student_name,
    },
    {
      key: "status_key",
      label: t("columns.status"),
      sortable: true,
      render: (row) => (
        <div className="flex gap-1 whitespace-nowrap">
          {STATUSES.map((status) => (
            <button
              key={status}
              type="button"
              disabled={!canRecord}
              onClick={(e) => {
                e.stopPropagation()
                patchEntry(row.student_id, { status })
              }}
              className={cn(
                "pressable min-h-8 rounded-full border px-2.5 text-xs font-medium transition-colors disabled:opacity-60",
                row.status === status
                  ? STATUS_STYLES[status]
                  : "bg-background text-muted-foreground hover:bg-muted"
              )}
            >
              {t(`statuses.${status}`)}
            </button>
          ))}
        </div>
      ),
      exportValue: (row) =>
        row.status ? t(`statuses.${row.status}`) : t("unmarked"),
    },
    {
      key: "check_in",
      label: t("checkIn"),
      render: (row) => (
        <TimePicker
          value={row.check_in ?? ""}
          disabled={!canRecord || row.status == null}
          onClick={(e) => e.stopPropagation()}
          onChange={(value) =>
            patchEntry(row.student_id, { check_in: value || null })
          }
          className="h-9 w-40 text-sm"
        />
      ),
      exportValue: (row) => row.check_in ?? "",
    },
    {
      key: "check_out",
      label: t("checkOut"),
      render: (row) => (
        <TimePicker
          value={row.check_out ?? ""}
          disabled={!canRecord || row.status == null}
          onClick={(e) => e.stopPropagation()}
          onChange={(value) =>
            patchEntry(row.student_id, { check_out: value || null })
          }
          className="h-9 w-40 text-sm"
        />
      ),
      exportValue: (row) => row.check_out ?? "",
    },
    {
      key: "source",
      label: t("source.label"),
      mobileHidden: true,
      // Only saved marks carry a source — schools audit device vs manual here.
      render: (row) =>
        row.source ? (
          <span
            className={cn(
              "inline-flex items-center gap-1.5 text-xs whitespace-nowrap",
              row.source === "device" ? "text-info" : "text-muted-foreground"
            )}
          >
            {row.source === "device" ? (
              <ScanLine className="size-3.5" strokeWidth={1.75} />
            ) : (
              <PenLine className="size-3.5" strokeWidth={1.75} />
            )}
            {t(`source.${row.source}`)}
          </span>
        ) : (
          <span className="text-xs text-muted-foreground">—</span>
        ),
      exportValue: (row) => (row.source ? t(`source.${row.source}`) : ""),
    },
    {
      key: "note",
      label: t("note"),
      render: (row) => {
        const editable = canRecord && row.status != null

        return (
          <Popover>
            <PopoverTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                disabled={!editable && !row.note}
                aria-label={t("note")}
                onClick={(e) => e.stopPropagation()}
                className={cn(row.note ? "text-primary" : "text-muted-foreground")}
              >
                <StickyNote className="size-4" strokeWidth={1.75} />
              </Button>
            </PopoverTrigger>
            <PopoverContent
              align="end"
              className="w-72 space-y-2"
              onClick={(e) => e.stopPropagation()}
            >
              <p className="text-sm font-medium">
                {t("note")} — {row.student_name}
              </p>
              <textarea
                value={row.note ?? ""}
                readOnly={!editable}
                rows={3}
                autoFocus
                onChange={(e) =>
                  patchEntry(row.student_id, { note: e.target.value || null })
                }
                placeholder={t("notePlaceholder")}
                className="w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              />
            </PopoverContent>
          </Popover>
        )
      },
      exportValue: (row) => row.note ?? "",
    },
  ]

  const filterDefs: DataTableFilter[] = [
    {
      key: "status_key",
      label: tc("filters.status"),
      options: [
        ...STATUSES.map((s) => ({
          label: t(`statuses.${s}`),
          value: s,
        })),
        { label: t("unmarked"), value: "unmarked" },
      ],
    },
  ]

  return (
    <div className="space-y-6">
      {/* Section + date live in the header's action slot (top right on
          desktop, stacked full-width under the title on mobile). */}
      <PageHeader
        title={t("title")}
        description={t("subtitle")}
        actions={
          branchReady ? (
            <div className="flex w-full flex-wrap items-end gap-3 sm:w-auto sm:justify-end">
              <div className="min-w-0 flex-1 space-y-1.5 sm:w-60 sm:flex-none">
                <label className="text-sm font-medium">{t("section")}</label>
                <Select value={sectionId} onValueChange={setSectionId}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t("selectSection")} />
                  </SelectTrigger>
                  <SelectContent>
                    {sections.map((s) => (
                      <SelectItem key={s.id} value={String(s.id)}>
                        {sectionLabel(s)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="min-w-0 flex-1 space-y-1.5 sm:w-44 sm:flex-none">
                <label className="text-sm font-medium">{t("date")}</label>
                <DatePicker
                  value={date}
                  onChange={(value) => setDate(value)}
                  max={today()}
                  clearable={false}
                  className="w-full"
                />
              </div>
            </div>
          ) : undefined
        }
      />

      {/* School-wide: which branch's sections to offer. */}
      {needsBranch && (
        <div className="page-gutter">
          <BranchScopePicker value={pickedBranchId} onChange={setPickedBranchId} />
        </div>
      )}

      {!branchReady ? (
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("noBranch")}
          </div>
        </div>
      ) : (
        <>
          {/* Live indicator + marked summary + save actions */}
          {sectionId && roster !== null && roster.length > 0 && (
            <div className="page-gutter flex flex-wrap items-center justify-between gap-2 sm:justify-end">
              {isToday && (
                <span className="inline-flex items-center gap-1.5 text-xs text-success">
                  <span className="size-1.5 animate-pulse rounded-full bg-success" />
                  {t("live")}
                </span>
              )}
              <span className="text-sm text-muted-foreground tabular-nums">
                {t("summary", { marked: markedCount, total: roster.length })}
              </span>
              {canRecord && (
                <>
                  <Button variant="outline" size="sm" onClick={markAllPresent}>
                    {t("markAllPresent")}
                  </Button>
                  <Button
                    size="sm"
                    onClick={save}
                    loading={saving} disabled={markedCount === 0}
                  >
                    {t("save")}
                  </Button>
                </>
              )}
            </div>
          )}

          {!sectionId ? (
            <div className="page-gutter">
              <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
                {t("pickSection")}
              </div>
            </div>
          ) : roster !== null && roster.length === 0 ? (
            <div className="page-gutter">
              <div className="rounded-2xl border bg-card shadow-xs">
                <EmptyState icon={UserCheck} title={t("empty")} compact />
              </div>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={rows}
              loading={roster === null}
              searchKeys={["student_name"]}
              searchPlaceholder={tc("actions.search")}
              filters={filterDefs}
              emptyMessage={t("empty")}
              exportFilename={`attendance-${date}`}
            />
          )}
        </>
      )}
    </div>
  )
}
