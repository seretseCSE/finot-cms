"use client"

import { IdCard, KeyRound, Send, Smartphone } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { PortalAccountChip } from "@/components/students/portal-account-chip"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { PhoneInput } from "@/components/ui/phone-input"
import { Label } from "@/components/ui/label"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { PortalAccount } from "@/lib/types"

interface Props {
  kind: "student" | "parent"
  personId: number
  personName: string
  account?: PortalAccount | null
  canManage: boolean
  /** Prefill for the student add-login dialog (their own number, if on file). */
  defaultPhone?: string | null
  onChanged?: () => void
}

/**
 * The "can this person log in?" block on profile rails: status chip, the
 * account's phone, and the staff actions — create a student's login (explicit
 * phone, SMS setup link) or re-send an unused setup link. Parents always have
 * a provisioned account, so their only action is the re-invite.
 */
export function PortalAccountSection({
  kind,
  personId,
  personName,
  account,
  canManage,
  defaultPhone,
  onChanged,
}: Props) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const [addOpen, setAddOpen] = useState(false)
  const [phone, setPhone] = useState(defaultPhone ?? "")
  /** "phone" = SMS-keyed login; "student_id" = phone-less ID + PIN login. */
  const [mode, setMode] = useState<"phone" | "student_id">("phone")
  const [saving, setSaving] = useState(false)
  const [sending, setSending] = useState(false)

  const inviteUrl =
    kind === "student"
      ? `/students/${personId}/portal-account/invite`
      : `/parents/${personId}/portal-account/invite`

  async function createLogin() {
    setSaving(true)
    try {
      await apiFetch(`/students/${personId}/portal-account`, {
        method: "POST",
        body: { phone: mode === "phone" ? phone : null },
      })
      toast.success(t("detail.loginCreated"))
      setAddOpen(false)
      onChanged?.()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("loadFailed"))
    } finally {
      setSaving(false)
    }
  }

  async function sendInvite() {
    setSending(true)
    try {
      await apiFetch(inviteUrl, { method: "POST" })
      toast.success(t("detail.inviteSent"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("loadFailed"))
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center gap-1.5">
        <PortalAccountChip account={account} />
      </div>

      {account?.phone ? (
        <ContactActionCell
          value={account.phone}
          kind="phone"
          name={personName}
          triggerClassName="px-0"
        />
      ) : null}

      {account != null && account.login_mode === "student_id" ? (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <IdCard className="size-3.5 shrink-0" />
          {t("detail.signsInWithId")}
        </p>
      ) : null}

      {canManage && kind === "student" && account == null ? (
        <Button
          type="button"
          variant="outline"
          className="h-9 w-full rounded-full"
          onClick={() => {
            setPhone(defaultPhone ?? "")
            setAddOpen(true)
          }}
        >
          <KeyRound className="size-4" />
          {t("detail.addLogin")}
        </Button>
      ) : null}

      {canManage && account != null && account.has_password === false ? (
        <Button
          type="button"
          variant="outline"
          className="h-9 w-full rounded-full"
          onClick={sendInvite}
          loading={sending}
        >
          <Send className="size-4" />
          {sending ? t("detail.sendingInvite") : t("detail.sendInvite")}
        </Button>
      ) : null}

      <Dialog open={addOpen} onOpenChange={setAddOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t("detail.addLoginTitle")}</DialogTitle>
            <DialogDescription>{t("detail.addLoginHint")}</DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-2">
              {(
                [
                  ["phone", Smartphone, t("detail.loginModePhone")],
                  ["student_id", IdCard, t("detail.loginModeId")],
                ] as const
              ).map(([value, Icon, label]) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => setMode(value)}
                  className={`flex items-center justify-center gap-2 rounded-xl border p-3 text-sm font-medium transition-colors ${
                    mode === value
                      ? "border-primary bg-primary/10 text-primary"
                      : "text-muted-foreground hover:bg-muted"
                  }`}
                >
                  <Icon className="size-4" />
                  {label}
                </button>
              ))}
            </div>

            {mode === "phone" ? (
              <div className="space-y-2">
                <Label htmlFor="portal-phone">{t("detail.loginPhone")}</Label>
                <PhoneInput
                  id="portal-phone"
                  value={phone}
                  onChange={setPhone}
                  placeholder="09xxxxxxxx"
                  autoFocus
                />
                <p className="text-xs text-muted-foreground">
                  {t("detail.loginPhoneNote")}
                </p>
              </div>
            ) : (
              <p className="rounded-xl bg-muted/50 p-3 text-xs leading-relaxed text-muted-foreground">
                {t("detail.loginModeIdNote")}
              </p>
            )}
          </div>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              className="rounded-full"
              onClick={() => setAddOpen(false)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              type="button"
              className="rounded-full"
              onClick={createLogin}
              loading={saving} disabled={(mode === "phone" && phone.trim() === "")}
            >
              {saving ? t("detail.creatingLogin") : t("detail.createLogin")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
