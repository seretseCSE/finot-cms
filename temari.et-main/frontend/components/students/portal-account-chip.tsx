"use client"

import { KeyRound } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { useTranslation } from "@/lib/i18n"
import type { PortalAccount } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtDate } from "@/lib/dates"

/**
 * "Can this person log in?" at a glance — shown on the student rail and on
 * guardian cards (PowerSchool-style: account status lives on the record staff
 * already work with, not in a separate admin screen).
 *
 *   no account          → muted "No login"
 *   invite never used   → primary "Invite sent" (the account EXISTS — created
 *                         at registration with a setup SMS; only the PIN is
 *                         missing. Rendering this as "No login" made staff
 *                         think no parent account was being created.)
 *   never signed in     → amber "Never logged in" (PIN set, no sign-in yet)
 *   active              → green "Active · last sign-in"
 *   inactive/banned     → red status label
 */
export function PortalAccountChip({ account }: { account?: PortalAccount | null }) {
  const { t } = useTranslation("students")

  const [tone, label] =
    account == null
      ? ["bg-muted text-muted-foreground", t("detail.noLogin")]
      : account.has_password === false
        ? ["bg-primary/10 text-primary", t("detail.inviteSentChip")]
        : account.status !== "active"
          ? ["bg-destructive/10 text-destructive", account.status_label]
          : account.last_login_at == null
            ? ["bg-warning/10 text-warning", t("detail.neverLoggedIn")]
            : [
                "bg-success/10 text-success",
                `${t("detail.active")} · ${fmtDate(account.last_login_at)}`,
              ]

  return (
    <Badge variant="secondary" className={cn("gap-1", tone)}>
      <KeyRound className="size-3" />
      {label}
    </Badge>
  )
}
