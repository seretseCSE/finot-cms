import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/** First letters of the first two words of a name, e.g. "Abebe Kebede" -> "AK". */
export function initials(name: string): string {
  return name
    .split(" ")
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("")
}

/*
 * Overlay open/close registry.
 *
 * Radix `Select` (in modal mode) closes and UNMOUNTS itself on the very same
 * pointer event that a wrapping Dialog/Sheet/Drawer then treats as an "outside"
 * interaction. By the time the modal's handler runs, the dropdown is already
 * gone from the DOM and the event target is the modal backdrop — so neither
 * target-matching nor a live DOM query can detect it. We therefore track overlay
 * open state directly (via each overlay's `onOpenChange`) and keep a short grace
 * window covering a dropdown that closed within the current interaction.
 */
let openOverlayCount = 0
let lastOverlayClosedAt = 0

/** Overlay primitives (Select/Popover/DropdownMenu) report open state here. */
export function registerOverlayOpenState(open: boolean): void {
  if (open) {
    openOverlayCount += 1
  } else {
    openOverlayCount = Math.max(0, openOverlayCount - 1)
    lastOverlayClosedAt = Date.now()
  }
}

/**
 * True when a Dialog/Sheet/Drawer `onInteractOutside` event should be ignored
 * because it is really dismissing a portalled dropdown, not the modal — i.e. a
 * dropdown is open, or just closed within this interaction (the grace window is
 * the reliable signal for Radix Select's unmount-first-then-dismiss-modal race).
 *
 * Centralized so every current and future modal gets the same correct behavior.
 */
export function isEventFromOverlay(): boolean {
  return openOverlayCount > 0 || Date.now() - lastOverlayClosedAt < 350
}

/**
 * `onOpenAutoFocus` guard for popovers that open on a search input (the
 * option-list comboboxes): on touch devices auto-focusing it pops the
 * keyboard over the very options the user came to browse, so skip the
 * autofocus there — the search field is still one tap away.
 */
export function preventAutoFocusOnTouch(event: Event): void {
  if (window.matchMedia("(pointer: coarse)").matches) {
    event.preventDefault()
  }
}

/** Ethiopian Birr, always two decimals with thousands separators. */
export function formatETB(value: string | number | null | undefined): string {
  const n = typeof value === "string" ? Number(value) : (value ?? 0)
  return `${new Intl.NumberFormat("en-ET", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0)} ETB`
}
