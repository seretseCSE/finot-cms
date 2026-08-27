"use client"

import { Download, Printer } from "lucide-react"
import { useState } from "react"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { useDocumentDownload } from "@/lib/use-document"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/** One render, bounded budget — mirrors the backend batch document cap. */
export const REPORT_CARD_BATCH_MAX = 60

export interface PrintTarget {
  mode: "semester" | "yearly" | "transcripts"
  /** Semester mode: the term the cards freeze. */
  termId?: number
  /** Yearly / transcripts mode: the academic year. */
  yearId?: number
  students: { id: number; name: string | null }[]
}

/**
 * The report-card print surface behind every roster action, single or bulk:
 * pick Download or Print (the official server-rendered PDF — never a browser
 * print of the page), and on the yearly card which SIDE of the duplex booklet
 * to produce (inside marks grid vs cover + remarks page).
 */
export function ReportCardPrintDialog({
  target,
  onOpenChange,
}: {
  target: PrintTarget | null
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation("grading")
  const { download, print, generating } = useDocumentDownload()
  // The side choice deliberately survives between opens — printing a class's
  // covers after its inside pages is the common two-pass flow.
  const [side, setSide] = useState<"inside" | "cover" | "both">("both")
  const [running, setRunning] = useState<"download" | "print" | null>(null)

  if (target === null) return null

  const count = target.students.length
  const overCap = count > REPORT_CARD_BATCH_MAX
  const single = count === 1

  async function run(action: "download" | "print") {
    if (!target || overCap) return
    setRunning(action)
    const go = action === "download" ? download : print
    try {
      if (target.mode === "transcripts") {
        await go("transcript_batch", undefined, {
          academic_year_id: target.yearId,
          student_ids: target.students.map((s) => s.id),
        })
      } else if (target.mode === "semester") {
        if (single) {
          await go("report_card", target.students[0].id, { term_id: target.termId })
        } else {
          await go("report_card_batch", undefined, {
            term_id: target.termId,
            student_ids: target.students.map((s) => s.id),
          })
        }
      } else if (single) {
        await go("year_report_card", target.students[0].id, {
          academic_year_id: target.yearId,
          side,
        })
      } else {
        await go("year_report_card_batch", undefined, {
          academic_year_id: target.yearId,
          side,
          student_ids: target.students.map((s) => s.id),
        })
      }
    } finally {
      setRunning(null)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !generating && onOpenChange(open)}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>
            {target.mode === "semester"
              ? t("printCards.semesterTitle")
              : target.mode === "yearly"
                ? t("printCards.yearlyTitle")
                : t("printCards.transcriptsTitle")}
          </DialogTitle>
          <DialogDescription>
            {single
              ? (target.students[0].name ?? "—")
              : t("printCards.selectedCount", { count })}
          </DialogDescription>
        </DialogHeader>

        {target.mode === "yearly" && (
          // The yearly card prints as a duplex booklet — pick which side,
          // or Both (cover then marks per student, duplex feed order).
          <div className="grid gap-2">
            {(["both", "inside", "cover"] as const).map((option) => (
              <button
                key={option}
                type="button"
                onClick={() => setSide(option)}
                aria-pressed={side === option}
                className={cn(
                  "min-h-11 rounded-xl border px-3 py-2 text-left transition-colors",
                  side === option
                    ? "border-primary bg-primary/5"
                    : "hover:bg-accent/40",
                )}
              >
                <span className="block text-sm font-medium">
                  {t(`printCards.side.${option}`)}
                </span>
                <span className="text-muted-foreground block text-xs">
                  {t(`printCards.side.${option}Hint`)}
                </span>
              </button>
            ))}
          </div>
        )}

        {overCap && (
          <p className="border-warning/30 bg-warning/10 rounded-xl border px-3 py-2 text-sm">
            {t("printCards.overCap", { max: REPORT_CARD_BATCH_MAX })}
          </p>
        )}

        <DialogFooter className="gap-2 sm:gap-2">
          <Button
            variant="outline"
            className="h-11 flex-1"
            onClick={() => void run("print")}
            loading={running === "print"}
            disabled={overCap || (generating && running !== "print")}
          >
            <Printer className="size-4" />
            {t("printCards.print")}
          </Button>
          <Button
            className="h-11 flex-1"
            onClick={() => void run("download")}
            loading={running === "download"}
            disabled={overCap || (generating && running !== "download")}
          >
            <Download className="size-4" />
            {t("printCards.download")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
