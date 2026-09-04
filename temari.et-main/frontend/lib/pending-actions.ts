/**
 * Global registry of in-flight mutations ("Saving assignment…", "Uploading
 * file…"). apiFetch auto-registers every mutating request; the PendingGuard
 * component subscribes to warn before the tab closes or the user navigates
 * away mid-save. Lives outside React so non-hook code (apiFetch) can write
 * to it; components read it via useSyncExternalStore.
 */

export interface PendingAction {
  id: number
  /** i18n key under the `common` domain (e.g. "pending.actions.saving"). */
  labelKey: string
}

let nextId = 1
let actions: PendingAction[] = []
const listeners = new Set<() => void>()

function emit() {
  for (const listener of listeners) listener()
}

/** Register an in-flight action; call the returned disposer when it settles. */
export function beginPendingAction(labelKey: string): () => void {
  const id = nextId++
  actions = [...actions, { id, labelKey }]
  emit()

  let done = false
  return () => {
    if (done) return
    done = true
    actions = actions.filter((action) => action.id !== id)
    emit()
  }
}

export function subscribePendingActions(listener: () => void): () => void {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

/** Stable snapshot for useSyncExternalStore. */
export function getPendingActions(): PendingAction[] {
  return actions
}

const EMPTY: PendingAction[] = []

export function getServerPendingActions(): PendingAction[] {
  return EMPTY
}
