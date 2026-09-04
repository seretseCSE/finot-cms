"use client"

import { useCallback, useState, type ReactNode } from "react"
import { toast } from "sonner"

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
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"

/**
 * PROJECT RULE (CLAUDE.md §5): bulk actions mirror the row actions and
 * SKIP-AND-REPORT. The server authorizes every row on its own and reports what
 * it could not do; this module is the client half of that contract.
 */

/** One row a bulk action could not touch. */
export interface BulkSkip {
  id: number
  name: string | null
  /** Stable machine key from `App\Http\Controllers\Concerns\HandlesBulkActions`. */
  reason: string
}

type Translator = (key: string, vars?: Record<string, string | number>) => string

/** The envelope every bulk endpoint returns under `meta`. */
export interface BulkMeta {
  requested: number
  skipped: BulkSkip[]
  [countKey: string]: number | BulkSkip[]
}

/**
 * Tell the user what a bulk action left undone. A selection is hand-picked, so
 * "12 updated" alone is a lie when three rows were out of reach — the warning
 * names them (up to three per reason) and says why. Returns whether it warned.
 *
 * Reason keys resolve from the SHARED `common.bulkSkip.*` vocabulary, so a page
 * only writes a string when it introduces a reason no other table uses.
 */
export function reportBulkSkips(skipped: BulkSkip[] | undefined, t: Translator): boolean {
  if (!skipped || skipped.length === 0) return false

  // Group by reason so "5 protected, 1 already deleted" reads as two facts.
  const byReason = new Map<string, BulkSkip[]>()
  for (const row of skipped) {
    const list = byReason.get(row.reason)
    if (list) list.push(row)
    else byReason.set(row.reason, [row])
  }

  const description = [...byReason.entries()]
    .map(([reason, rows]) => {
      const names = rows
        .map((r) => r.name)
        .filter((n): n is string => Boolean(n))
        .slice(0, 3)
        .join(", ")
      const why = t(`bulkSkip.${reason}`)
      return names ? `${why}: ${names}${rows.length > 3 ? ` +${rows.length - 3}` : ""}` : why
    })
    .join(" · ")

  toast.warning(t("bulkSkip.title", { count: skipped.length }), { description })

  return true
}

/**
 * Report one bulk run. "0 done" is not good news, so when nothing landed the
 * skip warning speaks alone — a green "0 updated" next to it just adds noise.
 */
export function reportBulkResult(
  done: number,
  skipped: BulkSkip[] | undefined,
  successMessage: string,
  t: Translator,
): void {
  const warned = reportBulkSkips(skipped, t)
  if (done > 0 || !warned) toast.success(successMessage)
}

/**
 * POST a bulk endpoint and report the outcome — the whole client half in one
 * call. `countKey` is the verb the endpoint counts under (`approved`,
 * `deleted`, `returned`…). Returns how many rows landed, or null if the request
 * itself failed.
 *
 *   await runBulk({
 *     url: "/leave-requests/bulk/decide", ids, body: { decision: "approved" },
 *     countKey: "decided", success: (n) => t("leave.bulk.approved", { count: n }), tc,
 *   })
 */
export async function runBulk({
  url,
  ids,
  body,
  countKey,
  success,
  tc,
}: {
  url: string
  ids: number[]
  body?: Record<string, unknown>
  countKey: string
  success: (count: number) => string
  /** The `common` translator — it owns the shared skip-reason vocabulary. */
  tc: Translator
}): Promise<number | null> {
  try {
    const res = await apiFetch<{ meta: BulkMeta }>(url, {
      method: "POST",
      body: { ids, ...(body ?? {}) },
    })
    const done = Number(res.meta[countKey] ?? 0)
    reportBulkResult(done, res.meta.skipped, success(done), tc)

    return done
  } catch (err) {
    toast.error(err instanceof ApiError ? err.message : tc("errors.generic"))

    return null
  }
}

interface PendingBulk {
  title: string
  description: string
  confirmLabel?: string
  destructive?: boolean
  action: () => void | Promise<void>
}

/**
 * Confirm-then-run for bulk actions — the selection-wide sibling of
 * `useConfirmDelete()`. One dialog per page serves every bulk action on it.
 *
 * Usage:
 *   const { confirmBulk, bulkDialog } = useBulkConfirm()
 *   …
 *   onClick: (rows) => confirmBulk({
 *     title: t("bulk.approveTitle", { count: rows.length }),
 *     description: t("bulk.approveDesc"),
 *     action: () => approveMany(rows),
 *   })
 *   …
 *   return <>{…page…}{bulkDialog}</>
 */
export function useBulkConfirm(): {
  confirmBulk: (options: PendingBulk) => void
  bulkDialog: ReactNode
  /** True while the confirmed action is in flight. */
  bulkWorking: boolean
} {
  const { t: tc } = useTranslation("common")
  const [pending, setPending] = useState<PendingBulk | null>(null)
  const [working, setWorking] = useState(false)

  const confirmBulk = useCallback((options: PendingBulk) => setPending(options), [])

  async function run() {
    if (!pending) return
    setWorking(true)
    try {
      await pending.action()
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  const bulkDialog = (
    <AlertDialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{pending?.title}</AlertDialogTitle>
          <AlertDialogDescription>{pending?.description}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
          <AlertDialogAction
            // Only destroying wears red: approving or restoring a selection is
            // ordinary work and should not look like a warning.
            variant={pending?.destructive ? "destructive" : "default"}
            loading={working}
            onClick={(e) => {
              e.preventDefault()
              run()
            }}
          >
            {pending?.confirmLabel ?? tc("actions.confirm")}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )

  return { confirmBulk, bulkDialog, bulkWorking: working }
}
