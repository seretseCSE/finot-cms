"use client"

import { generate } from "lean-qr"
import { toSvgDataURL } from "lean-qr/extras/svg"
import { useEffect, useState } from "react"

import { Logo } from "@/components/ui/logo"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { useTranslation } from "@/lib/i18n"
import type { TransferLetter } from "@/lib/types"

/** Public verification URL the letter's QR code points at. */
export function transferLetterPublicUrl(token: string): string {
  return `${window.location.origin}/letters/transfer/${token}`
}

/**
 * The A4 transfer-letter body — shared by the staff print page and the
 * PUBLIC verification page the QR code opens. The print stylesheet isolates
 * the letter so surrounding chrome never prints.
 */
export function TransferLetterArticle({ letter }: { letter: TransferLetter }) {
  const { t } = useTranslation("transfers")

  // QR encodes the public letter URL — window is only known client-side.
  const [qr, setQr] = useState<string | null>(null)
  useEffect(() => {
    if (!letter.public_token) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- QR needs window.origin (client only)
    setQr(
      toSvgDataURL(generate(transferLetterPublicUrl(letter.public_token)), {
        on: "#000000",
        off: "#ffffff",
        pad: 2,
      }),
    )
  }, [letter.public_token])

  return (
    <>
      <style>{`@media print {
        body * { visibility: hidden; }
        #transfer-letter, #transfer-letter * { visibility: visible; }
        #transfer-letter { position: absolute; inset: 0; margin: 0; border: none; box-shadow: none; }
      }`}</style>

      <article
        id="transfer-letter"
        className="mx-auto max-w-2xl rounded-2xl border bg-card p-8 shadow-xs md:p-12"
      >
        <header className="flex items-start justify-between gap-4 border-b pb-6">
          <div>
            <h2 className="font-display text-xl font-semibold">{letter.from_school}</h2>
            <p className="text-sm text-muted-foreground">{letter.from_branch}</p>
          </div>
          <div className="text-right">
            <Logo className="ml-auto" />
            <p className="mt-1 text-xs text-muted-foreground tabular-nums">
              {t("letter.reference")} {letter.reference}
            </p>
          </div>
        </header>

        <h1 className="mt-8 text-center font-display text-lg font-semibold tracking-tight">
          {t("letter.title")}
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
              {t("letter.transferredTo")}
            </dt>
            <dd className="mt-1 font-medium">
              {letter.to_school} — {letter.to_branch}
            </dd>
          </div>
          <div>
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("letter.placedInto")}
            </dt>
            <dd className="mt-1 font-medium">
              {letter.new_grade} · {letter.new_academic_year}
            </dd>
          </div>
          {letter.reason && (
            <div>
              <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {t("letter.reason")}
              </dt>
              <dd className="mt-1">{letter.reason}</dd>
            </div>
          )}
        </dl>

        <div className="mt-12 flex items-end justify-between gap-6 border-t pt-6 text-sm">
          <div>
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("letter.approvedBy")}
            </p>
            <p className="mt-1 font-medium">{letter.approved_by ?? "—"}</p>
            <div className="mt-6 h-px w-40 bg-border" aria-hidden />
          </div>
          <div className="text-right">
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("letter.date")}
            </p>
            <p className="mt-1 font-medium tabular-nums">{letter.approved_at ?? "—"}</p>
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
