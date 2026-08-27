"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { useRouter, useSearchParams } from "next/navigation"
import { Suspense } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { PasswordInput } from "@/components/ui/password-input"
import { ApiError } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"

const schema = z
  .object({
    password: z.string().regex(/^\d{4,}$/, "PIN must be at least 4 digits"),
    confirm: z.string().regex(/^\d{4,}$/, "Please confirm your PIN"),
  })
  .refine((data) => data.password === data.confirm, {
    message: "PINs do not match",
    path: ["confirm"],
  })

type FormValues = z.infer<typeof schema>

function SetPasswordForm() {
  const { t } = useTranslation("auth")
  const { setPassword } = useAuth()
  const router = useRouter()
  const token = useSearchParams().get("token") ?? ""

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { password: "", confirm: "" },
  })
  useLiveValidation(form)

  async function onSubmit(values: FormValues) {
    if (!token) {
      toast.error(t("setPassword.missingToken"))
      return
    }
    try {
      await setPassword(token, values.password, values.confirm)
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
    <Card>
      <CardHeader>
        <CardTitle className="text-xl">{t("setPassword.title")}</CardTitle>
        <CardDescription>{t("setPassword.subtitle")}</CardDescription>
      </CardHeader>
      <CardContent>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <FormField
              control={form.control}
              name="password"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("setPassword.newPassword")}</FormLabel>
                  <FormControl>
                    <PasswordInput inputMode="numeric" autoComplete="new-password" placeholder={t("setPassword.newPasswordPlaceholder")} {...field} />
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
                  <FormLabel>{t("setPassword.confirmPassword")}</FormLabel>
                  <FormControl>
                    <PasswordInput inputMode="numeric" autoComplete="new-password" placeholder={t("setPassword.confirmPasswordPlaceholder")} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <Button type="submit" className="h-11 w-full" loading={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t("setPassword.submitting") : t("setPassword.submit")}
            </Button>
          </form>
        </Form>
      </CardContent>
    </Card>
  )
}

export default function SetPasswordPage() {
  return (
    <Suspense>
      <SetPasswordForm />
    </Suspense>
  )
}
