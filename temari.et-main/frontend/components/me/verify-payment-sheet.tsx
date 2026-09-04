"use client"

import { CheckCircle2, CircleAlert, Clock3, FileUp, Hash, Link2 } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import { BankLogo } from "@/components/fees/bank-accounts-sheet"
import { Button } from "@/components/ui/button"
import { CopyableId } from "@/components/ui/copyable-id"
import { useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Bank, FeeBankAccount } from "@/lib/types"
import { cn } from "@/lib/utils"

/** check.et-verifiable bank/wallet codes (fallback labels for a cold catalog). */
const VERIFIABLE_BANKS = [
  ["cbe", "Commercial Bank of Ethiopia"],
  ["telebirr", "Telebirr"],
  ["cbebirr", "CBE Birr"],
  ["awash", "Awash Bank"],
  ["boa", "Bank of Abyssinia"],
  ["dashen", "Dashen Bank"],
  ["zemen", "Zemen Bank"],
  ["siinqee", "Siinqee Bank"],
  ["amhara", "Amhara Bank"],
  ["mpesa", "M-Pesa"],
] as const

export interface MyInvoice {
  id: number
  /** Human-facing invoice number (INV-000123) — always shown tap-to-copy. */
  number: string
  title: string
  amount: string
  amount_paid: string
  net_amount: string
  balance: string
  status: string
  due_date: string | null
  is_overdue?: boolean
  /** Late-payment penalty accrued so far, when the fee defines one. */
  penalty_amount?: string | null
  /** net_amount + penalty — what the family actually owes today. */
  total_due?: string | null
  academic_year_name?: string | null
  term_name?: string | null
  /** Where the school expects the money to land. */
  collection_accounts?: FeeBankAccount[]
  created_at?: string
  verifications?: {
    id: number
    status: "verified" | "failed" | "needs_review"
    failure_reason: string | null
    bank_code: string | null
    transaction_number: string | null
    created_at: string
  }[]
}

interface VerifyResult {
  status: "verified" | "failed" | "needs_review"
  failure_reason: string | null
  invoice: {
    id: number
    status: string
    amount_paid: string
    net_amount: string
    balance: string
  }
}

type Mode = "reference" | "link" | "file"

/** Mirrors the backend's `mimes:pdf,jpg,jpeg,png,webp` + `max:10240` rules. */
const RECEIPT_ACCEPT = ".pdf,.jpg,.jpeg,.png,.webp"
const RECEIPT_MAX_BYTES = 10 * 1024 * 1024

interface Props {
  studentId: number | null
  invoice: MyInvoice | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onResult: (invoiceId: number, result: VerifyResult) => void
}

/**
 * The parent's "I paid — check it" flow: bank + transaction number (primary),
 * a receipt share-link, or an uploaded PDF/screenshot, verified against bank
 * records via check.et.
 */
export function VerifyPaymentSheet({ studentId, invoice, open, onOpenChange, onResult }: Props) {
  const { t } = useTranslation("me")
  const { t: tc } = useTranslation("common")

  const [mode, setMode] = useState<Mode>("reference")
  const [bank, setBank] = useState("")
  const [reference, setReference] = useState("")
  const [url, setUrl] = useState("")
  const [file, setFile] = useState<File | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const fileInput = useRef<HTMLInputElement>(null)

  // Platform bank catalog — recognisable logos in the bank picker.
  const [banks, setBanks] = useState<Bank[]>([])
  useEffect(() => {
    if (!open || banks.length > 0) return
    let cancelled = false
    apiFetch<{ data: Bank[] }>("/me/banks")
      .then((res) => !cancelled && setBanks(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- fetch once per mount
  }, [open])

  const bankByCode = new Map(banks.map((b) => [b.code, b]))

  useEffect(() => {
    if (!open) return
    const timer = setTimeout(() => {
      setMode("reference")
      setBank("")
      setReference("")
      setUrl("")
      setFile(null)
    }, 0)
    return () => clearTimeout(timer)
  }, [open])

  // Picked or dropped, one receipt goes through the shared validator.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: RECEIPT_ACCEPT,
    maxSize: RECEIPT_MAX_BYTES,
    disabled: mode !== "file",
    onFiles: ([selected]) => setFile(selected),
  })

  const canSubmit =
    mode === "reference" ? bank !== "" && reference.trim() !== "" : mode === "link" ? url.trim() !== "" : file !== null

  async function submit() {
    if (!studentId || !invoice || !canSubmit) return
    setSubmitting(true)
    try {
      const body = new FormData()
      if (mode === "reference") {
        body.append("bank", bank)
        body.append("transaction_number", reference.trim())
      } else if (mode === "link") {
        body.append("receipt_url", url.trim())
      } else if (file) {
        body.append("receipt_file", file)
      }

      const res = await apiFetch<{ data: VerifyResult }>(
        `/me/children/${studentId}/invoices/${invoice.id}/verify-payment`,
        { method: "POST", body },
      )

      const result = res.data
      if (result.status === "verified") {
        toast.success(t("payments.verified"))
      } else if (result.status === "needs_review") {
        toast.info(t("payments.needsReview"))
      } else {
        toast.error(result.failure_reason ?? t("payments.failed"))
      }

      onResult(invoice.id, result)
      if (result.status !== "failed") onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {t("payments.submitTitle")}
            {invoice ? ` — ${invoice.title}` : ""}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-5">
          {invoice ? (
            <div className="space-y-3">
              <div className="flex items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                  {t("payments.balanceDue", { amount: invoice.balance })}
                </p>
                <CopyableId value={invoice.number} />
              </div>

              {/* Where to send the money — the fee's collection accounts. */}
              {(invoice.collection_accounts?.length ?? 0) > 0 ? (
                <div className="space-y-1.5">
                  <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("payments.payTo")}
                  </p>
                  {invoice.collection_accounts!.map((account) => (
                    <div
                      key={account.id}
                      className="flex items-center gap-2.5 rounded-xl border px-3 py-2"
                    >
                      <BankLogo
                        bank={{
                          id: account.id,
                          code: account.bank_code ?? "",
                          name: account.bank_name ?? "",
                          type: account.bank_type ?? "bank",
                          logo: account.bank_logo,
                        }}
                        size={28}
                      />
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">
                          {account.bank_name ?? account.account_name}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                          {account.account_name}
                        </p>
                      </div>
                      <CopyableId value={account.account_number} />
                    </div>
                  ))}
                </div>
              ) : null}
            </div>
          ) : null}

          {/* Input mode */}
          <div className="flex h-10 overflow-hidden rounded-full border text-xs font-medium">
            {(
              [
                ["reference", Hash, t("payments.modeReference")],
                ["link", Link2, t("payments.modeLink")],
                ["file", FileUp, t("payments.modeFile")],
              ] as const
            ).map(([value, Icon, label], i) => (
              <button
                key={value}
                type="button"
                onClick={() => setMode(value)}
                className={cn(
                  "flex flex-1 items-center justify-center gap-1.5 transition-colors",
                  i > 0 && "border-l",
                  mode === value
                    ? "bg-primary text-primary-foreground"
                    : "bg-background text-muted-foreground hover:bg-muted",
                )}
              >
                <Icon className="size-3.5" />
                {label}
              </button>
            ))}
          </div>

          {mode === "reference" ? (
            <div className="space-y-3">
              <div>
                <p className="mb-1.5 text-sm font-medium">{t("payments.bank")}</p>
                <Select value={bank} onValueChange={setBank}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t("payments.selectBank")} />
                  </SelectTrigger>
                  <SelectContent>
                    {VERIFIABLE_BANKS.map(([code, fallbackLabel]) => {
                      const catalog = bankByCode.get(code)
                      return (
                        <SelectItem key={code} value={code}>
                          <span className="flex items-center gap-2">
                            <BankLogo bank={catalog ?? null} size={22} />
                            {catalog?.name ?? fallbackLabel}
                          </span>
                        </SelectItem>
                      )
                    })}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <p className="mb-1.5 text-sm font-medium">{t("payments.reference")}</p>
                <Input
                  value={reference}
                  onChange={(e) => setReference(e.target.value)}
                  placeholder={t("payments.referencePlaceholder")}
                  autoCapitalize="characters"
                />
                <p className="mt-1 text-xs text-muted-foreground">{t("payments.referenceHint")}</p>
              </div>
            </div>
          ) : null}

          {mode === "link" ? (
            <div>
              <p className="mb-1.5 text-sm font-medium">{t("payments.link")}</p>
              <Input
                type="url"
                inputMode="url"
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                placeholder="https://…"
              />
              <p className="mt-1 text-xs text-muted-foreground">{t("payments.linkHint")}</p>
            </div>
          ) : null}

          {mode === "file" ? (
            <div className="space-y-2">
              <button
                type="button"
                onClick={() => fileInput.current?.click()}
                {...dropProps}
                className={cn(
                  "flex w-full flex-col items-center gap-2 rounded-2xl border border-dashed px-4 py-8 text-sm transition-colors",
                  dragOver
                    ? "border-primary bg-primary/5 text-primary"
                    : "text-muted-foreground hover:bg-muted/40",
                )}
              >
                <FileUp className="pointer-events-none size-6" />
                {file ? (
                  <span className="pointer-events-none max-w-full truncate">{file.name}</span>
                ) : (
                  <>
                    {/* Touch devices have no drag — keep the tap wording there. */}
                    <span className="pointer-events-none sm:hidden">{t("payments.filePrompt")}</span>
                    <span className="pointer-events-none hidden sm:inline">
                      {t("payments.fileDropPrompt")}
                    </span>
                  </>
                )}
              </button>
              <p className="text-xs text-muted-foreground">{t("payments.fileHint")}</p>
              <input
                ref={fileInput}
                type="file"
                accept={RECEIPT_ACCEPT}
                className="hidden"
                onChange={(e) => {
                  takeFiles(e.target.files)
                  e.target.value = ""
                }}
              />
            </div>
          ) : null}

          {/* Outcome legend */}
          <div className="space-y-1.5 rounded-2xl bg-muted/40 p-3 text-xs text-muted-foreground">
            <p className="flex items-center gap-1.5">
              <CheckCircle2 className="size-3.5 text-success" /> {t("payments.legendVerified")}
            </p>
            <p className="flex items-center gap-1.5">
              <Clock3 className="size-3.5 text-warning" /> {t("payments.legendReview")}
            </p>
            <p className="flex items-center gap-1.5">
              <CircleAlert className="size-3.5 text-destructive" /> {t("payments.legendFailed")}
            </p>
          </div>
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button type="button" variant="outline" className="h-11 flex-1" onClick={() => onOpenChange(false)}>
            {tc("actions.cancel")}
          </Button>
          <Button type="button" className="h-11 flex-1" onClick={submit} loading={submitting} disabled={!canSubmit}>
            {t("payments.submitAction")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
