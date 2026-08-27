"use client"

import { useCallback, useState, type ReactNode } from "react"

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
import { useTranslation } from "@/lib/i18n"

interface PendingDelete {
  action: () => void | Promise<void>
  /** Row-specific message; falls back to the generic "cannot be undone" copy. */
  description?: string
}

/**
 * PROJECT RULE: deleting data ALWAYS requires confirmation — no delete action
 * anywhere in the app may call the API directly from a click.
 *
 * Usage:
 *   const { confirmDelete, confirmDialog } = useConfirmDelete()
 *   …
 *   onClick: (row) => confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.name }))
 *   …
 *   return <>{…page…}{confirmDialog}</>
 */
export function useConfirmDelete(): {
  confirmDelete: (action: () => void | Promise<void>, description?: string) => void
  confirmDialog: ReactNode
} {
  const { t: tc } = useTranslation("common")
  const [pending, setPending] = useState<PendingDelete | null>(null)
  const [working, setWorking] = useState(false)

  const confirmDelete = useCallback(
    (action: () => void | Promise<void>, description?: string) =>
      setPending({ action, description }),
    [],
  )

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

  const confirmDialog = (
    <AlertDialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{tc("confirmDelete.title")}</AlertDialogTitle>
          <AlertDialogDescription>
            {pending?.description ?? tc("confirmDelete.description")}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
          <AlertDialogAction
            variant="destructive"
            loading={working}
            onClick={(e) => {
              e.preventDefault()
              run()
            }}
          >
            {tc("confirmDelete.confirm")}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )

  return { confirmDelete, confirmDialog }
}
