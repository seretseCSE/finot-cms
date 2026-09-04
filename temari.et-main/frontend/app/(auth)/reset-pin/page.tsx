"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { ArrowLeft } from "lucide-react"
import Link from "next/link"
import { useRouter, useSearchParams } from "next/navigation"
import { Suspense } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Button } from "@/components/ui/button"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { PasswordInput } from "@/components/ui/password-input"
import { ApiError } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"

const schema = z
  .object({
    otp: z.string().length(6, "Enter the 6-digit code").regex(/^\d+$/, "Digits only"),
    password: z.string().regex(/^\d{4,}$/, "PIN must be at least 4 digits"),
    confirm: z.string().regex(/^\d{4,}$/, "Please confirm your PIN"),
  })
  .refine((data) => data.password === data.confirm, {
    message: "PINs do not match",
    path: ["confirm"],
  })

type FormValues = z.infer<typeof schema>

function ResetPasswordForm() {
  const { t } = useTranslation("auth")
  const { resetPassword } = useAuth()
  const router = useRouter()
  const params = useSearchParams()
  // Phone or student ID, carried over from the forgot-PIN step.
  const identifier = params.get("identifier") ?? params.get("phone") ?? ""

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { otp: "", password: "", confirm: "" },
  })
  useLiveValidation(form)

  async function onSubmit(values: FormValues) {
    if (!identifier) {
      toast.error("Account identifier missing. Please start over.")
      router.replace("/forgot-pin")
      return
    }
    try {
      await resetPassword(identifier, values.otp, values.password, values.confirm)
      router.replace("/dashboard")
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof FormValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error("Something went wrong.")
      }
    }
  }

  return (
    <div className="flex flex-col">
      <div className="mb-8">
        <h2 className="text-2xl font-bold tracking-tight">{t("resetPassword.title")}</h2>
        <p className="text-muted-foreground mt-1 text-sm">{t("resetPassword.subtitle")}</p>
      </div>

      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-5">
          <FormField
            control={form.control}
            name="otp"
            render={({ field }) => (
              <FormItem>
                <FormLabel className="text-sm font-medium">{t("resetPassword.otp")}</FormLabel>
                <FormControl>
                  <Input
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={6}
                    placeholder={t("resetPassword.otpPlaceholder")}
                    className="bg-muted/60 h-14 rounded-xl border-0 px-4 text-center text-xl font-semibold tracking-[0.5em] focus-visible:ring-2 focus-visible:ring-primary/30"
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="password"
            render={({ field }) => (
              <FormItem>
                <FormLabel className="text-sm font-medium">{t("resetPassword.newPassword")}</FormLabel>
                <FormControl>
                  <PasswordInput
                    inputMode="numeric"
                    autoComplete="new-password"
                    placeholder={t("resetPassword.newPasswordPlaceholder")}
                    className="bg-muted/60 h-14 rounded-xl border-0 px-4 text-base focus-visible:ring-2 focus-visible:ring-primary/30"
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="confirm"
            render={({ field }) => (
              <FormItem>
                <FormLabel className="text-sm font-medium">{t("resetPassword.confirmPassword")}</FormLabel>
                <FormControl>
                  <PasswordInput
                    inputMode="numeric"
                    autoComplete="new-password"
                    placeholder={t("resetPassword.confirmPasswordPlaceholder")}
                    className="bg-muted/60 h-14 rounded-xl border-0 px-4 text-base focus-visible:ring-2 focus-visible:ring-primary/30"
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <Button
            type="submit"
            size="lg"
            className="mt-2 h-13 w-full text-base font-semibold shadow-sm"
            disabled={form.formState.isSubmitting}
          >
            {form.formState.isSubmitting ? (
              <span className="flex items-center gap-2">
                <span className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                {t("resetPassword.submitting")}
              </span>
            ) : (
              t("resetPassword.submit")
            )}
          </Button>
        </form>
      </Form>

      <p className="text-muted-foreground mt-6 text-center text-sm">
        <Link href="/login" className="text-primary inline-flex items-center gap-1 font-medium hover:underline">
          <ArrowLeft className="size-3.5" />
          {t("resetPassword.backToLogin")}
        </Link>
      </p>
    </div>
  )
}

export default function ResetPasswordPage() {
  return (
    <Suspense>
      <ResetPasswordForm />
    </Suspense>
  )
}
