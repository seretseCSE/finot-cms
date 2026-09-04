"use client"

import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { CopyableId } from "@/components/ui/copyable-id"
import { PageHeader } from "@/components/ui/page-header"
import { ThemeToggle } from "@/components/ui/theme-toggle"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { CATEGORY_META, FALLBACK_META } from "@/components/notifications/meta"
import { ApiError, apiFetch } from "@/lib/api"
import { LOCALES, useLocale, useTranslation } from "@/lib/i18n"
import type { Locale, NotificationCategory } from "@/lib/types"
import { cn } from "@/lib/utils"

interface Preferences {
  preferred_language: Locale
  notify_via_sms: boolean
  notify_via_email: boolean
  notify_via_push: boolean
  /** Per-category channel overrides — absent key = category fully on. */
  notification_preferences: Partial<Record<string, Partial<Record<"sms" | "email" | "push", boolean>>>>
  notification_categories: NotificationCategory[]
  phone: string
  email: string | null
  public_id: string | null
}

const LOCALE_LABELS: Record<Locale, string> = { en: "English", am: "አማርኛ", om: "Afaan Oromoo" }

const CHANNELS = [
  ["notify_via_sms", "sms", "smsHint"],
  ["notify_via_email", "email", "emailHint"],
  ["notify_via_push", "push", "pushHint"],
] as const

export default function SettingsPage() {
  const { t } = useTranslation("me")
  const { t: tc } = useTranslation("common")
  const { setLocale } = useLocale()

  const [prefs, setPrefs] = useState<Preferences | null>(null)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: Preferences }>("/me/preferences")
      .then((res) => !cancelled && setPrefs(res.data))
      .catch(() => !cancelled && setPrefs(null))
    return () => {
      cancelled = true
    }
  }, [])

  async function update(patch: Partial<Preferences>) {
    const previous = prefs
    setPrefs((current) => (current ? { ...current, ...patch } : current))
    try {
      const res = await apiFetch<{ data: Preferences }>("/me/preferences", {
        method: "PUT",
        body: patch,
      })
      setPrefs(res.data)
      if (patch.preferred_language) setLocale(patch.preferred_language)
      toast.success(t("settings.saved"))
    } catch (error) {
      setPrefs(previous ?? null)
      toast.error(error instanceof ApiError ? error.message : t("settings.saved"))
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t("settings.title")} description={t("settings.subtitle")} />

      <div className="page-gutter">
        <div className="mx-auto max-w-2xl space-y-4">
          {prefs === null ? (
            <>
              <Skeleton className="h-32 w-full rounded-2xl" />
              <Skeleton className="h-48 w-full rounded-2xl" />
            </>
          ) : (
            <>
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t("settings.account")}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                  {prefs.public_id ? (
                    <div className="flex items-center justify-between gap-4 rounded-xl bg-muted/40 p-3">
                      <div>
                        <p className="font-medium">{t("settings.publicId")}</p>
                        <p className="text-xs text-muted-foreground">{t("settings.publicIdHint")}</p>
                      </div>
                      <CopyableId
                        value={prefs.public_id}
                        className="bg-primary/10 px-3 py-1.5 text-sm font-semibold text-primary hover:bg-primary/15 hover:text-primary"
                      />
                    </div>
                  ) : null}
                  <div className="flex justify-between gap-4">
                    <span className="text-muted-foreground">{t("settings.phone")}</span>
                    <span className="font-medium">{prefs.phone}</span>
                  </div>
                  <div className="flex justify-between gap-4">
                    <span className="text-muted-foreground">{t("settings.emailAddress")}</span>
                    <span className="font-medium">{prefs.email ?? "—"}</span>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t("settings.appearance")}</CardTitle>
                  <p className="text-xs text-muted-foreground">{t("settings.appearanceHint")}</p>
                </CardHeader>
                <CardContent>
                  <ThemeToggle />
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t("settings.language")}</CardTitle>
                  <p className="text-xs text-muted-foreground">{t("settings.languageHint")}</p>
                </CardHeader>
                <CardContent>
                  <div className="flex flex-wrap gap-2">
                    {LOCALES.map((code) => (
                      <button
                        key={code}
                        type="button"
                        onClick={() => update({ preferred_language: code })}
                        className={cn(
                          "h-11 rounded-full border px-5 text-sm font-medium transition-colors",
                          prefs.preferred_language === code
                            ? "border-primary bg-primary/10 text-primary"
                            : "bg-background text-muted-foreground hover:bg-muted",
                        )}
                      >
                        {LOCALE_LABELS[code]}
                      </button>
                    ))}
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t("settings.notifications")}</CardTitle>
                  <p className="text-xs text-muted-foreground">{t("settings.notificationsHint")}</p>
                </CardHeader>
                <CardContent className="space-y-4">
                  {CHANNELS.map(([field, label, hint]) => (
                    <div key={field} className="flex items-center justify-between gap-4">
                      <div>
                        <p className="text-sm font-medium">{t(`settings.${label}`)}</p>
                        <p className="text-xs text-muted-foreground">{t(`settings.${hint}`)}</p>
                      </div>
                      <Switch
                        checked={prefs[field]}
                        onCheckedChange={(checked) => update({ [field]: checked })}
                      />
                    </div>
                  ))}
                </CardContent>
              </Card>

              {/* Per-topic fine-tuning: mute a whole category per channel.
                  Critical alerts (security, money due, child absent) always
                  deliver — the backend pierces these mutes by design. */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t("settings.notificationTopics")}</CardTitle>
                  <p className="text-xs text-muted-foreground">{t("settings.notificationTopicsHint")}</p>
                </CardHeader>
                <CardContent className="space-y-1">
                  <div className="text-muted-foreground grid grid-cols-[1fr_3.5rem_3.5rem] items-center gap-2 px-2 pb-1 text-[11px] font-semibold uppercase tracking-wider">
                    <span />
                    <span className="text-center">{t("settings.sms")}</span>
                    <span className="text-center">{t("settings.email")}</span>
                  </div>
                  {prefs.notification_categories.map((category) => {
                    const meta = CATEGORY_META[category] ?? FALLBACK_META
                    const Icon = meta.icon
                    const row = prefs.notification_preferences?.[category] ?? {}
                    const toggle = (channel: "sms" | "email", checked: boolean) =>
                      update({
                        notification_preferences: {
                          ...prefs.notification_preferences,
                          [category]: { ...row, [channel]: checked },
                        },
                      })
                    return (
                      <div
                        key={category}
                        className="grid grid-cols-[1fr_3.5rem_3.5rem] items-center gap-2 rounded-xl px-2 py-2 hover:bg-muted/40"
                      >
                        <div className="flex min-w-0 items-center gap-2.5">
                          <span
                            className={cn("flex size-7 shrink-0 items-center justify-center rounded-full", meta.bubble)}
                            aria-hidden
                          >
                            <Icon className="size-3.5" strokeWidth={2} />
                          </span>
                          <span className="truncate text-sm font-medium">
                            {tc(`notifications.categories.${category}`)}
                          </span>
                        </div>
                        <div className="flex justify-center">
                          <Switch
                            checked={row.sms !== false}
                            disabled={!prefs.notify_via_sms}
                            onCheckedChange={(checked) => toggle("sms", checked)}
                            aria-label={`${tc(`notifications.categories.${category}`)} — ${t("settings.sms")}`}
                          />
                        </div>
                        <div className="flex justify-center">
                          <Switch
                            checked={row.email !== false}
                            disabled={!prefs.notify_via_email}
                            onCheckedChange={(checked) => toggle("email", checked)}
                            aria-label={`${tc(`notifications.categories.${category}`)} — ${t("settings.email")}`}
                          />
                        </div>
                      </div>
                    )
                  })}
                  <p className="text-muted-foreground px-2 pt-2 text-xs">
                    {t("settings.criticalAlwaysOn")}
                  </p>
                </CardContent>
              </Card>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
