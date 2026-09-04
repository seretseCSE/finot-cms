"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { ArrowRight, KeyRound } from "lucide-react"
import Link from "next/link"
import { useRouter, useSearchParams } from "next/navigation"
import { Suspense, useEffect } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { IdentifierInput } from "@/components/auth/identifier-input"
import { Button } from "@/components/ui/button"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { PasswordInput } from "@/components/ui/password-input"
import { ApiError } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import { isPublicId, loginIdentifier } from "@/lib/validators"

const schema = z.object({
  identifier: loginIdentifier(),
  password: z.string().regex(/^\d{4,}$/, "PIN must be at least 4 digits"),
})

type FormValues = z.infer<typeof schema>

function LoginForm() {
  const { t } = useTranslation("auth")
  const { login, user, loading } = useAuth()
  const router = useRouter()
  // The QR on a student ID card deep-links here with ?id=H8R6WV — the student
  // only types their PIN.
  const prefillId = useSearchParams().get("id") ?? ""
  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      identifier: isPublicId(prefillId) ? prefillId.trim().toUpperCase() : "",
      password: "",
    },
  })
  useLiveValidation(form)

  useEffect(() => {
    if (!loading && user) router.replace("/dashboard")
  }, [loading, user, router])

  async function onSubmit(values: FormValues) {
    try {
      await login(values.identifier, values.password)
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
      {/* Heading */}
      <div className="mb-7">
        <h2 className="font-display text-2xl font-bold tracking-tight">{t("login.title")}</h2>
        <p className="text-muted-foreground mt-1.5 text-sm">{t("login.subtitle")}</p>
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
                    id="login-identifier"
                    name={field.name}
                    value={field.value}
                    onChange={field.onChange}
                    onBlur={field.onBlur}
                    autoFocus={!isPublicId(prefillId)}
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
                <div className="flex items-center justify-between">
                  <FormLabel className="text-sm font-medium">{t("login.password")}</FormLabel>
                  <Link
                    href="/forgot-pin"
                    className="text-primary text-xs font-medium hover:underline"
                    tabIndex={-1}
                  >
                    {t("login.forgotPassword")}
                  </Link>
                </div>
                <div className="relative">
                  <KeyRound className="text-muted-foreground pointer-events-none absolute left-4 top-1/2 z-10 size-4.5 -translate-y-1/2" />
                  <FormControl>
                    <PasswordInput
                      inputMode="numeric"
                      autoComplete="current-password"
                      autoFocus={isPublicId(prefillId)}
                      placeholder={t("login.passwordPlaceholder")}
                      className="bg-muted/60 h-13 rounded-xl border-0 pl-11 text-base tracking-widest focus-visible:ring-2 focus-visible:ring-primary/30"
                      {...field}
                    />
                  </FormControl>
                </div>
                <FormMessage />
              </FormItem>
            )}
          />

          <Button
            type="submit"
            size="lg"
            className="pressable mt-1 h-13 w-full text-base font-semibold shadow-sm"
            disabled={form.formState.isSubmitting}
          >
            {form.formState.isSubmitting ? (
              <span className="flex items-center gap-2">
                <span className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                {t("login.submitting")}
              </span>
            ) : (
              <span className="flex items-center gap-2">
                {t("login.submit")}
                <ArrowRight className="size-4.5" />
              </span>
            )}
          </Button>
        </form>
      </Form>

      {/* Self-signup: activation for school-registered families, public
          exam-prep accounts for everyone else. */}
      <p className="text-muted-foreground mt-6 text-center text-sm">
        {t("login.noAccount")}{" "}
        <Link href="/signup" className="text-primary font-medium hover:underline">
          {t("login.signUp")}
        </Link>
      </p>

      {/* Footer */}
      <p className="text-muted-foreground mt-7 text-center text-xs leading-relaxed">
        {t("brand.agree")}{" "}
        <Link href="/terms" className="text-foreground underline underline-offset-2">
          {t("brand.terms")}
        </Link>{" "}
        {t("brand.and")}{" "}
        <Link href="/privacy" className="text-foreground underline underline-offset-2">
          {t("brand.privacy")}
        </Link>
      </p>
    </div>
  )
}

export default function LoginPage() {
  return (
    <Suspense>
      <LoginForm />
    </Suspense>
  )
}
