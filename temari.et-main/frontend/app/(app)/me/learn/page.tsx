"use client"

import { useRouter, useSearchParams } from "next/navigation"
import { Suspense, useEffect } from "react"

import { Skeleton } from "@/components/ui/skeleton"

/**
 * Legacy /me/learn — the nested-tab classwork page was split into first-class
 * pages (/me/assignments, /me/exams, /me/materials). Old deep links keep
 * working via their tab parameter.
 */
function LearnRedirect() {
  const router = useRouter()
  const params = useSearchParams()

  useEffect(() => {
    const tab = params.get("tab")
    const target =
      tab === "exams" ? "/me/exams" : tab === "materials" ? "/me/materials" : "/me/assignments"
    router.replace(target)
  }, [params, router])

  return (
    <div className="page-gutter space-y-3 pt-6">
      <Skeleton className="h-24 w-full rounded-2xl" />
    </div>
  )
}

export default function LegacyLearnPage() {
  return (
    <Suspense fallback={null}>
      <LearnRedirect />
    </Suspense>
  )
}
