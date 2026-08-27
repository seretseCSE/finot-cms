"use client"

import { useSearchParams } from "next/navigation"
import { useCallback, useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import {
  exportCSV,
  exportExcel,
  type DataTableExportOptions,
  type DataTablePagination,
} from "@/components/ui/data-table"
import { ApiError, apiFetch } from "@/lib/api"
import type { Paginated } from "@/lib/types"
import {
  initialPageSize,
  normalizePageSize,
  setStoredPageSize,
} from "@/lib/use-page-size"

export interface ServerTableSort {
  key: string
  dir: "asc" | "desc"
}

export interface UseServerTableOptions {
  /** List endpoint path, e.g. `/schools`. */
  endpoint: string
  /** Full-result export endpoint, e.g. `/schools/export`. Omit to disable server export. */
  exportEndpoint?: string
  /** Initial sort. Also used as the fallback when a sort is cleared. */
  defaultSort: ServerTableSort
  /** Starting page size, used until the user picks one of their own from the
   *  footer (the choice is app-wide — see `lib/use-page-size`). The server
   *  clamps to ≤100, which is also the largest offered option. */
  perPage?: number
  /** When false, no request is made (e.g. staff page before a branch is picked). */
  enabled?: boolean
  /** Changing this value refetches and resets to page 1 — use it for external
   *  scope that affects the result set (e.g. the active school/branch context). */
  refreshKey?: string | number
  /** Fixed query params sent with every request (e.g. `{ branch_id: "3" }`
   *  when a school-wide page is narrowed to one branch). */
  extraParams?: Record<string, string>
  /** Called with the full response `meta` after every fetch — for endpoints
   *  that piggyback aggregates (e.g. the cashbook totals strip) on the page. */
  onMeta?: (meta: Record<string, unknown>) => void
  /** Message shown when the initial load fails. */
  loadFailedMessage?: string
  /** Message shown when an export returns no rows. */
  exportEmptyMessage?: string
  /** Generic error toast. */
  errorMessage?: string
}

export interface UseServerTableResult<T> {
  rows: T[]
  loading: boolean
  total: number
  searchInput: string
  setSearchInput: (value: string) => void
  filters: Record<string, string>
  setFilter: (key: string, value: string) => void
  dates: Record<string, string>
  setDate: (key: string, value: string) => void
  clearDates: () => void
  activeDateCount: number
  sort: ServerTableSort
  onSortChange: (key: string | null, dir: "asc" | "desc") => void
  page: number
  setPage: (page: number) => void
  perPage: number
  setPerPage: (size: number) => void
  refetch: () => Promise<void>
  handleExport: (options: DataTableExportOptions<T>) => Promise<void>
  pagination: DataTablePagination
}

/**
 * Server-driven table state: debounced search, multi-select filters, date
 * ranges, whitelisted sort, pagination and full-result export — the client-side
 * companion to the backend `HandlesListQueries` trait. Every server-mode list
 * page (users, schools, branches, staff, …) should build on this so the query
 * contract stays identical across the platform.
 */
export function useServerTable<T extends { id?: number | string }>(
  options: UseServerTableOptions,
): UseServerTableResult<T> {
  const {
    endpoint,
    exportEndpoint,
    defaultSort,
    perPage: defaultPerPage,
    enabled = true,
    refreshKey,
    extraParams,
    onMeta,
    loadFailedMessage = "Failed to load records.",
    exportEmptyMessage = "Nothing to export.",
    errorMessage = "Something went wrong.",
  } = options

  const [rows, setRows] = useState<T[] | null>(null)
  const [meta, setMeta] = useState({ total: 0, last_page: 1, current_page: 1 })
  const [loading, setLoading] = useState(true)

  // Deep links (the ⌘K palette) may pre-fill the search box via ?q=. Read
  // through useSearchParams — during client-side navigation the URL bar only
  // updates AFTER the first render, so window.location misses the param.
  const deepLinkedSearch = useSearchParams().get("q") ?? ""
  const [searchInput, setSearchInput] = useState(deepLinkedSearch)
  const [search, setSearch] = useState(deepLinkedSearch)
  useEffect(() => {
    if (deepLinkedSearch) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- URL → state sync
      setSearchInput(deepLinkedSearch)
      setSearch(deepLinkedSearch)
    }
  }, [deepLinkedSearch])
  const [filters, setFilters] = useState<Record<string, string>>({})
  const [dates, setDates] = useState<Record<string, string>>({})
  const [sort, setSort] = useState<ServerTableSort>(defaultSort)
  const [page, setPage] = useState(1)
  // Seeded synchronously from the stored preference so a user who works in
  // hundreds gets one request for 100 rows, never 25 followed by a second
  // fetch. (The DataTable reads the same store reactively — nothing here
  // renders before the first response, so there is no hydration mismatch.)
  const [perPage, setPerPageState] = useState(() => initialPageSize(defaultPerPage))

  // Debounce the search box.
  useEffect(() => {
    const id = setTimeout(() => setSearch(searchInput), 300)
    return () => clearTimeout(id)
  }, [searchInput])

  // Value identity for the deps array — callers pass fresh object literals.
  const extraKey = JSON.stringify(extraParams ?? {})

  const buildParams = useCallback(
    (withPaging: boolean) => {
      const p = new URLSearchParams()
      for (const [k, v] of Object.entries(extraParams ?? {})) if (v) p.set(k, v)
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
    // eslint-disable-next-line react-hooks/exhaustive-deps -- extraParams keyed by value via extraKey
    [search, filters, dates, sort, page, perPage, extraKey],
  )

  const refetch = useCallback(async () => {
    if (!enabled) {
      setRows([])
      setLoading(false)
      return
    }
    setLoading(true)
    try {
      const res = await apiFetch<Paginated<T>>(`${endpoint}?${buildParams(true).toString()}`)
      setRows(res.data)
      setMeta({ total: res.meta.total, last_page: res.meta.last_page, current_page: res.meta.current_page })
      onMeta?.(res.meta as unknown as Record<string, unknown>)
    } catch (err) {
      // Swallow auth/context redirects (ApiError with a code) — apiFetch handles them.
      if (!(err instanceof ApiError) || err.code === null) {
        toast.error(err instanceof ApiError ? err.message : loadFailedMessage)
      }
      setRows([])
    } finally {
      setLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- refreshKey is an external scope signal, not read inside
  }, [enabled, endpoint, buildParams, loadFailedMessage, refreshKey])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- syncing list state with the API
    refetch()
  }, [refetch])

  // Reset to page 1 whenever the query (but not the page itself) changes.
  const queryKey = JSON.stringify({ search, filters, dates, sort, enabled, refreshKey })
  const firstRender = useRef(true)
  useEffect(() => {
    if (firstRender.current) {
      firstRender.current = false
      return
    }
    setPage(1)
  }, [queryKey])

  const setFilter = useCallback((key: string, value: string) => {
    setFilters((f) => ({ ...f, [key]: value }))
  }, [])

  const setDate = useCallback((key: string, value: string) => {
    setDates((d) => ({ ...d, [key]: value }))
  }, [])

  const clearDates = useCallback(() => setDates({}), [])

  /** Rows per page — remembered app-wide, and always back to page 1 so the
   *  user lands on the rows they were just looking at, not past them. */
  const setPerPage = useCallback((size: number) => {
    const next = normalizePageSize(size)
    setStoredPageSize(next)
    setPerPageState(next)
    setPage(1)
  }, [])

  const onSortChange = useCallback(
    (key: string | null, dir: "asc" | "desc") => setSort({ key: key ?? defaultSort.key, dir }),
    [defaultSort.key],
  )

  const handleExport = useCallback(
    async ({ format, filename, columns }: DataTableExportOptions<T>) => {
      if (!exportEndpoint) return
      try {
        const res = await apiFetch<{ data: T[] }>(`${exportEndpoint}?${buildParams(false).toString()}`)
        if (res.data.length === 0) {
          toast.info(exportEmptyMessage)
          return
        }
        if (format === "csv") exportCSV(columns, res.data, filename)
        else exportExcel(columns, res.data, filename)
      } catch (err) {
        toast.error(err instanceof ApiError ? err.message : errorMessage)
      }
    },
    [exportEndpoint, buildParams, exportEmptyMessage, errorMessage],
  )

  const from = meta.total === 0 ? 0 : (meta.current_page - 1) * perPage + 1
  const to = Math.min(meta.current_page * perPage, meta.total)
  const activeDateCount = Object.values(dates).filter(Boolean).length

  return {
    rows: rows ?? [],
    loading: rows === null || loading,
    total: meta.total,
    searchInput,
    setSearchInput,
    filters,
    setFilter,
    dates,
    setDate,
    clearDates,
    activeDateCount,
    sort,
    onSortChange,
    page,
    setPage,
    perPage,
    setPerPage,
    refetch,
    handleExport,
    pagination: {
      page: meta.current_page,
      pageCount: meta.last_page,
      total: meta.total,
      from,
      to,
      onPageChange: setPage,
      pageSize: perPage,
      onPageSizeChange: setPerPage,
    },
  }
}
