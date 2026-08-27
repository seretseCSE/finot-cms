"use client"

import { useCallback, useSyncExternalStore } from "react"

/**
 * Rows-per-page choices offered in every table footer.
 *
 * The backend clamps `per_page` at 100 (`HandlesListQueries::perPage`) — never
 * offer a bigger number here or the server will silently hand back fewer rows
 * than the footer promises.
 */
export const PAGE_SIZE_OPTIONS = [25, 50, 75, 100] as const

export const DEFAULT_PAGE_SIZE = 25

const STORAGE_KEY = "temari:rows-per-page"

/**
 * Snap any value onto an offered option. Stored preferences outlive code
 * changes, so a dropped option (or a hand-edited localStorage value) must never
 * render a page size the picker cannot show. Ties round UP — a user who asked
 * for more rows gets more, never fewer.
 */
export function normalizePageSize(
  value: unknown,
  fallback: number = DEFAULT_PAGE_SIZE,
): number {
  const n =
    typeof value === "number"
      ? value
      : typeof value === "string"
        ? Number.parseInt(value, 10)
        : NaN
  if (!Number.isFinite(n) || n <= 0) return normalizeKnown(fallback)
  return normalizeKnown(n)
}

function normalizeKnown(n: number): number {
  let best: number = PAGE_SIZE_OPTIONS[0]
  let bestGap = Number.POSITIVE_INFINITY
  for (const option of PAGE_SIZE_OPTIONS) {
    const gap = Math.abs(option - n)
    if (gap < bestGap || (gap === bestGap && option > best)) {
      best = option
      bestGap = gap
    }
  }
  return best
}

// ── Preference store ────────────────────────────────────────────────────────
// One preference for the whole app: a user who wants 100 rows wants 100 rows on
// every register, not per table. Kept in a module store (not just localStorage)
// so every mounted table re-renders the moment the choice changes, and mirrored
// across tabs through the `storage` event.

let cached: number | null | undefined
const listeners = new Set<() => void>()

function readStored(): number | null {
  if (typeof window === "undefined") return null
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    return raw === null ? null : normalizePageSize(raw)
  } catch {
    // Private-mode / disabled storage — the preference is simply not sticky.
    return null
  }
}

function snapshot(): number | null {
  if (cached === undefined) cached = readStored()
  return cached
}

/** SSR + the hydration pass: nothing stored is knowable yet. */
function serverSnapshot(): number | null {
  return null
}

function subscribe(onChange: () => void): () => void {
  listeners.add(onChange)
  const onStorage = (e: StorageEvent) => {
    if (e.key !== null && e.key !== STORAGE_KEY) return
    cached = readStored()
    listeners.forEach((fn) => fn())
  }
  window.addEventListener("storage", onStorage)
  return () => {
    listeners.delete(onChange)
    window.removeEventListener("storage", onStorage)
  }
}

/** The stored preference, or `null` when the user has never chosen one. */
export function storedPageSize(): number | null {
  return snapshot()
}

/** The size a table should start at: the user's preference, else its own default. */
export function initialPageSize(fallback: number = DEFAULT_PAGE_SIZE): number {
  return normalizePageSize(snapshot() ?? fallback, fallback)
}

export function setStoredPageSize(size: number): void {
  const next = normalizePageSize(size)
  if (cached === next) return
  cached = next
  try {
    window.localStorage.setItem(STORAGE_KEY, String(next))
  } catch {
    // Not sticky, still applied for this session.
  }
  listeners.forEach((fn) => fn())
}

/**
 * Live rows-per-page preference. `fallback` is the table's own default, used
 * until the user picks a size (e.g. the cashbook opens at 50).
 *
 * Reads through `useSyncExternalStore` so the server-rendered pass and the
 * hydration pass agree (both see the fallback) and React re-renders once the
 * real preference is known — no hydration mismatch, no flash of the wrong count.
 */
export function usePageSize(
  fallback: number = DEFAULT_PAGE_SIZE,
): [number, (size: number) => void] {
  const stored = useSyncExternalStore(subscribe, snapshot, serverSnapshot)
  const setPageSize = useCallback((size: number) => setStoredPageSize(size), [])
  return [normalizePageSize(stored ?? fallback, fallback), setPageSize]
}

/** Test seam — resets the module cache between cases. */
export function __resetPageSizeCache(): void {
  cached = undefined
}
