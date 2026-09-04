"use client"

import { generate } from "lean-qr"
import { toSvgDataURL } from "lean-qr/extras/svg"
import { useEffect, useState } from "react"

import { Logo } from "@/components/ui/logo"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { useTranslation } from "@/lib/i18n"
import type { WithdrawalLetter } from "@/lib/types"

/** Public verification URL the letter's QR code points at. */
export function withdrawalLetterPublicUrl(token: string): string {
  return `${window.location.origin}/letters/withdrawal/${token}`
}

/**
 * The A4 withdrawal clearance letter — for students leaving school or moving
 * to a school OUTSIDE Temari. Shared by the staff print page and the PUBLIC
 * verification page its QR code opens. Outstanding fees are noted on the
 * letter, never a blocker.
 */
export function WithdrawalLetterArticle({ letter }: { letter: WithdrawalLetter }) {
  const { t } = useTranslation("transfers")

  // QR encodes the public letter URL — window is only known client-side.
  const [qr, setQr] = useState<string | null>(null)
  useEffect(() => {
    if (!letter.public_token) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- QR needs window.origin (client only)
    setQr(
      toSvgDataURL(generate(withdrawalLetterPublicUrl(letter.public_token)), {
        on: "#000000",
        off: "#ffffff",
        pad: 2,
      }),
    )
  }, [letter.public_token])

  const outstanding = Number(letter.outstanding_amount)

  return (
    <>
      <style>{`@media print {
        body * { visibility: hidden; }
        #withdrawal-letter, #withdrawal-letter * { visibility: visible; }
        #withdrawal-letter { position: absolute; inset: 0; margin: 0; border: none; box-shadow: none; }
      }`}</style>

      <article
        id="withdrawal-letter"
        className="mx-auto max-w-2xl rounded-2xl border bg-card p-8 shadow-xs md:p-12"
      >
        <header className="flex items-start justify-between gap-4 border-b pb-6">
          <div>
            <h2 className="font-display text-xl font-semibold">{letter.school}</h2>
            <p className="text-sm text-muted-foreground">{letter.branch}</p>
          </div>
          <div className="text-right">
            <Logo className="ml-auto" />
            <p className="mt-1 text-xs text-muted-foreground tabular-nums">
              {t("letter.reference")} {letter.reference}
            </p>
          </div>
        </header>

        <h1 className="mt-8 text-center font-display text-lg font-semibold tracking-tight">
          {t("withdrawal.letterTitle")}
        </h1>

        <div className="mt-8 flex items-center gap-4">
          <PersonAvatar
            name={letter.student.full_name}
            photoUrl={letter.student.photo_url}
            className="size-16 text-lg"
          />
          <div>
            <p className="text-lg font-semibold">{letter.student.full_name}</p>
            <p className="text-sm text-muted-foreground tabular-nums">
              {t("letter.studentId")}: {letter.student.public_id ?? "—"}
              {letter.student.date_of_birth && (
                <> · {t("letter.dateOfBirth")}: {letter.student.date_of_birth}</>
              )}
            </p>
          </div>
        </div>

        <dl className="mt-8 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("letter.lastAttended")}
            </dt>
            <dd className="mt-1 font-medium">
              {letter.last_grade}
              {letter.last_section ? ` — ${letter.last_section}` : ""} ·{" "}
              {letter.last_academic_year}
            </dd>
          </div>
          <div>
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("withdrawal.withdrawnOn")}
            </dt>
            <dd className="mt-1 font-medium tabular-nums">{letter.withdrawn_on}</dd>
          </div>
          {letter.destination && (
            <div>
              <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {t("withdrawal.destination")}
              </dt>
              <dd className="mt-1 font-medium">{letter.destination}</dd>
            </div>
          )}
          <div>
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("letter.reason")}
            </dt>
            <dd className="mt-1">{letter.reason}</dd>
          </div>
        </dl>

        {/* The RTE-style rule: the letter is never withheld over fees — the
            debt is recorded on it instead. */}
        {outstanding > 0 && (
          <p className="mt-6 rounded-xl border border-dashed bg-muted/40 p-3 text-sm">
            {t("withdrawal.outstandingNote", {
              amount: outstanding.toLocaleString(),
            })}
          </p>
        )}

        <div className="mt-12 flex items-end justify-between gap-6 border-t pt-6 text-sm">
          <div>
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("withdrawal.issuedBy")}
            </p>
            <p className="mt-1 font-medium">{letter.issued_by ?? "—"}</p>
            <div className="mt-6 h-px w-40 bg-border" aria-hidden />
          </div>
          <div className="text-right">
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("letter.date")}
            </p>
            <p className="mt-1 font-medium tabular-nums">{letter.withdrawn_on}</p>
          </div>
        </div>

        {/* Verification: scanning the QR opens the public copy of this letter. */}
        <div className="mt-8 flex items-center gap-4 rounded-xl border border-dashed p-3">
          {qr ? (
            // eslint-disable-next-line @next/next/no-img-element -- inline data URL
            <img src={qr} alt="QR" className="size-20 shrink-0 rounded-md bg-white" />
          ) : (
            <div className="size-20 shrink-0 rounded-md bg-muted" aria-hidden />
          )}
          <div className="min-w-0 text-left">
            <p className="text-xs font-medium">{t("letter.scanToVerify")}</p>
            <p className="mt-0.5 text-[11px] text-muted-foreground">
              {t("letter.footer", { ref: letter.reference })}
            </p>
          </div>
        </div>
      </article>
    </>
  )
}
