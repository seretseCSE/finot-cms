"use client"

import { KeyRound, UserCheck, UserX } from "lucide-react"
import Link from "next/link"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"

interface LoginClaim {
  id: number
  created_at: string
  claimant: { name: string | null; phone: string | null }
  student: {
    id: number | null
    full_name: string | null
    public_id: string | null
    primary_phone: string | null
  }
}

/**
 * Registrar queue for self-signup student-ID claims: someone signed up with a
 * phone the school has no record of and entered a student's Temari ID. Renders
 * nothing while the queue is empty — the button only appears when there is
 * something to review.
 */
export function LoginClaimsButton({ refreshKey }: { refreshKey?: string }) {
  const { t } = useTranslation("students")
  const [claims, setClaims] = useState<LoginClaim[]>([])
  const [open, setOpen] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)

  const load = useCallback(() => {
    apiFetch<{ data: LoginClaim[] }>("/account-link-requests")
      .then((res) => setClaims(res.data))
      .catch(() => setClaims([]))
  }, [])

  // refreshKey re-runs the fetch when the workspace context switches.
  useEffect(() => {
    load()
  }, [load, refreshKey])

  async function decide(claim: LoginClaim, verb: "approve" | "reject") {
    setBusyId(claim.id)
    try {
      const res = await apiFetch<{ message: string }>(
        `/account-link-requests/${claim.id}/${verb}`,
        { method: "POST" },
      )
      toast.success(res.message)
      setClaims((prev) => prev.filter((c) => c.id !== claim.id))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("loadFailed"))
    } finally {
      setBusyId(null)
    }
  }

  if (claims.length === 0) return null

  return (
    <>
      <Button variant="outline" className="h-11 rounded-full" onClick={() => setOpen(true)}>
        <KeyRound className="size-4" />
        {t("claims.button")}
        <Badge className="ml-1 bg-warning/15 text-warning">{claims.length}</Badge>
      </Button>

      <ResponsiveSheet open={open} onOpenChange={setOpen}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("claims.title")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-3">
            <p className="text-sm text-muted-foreground">{t("claims.hint")}</p>
            {claims.map((claim) => (
              <div key={claim.id} className="space-y-3 rounded-2xl border p-4">
                <div className="space-y-1">
                  <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {t("claims.claimant")}
                  </p>
                  <p className="text-sm font-medium">{claim.claimant.name ?? "—"}</p>
                  {claim.claimant.phone ? (
                    <ContactActionCell
                      value={claim.claimant.phone}
                      kind="phone"
                      name={claim.claimant.name ?? ""}
                      triggerClassName="px-0"
                    />
                  ) : null}
                </div>
                <div className="space-y-1 border-t pt-3">
                  <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {t("claims.student")}
                  </p>
                  <p className="flex flex-wrap items-center gap-2 text-sm font-medium">
                    {claim.student.id ? (
                      <Link href={`/students/${claim.student.id}`} className="hover:underline">
                        {claim.student.full_name}
                      </Link>
                    ) : (
                      claim.student.full_name
                    )}
                    {claim.student.public_id ? (
                      <CopyableId value={claim.student.public_id} className="text-xs" />
                    ) : null}
                  </p>
                  {claim.student.primary_phone ? (
                    <p className="text-xs text-muted-foreground">
                      {t("claims.phoneOnFile")}: {claim.student.primary_phone}
                    </p>
                  ) : (
                    <p className="text-xs text-muted-foreground">{t("claims.noPhoneOnFile")}</p>
                  )}
                </div>
                <div className="flex justify-end gap-2 border-t pt-3">
                  <Button
                    type="button"
                    variant="outline"
                    className="h-9 rounded-full text-destructive"
                    loading={busyId === claim.id}
                    onClick={() => decide(claim, "reject")}
                  >
                    <UserX className="size-4" />
                    {t("claims.reject")}
                  </Button>
                  <Button
                    type="button"
                    className="h-9 rounded-full"
                    loading={busyId === claim.id}
                    onClick={() => decide(claim, "approve")}
                  >
                    <UserCheck className="size-4" />
                    {t("claims.approve")}
                  </Button>
                </div>
              </div>
            ))}
          </ResponsiveSheetBody>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </>
  )
}
