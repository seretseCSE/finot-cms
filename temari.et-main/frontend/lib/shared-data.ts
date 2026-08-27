"use client"

import { useCallback, useEffect, useRef, useSyncExternalStore } from "react"

/**
 * Session-wide stale-while-revalidate store for reference data (workspace
 * contexts, grade levels, academic years, terms, sections...).
 *
 * Why it exists: creating a branch on /branches then opening the employee
 * wizard used to show "No branches yet" — every screen loaded its reference
 * lists once and never heard about later changes. This store fixes that class
 * of bug app-wide:
 *
 *  - Cached responses are served INSTANTLY (no spinner on revisit) and
 *    revalidated in the background when older than `staleMs`.
 *  - Every successful mutation through `apiFetch` calls `notifyMutation`,
 *    which invalidates the caches tagged with that resource (plus its known
 *    ripples) — live consumers silently refetch, unmounted caches are dropped
 *    so the next open is fresh.
 *  - Returning to the tab (focus / visibility / back online) revalidates
 *    everything on screen — the app feels native on mobile/PWA where the tab
 *    sleeps between uses.
 *
 * The store never blocks the UI on a refresh: `loading` is true only while
 * there is nothing to show yet.
 */

interface Entry {
  data: unknown
  hasData: boolean
  fetchedAt: number
  stale: boolean
  tags: readonly string[]
  promise: Promise<void> | null
  fetcher: () => Promise<unknown>
}

const entries = new Map<string, Entry>()
const listeners = new Map<string, Set<() => void>>()
const versions = new Map<string, number>()

/** How long a cached response is trusted before a background revalidation. */
const DEFAULT_STALE_MS = 30_000

function notify(key: string) {
  versions.set(key, (versions.get(key) ?? 0) + 1)
  listeners.get(key)?.forEach((listener) => listener())
}

function revalidate(key: string): Promise<void> {
  const entry = entries.get(key)
  if (!entry) return Promise.resolve()
  if (entry.promise) return entry.promise

  const promise = entry
    .fetcher()
    .then((data) => {
      entry.data = data
      entry.hasData = true
      entry.fetchedAt = Date.now()
      entry.stale = false
    })
    .catch(() => {
      // Keep serving the previous value; the next trigger retries.
      entry.fetchedAt = Date.now()
    })
    .finally(() => {
      entry.promise = null
      notify(key)
    })

  entry.promise = promise
  return promise
}

/**
 * Mark every cache carrying one of these tags as stale. Caches somebody is
 * currently looking at refetch immediately (in the background); idle ones are
 * dropped so their next consumer starts fresh.
 */
export function invalidateShared(...tags: string[]) {
  for (const [key, entry] of entries) {
    if (!entry.tags.some((tag) => tags.includes(tag))) continue
    entry.stale = true
    if (listeners.get(key)?.size) {
      void revalidate(key)
    } else {
      entries.delete(key)
    }
  }
}

/** Forget everything — called on login/logout so no data crosses accounts. */
export function clearSharedData() {
  entries.clear()
}

/**
 * Ripple map: a mutation on the key resource also refreshes these tags.
 * Branches/schools feed the workspace switcher (`contexts`) and the branch
 * grade-offering (`grade-levels`); years and semesters shape the term
 * pickers; positions/roles sync memberships, which shape the contexts.
 */
const RIPPLES: Record<string, string[]> = {
  branches: ["contexts", "grade-levels"],
  schools: ["contexts", "grade-levels"],
  "branch-settings": ["contexts", "grade-levels"],
  employees: ["contexts"],
  users: ["contexts"],
  "academic-years": ["terms", "semesters"],
  semesters: ["terms", "academic-years"],
  terms: ["semesters", "academic-years"],
  subjects: ["grade-levels"],
}

/**
 * Called by `apiFetch` after every successful mutating request. Derives the
 * resource from the URL's first segment (`/branches/3/activate` -> `branches`)
 * and invalidates it plus its ripples. Unknown resources are a cheap no-op.
 */
export function notifyMutation(path: string) {
  const resource = path.replace(/^\//, "").split(/[/?#]/, 1)[0]
  if (!resource) return
  invalidateShared(resource, ...(RIPPLES[resource] ?? []))
}

/* When the app comes back to the foreground (or back online), everything on
 * screen quietly revalidates — at most once per throttle window. */
let lastForegroundSweep = 0

function revalidateVisible() {
  const now = Date.now()
  if (now - lastForegroundSweep < 15_000) return
  lastForegroundSweep = now
  for (const [key, entry] of entries) {
    entry.stale = true
    if (listeners.get(key)?.size) void revalidate(key)
  }
}

if (typeof window !== "undefined") {
  window.addEventListener("focus", revalidateVisible)
  window.addEventListener("online", revalidateVisible)
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") revalidateVisible()
  })
}

interface SharedDataOptions {
  /** Invalidation tags — defaults to the key itself. */
  tags?: string[]
  /** Trust window before a background revalidation (default 30s). */
  staleMs?: number
}

/**
 * Subscribe to a shared cache entry. Pass `key: null` to disable (the hook
 * returns `{ data: undefined, loading: false }`). Consumers of the same key
 * share one request and all update together when it's invalidated.
 */
export function useSharedData<T>(
  key: string | null,
  fetcher: () => Promise<T>,
  options: SharedDataOptions = {},
) {
  const { staleMs = DEFAULT_STALE_MS } = options
  const tagsKey = (options.tags ?? (key ? [key] : [])).join(",")

  const fetcherRef = useRef(fetcher)
  useEffect(() => {
    fetcherRef.current = fetcher
  })

  // Re-render whenever this key's cache entry changes (fetch completes,
  // invalidation refetch lands...). The store is the external system, so
  // useSyncExternalStore is the tear-free subscription primitive for it.
  useSyncExternalStore(
    useCallback(
      (onStoreChange: () => void) => {
        if (!key) return () => {}
        let subs = listeners.get(key)
        if (!subs) {
          subs = new Set()
          listeners.set(key, subs)
        }
        subs.add(onStoreChange)
        return () => {
          subs.delete(onStoreChange)
        }
      },
      [key],
    ),
    () => (key ? (versions.get(key) ?? 0) : 0),
    () => 0,
  )

  useEffect(() => {
    if (!key) return

    let entry = entries.get(key)
    if (!entry) {
      entry = {
        data: undefined,
        hasData: false,
        fetchedAt: 0,
        stale: true,
        tags: [],
        promise: null,
        fetcher: () => fetcherRef.current(),
      }
      entries.set(key, entry)
    }
    entry.fetcher = () => fetcherRef.current()
    entry.tags = tagsKey ? tagsKey.split(",") : []

    if (!entry.hasData || entry.stale || Date.now() - entry.fetchedAt > staleMs) {
      void revalidate(key)
    }
  }, [key, tagsKey, staleMs])

  const entry = key ? entries.get(key) : undefined
  const data = entry?.hasData ? (entry.data as T) : undefined

  return {
    data,
    /** True only while there is nothing cached to show yet (first load). */
    loading: key !== null && !entry?.hasData && (!entry || entry.promise !== null),
    /** Force an immediate background refresh of this key. */
    refresh: useCallback(() => {
      if (key) void revalidate(key)
    }, [key]),
  }
}
