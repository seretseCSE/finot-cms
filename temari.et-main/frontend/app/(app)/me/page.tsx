"use client"

import { useRouter } from "next/navigation"
import { useEffect } from "react"

import { hasStaffMembership } from "@/components/app-shell/nav-config"
import { Skeleton } from "@/components/ui/skeleton"
import { useAuth } from "@/lib/auth/auth-context"

/**
 * The family Home entry point (ADR-012): one nav item, one URL. Students land
 * on their learning home, parents on the children overview; staff who stray
 * here go back to their dashboard, and hat-less B2C learners go to their
 * exam-prep home (never /dashboard — that would ping-pong them back here).
 */
export default function MeHomePage() {
  const { user } = useAuth()
  const router = useRouter()

  useEffect(() => {
    if (!user) return
    if (user.is_student) router.replace("/me/student")
    else if (user.is_parent) router.replace("/me/children")
    else if (hasStaffMembership(user)) router.replace("/dashboard")
    else if (user.is_tutor) router.replace("/tutoring")
    else router.replace("/me/exam-prep")
  }, [user, router])

  return (
    <div className="page-gutter space-y-3 pt-6">
      <Skeleton className="h-24 w-full rounded-2xl" />
      <Skeleton className="h-40 w-full rounded-2xl" />
    </div>
  )
}
