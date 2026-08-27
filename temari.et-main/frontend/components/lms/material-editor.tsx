"use client"

import { CirclePlay, FileUp, FolderOpen, Link2, Loader2, StickyNote, Users, X } from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import {
  AudienceRows,
  audienceRowsFromTargets,
  audienceTargetIds,
  makeAudienceRow,
  type AudienceRow,
} from "@/components/lms/audience-rows"
import { useClassOptions } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { baseName, renamedFile } from "@/components/lms/pending-files"
import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { RichTextEditor } from "@/components/ui/rich-text"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { stripHtml } from "@/lib/sanitize-html"
import type { CourseMaterial, Subject } from "@/lib/types"
import { cn } from "@/lib/utils"

const TYPE_ICONS = { file: FileUp, link: Link2, youtube: CirclePlay, text: StickyNote } as const

interface Props {
  material: CourseMaterial | null
  platform?: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}

/**
 * The full-screen material studio, mirroring the exam/question editors
 * (ADR-016 layout convention): center canvas for the material itself, right
 * rail for audience + settings. Audience targeting is grade → sections rows
 * over the poster's own classes (useClassOptions) — one default "everyone"
 * row, plus as many grade/section-scoped rows as needed (a section already
 * claimed by one row is hidden from the others so the same class can never
 * be targeted twice); resolved client-side into the flat
 * subject_assignment_ids the backend's course_material_targets expects.
 */
export function MaterialEditor({ material, platform = false, open, onOpenChange, onSaved }: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const { classes } = useClassOptions()
  const fileInput = useRef<HTMLInputElement>(null)
  const rowsHydrated = useRef(false)

  const [type, setType] = useState<CourseMaterial["type"]>("file")
  const [title, setTitle] = useState("")
  const [description, setDescription] = useState("")
  const [url, setUrl] = useState("")
  const [body, setBody] = useState("")
  const [file, setFile] = useState<File | null>(null)
  const [fileName, setFileName] = useState("")
  const [imgUploading, setImgUploading] = useState(false)

  // Picked or dropped, the material file keeps its editable display name.
  const fileDrop = useFileDrop({
    onFiles: ([picked]) => {
      setFile(picked)
      setFileName(baseName(picked.name))
    },
  })
  const [rows, setRows] = useState<AudienceRow[]>([makeAudienceRow()])
  const [subjectId, setSubjectId] = useState<string>("")
  const [minGrade, setMinGrade] = useState("")
  const [maxGrade, setMaxGrade] = useState("")
  const [pinned, setPinned] = useState(false)
  const [subjects, setSubjects] = useState<Subject[]>([])
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [saving, setSaving] = useState(false)

  const targetIds = useMemo(() => audienceTargetIds(rows, classes), [rows, classes])

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- sync editor with the edited row */
    setErrors({})
    setType(material?.type ?? "file")
    setTitle(material?.title ?? "")
    setDescription(material?.description ?? "")
    setUrl(material?.content.url ?? "")
    setBody(material?.content.body ?? "")
    setFile(null)
    setFileName("")
    setRows([makeAudienceRow()])
    setSubjectId(material?.subject_id ? String(material.subject_id) : "")
    setMinGrade(material?.min_grade_sort ? String(material.min_grade_sort) : "")
    setMaxGrade(material?.max_grade_sort ? String(material.max_grade_sort) : "")
    setPinned(material?.is_pinned ?? false)
    /* eslint-enable react-hooks/set-state-in-effect */
    rowsHydrated.current = false

    let cancelled = false
    apiFetch<{ data: Subject[] }>(platform ? "/catalogs/subjects?per_page=100" : "/subjects?per_page=100")
      .then((res) => !cancelled && setSubjects(res.data))
      .catch(() => !cancelled && setSubjects([]))
    return () => {
      cancelled = true
    }
  }, [open, material, platform])

  // Rebuild audience rows from saved targets once classes have loaded.
  useEffect(() => {
    if (!open || platform || rowsHydrated.current) return
    const targets = material?.targets ?? []
    if (targets.length === 0) {
      rowsHydrated.current = true
      return
    }
    if (classes.length === 0) return
    const rebuilt = audienceRowsFromTargets(targets.map((target) => target.subject_assignment_id), classes)
    if (rebuilt !== null) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- one-shot resolve from loaded data
      setRows(rebuilt)
    }
    rowsHydrated.current = true
  }, [open, platform, material, classes])

  async function uploadImage(uploaded: File) {
    const form = new FormData()
    form.append("file", uploaded)
    const res = await apiFetch<{ data: { url: string; path: string } }>("/lms/uploads", {
      method: "POST",
      body: form,
    })
    return res.data
  }

  function clearError(key: string) {
    setErrors((prev) => {
      if (!(key in prev)) return prev
      const next = { ...prev }
      delete next[key]
      return next
    })
  }

  function validate(): boolean {
    const found: Record<string, string[]> = {}
    if (title.trim() === "") found.title = [tc("validation.required")]
    if (!platform && targetIds.length === 0) found.subject_assignment_ids = [t("materials.pickAudienceError")]
    setErrors(found)
    return Object.keys(found).length === 0
  }

  async function save() {
    if (!validate()) return
    setSaving(true)
    try {
      const form = new FormData()
      form.append("title", title)
      if (description) form.append("description", description)
      if (material === null) {
        form.append("type", type)
        if (platform) form.append("platform", "1")
      }
      if (type === "file" && file) form.append("file", renamedFile({ file, name: fileName }))
      if ((type === "link" || type === "youtube") && url) form.append("url", url)
      if (type === "text" && body) form.append("body", body)
      if (subjectId) form.append("subject_id", subjectId)
      if (minGrade) form.append("min_grade_sort", minGrade)
      if (maxGrade) form.append("max_grade_sort", maxGrade)
      form.append("is_pinned", pinned ? "1" : "0")
      targetIds.forEach((id, index) => form.append(`subject_assignment_ids[${index}]`, String(id)))
      if (material !== null) form.append("_method", "PUT")

      await apiFetch(material ? `/course-materials/${material.id}` : "/course-materials", {
        method: "POST",
        body: form,
      })
      toast.success(material ? t("materials.saved") : t("materials.posted"))
      onOpenChange(false)
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setErrors(error.errors)
        toast.error(error.message)
      } else {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      }
    } finally {
      setSaving(false)
    }
  }

  const hasBody = stripHtml(body).trim() !== "" || /<img/i.test(body)

  const canSave =
    !saving &&
    !imgUploading &&
    title.trim() !== "" &&
    (material !== null ||
      (type === "file" ? file !== null : type === "text" ? hasBody : url.trim() !== "")) &&
    (platform || targetIds.length > 0)

  return (
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/20 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
        <DialogPrimitive.Content
          className="fixed inset-0 z-50 flex flex-col bg-background data-open:animate-in data-open:fade-in-0 data-open:zoom-in-[0.99] data-closed:animate-out data-closed:fade-out-0"
          onEscapeKeyDown={(e) => e.preventDefault()}
          onPointerDownOutside={(e) => e.preventDefault()}
          onInteractOutside={(e) => e.preventDefault()}
        >
          {/* ── Top bar ─────────────────────────────────────────────── */}
          <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-background px-3 md:px-5">
            <Button
              variant="ghost"
              size="icon"
              className="text-muted-foreground"
              onClick={() => onOpenChange(false)}
              aria-label={tc("actions.close")}
            >
              <X className="size-5" />
            </Button>
            <div className="flex min-w-0 items-center gap-2.5">
              <div className="hidden size-8 items-center justify-center rounded-lg bg-primary/10 text-primary sm:flex">
                <FolderOpen className="size-4.5" />
              </div>
              <div className="min-w-0">
                <DialogPrimitive.Title className="truncate text-sm font-semibold">
                  {material ? t("materials.edit") : t("materials.add")}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {platform ? t("materials.platformTitle") : title || t("materials.materialTitle")}
                </p>
              </div>
            </div>

            <div className="ml-auto flex items-center">
              <Button className="h-10 px-5" disabled={!canSave} onClick={save}>
                {saving && <Loader2 className="size-4 animate-spin" />}
                {tc("actions.save")}
              </Button>
            </div>
          </header>

          {/* ── Body: canvas + settings rail. The audience decides everything
              else, so on mobile the rail comes FIRST (flex order); desktop
              keeps it on the right. */}
          <div className="flex min-h-0 flex-1 flex-col overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex-row md:overflow-hidden">
            <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
              <div className="mx-auto w-full max-w-5xl space-y-5 p-4 pb-8 md:p-8">
                {/* The material */}
                <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <div className="space-y-2">
                    <Label>
                      {t("materials.materialTitle")} <span className="text-destructive">*</span>
                    </Label>
                    <Input
                      value={title}
                      onChange={(e) => {
                        setTitle(e.target.value)
                        clearError("title")
                      }}
                    />
                    {errors.title && <p className="text-destructive text-xs">{errors.title[0]}</p>}
                  </div>

                  <div className="space-y-2">
                    <Label>{t("materials.description")}</Label>
                    <RichTextEditor value={description} onChange={setDescription} onUploadingChange={setImgUploading} onUploadImage={uploadImage} />
                  </div>

                  {material === null && (
                    <div className="space-y-2">
                      <Label>{t("materials.type")}</Label>
                      <div className="flex flex-wrap justify-center gap-6 py-1 sm:justify-start">
                        {(["file", "link", "youtube", "text"] as const).map((option) => {
                          const Icon = TYPE_ICONS[option]
                          const selected = type === option
                          return (
                            <button
                              key={option}
                              type="button"
                              onClick={() => setType(option)}
                              className="pressable flex flex-col items-center gap-2"
                            >
                              <span
                                className={cn(
                                  "flex size-16 items-center justify-center rounded-full border-2 transition-colors",
                                  selected
                                    ? "border-primary bg-primary/10 text-primary"
                                    : "border-border text-muted-foreground hover:bg-accent",
                                )}
                              >
                                <Icon className="size-6" strokeWidth={1.75} />
                              </span>
                              <span
                                className={cn(
                                  "text-xs font-medium",
                                  selected ? "text-primary" : "text-muted-foreground",
                                )}
                              >
                                {t(
                                  `materials.type${option === "file" ? "File" : option === "link" ? "Link" : option === "youtube" ? "Youtube" : "Text"}`,
                                )}
                              </span>
                            </button>
                          )
                        })}
                      </div>
                    </div>
                  )}

                  {type === "file" && material === null && (
                    <div
                      {...fileDrop.dropProps}
                      className={cn("space-y-2 rounded-2xl", fileDrop.dragOver && DROP_ACTIVE)}
                    >
                      <Label>
                        {t("materials.file")} <span className="text-destructive">*</span>
                      </Label>
                      <input
                        ref={fileInput}
                        type="file"
                        className="hidden"
                        onChange={(e) => {
                          fileDrop.takeFiles(e.target.files)
                          e.target.value = ""
                        }}
                      />
                      <Button
                        type="button"
                        variant="outline"
                        className="w-full justify-start"
                        onClick={() => fileInput.current?.click()}
                      >
                        <FileUp className="size-4" />
                        <span className="truncate">{file ? file.name : t("materials.file")}</span>
                      </Button>
                      {file !== null && (
                        <Input
                          value={fileName}
                          onChange={(e) => setFileName(e.target.value)}
                          placeholder={t("assignments.fileName")}
                          aria-label={t("assignments.fileName")}
                        />
                      )}
                      {errors.file && <p className="text-destructive text-xs">{errors.file[0]}</p>}
                    </div>
                  )}

                  {(type === "link" || type === "youtube") && (
                    <div className="space-y-2">
                      <Label>
                        {type === "youtube" ? t("materials.youtubeUrl") : t("materials.url")}{" "}
                        <span className="text-destructive">*</span>
                      </Label>
                      <Input
                        type="url"
                        inputMode="url"
                        value={url}
                        onChange={(e) => setUrl(e.target.value)}
                        placeholder="https://…"
                      />
                      {errors.url && <p className="text-destructive text-xs">{errors.url[0]}</p>}
                    </div>
                  )}

                  {type === "text" && (
                    <div className="space-y-2">
                      <Label>
                        {t("materials.body")} <span className="text-destructive">*</span>
                      </Label>
                      <RichTextEditor value={body} onChange={setBody} onUploadingChange={setImgUploading} onUploadImage={uploadImage} />
                    </div>
                  )}
                </section>
              </div>
            </main>

            {/* Settings rail: audience first — it decides everything else */}
            <aside className="order-first border-b bg-background md:order-none md:min-h-0 md:w-[340px] md:shrink-0 md:overflow-y-auto md:border-l md:border-b-0">
              <div className="space-y-5 p-4 md:p-5">
                {!platform ? (
                  <section className="rounded-2xl border bg-card p-3.5 shadow-xs">
                    <div className="mb-1 flex items-center gap-2 px-0.5">
                      <Users className="size-4 text-muted-foreground" />
                      <Label>{t("materials.audience")}</Label>
                    </div>
                    <p className="mb-3 px-0.5 text-xs text-muted-foreground">{t("materials.audienceHint")}</p>

                    <div className="mb-3 space-y-1.5">
                      <Label className="text-xs text-muted-foreground">{t("materials.subject")}</Label>
                      <Select value={subjectId || "any"} onValueChange={(v) => setSubjectId(v === "any" ? "" : v)}>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="any">{t("banks.anySubject")}</SelectItem>
                          {subjects.map((subject) => (
                            <SelectItem key={subject.id} value={String(subject.id)}>
                              {subject.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>

                    <AudienceRows
                      classes={classes}
                      rows={rows}
                      onChange={setRows}
                      onInteract={() => clearError("subject_assignment_ids")}
                    />

                    {errors.subject_assignment_ids && (
                      <p className="mt-2 text-xs text-destructive">{errors.subject_assignment_ids[0]}</p>
                    )}

                    <div className="mt-3 rounded-xl bg-muted/50 px-3.5 py-3 text-xs text-muted-foreground">
                      <Badge variant="secondary" className="mb-1.5">
                        {t("materials.audienceSummary", { count: targetIds.length })}
                      </Badge>
                    </div>
                  </section>
                ) : (
                  <section className="space-y-3">
                    <div className="mb-1 flex items-center gap-2">
                      <Users className="size-4 text-muted-foreground" />
                      <Label>{t("materials.platformAudience")}</Label>
                    </div>
                    <div className="space-y-1.5">
                      <Label className="text-xs text-muted-foreground">{t("materials.subject")}</Label>
                      <Select value={subjectId || "any"} onValueChange={(v) => setSubjectId(v === "any" ? "" : v)}>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="any">{t("banks.anySubject")}</SelectItem>
                          {subjects.map((subject) => (
                            <SelectItem key={subject.id} value={String(subject.id)}>
                              {subject.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <div className="space-y-2">
                        <Label className="text-muted-foreground">{t("materials.gradeWindow")} (min)</Label>
                        <Input
                          type="number"
                          min="1"
                          className="no-spinner"
                          value={minGrade}
                          onChange={(e) => setMinGrade(e.target.value)}
                        />
                      </div>
                      <div className="space-y-2">
                        <Label className="text-muted-foreground">{t("materials.gradeWindow")} (max)</Label>
                        <Input
                          type="number"
                          min="1"
                          className="no-spinner"
                          value={maxGrade}
                          onChange={(e) => setMaxGrade(e.target.value)}
                        />
                      </div>
                    </div>
                  </section>
                )}

                <div className="space-y-2">
                  <label className="flex items-center justify-between rounded-xl border px-3.5 py-3">
                    <span className="text-sm">{t("materials.pin")}</span>
                    <Switch checked={pinned} onCheckedChange={setPinned} />
                  </label>
                  <p className="text-xs text-muted-foreground">{t("materials.pinHint")}</p>
                </div>
              </div>
            </aside>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
