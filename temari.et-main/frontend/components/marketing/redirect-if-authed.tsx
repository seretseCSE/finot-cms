"use client"

import { useRouter } from "next/navigation"
import { useEffect } from "react"

import { useAuth } from "@/lib/auth/auth-context"

/**
 * Homepage leaf: signed-in visitors skip the marketing home and land in the
 * app (same destination as the login bounce and the nav "Open app" CTA).
 * Relationship-only accounts are then routed to /me by the dashboard.
 */
export function RedirectIfAuthed() {
  const { user, loading } = useAuth()
  const router = useRouter()

  useEffect(() => {
    if (!loading && user) router.replace("/dashboard")
  }, [loading, user, router])

  return null
}
