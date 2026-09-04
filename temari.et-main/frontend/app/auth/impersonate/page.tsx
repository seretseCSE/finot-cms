"use client"

import { useRouter, useSearchParams } from "next/navigation"
import { Suspense, useEffect, useRef } from "react"
import { toast } from "sonner"

import { apiFetch, setActiveContext, setToken } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import type { Membership, User } from "@/lib/types"

function ImpersonateContent() {
  const searchParams = useSearchParams()
  const router = useRouter()
  const { refresh } = useAuth()
  const attempted = useRef(false)

  useEffect(() => {
    if (attempted.current) return
    attempted.current = true

    const token = searchParams.get("token")
    if (!token) {
      toast.error("Invalid impersonation link.")
      router.replace("/login")
      return
    }

    apiFetch<{ data: User; meta: { token: string } }>("/auth/impersonate", {
      method: "POST",
      body: { token },
      anonymous: true,
    })
      .then(async (res) => {
        setToken(res.meta.token)
        const primary: Membership | undefined =
          res.data.memberships?.find((m) => m.is_active) ?? res.data.memberships?.[0]
        setActiveContext(primary?.school_id ?? null, primary?.branch_id ?? null)
        // Sync the in-memory auth state before navigating so AppShell
        // doesn't see user=null and redirect back to /login.
        await refresh()
        toast.success(`Logged in as ${res.data.name}`)
        router.replace("/dashboard")
      })
      .catch((err) => {
        toast.error(err?.message ?? "Impersonation failed — link may have expired.")
        router.replace("/login")
      })
  }, [searchParams, router, refresh])

  return (
    <div className="flex min-h-svh items-center justify-center">
      <p className="text-muted-foreground text-sm">Authenticating…</p>
    </div>
  )
}

export default function ImpersonatePage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-svh items-center justify-center">
          <p className="text-muted-foreground text-sm">Loading…</p>
        </div>
      }
    >
      <ImpersonateContent />
    </Suspense>
  )
}
