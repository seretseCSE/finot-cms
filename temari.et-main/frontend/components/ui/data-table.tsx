"use client"

import * as React from "react"
import {
  ArrowUpDown,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  Download,
  MoreHorizontal,
  Rows3,
  Search,
  X,
} from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerTitle,
  DrawerTrigger,
} from "@/components/ui/drawer"
import { Input } from "@/components/ui/input"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { EmptyState } from "@/components/ui/empty-state"
import { useTranslation } from "@/lib/i18n"
import {
  DEFAULT_PAGE_SIZE,
  PAGE_SIZE_OPTIONS,
  usePageSize,
} from "@/lib/use-page-size"
import { matchesSearch } from "@/lib/search"
import { cn } from "@/lib/utils"

// ─── Column definition ─────────────────────────────────────────────────────

export interface DataTableColumn<T> {
  /** Unique key for this column */
  key: string
  /** Header label */
  label: string
  /** Custom cell renderer. Defaults to `String(row[key as keyof T])` */
  render?: (row: T) => React.ReactNode
  /**
   * Whether this column is sortable. Client mode: every labelled column is
   * sortable by default — set `false` to opt out (e.g. composed cells with no
   * meaningful order). Server mode: opt-in only, and the key must be in the
   * endpoint's `applySort` whitelist.
   */
  sortable?: boolean
  /** Client-mode sort value for computed/nested columns (numbers sort
   *  numerically, strings via locale compare). Defaults to the flat row key. */
  sortValue?: (row: T) => string | number | null | undefined
  /** Value extractor for export (plain text). Defaults to `String(row[key as keyof T])` */
  exportValue?: (row: T) => string
  /** Extra className on <th> and <td> */
  className?: string
  /**
   * On mobile (card layout) this column is the card title shown prominently.
   * Defaults to the first column when no column opts in.
   */
  primary?: boolean
  /** Hide this column from the mobile card layout (still shown on desktop). */
  mobileHidden?: boolean
  /**
   * On mobile, render this field as a full-width block (label above, value
   * below, left-aligned) instead of the compact label-left / value-right row.
   * Use it for rich, multi-line cells (trees, chip stacks) that don't belong on
   * a single line. Scalar cells (text, a badge, a date) should stay compact.
   */
  mobileBlock?: boolean
}

// ─── Filter definition ─────────────────────────────────────────────────────

export interface DataTableFilterOption {
  label: string
  value: string
}

export interface DataTableFilter {
  /** Must match one of the column keys (or a custom data key) */
  key: string
  /** Filter label */
  label: string
  /** Available filter options. With `dependsOn`, may be a function of the
   *  parent filter's current value (comma-joined for multi-select). */
  options:
    | DataTableFilterOption[]
    | ((parentValue: string) => DataTableFilterOption[])
  /** Key of the filter this one cascades from (e.g. section → grade,
   *  branch → school). The filter stays hidden until the parent has a value
   *  and is cleared automatically whenever the parent changes. */
  dependsOn?: string
  /** The page applies this filter itself by refetching with the value as a
   *  query param (e.g. the school/branch scope narrowing on a client-mode
   *  table) — client-side row matching skips it. Ignored in server mode. */
  serverOnly?: boolean
}

// ─── Export options (passed to `onExport` from the export dialog) ────────────

export interface DataTableExportOptions<T> {
  format: "csv" | "excel"
  /** Filename without extension, as entered by the user. */
  filename: string
  /** Columns the user chose to include. */
  columns: DataTableColumn<T>[]
  /** Which rows to export: everything, or only the checked rows. Selected
   *  rows are always exported client-side (they are loaded by definition);
   *  "all" delegates to `onExport` in server mode. */
  rows: "all" | "selected"
}

// ─── Row action definition ──────────────────────────────────────────────────

export interface DataTableAction<T> {
  label: string
  onClick: (row: T) => void
  destructive?: boolean
  hidden?: (row: T) => boolean
  /**
   * When provided, this action is rendered as an inline icon button in the row
   * instead of inside the overflow (kebab) menu. The `label` becomes its tooltip.
   */
  icon?: React.ComponentType<{ className?: string }>
  /** Disable the action for a given row (e.g. while a request is in flight). */
  disabled?: (row: T) => boolean
  /**
   * Marks the row's MAIN action: clicking anywhere on the row triggers it
   * (view-first convention — prefer the detail view, else edit). At most one
   * action should be primary; an explicit `onRowClick` prop wins over it.
   */
  primary?: boolean
}

// ─── Bulk action definition ──────────────────────────────────────────────────

export interface DataTableBulkAction<T> {
  label: string
  onClick: (rows: T[]) => void
  icon?: React.ComponentType<{ className?: string }>
  destructive?: boolean
}

// ─── Server-side pagination ───────────────────────────────────────────────────

export interface DataTablePagination {
  page: number
  pageCount: number
  total: number
  from: number
  to: number
  onPageChange: (page: number) => void
  /** Rows fetched per page. Omit to hide the rows-per-page picker. */
  pageSize?: number
  /** Called when the user picks a different page size (`useServerTable` wires
   *  this to the shared preference and refetches from page 1). */
  onPageSizeChange?: (size: number) => void
}

// ─── Main props ─────────────────────────────────────────────────────────────

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[]
  data: T[]
  /** Total record count (for display). Defaults to `data.length`. */
  total?: number
  /** Keys to include in client-side text search */
  searchKeys?: (keyof T)[]
  searchPlaceholder?: string
  /** Filter definitions */
  filters?: DataTableFilter[]
  /** Per-row action items */
  actions?: DataTableAction<T>[]
  /** Click handler for a row */
  onRowClick?: (row: T) => void
  /** Informational summary line shown above the table */
  summary?: React.ReactNode
  /** Show skeleton loading state */
  loading?: boolean
  /** Empty state message */
  emptyMessage?: string
  /** Base filename for exports (no extension) */
  exportFilename?: string
  /** Tighter row padding — for long registers where scroll length matters. */
  dense?: boolean
  /** Extra classes per row (desktop rows + mobile cards) — e.g. status tints. */
  rowClassName?: (row: T) => string | undefined
  className?: string

  // ── Server-driven mode (opt-in; leaves client mode untouched) ──────────────
  /** When true, search/filter/sort are NOT applied client-side — `data` renders
   *  as-is and the callbacks below fire so the parent can query the server. */
  serverMode?: boolean
  /** Show the search input even without client-side searchKeys (server mode). */
  searchable?: boolean
  /** Seed for the CLIENT-mode search box (e.g. a `?q=` palette deep-link). */
  initialSearch?: string
  /** Controlled search value (server mode). */
  searchValue?: string
  onSearchChange?: (query: string) => void
  /** Controlled active filters (works in BOTH modes — pass with onFilterChange
   *  to preselect defaults in client mode). Keyed by filter key. Multi-select
   *  values are comma-separated (e.g. `"active,inactive"`). */
  filterValues?: Record<string, string>
  onFilterChange?: (key: string, value: string) => void
  onSortChange?: (key: string | null, dir: "asc" | "desc") => void
  /** When provided, the export dialog delegates to this instead of exporting the
   *  loaded rows — used to export the full server-side filtered result. */
  onExport?: (options: DataTableExportOptions<T>) => void | Promise<void>
  /** Extra controls rendered inline with the filter buttons (e.g. date range). */
  toolbarSlot?: React.ReactNode
  /** Server pagination controls, rendered as a footer. */
  pagination?: DataTablePagination

  // ── Client-mode paging (automatic; both knobs are escape hatches) ──────────
  /** Rows shown per page in CLIENT mode until the user picks a size of their
   *  own. Defaults to 25 — the platform default. Ignored in server mode, where
   *  `pagination` carries the size. */
  defaultPageSize?: number
  /** Set false for a table that must render every row at once (a sheet's short
   *  read-only list, a block that is measured or printed as a whole). */
  paginated?: boolean
  /** Bulk actions shown in a floating bar when rows are selected. */
  bulkActions?: DataTableBulkAction<T>[]

  /** Group rows under full-width header rows. Rows are grouped in DISPLAY
   *  order (group your data before passing it) — a header renders whenever
   *  the key changes between consecutive rows. Works with search/filters. */
  groupBy?: (row: T) => string
  /** Custom header content per group (defaults to the group key). */
  renderGroupHeader?: (key: string, rows: T[]) => React.ReactNode
}

// ─── Helpers ────────────────────────────────────────────────────────────────

/** Shared empty default — a fresh `[]` per render would invalidate every
 *  filter/search/sort memo downstream on every keystroke. */
const NO_FILTERS: DataTableFilter[] = []

function getCellValue<T>(row: T, col: DataTableColumn<T>): string {
  if (col.exportValue) return col.exportValue(row)
  const v = row[col.key as keyof T]
  return v == null ? "" : String(v)
}

export function exportCSV<T>(
  columns: DataTableColumn<T>[],
  rows: T[],
  filename: string
) {
  const headers = columns.map((c) => `"${c.label.replace(/"/g, '""')}"`)
  const body = rows.map((row) =>
    columns
      .map((c) => `"${getCellValue(row, c).replace(/"/g, '""')}"`)
      .join(",")
  )
  // UTF-8 BOM for Excel Amharic support
  const content = "﻿" + [headers.join(","), ...body].join("\r\n")
  download(content, `${filename}.csv`, "text/csv;charset=utf-8")
}

export function exportExcel<T>(
  columns: DataTableColumn<T>[],
  rows: T[],
  filename: string
) {
  const th = columns.map((c) => `<th>${esc(c.label)}</th>`).join("")
  const trs = rows
    .map(
      (row) =>
        `<tr>${columns.map((c) => `<td>${esc(getCellValue(row, c))}</td>`).join("")}</tr>`
    )
    .join("")
  const html = `<table><thead><tr>${th}</tr></thead><tbody>${trs}</tbody></table>`
  download(html, `${filename}.xls`, "application/vnd.ms-excel;charset=utf-8")
}

const esc = (s: string) =>
  s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")

function download(content: string, filename: string, mimeType: string) {
  const blob = new Blob([content], { type: mimeType })
  const url = URL.createObjectURL(blob)
  const a = document.createElement("a")
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

/** 1 … (page-1) page (page+1) … count — the classic windowed pager. */
function pageItems(page: number, count: number): (number | "gap")[] {
  if (count <= 7) return Array.from({ length: count }, (_, i) => i + 1)
  const items: (number | "gap")[] = [1]
  const start = Math.max(2, page - 1)
  const end = Math.min(count - 1, page + 1)
  if (start > 2) items.push("gap")
  for (let p = start; p <= end; p++) items.push(p)
  if (end < count - 1) items.push("gap")
  items.push(count)
  return items
}

// ─── Component ───────────────────────────────────────────────────────────────

export function DataTable<T extends { id?: number | string }>({
  columns,
  data,
  total,
  searchKeys,
  searchPlaceholder = "Search…",
  filters = NO_FILTERS,
  actions,
  onRowClick,
  summary,
  loading = false,
  emptyMessage = "No records found.",
  exportFilename = "export",
  dense = false,
  rowClassName,
  className,
  serverMode = false,
  searchable = false,
  initialSearch,
  searchValue,
  onSearchChange,
  filterValues,
  onFilterChange,
  onSortChange,
  onExport,
  toolbarSlot,
  pagination,
  defaultPageSize = DEFAULT_PAGE_SIZE,
  paginated = true,
  bulkActions,
  groupBy,
  renderGroupHeader,
}: DataTableProps<T>) {
  const { t } = useTranslation("common")
  const [exportOpen, setExportOpen] = React.useState(false)
  const [searchInternal, setSearchInternal] = React.useState(initialSearch ?? "")
  const search = serverMode && searchValue !== undefined ? searchValue : searchInternal
  const [filtersInternal, setFiltersInternal] = React.useState<
    Record<string, string>
  >({})
  const activeFilters = filterValues !== undefined ? filterValues : filtersInternal
  const [sortKey, setSortKey] = React.useState<string | null>(null)
  const [sortDir, setSortDir] = React.useState<"asc" | "desc">("asc")
  const [selected, setSelected] = React.useState<Set<number>>(new Set())
  // Mobile selection is opt-in (like iOS Mail): checkboxes stay hidden until the
  // user taps "Select". Desktop always shows the checkbox column. Selection is
  // only offered on mobile when there's something to do with it (bulk actions).
  const selectable = !!bulkActions?.length
  const [selectionMode, setSelectionMode] = React.useState(false)

  function setSearch(value: string) {
    if (serverMode) onSearchChange?.(value)
    else setSearchInternal(value)
  }

  function applyFilter(key: string, value: string) {
    if (onFilterChange) onFilterChange(key, value)
    else if (!serverMode) setActiveFiltersInternal(key, value)
  }

  function setFilter(key: string, value: string) {
    applyFilter(key, value)
    clearDependents(key)
  }

  /** Cascading children reset whenever their parent filter changes. */
  function clearDependents(parentKey: string) {
    for (const f of filters) {
      if (f.dependsOn === parentKey) {
        if (activeFilters[f.key]) applyFilter(f.key, "")
        clearDependents(f.key)
      }
    }
  }

  function setActiveFiltersInternal(key: string, value: string) {
    setFiltersInternal((p) => ({ ...p, [key]: value }))
  }

  // ── Search (client mode only) ───────────────────────────────────────────────
  // Word by word across all searchKeys at once — see lib/search.ts for why a
  // whole-phrase match can never find a full Ethiopian name.
  const searched = React.useMemo(() => {
    if (serverMode) return data
    if (!search.trim() || !searchKeys?.length) return data
    return data.filter((row) => matchesSearch(searchKeys.map((k) => row[k]), search))
  }, [data, search, searchKeys, serverMode])

  // ── Filter (client mode only) ───────────────────────────────────────────────
  const serverOnlyKeys = React.useMemo(
    () => new Set(filters.filter((f) => f.serverOnly).map((f) => f.key)),
    [filters]
  )
  const filtered = React.useMemo(() => {
    if (serverMode) return searched
    return searched.filter((row) =>
      Object.entries(activeFilters).every(([key, val]) => {
        if (!val || serverOnlyKeys.has(key)) return true
        const selected = val.split(",")
        const rowVal = row[key as keyof T]
        // Array fields (e.g. a staff member's job_titles) match when ANY
        // element is selected.
        if (Array.isArray(rowVal)) {
          return rowVal.some((v) => selected.includes(String(v)))
        }
        return selected.includes(String(rowVal ?? ""))
      })
    )
  }, [searched, activeFilters, serverMode, serverOnlyKeys])

  // ── Sort ────────────────────────────────────────────────────────────────────
  // Client mode: every labelled column sorts unless it opts out. Server mode
  // stays opt-in — the key must be in the endpoint's sort whitelist.
  const isSortable = React.useCallback(
    (col: DataTableColumn<T>) =>
      col.sortable ?? (!serverMode && !!col.label),
    [serverMode]
  )
  const sortableColumns = React.useMemo(
    () => columns.filter(isSortable),
    [columns, isSortable]
  )

  const sorted = React.useMemo(() => {
    if (serverMode || !sortKey) return filtered
    const col = columns.find((c) => c.key === sortKey)
    const valueOf = (row: T): string | number | null | undefined =>
      col?.sortValue
        ? col.sortValue(row)
        : (row[sortKey as keyof T] as string | number | null | undefined)
    return [...filtered].sort((a, b) => {
      const av = valueOf(a)
      const bv = valueOf(b)
      // Empty values always sink to the bottom, whatever the direction.
      const aEmpty = av == null || av === ""
      const bEmpty = bv == null || bv === ""
      if (aEmpty || bEmpty) return aEmpty === bEmpty ? 0 : aEmpty ? 1 : -1
      const cmp =
        typeof av === "number" && typeof bv === "number"
          ? av - bv
          : String(av).localeCompare(String(bv), undefined, {
              numeric: true,
              sensitivity: "base",
            })
      return sortDir === "asc" ? cmp : -cmp
    })
  }, [filtered, sortKey, sortDir, serverMode, columns])

  // ── Client-mode paging ──────────────────────────────────────────────────────
  // Server mode never slices here — the API already returned exactly one page.
  // In client mode the whole result set is in hand, so only the current slice
  // is rendered: a 900-row register costs 25 rows of DOM, not 900.
  const [pageSize, setPageSize] = usePageSize(defaultPageSize)
  const [clientPage, setClientPage] = React.useState(1)
  const clientPaged = !serverMode && !pagination && paginated
  const clientPageCount = clientPaged
    ? Math.max(1, Math.ceil(sorted.length / pageSize))
    : 1
  // Clamped rather than corrected in an effect, so deleting the last row of the
  // last page falls back a page instead of flashing an empty table.
  const currentPage = Math.min(clientPage, clientPageCount)
  const visible = React.useMemo(
    () =>
      clientPaged
        ? sorted.slice((currentPage - 1) * pageSize, currentPage * pageSize)
        : sorted,
    [clientPaged, sorted, currentPage, pageSize]
  )

  // What the user asked to see, as a value. Row arrays are re-derived on every
  // render (callers pass fresh `filterValues` / column literals), so the two
  // effects below key off this signature instead of array identity — keying off
  // `sorted` would bounce a user on page 3 back to page 1 on every re-render.
  const queryKey = `${search}|${sortKey}|${sortDir}|${JSON.stringify(activeFilters)}`

  // Any new query — or a fresh result set — lands back on page 1.
  React.useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- the result set changed; the old page number no longer means anything
    setClientPage(1)
  }, [queryKey, data])

  // Selection is index-based; clear it whenever the rendered rows change
  // (paging, refiltering, re-sorting) so a tick never lands on another row.
  React.useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale selection when rows change
    setSelected(new Set())
  }, [queryKey, data, currentPage, pageSize])

  function toggleSort(key: string) {
    const nextDir: "asc" | "desc" = sortKey === key && sortDir === "asc" ? "desc" : "asc"
    setSortKey(key)
    setSortDir(nextDir)
    onSortChange?.(key, nextDir)
  }

  function setSortState(key: string | null, dir: "asc" | "desc") {
    setSortKey(key)
    setSortDir(dir)
    onSortChange?.(key, dir)
  }

  // ── Selection ─────────────────────────────────────────────────────────────
  // Indices point into `visible` — what the user can actually see and tick.
  // "Select all" therefore means "everything on this page", the same promise
  // the header checkbox makes in server mode.
  const allChecked = visible.length > 0 && selected.size === visible.length
  const someChecked = selected.size > 0 && selected.size < visible.length
  // Checkboxes stay hidden until the pointer is over a row or a selection has
  // started — the header checkbox is the always-visible entry point.

  function toggleAll() {
    if (allChecked) {
      setSelected(new Set())
    } else {
      setSelected(new Set(visible.map((_, i) => i)))
    }
  }

  function toggleRow(index: number) {
    setSelected((prev) => {
      const next = new Set(prev)
      if (next.has(index)) next.delete(index)
      else next.add(index)
      return next
    })
  }

  // ── Filter helpers ────────────────────────────────────────────────────────
  const activeFilterCount = Object.values(activeFilters).filter(Boolean).length

  function clearFilter(key: string) {
    if (onFilterChange) {
      onFilterChange(key, "")
    } else if (!serverMode) {
      setFiltersInternal((p) => {
        const n = { ...p }
        delete n[key]
        return n
      })
    }
  }

  function clearAllFilters() {
    if (onFilterChange) {
      Object.keys(activeFilters).forEach((key) => onFilterChange(key, ""))
    } else if (!serverMode) {
      setFiltersInternal({})
    }
  }

  const displayTotal = pagination?.total ?? total ?? data.length
  const cellPad = dense ? "py-1.5" : "py-3.5"

  // ── Footer descriptor ──────────────────────────────────────────────────────
  // One shape for both modes: the page's own server pagination, or the client
  // slice computed above. It appears as soon as there is more to see than the
  // smallest page size — a 40-row list on 100 rows/page still offers the picker,
  // a 12-row list stays footer-free.
  const footer: DataTablePagination | null = pagination
    ? pagination
    : clientPaged
      ? {
          page: currentPage,
          pageCount: clientPageCount,
          total: sorted.length,
          from: sorted.length === 0 ? 0 : (currentPage - 1) * pageSize + 1,
          to: Math.min(currentPage * pageSize, sorted.length),
          onPageChange: setClientPage,
          pageSize,
          onPageSizeChange: setPageSize,
        }
      : null
  const showFooter =
    !!footer && (footer.pageCount > 1 || footer.total > PAGE_SIZE_OPTIONS[0])

  // ── Row click: explicit handler wins, else the action marked `primary` ─────
  const primaryAction = actions?.find((a) => a.primary)
  const rowClick =
    onRowClick ??
    (primaryAction
      ? (row: T) => {
          if (primaryAction.hidden?.(row) || primaryAction.disabled?.(row)) return
          primaryAction.onClick(row)
        }
      : undefined)

  // ── Sticky actions column: pinned to the right edge of the scroll container;
  //    a soft edge shadow appears only while columns are hidden beneath it. ────
  const desktopRef = React.useRef<HTMLDivElement>(null)
  const hasActions = !!actions?.length
  const [actionsPinned, setActionsPinned] = React.useState(false)

  React.useEffect(() => {
    if (!hasActions) return
    const container = desktopRef.current?.querySelector<HTMLElement>(
      '[data-slot="table-container"]'
    )
    if (!container) return
    const update = () =>
      setActionsPinned(
        container.scrollLeft + container.clientWidth < container.scrollWidth - 1
      )
    update()
    container.addEventListener("scroll", update, { passive: true })
    const observer = new ResizeObserver(update)
    observer.observe(container)
    return () => {
      container.removeEventListener("scroll", update)
      observer.disconnect()
    }
  }, [hasActions, data, loading])

  // Sticky cells need opaque backgrounds that mirror the row states they float
  // over (plain, hover, selected) — mixed against the card surface.
  const stickyHead =
    "sticky right-0 z-10 bg-[color-mix(in_oklab,var(--color-muted)_40%,var(--color-card))]"
  const stickyCell =
    "sticky right-0 z-[1] bg-card transition-colors group-hover/row:bg-[color-mix(in_oklab,var(--color-accent)_40%,var(--color-card))] group-data-[state=selected]/row:bg-[color-mix(in_oklab,var(--color-primary)_5%,var(--color-card))]"
  const stickyShadow =
    "after:pointer-events-none after:absolute after:inset-y-0 after:-left-4 after:w-4 after:bg-gradient-to-l after:from-foreground/10 after:to-transparent"

  // ── Mobile card layout: pick a title column + the rest as label/value pairs ─
  const primaryCol = columns.find((c) => c.primary) ?? columns[0]
  const secondaryCols = columns.filter(
    (c) => c !== primaryCol && !c.mobileHidden
  )
  const cellText = (row: T, col: DataTableColumn<T>) =>
    col.render ? col.render(row) : String(row[col.key as keyof T] ?? "—")

  // ── Render ────────────────────────────────────────────────────────────────
  return (
    <div className={cn("px-4 md:px-8", className)}>
      <div className="bg-card overflow-hidden rounded-2xl border shadow-xs">
        {/* ── Toolbar: pill search + pill filters + count + export ── */}
        <div className="flex flex-wrap items-center gap-2 border-b px-3 py-3 md:px-4">
          {searchKeys?.length || searchable ? (
            <div className="relative w-full min-w-52 flex-1 md:w-auto md:max-w-xs">
              <Search className="text-muted-foreground pointer-events-none absolute left-3.5 top-1/2 size-3.5 -translate-y-1/2" />
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={searchPlaceholder}
                className="bg-muted/60 h-9 rounded-full border-0 pl-9 text-sm focus-visible:ring-2 focus-visible:ring-primary/30"
              />
            </div>
          ) : null}

          {/* Filters — one checkbox dropdown per filter, listed horizontally.
              Cascading filters stay hidden until their parent has a value. */}
          {filters.map((f) => {
            const parentValue = f.dependsOn ? (activeFilters[f.dependsOn] ?? "") : ""
            if (f.dependsOn && !parentValue) return null
            const options =
              typeof f.options === "function" ? f.options(parentValue) : f.options
            return (
              <FilterButton
                key={f.key}
                filter={f}
                options={options}
                value={activeFilters[f.key] ?? ""}
                onChange={(v) => setFilter(f.key, v)}
                onClear={() => {
                  clearFilter(f.key)
                  clearDependents(f.key)
                }}
              />
            )
          })}

          {/* Mobile sort — the card layout has no column headers, so sorting
              gets its own pill (hidden on desktop where headers do the job). */}
          {sortableColumns.length > 0 && (
            <SortButton
              columns={sortableColumns}
              sortKey={sortKey}
              sortDir={sortDir}
              onSort={setSortState}
              className="md:hidden"
            />
          )}

          {/* Mobile multi-select toggle — reveals the per-row checkboxes. */}
          {selectable && (
            <Button
              variant="outline"
              size="sm"
              className={cn(
                "h-9 rounded-full md:hidden",
                selectionMode && "border-primary/40 bg-primary/10 hover:bg-primary/15"
              )}
              onClick={() =>
                setSelectionMode((m) => {
                  if (m) setSelected(new Set())
                  return !m
                })
              }
            >
              {selectionMode ? t("dataTable.done") : t("dataTable.select")}
            </Button>
          )}

          {toolbarSlot}

          {activeFilterCount > 1 && (
            <Button
              variant="ghost"
              size="sm"
              className="text-muted-foreground h-9 rounded-full text-xs"
              onClick={clearAllFilters}
            >
              {t("dataTable.clearAll")}
            </Button>
          )}

          <div className="flex-1" />

          {/* Record count */}
          <span className="bg-muted text-muted-foreground hidden rounded-full px-3 py-1.5 text-xs font-medium tabular-nums sm:inline-block">
            {sorted.length !== displayTotal
              ? t("dataTable.recordsOf", { shown: sorted.length, total: displayTotal })
              : t(displayTotal === 1 ? "dataTable.record" : "dataTable.records", { count: displayTotal })}
          </span>

          {/* Export */}
          <Button
            variant="outline"
            size="sm"
            className="h-9 gap-1.5 rounded-full"
            onClick={() => setExportOpen(true)}
          >
            <Download className="size-3.5" />
            <span className="hidden sm:inline">{t("dataTable.export")}</span>
          </Button>
        </div>

        <ExportDialog
          open={exportOpen}
          onOpenChange={setExportOpen}
          // Label-less columns are action/icon columns — they have nothing to
          // export and would render as unlabelled checkboxes in the dialog.
          columns={columns.filter((col) => col.label)}
          defaultFilename={`${exportFilename}-${new Date().toISOString().slice(0, 10)}`}
          total={displayTotal}
          selectedCount={selected.size}
          onConfirm={async (options) => {
            // Checked rows are in hand — export them directly in both modes.
            if (options.rows === "selected") {
              const rows = [...selected]
                .sort((a, b) => a - b)
                .map((idx) => visible[idx])
                .filter(Boolean) as T[]
              if (options.format === "csv") exportCSV(options.columns, rows, options.filename)
              else exportExcel(options.columns, rows, options.filename)
            } else if (onExport) await onExport(options)
            else if (options.format === "csv") exportCSV(options.columns, sorted, options.filename)
            else exportExcel(options.columns, sorted, options.filename)
          }}
        />

        {/* Summary line — between toolbar and table */}
        {summary && (
          <p className="text-muted-foreground border-b px-4 py-3 text-sm md:px-5">
            {summary}
          </p>
        )}

        {/* ── Desktop table ── */}
        <div ref={desktopRef} className="hidden md:block">
          <Table>
            <TableHeader>
              <TableRow className="bg-muted/40 hover:bg-muted/40">
                {/* Checkbox column — the always-visible selection entry point */}
                <TableHead className="w-12 py-3 pl-5 !pr-4">
                  <Checkbox
                    checked={allChecked}
                    ref={(el) => {
                      if (el) (el as HTMLInputElement).indeterminate = someChecked
                    }}
                    onCheckedChange={toggleAll}
                    aria-label="Select all"
                  />
                </TableHead>
                {columns.map((col) => (
                  <TableHead
                    key={col.key}
                    className={cn(
                      "text-muted-foreground group/head h-11 py-0 text-xs font-medium",
                      col.className
                    )}
                  >
                    {isSortable(col) ? (
                      <button
                        onClick={() => toggleSort(col.key)}
                        className={cn(
                          "hover:text-foreground flex items-center gap-1 transition-colors",
                          sortKey === col.key && "text-foreground"
                        )}
                      >
                        {col.label}
                        {sortKey === col.key ? (
                          sortDir === "asc" ? (
                            <ChevronUp className="size-3" />
                          ) : (
                            <ChevronDown className="size-3" />
                          )
                        ) : (
                          <ArrowUpDown className="size-3 opacity-0 transition-opacity group-hover/head:opacity-50" />
                        )}
                      </button>
                    ) : (
                      col.label
                    )}
                  </TableHead>
                ))}
                {actions && (
                  <TableHead
                    className={cn(
                      "w-10 py-3 pr-5",
                      stickyHead,
                      actionsPinned && stickyShadow
                    )}
                  />
                )}
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                Array.from({ length: 5 }).map((_, i) => (
                  <TableRow key={i}>
                    <TableCell className="py-4 pl-5 !pr-4">
                      <Skeleton className="size-4 rounded" />
                    </TableCell>
                    {columns.map((col) => (
                      <TableCell key={col.key} className="py-4">
                        <Skeleton className="h-4 w-3/4 max-w-40" />
                      </TableCell>
                    ))}
                    {actions && <TableCell className="py-4 pr-5" />}
                  </TableRow>
                ))
              ) : visible.length === 0 ? (
                <TableRow className="hover:bg-transparent">
                  <TableCell colSpan={columns.length + (actions ? 2 : 1)} className="p-0">
                    <EmptyState compact icon={Search} title={emptyMessage} />
                  </TableCell>
                </TableRow>
              ) : (
                visible.map((row, i) => (
                  <React.Fragment key={i}>
                  {groupBy && (i === 0 || groupBy(visible[i - 1]) !== groupBy(row)) && (
                    <TableRow className="hover:bg-transparent">
                      <TableCell
                        colSpan={columns.length + (actions ? 2 : 1)}
                        className="bg-muted/40 border-y px-5 py-2 text-xs font-semibold"
                      >
                        {renderGroupHeader
                          ? renderGroupHeader(
                              groupBy(row),
                              visible.filter((r) => groupBy(r) === groupBy(row))
                            )
                          : groupBy(row)}
                      </TableCell>
                    </TableRow>
                  )}
                  <TableRow
                    data-state={selected.has(i) ? "selected" : undefined}
                    onClick={rowClick ? () => rowClick(row) : undefined}
                    className={cn(
                      "group/row hover:bg-accent/40 data-[state=selected]:bg-primary/5 transition-colors",
                      rowClick && "cursor-pointer",
                      rowClassName?.(row)
                    )}
                  >
                    <TableCell
                      className={cn(cellPad, "pl-5 !pr-4")}
                      onClick={(e) => e.stopPropagation()}
                    >
                      <Checkbox
                        checked={selected.has(i)}
                        onCheckedChange={() => toggleRow(i)}
                        aria-label={`Select row ${i + 1}`}
                      />
                    </TableCell>
                    {columns.map((col) => (
                      <TableCell
                        key={col.key}
                        className={cn(cellPad, col.className)}
                      >
                        {col.render
                          ? col.render(row)
                          : String(row[col.key as keyof T] ?? "—")}
                      </TableCell>
                    ))}
                    {actions && (
                      <TableCell
                        className={cn(
                          cellPad,
                          "pr-5 text-right",
                          stickyCell,
                          actionsPinned && stickyShadow
                        )}
                        onClick={(e) => e.stopPropagation()}
                      >
                        <RowActions row={row} actions={actions} />
                      </TableCell>
                    )}
                  </TableRow>
                  </React.Fragment>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        {/* ── Mobile card list — app-like rows, no horizontal scroll ── */}
        <div className="divide-y md:hidden">
          {loading ? (
            Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="flex items-start gap-3 px-4 py-4">
                <Skeleton className="mt-0.5 size-4 rounded" />
                <div className="flex-1 space-y-2.5">
                  <Skeleton className="h-4 w-2/5" />
                  <div className="flex gap-3">
                    <Skeleton className="h-3 w-16" />
                    <Skeleton className="h-3 w-20" />
                  </div>
                </div>
              </div>
            ))
          ) : visible.length === 0 ? (
            <EmptyState compact icon={Search} title={emptyMessage} />
          ) : (
            visible.map((row, i) => {
              const isSelected = selected.has(i)
              return (
                <React.Fragment key={i}>
                {groupBy && (i === 0 || groupBy(visible[i - 1]) !== groupBy(row)) && (
                  <div className="bg-muted/40 px-4 py-2 text-xs font-semibold">
                    {renderGroupHeader
                      ? renderGroupHeader(
                          groupBy(row),
                          visible.filter((r) => groupBy(r) === groupBy(row))
                        )
                      : groupBy(row)}
                  </div>
                )}
                <div
                  data-state={isSelected ? "selected" : undefined}
                  onClick={
                    selectable && selectionMode
                      ? () => toggleRow(i)
                      : rowClick
                        ? () => rowClick(row)
                        : undefined
                  }
                  className={cn(
                    "data-[state=selected]:bg-primary/5 flex items-center gap-3 px-4 transition-colors duration-150",
                    dense ? "py-2.5" : "py-3",
                    (rowClick || (selectable && selectionMode)) &&
                      "active:bg-accent/60 cursor-pointer",
                    rowClassName?.(row)
                  )}
                >
                  {selectable && selectionMode && (
                    <div
                      className="shrink-0"
                      onClick={(e) => e.stopPropagation()}
                    >
                      <Checkbox
                        checked={isSelected}
                        onCheckedChange={() => toggleRow(i)}
                        aria-label={`Select row ${i + 1}`}
                      />
                    </div>
                  )}

                  <div className="min-w-0 flex-1 space-y-2">
                    {primaryCol && (
                      <div className="flex items-center gap-2 text-[0.9375rem] font-medium leading-snug">
                        {cellText(row, primaryCol)}
                      </div>
                    )}
                    {secondaryCols.length > 0 && (
                      <dl className="space-y-1.5">
                        {secondaryCols.map((col) =>
                          col.mobileBlock ? (
                            <div key={col.key} className="space-y-1">
                              <dt className="text-muted-foreground text-xs">
                                {col.label}
                              </dt>
                              <dd className="text-foreground text-sm">
                                {cellText(row, col)}
                              </dd>
                            </div>
                          ) : (
                            <div
                              key={col.key}
                              className="flex items-center justify-between gap-3 text-sm"
                            >
                              <dt className="text-muted-foreground shrink-0 text-xs">
                                {col.label}
                              </dt>
                              <dd className="text-foreground min-w-0 truncate text-right">
                                {cellText(row, col)}
                              </dd>
                            </div>
                          )
                        )}
                      </dl>
                    )}
                  </div>

                  {selectable && selectionMode ? null : actions ? (
                    <MobileRowActions
                      row={row}
                      actions={actions}
                      title={primaryCol ? cellText(row, primaryCol) : undefined}
                    />
                  ) : rowClick ? (
                    <ChevronRight className="text-muted-foreground/50 size-5 shrink-0" />
                  ) : null}
                </div>
                </React.Fragment>
              )
            })
          )}
        </div>

        {/* ── Pagination footer (both modes) ── */}
        {showFooter && footer && (
          <div className="flex items-center justify-between gap-2 border-t px-3 py-2 md:gap-4 md:px-5 md:py-2.5">
            <div className="flex min-w-0 items-center gap-1.5">
              {footer.onPageSizeChange && (
                <PageSizeSelect
                  value={footer.pageSize ?? PAGE_SIZE_OPTIONS[0]}
                  onChange={footer.onPageSizeChange}
                />
              )}
              <p className="text-muted-foreground truncate text-xs tabular-nums">
                {t("dataTable.paginationRange", { from: footer.from, to: footer.to, total: footer.total })}
              </p>
            </div>

            {footer.pageCount > 1 && (
              <div className="flex shrink-0 items-center gap-1">
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8 rounded-full"
                  disabled={footer.page <= 1}
                  onClick={() => footer.onPageChange(footer.page - 1)}
                  aria-label={t("dataTable.previousPage")}
                >
                  <ChevronLeft className="size-4" />
                </Button>

                {/* Numbered pages on desktop, compact fraction on mobile */}
                <div className="hidden items-center gap-1 md:flex">
                  {pageItems(footer.page, footer.pageCount).map((item, idx) =>
                    item === "gap" ? (
                      <span
                        key={`gap-${idx}`}
                        className="text-muted-foreground/60 px-1 text-sm"
                      >
                        …
                      </span>
                    ) : (
                      <button
                        key={item}
                        onClick={() => footer.onPageChange(item)}
                        aria-current={item === footer.page ? "page" : undefined}
                        className={cn(
                          "size-8 rounded-full text-sm tabular-nums transition-colors",
                          item === footer.page
                            ? "bg-primary text-primary-foreground font-semibold"
                            : "text-muted-foreground hover:bg-accent hover:text-foreground"
                        )}
                      >
                        {item}
                      </button>
                    )
                  )}
                </div>
                <span className="text-muted-foreground text-sm font-medium tabular-nums md:hidden">
                  {footer.page}
                  <span className="mx-1 opacity-50">/</span>
                  {footer.pageCount}
                </span>

                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8 rounded-full"
                  disabled={footer.page >= footer.pageCount}
                  onClick={() => footer.onPageChange(footer.page + 1)}
                  aria-label={t("dataTable.nextPage")}
                >
                  <ChevronRight className="size-4" />
                </Button>
              </div>
            )}
          </div>
        )}
      </div>

      {/* ── Floating bulk-action bar — appears over the content when rows are
             selected; sits above the mobile bottom nav ── */}
      {bulkActions?.length && selected.size > 0 ? (
        <div className="pointer-events-none fixed inset-x-0 bottom-24 z-40 flex justify-center px-4 md:bottom-8">
          <div className="bg-foreground text-background pointer-events-auto flex max-w-full items-center gap-1 overflow-x-auto rounded-full py-1.5 pl-4 pr-1.5 shadow-lg">
            <span className="whitespace-nowrap text-sm font-medium tabular-nums">
              {t("dataTable.selected", { count: selected.size })}
            </span>
            <span className="bg-background/25 mx-1.5 h-4 w-px shrink-0" />
            {bulkActions.map((action, i) => {
              const Icon = action.icon
              return (
                <Button
                  key={`bulk-${i}`}
                  variant="ghost"
                  size="sm"
                  className={cn(
                    "h-8 shrink-0 gap-1.5 rounded-full",
                    action.destructive
                      ? "text-destructive hover:bg-destructive/15 hover:text-destructive"
                      : "text-background hover:bg-background/15 hover:text-background"
                  )}
                  onClick={() =>
                    action.onClick(
                      [...selected].map((idx) => visible[idx]).filter(Boolean) as T[]
                    )
                  }
                >
                  {Icon && <Icon className="size-3.5" />}
                  {action.label}
                </Button>
              )
            })}
            <Button
              variant="ghost"
              size="icon"
              className="text-background hover:bg-background/15 hover:text-background size-8 shrink-0 rounded-full"
              onClick={() => setSelected(new Set())}
              aria-label={t("dataTable.clear")}
            >
              <X className="size-4" />
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  )
}

// ─── Rows-per-page picker ────────────────────────────────────────────────────
// The left end of every table footer. The choice is ONE app-wide preference
// (see lib/use-page-size) — a registrar who works in hundreds sets it once and
// every register follows, on this device and the next visit.

function PageSizeSelect({
  value,
  onChange,
}: {
  value: number
  onChange: (size: number) => void
}) {
  const { t } = useTranslation("common")
  const [open, setOpen] = React.useState(false)

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="ghost"
          size="sm"
          className="text-muted-foreground hover:text-foreground -ml-1 h-9 shrink-0 gap-1 rounded-full px-2.5 text-xs font-medium tabular-nums md:h-8 md:px-2"
          aria-label={t("dataTable.rowsPerPage")}
          title={t("dataTable.rowsPerPage")}
        >
          <Rows3 className="size-3.5" />
          {value}
          <ChevronDown
            className={cn(
              "size-3 opacity-60 transition-transform",
              open && "rotate-180"
            )}
          />
        </Button>
      </PopoverTrigger>
      {/* Opens upward — the footer sits at the bottom of a long table. */}
      <PopoverContent align="start" side="top" className="w-44 rounded-xl p-1.5">
        <p className="text-muted-foreground px-2 pb-1 pt-0.5 text-[0.6875rem] font-medium uppercase tracking-wide">
          {t("dataTable.rowsPerPage")}
        </p>
        {PAGE_SIZE_OPTIONS.map((option) => (
          <button
            key={option}
            onClick={() => {
              onChange(option)
              setOpen(false)
            }}
            aria-current={option === value ? "true" : undefined}
            className={cn(
              // Roomy taps on a phone (a mis-tap here re-queries the list),
              // tighter rows on a pointer device.
              "hover:bg-muted flex min-h-11 w-full items-center justify-between gap-2 rounded-md px-2.5 text-left text-sm tabular-nums md:min-h-9",
              option === value && "text-primary font-medium"
            )}
          >
            {t("dataTable.rowsCount", { count: option })}
            {option === value && <Check className="size-3.5 shrink-0" />}
          </button>
        ))}
      </PopoverContent>
    </Popover>
  )
}

// ─── Sort button (mobile) ─────────────────────────────────────────────────────
// The card layout has no clickable headers, so mobile gets a dedicated pill:
// tap a field to sort ascending, tap again to flip, clear from the footer.

function SortButton<T>({
  columns,
  sortKey,
  sortDir,
  onSort,
  className,
}: {
  columns: DataTableColumn<T>[]
  sortKey: string | null
  sortDir: "asc" | "desc"
  onSort: (key: string | null, dir: "asc" | "desc") => void
  className?: string
}) {
  const { t } = useTranslation("common")
  const active = columns.find((c) => c.key === sortKey)

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className={cn(
            "h-9 gap-1.5 rounded-full",
            active && "border-primary/40 bg-primary/10 hover:bg-primary/15",
            className
          )}
        >
          <ArrowUpDown className="size-3.5" />
          {active ? (
            <Badge
              variant="secondary"
              className="max-w-36 truncate rounded-full px-1.5 py-0 text-xs font-normal"
            >
              {active.label}
            </Badge>
          ) : (
            t("dataTable.sort")
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-56 rounded-xl p-0">
        <div className="max-h-64 space-y-0.5 overflow-y-auto p-1.5">
          {columns.map((col) => {
            const isActive = col.key === sortKey
            return (
              <button
                key={col.key}
                onClick={() =>
                  onSort(col.key, isActive && sortDir === "asc" ? "desc" : "asc")
                }
                className={cn(
                  "hover:bg-muted flex w-full items-center justify-between gap-2.5 rounded-md px-2.5 py-2 text-left text-sm",
                  isActive && "text-primary font-medium"
                )}
              >
                <span className="min-w-0 flex-1 truncate">{col.label}</span>
                {isActive ? (
                  sortDir === "asc" ? (
                    <ChevronUp className="size-3.5 shrink-0" />
                  ) : (
                    <ChevronDown className="size-3.5 shrink-0" />
                  )
                ) : (
                  <ArrowUpDown className="text-muted-foreground/50 size-3.5 shrink-0" />
                )}
              </button>
            )
          })}
        </div>
        {active && (
          <div className="border-t p-1.5">
            <Button
              variant="ghost"
              size="sm"
              className="h-7 w-full text-xs"
              onClick={() => onSort(null, "asc")}
            >
              {t("dataTable.clear")}
            </Button>
          </div>
        )}
      </PopoverContent>
    </Popover>
  )
}

// ─── Filter button ────────────────────────────────────────────────────────────
// One pill per filter: the button shows the active selection and the popover
// lists checkbox options (multi-select, comma-joined value).

function FilterButton({
  filter,
  options,
  value,
  onChange,
  onClear,
}: {
  filter: DataTableFilter
  /** Options resolved by the parent (handles cascading option functions). */
  options: DataTableFilterOption[]
  value: string
  onChange: (value: string) => void
  onClear: () => void
}) {
  const { t } = useTranslation("common")
  const [query, setQuery] = React.useState("")

  const selected = React.useMemo(
    () => new Set(value.split(",").filter(Boolean)),
    [value]
  )
  const searchable = options.length > 8
  const q = query.trim().toLowerCase()
  const shown = q
    ? options.filter((o) => o.label.toLowerCase().includes(q))
    : options

  function toggle(optValue: string) {
    const next = new Set(selected)
    if (next.has(optValue)) next.delete(optValue)
    else next.add(optValue)
    onChange([...next].join(","))
  }

  const summary =
    selected.size === 1
      ? (options.find((o) => selected.has(o.value))?.label ?? "1")
      : selected.size > 1
        ? String(selected.size)
        : null

  return (
    <Popover onOpenChange={(open) => !open && setQuery("")}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className={cn(
            "h-9 gap-1.5 rounded-full",
            selected.size > 0 && "border-primary/40 bg-primary/10 hover:bg-primary/15"
          )}
        >
          {filter.label}
          {summary && (
            <Badge
              variant="secondary"
              className="max-w-52 truncate rounded-full px-1.5 py-0 text-xs font-normal"
            >
              {summary}
            </Badge>
          )}
          <ChevronDown className="text-muted-foreground size-3" />
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-56 rounded-xl p-0">
        {searchable && (
          <div className="border-b p-2">
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder={t("dataTable.searchOptions")}
              className="h-8 text-sm"
            />
          </div>
        )}
        <div className="max-h-64 space-y-0.5 overflow-y-auto p-1.5">
          {shown.length === 0 ? (
            <p className="text-muted-foreground px-2 py-3 text-center text-xs">
              {t("dataTable.noMatches")}
            </p>
          ) : (
            shown.map((opt) => (
              <label
                key={opt.value}
                className="hover:bg-muted flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm"
              >
                <Checkbox
                  checked={selected.has(opt.value)}
                  onCheckedChange={() => toggle(opt.value)}
                />
                <span className="min-w-0 flex-1 truncate">{opt.label}</span>
              </label>
            ))
          )}
        </div>
        {selected.size > 0 && (
          <div className="border-t p-1.5">
            <Button
              variant="ghost"
              size="sm"
              className="h-7 w-full text-xs"
              onClick={onClear}
            >
              {t("dataTable.clear")}
            </Button>
          </div>
        )}
      </PopoverContent>
    </Popover>
  )
}

// ─── Export dialog ────────────────────────────────────────────────────────────

function ExportDialog<T>({
  open,
  onOpenChange,
  columns,
  defaultFilename,
  total,
  selectedCount,
  onConfirm,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  columns: DataTableColumn<T>[]
  defaultFilename: string
  total: number
  selectedCount: number
  onConfirm: (options: DataTableExportOptions<T>) => Promise<void>
}) {
  const { t } = useTranslation("common")
  const [filename, setFilename] = React.useState(defaultFilename)
  const [format, setFormat] = React.useState<"csv" | "excel">("csv")
  const [rows, setRows] = React.useState<"all" | "selected">("all")
  const [included, setIncluded] = React.useState<Set<string>>(
    () => new Set(columns.map((c) => c.key))
  )
  const [busy, setBusy] = React.useState(false)

  React.useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- reset the form each time the dialog opens */
    setFilename(defaultFilename)
    setFormat("csv")
    // Checked rows before opening the dialog signal intent: default to them.
    setRows(selectedCount > 0 ? "selected" : "all")
    setIncluded(new Set(columns.map((c) => c.key)))
    /* eslint-enable react-hooks/set-state-in-effect */
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when (re)opened; columns identity changes every parent render
  }, [open])

  function toggleColumn(key: string) {
    setIncluded((prev) => {
      const next = new Set(prev)
      if (next.has(key)) next.delete(key)
      else next.add(key)
      return next
    })
  }

  async function handleDownload() {
    const cols = columns.filter((c) => included.has(c.key))
    if (cols.length === 0) return
    setBusy(true)
    try {
      await onConfirm({
        format,
        filename: filename.trim() || defaultFilename,
        columns: cols,
        rows: selectedCount > 0 ? rows : "all",
      })
      onOpenChange(false)
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(v) => !busy && onOpenChange(v)}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t("dataTable.exportTitle")}</DialogTitle>
        </DialogHeader>

        <div className="space-y-5">
          {/* Rows — offered only when a selection exists */}
          {selectedCount > 0 && (
            <div className="space-y-1.5">
              <p className="text-sm font-medium">{t("dataTable.rowsToExport")}</p>
              {(
                [
                  ["selected", t("dataTable.selectedRowsOnly", { count: selectedCount })],
                  ["all", t("dataTable.allRows", { count: total })],
                ] as const
              ).map(([value, label]) => (
                <label
                  key={value}
                  className="flex cursor-pointer items-center gap-2.5 text-sm"
                >
                  <input
                    type="radio"
                    name="export-rows"
                    className="accent-primary size-4"
                    checked={rows === value}
                    onChange={() => setRows(value)}
                  />
                  {label}
                </label>
              ))}
            </div>
          )}

          {/* File name */}
          <div className="space-y-1.5">
            <label htmlFor="export-filename" className="text-sm font-medium">
              {t("dataTable.fileName")}
            </label>
            <Input
              id="export-filename"
              value={filename}
              onChange={(e) => setFilename(e.target.value)}
            />
          </div>

          {/* File format */}
          <div className="space-y-1.5">
            <p className="text-sm font-medium">{t("dataTable.fileFormat")}</p>
            {(
              [
                ["csv", ".csv"],
                ["excel", ".xls (Excel)"],
              ] as const
            ).map(([value, label]) => (
              <label
                key={value}
                className="flex cursor-pointer items-center gap-2.5 text-sm"
              >
                <input
                  type="radio"
                  name="export-format"
                  className="accent-primary size-4"
                  checked={format === value}
                  onChange={() => setFormat(value)}
                />
                {label}
              </label>
            ))}
          </div>

          {/* Columns */}
          <div className="space-y-1.5">
            <p className="text-sm font-medium">{t("dataTable.columns")}</p>
            <div className="grid grid-cols-2 gap-x-3 gap-y-1.5">
              {columns.map((col) => (
                <label
                  key={col.key}
                  className="flex cursor-pointer items-center gap-2 text-sm"
                >
                  <Checkbox
                    checked={included.has(col.key)}
                    onCheckedChange={() => toggleColumn(col.key)}
                  />
                  <span className="min-w-0 truncate">{col.label}</span>
                </label>
              ))}
            </div>
          </div>

          <p className="text-muted-foreground text-xs">
            {t("dataTable.records", {
              count: selectedCount > 0 && rows === "selected" ? selectedCount : total,
            })}
          </p>
        </div>

        <DialogFooter>
          <Button variant="outline" disabled={busy} onClick={() => onOpenChange(false)}>
            {t("dataTable.cancel")}
          </Button>
          <Button loading={busy} disabled={included.size === 0} onClick={handleDownload}>
            {t("dataTable.download")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

// ─── Row actions ──────────────────────────────────────────────────────────────
// EVERY action renders as its own inline button — there is deliberately no
// overflow/kebab menu (project rule). Actions with an icon become tooltip'd
// icon buttons; an action missing its icon (a bug — always set `icon:`) falls
// back to a small labeled button so it stays visible, never hidden.

function RowActions<T>({
  row,
  actions,
}: {
  row: T
  actions: DataTableAction<T>[]
}) {
  const visible = actions.filter((a) => !a.hidden?.(row))
  if (visible.length === 0) return null

  return (
    <div className="flex items-center justify-end gap-0.5">
      {visible.map((action, i) => {
        const Icon = action.icon
        const disabled = action.disabled?.(row)

        if (!Icon) {
          return (
            <Button
              key={`action-${i}`}
              type="button"
              variant="ghost"
              size="sm"
              className={cn(
                "text-muted-foreground hover:text-foreground h-7 rounded-full px-2 text-xs",
                action.destructive &&
                  "hover:text-destructive focus-visible:text-destructive"
              )}
              disabled={disabled}
              onClick={() => action.onClick(row)}
            >
              {action.label}
            </Button>
          )
        }

        return (
          <Tooltip key={`action-${i}`}>
            <TooltipTrigger asChild>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className={cn(
                  "text-muted-foreground hover:text-foreground size-7 rounded-full",
                  action.destructive &&
                    "hover:text-destructive focus-visible:text-destructive"
                )}
                disabled={disabled}
                aria-label={action.label}
                onClick={() => action.onClick(row)}
              >
                <Icon className="size-4" />
              </Button>
            </TooltipTrigger>
            <TooltipContent>{action.label}</TooltipContent>
          </Tooltip>
        )
      })}
    </div>
  )
}

// ─── Row actions (mobile) ─────────────────────────────────────────────────────
// On a phone, tooltips don't exist and a strip of unlabeled icons overflows the
// row. Instead every row exposes ONE "more" button that opens a native-style
// bottom action sheet with big, labeled, thumb-friendly targets. The single
// most common action stays one tap away because the whole card is tappable.

function MobileRowActions<T>({
  row,
  actions,
  title,
}: {
  row: T
  actions: DataTableAction<T>[]
  title?: React.ReactNode
}) {
  const { t } = useTranslation("common")
  const [open, setOpen] = React.useState(false)
  const visible = actions.filter((a) => !a.hidden?.(row))
  if (visible.length === 0) return null

  return (
    <Drawer open={open} onOpenChange={setOpen}>
      <DrawerTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="text-muted-foreground -mr-1.5 size-9 shrink-0 rounded-full"
          aria-label={t("dataTable.actions")}
          onClick={(e) => e.stopPropagation()}
        >
          <MoreHorizontal className="size-5" />
        </Button>
      </DrawerTrigger>
      <DrawerContent>
        <DrawerHeader className="text-left">
          <DrawerTitle className="truncate text-base">
            {title ?? t("dataTable.actions")}
          </DrawerTitle>
        </DrawerHeader>
        <div className="flex flex-col gap-0.5 p-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))]">
          {visible.map((action, i) => {
            const Icon = action.icon
            const disabled = action.disabled?.(row)
            return (
              <button
                key={`m-action-${i}`}
                type="button"
                disabled={disabled}
                onClick={(e) => {
                  e.stopPropagation()
                  setOpen(false)
                  action.onClick(row)
                }}
                className={cn(
                  "flex items-center gap-3.5 rounded-2xl px-3 py-3 text-left text-[0.9375rem] font-medium transition-colors active:bg-muted disabled:pointer-events-none disabled:opacity-40",
                  action.destructive ? "text-destructive" : "text-foreground"
                )}
              >
                <span
                  className={cn(
                    "flex size-9 shrink-0 items-center justify-center rounded-full",
                    action.destructive
                      ? "bg-destructive/10"
                      : "bg-muted text-muted-foreground"
                  )}
                >
                  {Icon ? <Icon className="size-[1.15rem]" /> : null}
                </span>
                {action.label}
              </button>
            )
          })}
        </div>
      </DrawerContent>
    </Drawer>
  )
}
