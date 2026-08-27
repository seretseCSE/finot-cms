"use client"

import { useCallback, useEffect, useRef, useState, type DragEvent as ReactDragEvent } from "react"
import { toast } from "sonner"

import { formatFileSize } from "@/lib/files"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/**
 * The one drag-and-drop upload behaviour for the whole app.
 *
 * Every file picker in Temari is a hidden `<input type="file">` behind a
 * button — that stays the only path on touch (phones have no drag). On a
 * desktop the same region also accepts a dragged file: spread `useFileDrop`'s
 * props on it. Both paths go through the hook's `takeFiles`, so a dropped file
 * meets the same rules and lands in the same pending/rename step as a picked
 * one — no upload surface gets a second, divergent implementation.
 */

export interface FileDropOptions {
  /** Receives the accepted files — the same handler the file input uses. */
  onFiles: (files: File[]) => void
  /** The input's `accept` string (".pdf,.jpg" / "image/*"); unset = anything. */
  accept?: string
  /** Per-file byte cap, mirroring the endpoint's own rule. */
  maxSize?: number
  /** When false only the first dropped file is taken. */
  multiple?: boolean
  disabled?: boolean
}

/** `accept` entries are extensions (".pdf"), mime types, or wildcards ("image/*"). */
function matchesAccept(file: File, accept?: string): boolean {
  if (!accept) return true
  const rules = accept
    .split(",")
    .map((rule) => rule.trim().toLowerCase())
    .filter(Boolean)
  if (rules.length === 0) return true

  const name = file.name.toLowerCase()
  const type = file.type.toLowerCase()
  return rules.some((rule) => {
    if (rule.startsWith(".")) return name.endsWith(rule)
    if (rule.endsWith("/*")) return type.startsWith(rule.slice(0, -1))
    return type === rule
  })
}

/**
 * A drag that carries files. Without this check the app's OTHER drags — exam
 * questions being reordered, selected text — would light up every dropzone
 * they pass over.
 */
function carriesFiles(transfer: DataTransfer | null): boolean {
  return Array.from(transfer?.types ?? []).includes("Files")
}

/**
 * Zones nest (a photo tile inside a documents region), and a React event
 * visits the inner one first. The inner zone claims the event so the outer
 * neither highlights nor takes a second copy of the same file.
 */
const CLAIMED = Symbol.for("temari.dropzone.claimed")

type ClaimableEvent = DragEvent & { [CLAIMED]?: boolean }

function claim(event: ReactDragEvent): boolean {
  const native = event.nativeEvent as ClaimableEvent
  if (native[CLAIMED]) return false
  native[CLAIMED] = true
  return true
}

/**
 * While any dropzone is live, a file dropped just OUTSIDE it must not make the
 * browser open that file and throw the user out of a half-filled form. One
 * window listener, reference-counted across every mounted zone.
 */
let strayDropGuards = 0
let detachStrayGuard: (() => void) | null = null

function holdStrayDropGuard(): () => void {
  if (strayDropGuards === 0 && typeof window !== "undefined") {
    const swallow = (event: DragEvent) => {
      if (carriesFiles(event.dataTransfer)) event.preventDefault()
    }
    window.addEventListener("dragover", swallow)
    window.addEventListener("drop", swallow)
    detachStrayGuard = () => {
      window.removeEventListener("dragover", swallow)
      window.removeEventListener("drop", swallow)
    }
  }
  strayDropGuards += 1

  let released = false
  return () => {
    if (released) return
    released = true
    strayDropGuards -= 1
    if (strayDropGuards === 0) {
      detachStrayGuard?.()
      detachStrayGuard = null
    }
  }
}

/**
 * The highlight a region wears while a file hovers it. An OUTLINE, never a
 * border — borders resize the box and make the layout jump mid-drag.
 */
export const DROP_ACTIVE = "outline-2 outline-offset-2 outline-dashed outline-primary/60"

/**
 * Drag-and-drop handlers for an arbitrary region. Returns the props to spread,
 * whether a file is currently hovering (so the region can wear `DROP_ACTIVE`),
 * and `takeFiles` — hand the file input's `onChange` list to it too, so picked
 * and dropped files share one validator and one set of error messages.
 */
export function useFileDrop({ onFiles, accept, maxSize, multiple = false, disabled }: FileDropOptions) {
  const { t: tc } = useTranslation("common")
  const [dragOver, setDragOver] = useState(false)
  /** Open dragenter/dragleave pairs — see the leave handler. */
  const depth = useRef(0)

  useEffect(() => {
    if (disabled) return
    return holdStrayDropGuard()
  }, [disabled])

  const take = useCallback(
    (list: FileList | null) => {
      const dropped = Array.from(list ?? [])
      if (dropped.length === 0) return

      const wanted = multiple ? dropped : dropped.slice(0, 1)
      const accepted = wanted.filter((file) => {
        if (!matchesAccept(file, accept)) {
          toast.error(tc("upload.wrongType", { name: file.name }))
          return false
        }
        if (maxSize && file.size > maxSize) {
          toast.error(tc("upload.tooLarge", { name: file.name, size: formatFileSize(maxSize) }))
          return false
        }
        return true
      })

      if (accepted.length > 0) onFiles(accepted)
    },
    [accept, maxSize, multiple, onFiles, tc],
  )

  const dropProps = {
    onDragEnter: (event: ReactDragEvent) => {
      if (disabled || !carriesFiles(event.dataTransfer)) return
      depth.current += 1
    },
    onDragOver: (event: ReactDragEvent) => {
      if (disabled || !carriesFiles(event.dataTransfer)) return
      event.preventDefault()
      event.dataTransfer.dropEffect = "copy"
      // An inner zone owns this drag — stay dark so only one region lights up.
      setDragOver(claim(event))
    },
    onDragLeave: (event: ReactDragEvent) => {
      if (disabled || !carriesFiles(event.dataTransfer)) return
      // Entering a child fires leave on the parent. Counting enters against
      // leaves is the only browser-independent way to tell "moved to a child"
      // from "left for good" — Safari never fills in `relatedTarget` here.
      depth.current = Math.max(0, depth.current - 1)
      if (depth.current === 0) setDragOver(false)
    },
    onDrop: (event: ReactDragEvent) => {
      if (disabled || !carriesFiles(event.dataTransfer)) return
      event.preventDefault()
      depth.current = 0
      setDragOver(false)
      if (claim(event)) take(event.dataTransfer.files)
    },
  }

  // Derived, never stored: a zone disabled mid-drag must not stay lit.
  return { dragOver: dragOver && !disabled, dropProps, takeFiles: take }
}

/**
 * "or drag files here" — the desktop-only nudge that makes the drop target
 * discoverable next to an attach button. Hidden on touch layouts, where the
 * gesture does not exist.
 */
export function DropHint({ className }: { className?: string }) {
  const { t: tc } = useTranslation("common")
  return (
    <span className={cn("hidden text-xs text-muted-foreground sm:inline", className)}>
      {tc("upload.dragHint")}
    </span>
  )
}
