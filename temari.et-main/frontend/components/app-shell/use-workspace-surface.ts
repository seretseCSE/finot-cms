"use client"

import { usePathname } from "next/navigation"
import { useEffect, useState } from "react"

import { useAuth } from "@/lib/auth/auth-context"

import { hasStaffMembership, type WorkspaceSurface } from "./nav-config"

const SURFACE_KEY = "active_surface"

// Routes every workspace shares (chat, AI, notifications, settings, docs):
// they stay in whichever workspace the user opened them from.
const SHARED_PREFIXES = ["/messages", "/ai", "/notifications", "/settings", "/docs"]

function under(pathname: string, prefix: string): boolean {
  return pathname === prefix || pathname.startsWith(`${prefix}/`)
}

/**
 * The active workspace SURFACE — the one nav lane the shell renders.
 *
 * Derivation: `/me/*` is unambiguously the family workspace, `/tutoring/*`
 * the tutor workspace, and every other owned route the staff workspace.
 * Shared routes (messages, AI, settings…) keep the last unambiguous surface
 * (persisted per device), so opening Messages from the family workspace does
 * not flip the nav back to the staff lane. Deep links and stale storage can
 * never present a surface the account doesn't actually hold.
 */
export function useWorkspaceSurface(): WorkspaceSurface {
  const pathname = usePathname() ?? "/"
  const { user } = useAuth()
  const isStaff = hasStaffMembership(user)
  const familyHat = user?.is_parent === true || user?.is_student === true
  const tutorHat = user?.is_tutor === true

  const derived: WorkspaceSurface | null = under(pathname, "/me")
    ? "family"
    : under(pathname, "/tutoring")
      ? "tutor"
      : SHARED_PREFIXES.some((prefix) => under(pathname, prefix))
        ? null
        : "staff"

  const [sticky, setSticky] = useState<WorkspaceSurface | null>(null)
  useEffect(() => {
    if (derived) {
      localStorage.setItem(SURFACE_KEY, derived)
      // eslint-disable-next-line react-hooks/set-state-in-effect -- mirror the persisted surface for shared routes
      setSticky(derived)
    } else {
      const stored = localStorage.getItem(SURFACE_KEY)
      if (stored === "staff" || stored === "family" || stored === "tutor") {
        // eslint-disable-next-line react-hooks/set-state-in-effect -- client-only storage hydration
        setSticky(stored)
      }
    }
  }, [derived])

  // Which surface this account falls back to when nothing (valid) is chosen.
  const fallback: WorkspaceSurface = isStaff
    ? "staff"
    : familyHat
      ? "family"
      : tutorHat
        ? "tutor"
        : "family" // pure B2C learner: their home is the /me learner lane

  let surface = derived ?? sticky ?? fallback
  if (surface === "family" && !familyHat && isStaff) surface = "staff"
  else if (surface === "tutor" && !tutorHat) surface = fallback
  else if (surface === "staff" && !isStaff) surface = fallback
  return surface
}
