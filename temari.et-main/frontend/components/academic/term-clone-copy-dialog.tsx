"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { useEffect, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Term } from "@/lib/types"

const cloneSchema = z.object({ name: z.string().min(1, "Name is required").max(100) })
const copySchema = z.object({ source_term_id: z.string().min(1, "Pick a semester") })

export type TermGridAction = { mode: "clone" | "copy"; term: Term } | null

/**
 * One dialog for the two grid-reuse flows: CLONE duplicates the semester
 * (settings + its section/subject/teacher grid) into a new one; COPY pulls
 * another semester's grid into this one (existing pairs stay untouched).
 */
export function TermCloneCopyDialog({
  action,
  terms,
  onOpenChange,
  onDone,
}: {
  action: TermGridAction
  /** Candidate source semesters (same branch list the page already holds). */
  terms: Term[]
  onOpenChange: (open: boolean) => void
  onDone: () => void
}) {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")

  const cloneForm = useForm<z.infer<typeof cloneSchema>>({
    resolver: zodResolver(cloneSchema),
    defaultValues: { name: "" },
  })
  const copyForm = useForm<z.infer<typeof copySchema>>({
    resolver: zodResolver(copySchema),
    defaultValues: { source_term_id: "" },
  })
  // Set when a plain copy found nothing new — offers the force-replace pass.
  const [forceSourceId, setForceSourceId] = useState<number | null>(null)
  const [forcing, setForcing] = useState(false)

  useEffect(() => {
    if (!action) return
    cloneForm.reset({ name: t("terms.cloneDefaultName", { name: action.term.name }) })
    copyForm.reset({ source_term_id: "" })
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per action
    setForceSourceId(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [action])

  if (!action) return null

  // Same-branch semesters only. branch_id is always present; the old
  // branch_name comparison broke when one side came from a different context.
  const sources = terms.filter(
    (x) =>
      x.id !== action.term.id &&
      (x.branch_id == null || action.term.branch_id == null || x.branch_id === action.term.branch_id),
  )

  async function submitClone(values: z.infer<typeof cloneSchema>) {
    if (!action) return
    try {
      await apiFetch(`/terms/${action.term.id}/clone`, { method: "POST", body: values })
      toast.success(t("terms.cloned"))
      onOpenChange(false)
      onDone()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("terms.cloneFailed"))
    }
  }

  async function submitCopy(values: z.infer<typeof copySchema>) {
    if (!action) return
    try {
      const res = await apiFetch<{ data: { created: number; updated?: number }; message?: string }>(
        `/terms/${action.term.id}/copy-assignments`,
        { method: "POST", body: { source_term_id: Number(values.source_term_id) } },
      )
      if (res.data.created === 0 && (res.data.updated ?? 0) === 0) {
        // Every pair already exists — offer the forced overwrite instead.
        setForceSourceId(Number(values.source_term_id))
        return
      }
      toast.success(res.message ?? t("terms.copied"))
      onOpenChange(false)
      onDone()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("terms.copyFailed"))
    }
  }

  async function forceCopy() {
    if (!action || forceSourceId === null) return
    setForcing(true)
    try {
      const res = await apiFetch<{ data: { created: number; updated?: number }; message?: string }>(
        `/terms/${action.term.id}/copy-assignments`,
        { method: "POST", body: { source_term_id: forceSourceId, replace: true } },
      )
      toast.success(res.message ?? t("terms.copied"))
      setForceSourceId(null)
      onOpenChange(false)
      onDone()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("terms.copyFailed"))
    } finally {
      setForcing(false)
    }
  }

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        {action.mode === "clone" ? (
          <>
            <DialogHeader>
              <DialogTitle>{t("terms.cloneTitle", { name: action.term.name })}</DialogTitle>
              <DialogDescription>{t("terms.cloneDescription")}</DialogDescription>
            </DialogHeader>
            <Form {...cloneForm}>
              <form onSubmit={cloneForm.handleSubmit(submitClone)} className="space-y-4">
                <FormField
                  control={cloneForm.control}
                  name="name"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("terms.name")}</FormLabel>
                      <FormControl>
                        <Input {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <DialogFooter>
                  <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                    {tc("actions.cancel")}
                  </Button>
                  <Button type="submit" loading={cloneForm.formState.isSubmitting}>
                    {t("terms.cloneCta")}
                  </Button>
                </DialogFooter>
              </form>
            </Form>
          </>
        ) : (
          <>
            <DialogHeader>
              <DialogTitle>{t("terms.copyTitle", { name: action.term.name })}</DialogTitle>
              <DialogDescription>{t("terms.copyDescription")}</DialogDescription>
            </DialogHeader>
            <Form {...copyForm}>
              <form onSubmit={copyForm.handleSubmit(submitCopy)} className="space-y-4">
                <FormField
                  control={copyForm.control}
                  name="source_term_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("terms.copySource")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={t("terms.copySourcePlaceholder")} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {sources.map((source) => (
                            <SelectItem key={source.id} value={String(source.id)}>
                              {source.name}
                              {source.academic_year_name ? ` · ${source.academic_year_name}` : ""}
                              {source.program?.name ? ` · ${source.program.name}` : ""}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <DialogFooter>
                  <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                    {tc("actions.cancel")}
                  </Button>
                  <Button type="submit" loading={copyForm.formState.isSubmitting}>
                    {t("terms.copyCta")}
                  </Button>
                </DialogFooter>
              </form>
            </Form>

            {/* Force pass: everything already exists → replace teachers instead. */}
            <AlertDialog
              open={forceSourceId !== null}
              onOpenChange={(open) => !open && setForceSourceId(null)}
            >
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>{t("terms.copyForceTitle")}</AlertDialogTitle>
                  <AlertDialogDescription>
                    {t("terms.copyForceDesc", {
                      name: action.term.name,
                      source: sources.find((x) => x.id === forceSourceId)?.name ?? "",
                    })}
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel disabled={forcing}>{tc("actions.cancel")}</AlertDialogCancel>
                  <AlertDialogAction
                    loading={forcing}
                    onClick={(e) => {
                      e.preventDefault()
                      forceCopy()
                    }}
                  >
                    {t("terms.copyForceCta")}
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
