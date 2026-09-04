"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { ArrowLeft } from "lucide-react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { IdentifierInput } from "@/components/auth/identifier-input"
import { Button } from "@/components/ui/button"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { ApiError } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import { isPublicId, loginIdentifier } from "@/lib/validators"

const schema = z.object({
  identifier: loginIdentifier(),
})

type FormValues = z.infer<typeof schema>

export default function ForgotPasswordPage() {
  const { t } = useTranslation("auth")
  const { forgotPassword } = useAuth()
  const router = useRouter()
  const [sent, setSent] = useState(false)
  const [identifier, setIdentifier] = useState("")

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { identifier: "" },
  })
  useLiveValidation(form)

  async function onSubmit(values: FormValues) {
    try {
      await forgotPassword(values.identifier)
      setIdentifier(values.identifier)
      setSent(true)
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

  if (sent) {
    return (
      <div className="flex flex-col">
        <div className="mb-8">
          <h2 className="text-2xl font-bold tracking-tight">{t("forgotPassword.title")}</h2>
          <p className="text-muted-foreground mt-1 text-sm">
            {isPublicId(identifier) ? t("forgotPassword.successStudentId") : t("forgotPassword.success")}
          </p>
        </div>

        <Button
          size="lg"
          className="h-13 w-full text-base font-semibold shadow-sm"
          onClick={() => router.push(`/reset-pin?identifier=${encodeURIComponent(identifier)}`)}
        >
          {t("resetPassword.submit")}
        </Button>

        <p className="text-muted-foreground mt-6 text-center text-sm">
          <Link href="/login" className="text-primary inline-flex items-center gap-1 font-medium hover:underline">
            <ArrowLeft className="size-3.5" />
            {t("resetPassword.backToLogin")}
          </Link>
        </p>
      </div>
    )
  }

  return (
    <div className="flex flex-col">
      <div className="mb-8">
        <h2 className="text-2xl font-bold tracking-tight">{t("forgotPassword.title")}</h2>
        <p className="text-muted-foreground mt-1 text-sm">{t("forgotPassword.subtitle")}</p>
      </div>

      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-5">
          <FormField
            control={form.control}
            name="identifier"
            render={({ field }) => (
              <FormItem>
                <FormLabel className="text-sm font-medium">{t("login.identifier")}</FormLabel>
                <FormControl>
                  <IdentifierInput
                    id="forgot-identifier"
                    name={field.name}
                    value={field.value}
                    onChange={field.onChange}
                    onBlur={field.onBlur}
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
                {t("forgotPassword.submitting")}
              </span>
            ) : (
              t("forgotPassword.submit")
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
