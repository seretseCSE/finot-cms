"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { ArrowLeft, ArrowRight, IdCard, KeyRound, Smartphone, UserRound } from "lucide-react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { PasswordInput } from "@/components/ui/password-input"
import { Switch } from "@/components/ui/switch"
import { ApiError } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import { ethPhone } from "@/lib/validators"

const phoneSchema = z.object({
  phone: ethPhone(),
})

const verifySchema = z
  .object({
    otp: z.string().length(6, "Enter the 6-digit code").regex(/^\d+$/, "Digits only"),
    name: z.string().trim().min(2, "Enter your full name"),
    password: z.string().regex(/^\d{4,}$/, "PIN must be at least 4 digits"),
    confirm: z.string().regex(/^\d{4,}$/, "Please confirm your PIN"),
    student_public_id: z.string().trim().max(12).optional().or(z.literal("")),
  })
  .refine((data) => data.password === data.confirm, {
    message: "PINs do not match",
    path: ["confirm"],
  })

type PhoneValues = z.infer<typeof phoneSchema>
type VerifyValues = z.infer<typeof verifySchema>

/**
 * Public self-signup: phone → SMS code → name + PIN. The verified phone is
 * the identity: if the school already registered this number the account
 * simply activates with the school attached; otherwise it becomes a public
 * exam-prep account. The optional Temari ID either auto-links (phone on the
 * student record) or files a claim the school approves — never an instant
 * link by ID alone.
 */
export default function SignupPage() {
  const { t, locale } = useTranslation("auth")
  const { requestSignupOtp, signup, login } = useAuth()
  const router = useRouter()

  const [step, setStep] = useState<"phone" | "verify">("phone")
  const [phone, setPhone] = useState("")
  const [hasId, setHasId] = useState(false)

  // The phone already has a usable account (409 account_exists): offer an
  // inline sign-in — the number is already typed, only the PIN is missing.
  const [existingOpen, setExistingOpen] = useState(false)
  const [existingPin, setExistingPin] = useState("")
  const [signingIn, setSigningIn] = useState(false)
  const [existingError, setExistingError] = useState<string | null>(null)

  async function onExistingSignIn() {
    if (!/^\d{4,}$/.test(existingPin)) {
      setExistingError(t("login.passwordPlaceholder"))
      return
    }
    setSigningIn(true)
    setExistingError(null)
    try {
      await login(phoneForm.getValues("phone"), existingPin)
      router.replace("/dashboard")
    } catch (error) {
      setExistingError(
        error instanceof ApiError ? error.message : t("signup.genericError"),
      )
      setSigningIn(false)
    }
  }

  const phoneForm = useForm<PhoneValues>({
    resolver: zodResolver(phoneSchema),
    defaultValues: { phone: "" },
  })
  useLiveValidation(phoneForm)

  const verifyForm = useForm<VerifyValues>({
    resolver: zodResolver(verifySchema),
    defaultValues: { otp: "", name: "", password: "", confirm: "", student_public_id: "" },
  })
  useLiveValidation(verifyForm)

  async function onRequestOtp(values: PhoneValues) {
    try {
      await requestSignupOtp(values.phone, locale)
      setPhone(values.phone)
      setStep("verify")
      toast.success(t("signup.otpSent"))
    } catch (error) {
      if (error instanceof ApiError) {
        if (error.code === "account_exists") {
          setExistingPin("")
          setExistingError(null)
          setExistingOpen(true)
          return
        }
        for (const [field, messages] of Object.entries(error.errors)) {
          phoneForm.setError(field as keyof PhoneValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error("Something went wrong.")
      }
    }
  }

  async function onSignup(values: VerifyValues) {
    try {
      const linked = await signup({
        phone,
        otp: values.otp,
        name: values.name,
        password: values.password,
        password_confirmation: values.confirm,
        student_public_id: hasId && values.student_public_id ? values.student_public_id : undefined,
        preferred_language: locale,
      })
      if (linked === "claim_pending") {
        toast.success(t("signup.claimPending"), { duration: 9000 })
      } else if (linked === "parent" || linked === "student") {
        toast.success(t("signup.linkedWelcome"))
      }
      router.replace("/dashboard")
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          verifyForm.setError(field as keyof VerifyValues, {
            type: "server",
            message: messages[0],
          })
        }
        if (error.errors.phone?.length) toast.error(error.errors.phone[0])
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error("Something went wrong.")
      }
    }
  }

  const inputClass =
    "bg-muted/60 h-13 rounded-xl border-0 pl-11 pr-4 text-base focus-visible:ring-2 focus-visible:ring-primary/30"

  return (
    <div className="flex flex-col">
      <div className="mb-7">
        <h2 className="font-display text-2xl font-bold tracking-tight">{t("signup.title")}</h2>
        <p className="text-muted-foreground mt-1.5 text-sm">
          {step === "phone" ? t("signup.subtitle") : t("signup.verifySubtitle", { phone })}
        </p>
      </div>

      {step === "phone" ? (
        <Form {...phoneForm}>
          {/* key: the two steps render structurally identical trees, so
              without it React recycles the first FormField's Controller
              across forms — it stays bound to the OLD form's control and
              the field silently stops accepting input. */}
          <form
            key="phone"
            onSubmit={phoneForm.handleSubmit(onRequestOtp)}
            className="flex flex-col gap-5"
          >
            <FormField
              control={phoneForm.control}
              name="phone"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="text-sm font-medium">{t("signup.phone")}</FormLabel>
                  <div className="relative">
                    <Smartphone className="text-muted-foreground pointer-events-none absolute left-4 top-1/2 size-4.5 -translate-y-1/2" />
                    <FormControl>
                      <Input
                        type="tel"
                        inputMode="tel"
                        autoComplete="tel"
                        placeholder={t("signup.phonePlaceholder")}
                        className={inputClass}
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
              disabled={phoneForm.formState.isSubmitting}
            >
              {phoneForm.formState.isSubmitting ? (
                <span className="flex items-center gap-2">
                  <span className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                  {t("signup.sendingCode")}
                </span>
              ) : (
                <span className="flex items-center gap-2">
                  {t("signup.sendCode")}
                  <ArrowRight className="size-4.5" />
                </span>
              )}
            </Button>
          </form>
        </Form>
      ) : (
        <Form {...verifyForm}>
          <form
            key="verify"
            onSubmit={verifyForm.handleSubmit(onSignup)}
            className="flex flex-col gap-5"
          >
            <FormField
              control={verifyForm.control}
              name="otp"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="text-sm font-medium">{t("signup.otp")}</FormLabel>
                  <FormControl>
                    <Input
                      inputMode="numeric"
                      autoComplete="one-time-code"
                      placeholder={t("signup.otpPlaceholder")}
                      className="bg-muted/60 h-13 rounded-xl border-0 px-4 text-center text-lg tracking-[0.5em] focus-visible:ring-2 focus-visible:ring-primary/30"
                      maxLength={6}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={verifyForm.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="text-sm font-medium">{t("signup.name")}</FormLabel>
                  <div className="relative">
                    <UserRound className="text-muted-foreground pointer-events-none absolute left-4 top-1/2 size-4.5 -translate-y-1/2" />
                    <FormControl>
                      <Input
                        autoComplete="name"
                        placeholder={t("signup.namePlaceholder")}
                        className={inputClass}
                        {...field}
                      />
                    </FormControl>
                  </div>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={verifyForm.control}
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-sm font-medium">{t("signup.pin")}</FormLabel>
                    <div className="relative">
                      <KeyRound className="text-muted-foreground pointer-events-none absolute left-4 top-1/2 z-10 size-4.5 -translate-y-1/2" />
                      <FormControl>
                        <PasswordInput
                          inputMode="numeric"
                          autoComplete="new-password"
                          placeholder={t("signup.pinPlaceholder")}
                          className="bg-muted/60 h-13 rounded-xl border-0 pl-11 text-base tracking-widest focus-visible:ring-2 focus-visible:ring-primary/30"
                          {...field}
                        />
                      </FormControl>
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={verifyForm.control}
                name="confirm"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-sm font-medium">{t("signup.confirmPin")}</FormLabel>
                    <div className="relative">
                      <KeyRound className="text-muted-foreground pointer-events-none absolute left-4 top-1/2 z-10 size-4.5 -translate-y-1/2" />
                      <FormControl>
                        <PasswordInput
                          inputMode="numeric"
                          autoComplete="new-password"
                          placeholder={t("signup.confirmPinPlaceholder")}
                          className="bg-muted/60 h-13 rounded-xl border-0 pl-11 text-base tracking-widest focus-visible:ring-2 focus-visible:ring-primary/30"
                          {...field}
                        />
                      </FormControl>
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            {/* Optional Temari ID — off by default. Most people never need
                it: a phone the school registered links automatically. */}
            <div className="rounded-2xl border p-4">
              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <p className="text-sm font-medium">{t("signup.haveId")}</p>
                  <p className="text-muted-foreground mt-0.5 text-xs">{t("signup.haveIdHint")}</p>
                </div>
                <Switch checked={hasId} onCheckedChange={setHasId} />
              </div>
              {hasId ? (
                <FormField
                  control={verifyForm.control}
                  name="student_public_id"
                  render={({ field }) => (
                    <FormItem className="mt-3">
                      <FormLabel className="text-sm font-medium">{t("signup.temariId")}</FormLabel>
                      <div className="relative">
                        <IdCard className="text-muted-foreground pointer-events-none absolute left-4 top-1/2 size-4.5 -translate-y-1/2" />
                        <FormControl>
                          <Input
                            placeholder={t("signup.temariIdPlaceholder")}
                            className={`${inputClass} uppercase`}
                            maxLength={12}
                            {...field}
                          />
                        </FormControl>
                      </div>
                      <p className="text-muted-foreground text-xs">{t("signup.temariIdNote")}</p>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ) : null}
            </div>

            <Button
              type="submit"
              size="lg"
              className="pressable mt-1 h-13 w-full text-base font-semibold shadow-sm"
              disabled={verifyForm.formState.isSubmitting}
            >
              {verifyForm.formState.isSubmitting ? (
                <span className="flex items-center gap-2">
                  <span className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                  {t("signup.submitting")}
                </span>
              ) : (
                <span className="flex items-center gap-2">
                  {t("signup.submit")}
                  <ArrowRight className="size-4.5" />
                </span>
              )}
            </Button>

            <button
              type="button"
              onClick={() => setStep("phone")}
              className="text-muted-foreground inline-flex items-center justify-center gap-1.5 text-sm hover:text-foreground"
            >
              <ArrowLeft className="size-4" />
              {t("signup.changePhone")}
            </button>
          </form>
        </Form>
      )}

      <p className="text-muted-foreground mt-7 text-center text-sm">
        {t("signup.haveAccount")}{" "}
        <Link href="/login" className="text-primary font-medium hover:underline">
          {t("signup.signIn")}
        </Link>
      </p>

      {/* This phone already has an account — sign in right here. */}
      <Dialog open={existingOpen} onOpenChange={setExistingOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t("signup.existingTitle")}</DialogTitle>
            <DialogDescription>
              {t("signup.existingBody", { phone: phoneForm.getValues("phone") })}
            </DialogDescription>
          </DialogHeader>

          <div className="mt-1">
            <div className="flex items-center justify-between">
              <label htmlFor="existing-pin" className="text-sm font-medium">
                {t("login.password")}
              </label>
              <Link
                href="/forgot-pin"
                className="text-primary text-xs font-medium hover:underline"
              >
                {t("login.forgotPassword")}
              </Link>
            </div>
            <div className="relative mt-2">
              <KeyRound className="text-muted-foreground pointer-events-none absolute left-4 top-1/2 z-10 size-4.5 -translate-y-1/2" />
              <PasswordInput
                id="existing-pin"
                inputMode="numeric"
                autoComplete="current-password"
                autoFocus
                placeholder={t("login.passwordPlaceholder")}
                className="bg-muted/60 h-13 rounded-xl border-0 pl-11 text-base tracking-widest focus-visible:ring-2 focus-visible:ring-primary/30"
                value={existingPin}
                onChange={(e) => setExistingPin(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    e.preventDefault()
                    void onExistingSignIn()
                  }
                }}
              />
            </div>
            {existingError ? (
              <p className="text-destructive mt-2 text-sm">{existingError}</p>
            ) : null}
          </div>

          <DialogFooter className="mt-2 flex-col gap-2 sm:flex-col">
            <Button
              size="lg"
              className="h-12 w-full text-base font-semibold"
              loading={signingIn}
              onClick={() => void onExistingSignIn()}
            >
              <span className="flex items-center gap-2">
                {t("login.submit")}
                <ArrowRight className="size-4.5" />
              </span>
            </Button>
            <Button
              variant="ghost"
              className="w-full"
              disabled={signingIn}
              onClick={() => setExistingOpen(false)}
            >
              {t("signup.existingOther")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
