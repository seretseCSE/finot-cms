"use client"

import { FileText, Import, Plus, Trash2, Upload } from "lucide-react"
import { useRouter } from "next/navigation"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { PageHeader } from "@/components/ui/page-header"
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
import { Skeleton } from "@/components/ui/skeleton"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

const CREDENTIAL_ACCEPT = ".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
const CREDENTIAL_MAX_BYTES = 10 * 1024 * 1024

const TEXTAREA_CLASS =
  "w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"

interface CatalogSubject {
  id: number
  name: string
  code: string | null
}

interface CatalogGrade {
  id: number
  name: string
  sort_order: number
}

interface Attachment {
  id: number
  name: string
  url: string | null
  imported: boolean
}

interface TutorProfilePayload {
  status: string
  headline: string | null
  bio: string | null
  video_url: string | null
  hourly_rate: string | null
  additional_child_rate: string | null
  mode: string
  region: string | null
  city: string | null
  sub_city: string | null
  languages: string[]
  education_level: string | null
  experience_years: number | null
  has_fayda: boolean
  decline_reason: string | null
  suspend_reason: string | null
  commission_percent: number
  subjects: { subject_id: number; grade_sorts: number[] }[]
  attachments: Attachment[]
}

interface ShowPayload {
  profile: TutorProfilePayload | null
  languages: Record<string, string>
  subjects: CatalogSubject[]
  grade_levels: CatalogGrade[]
}

interface EmployeeDoc {
  id: number
  name: string
  mime_type: string | null
}

interface SubjectRow {
  subject_id: number | null
  grade_sorts: number[]
}

/**
 * The tutor application / profile editor: one autosave-on-demand form
 * covering profile, subjects × grades, rate, Fayda identity and the
 * verification documents (with the teacher shortcut importing from the
 * user's own employee file).
 */
export default function TutorApplyPage() {
  const { t } = useTranslation("tutoring")
  const { t: tc } = useTranslation("common")
  const router = useRouter()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const fileInput = useRef<HTMLInputElement>(null)

  const [loading, setLoading] = useState(true)
  const [profile, setProfile] = useState<TutorProfilePayload | null>(null)
  const [catalogSubjects, setCatalogSubjects] = useState<CatalogSubject[]>([])
  const [grades, setGrades] = useState<CatalogGrade[]>([])
  const [languageOptions, setLanguageOptions] = useState<Record<string, string>>({})

  // Form state.
  const [headline, setHeadline] = useState("")
  const [bio, setBio] = useState("")
  const [videoUrl, setVideoUrl] = useState("")
  const [hourlyRate, setHourlyRate] = useState("")
  const [additionalChildRate, setAdditionalChildRate] = useState("")
  const [mode, setMode] = useState("both")
  const [region, setRegion] = useState("")
  const [city, setCity] = useState("")
  const [subCity, setSubCity] = useState("")
  const [languages, setLanguages] = useState<string[]>(["am", "en"])
  const [educationLevel, setEducationLevel] = useState("")
  const [experienceYears, setExperienceYears] = useState("")
  const [faydaId, setFaydaId] = useState("")
  const [subjects, setSubjects] = useState<SubjectRow[]>([{ subject_id: null, grade_sorts: [] }])

  const [saving, setSaving] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [pendingFileName, setPendingFileName] = useState("")
  const [pendingFile, setPendingFile] = useState<File | null>(null)

  // Employee import sheet.
  const [importOpen, setImportOpen] = useState(false)
  const [employeeDocs, setEmployeeDocs] = useState<EmployeeDoc[] | null>(null)
  const [importSelection, setImportSelection] = useState<number[]>([])
  const [importing, setImporting] = useState(false)

  const editable = !profile || profile.status === "draft" || profile.status === "declined"

  // Picked or dropped, a credential lands in the same rename-then-upload card.
  const documentDrop = useFileDrop({
    accept: CREDENTIAL_ACCEPT,
    maxSize: CREDENTIAL_MAX_BYTES,
    disabled: !editable || uploading,
    onFiles: ([file]) => {
      setPendingFile(file)
      setPendingFileName(file.name.replace(/\.[^.]+$/, ""))
    },
  })
  const businessEditable = editable || profile?.status === "approved"

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiFetch<{ data: ShowPayload }>("/tutoring/profile")
      setCatalogSubjects(res.data.subjects)
      setGrades(res.data.grade_levels)
      setLanguageOptions(res.data.languages)
      setProfile(res.data.profile)

      const p = res.data.profile
      if (p) {
        setHeadline(p.headline ?? "")
        setBio(p.bio ?? "")
        setVideoUrl(p.video_url ?? "")
        setHourlyRate(p.hourly_rate ?? "")
        setAdditionalChildRate(p.additional_child_rate ?? "")
        setMode(p.mode)
        setRegion(p.region ?? "")
        setCity(p.city ?? "")
        setSubCity(p.sub_city ?? "")
        setLanguages(p.languages.length > 0 ? p.languages : ["am"])
        setEducationLevel(p.education_level ?? "")
        setExperienceYears(p.experience_years !== null ? String(p.experience_years) : "")
        setSubjects(
          p.subjects.length > 0
            ? p.subjects.map((s) => ({ subject_id: s.subject_id, grade_sorts: s.grade_sorts }))
            : [{ subject_id: null, grade_sorts: [] }],
        )
      }
    } catch {
      toast.error(t("apply.title"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      if (!cancelled) await load()
    })()
    return () => {
      cancelled = true
    }
  }, [load])

  async function save(showToast = true): Promise<boolean> {
    setSaving(true)
    try {
      const res = await apiFetch<{ data: TutorProfilePayload }>("/tutoring/profile", {
        method: "PUT",
        body: JSON.stringify({
          headline: headline || null,
          bio: bio || null,
          video_url: videoUrl || null,
          hourly_rate: hourlyRate === "" ? null : Number(hourlyRate),
          additional_child_rate: additionalChildRate === "" ? null : Number(additionalChildRate),
          mode,
          region: region || null,
          city: city || null,
          sub_city: subCity || null,
          languages,
          education_level: educationLevel || null,
          experience_years: experienceYears === "" ? null : Number(experienceYears),
          fayda_id: faydaId || null,
          subjects: subjects
            .filter((row) => row.subject_id !== null)
            .map((row) => ({ subject_id: row.subject_id, grade_sorts: row.grade_sorts })),
        }),
      })
      setProfile(res.data)
      setFaydaId("")
      if (showToast) toast.success(tc("actions.saved"))
      return true
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
      return false
    } finally {
      setSaving(false)
    }
  }

  async function submitApplication() {
    setSubmitting(true)
    const saved = await save(false)
    if (!saved) {
      setSubmitting(false)
      return
    }
    try {
      await apiFetch("/tutoring/profile/submit", { method: "POST" })
      toast.success(t("apply.submitted"))
      router.push("/tutoring")
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setSubmitting(false)
    }
  }

  async function uploadDocument() {
    if (!pendingFile) return
    setUploading(true)
    try {
      const body = new FormData()
      body.append("name", pendingFileName || pendingFile.name)
      body.append("file", pendingFile)
      await apiFetch("/tutoring/profile/attachments", { method: "POST", body })
      setPendingFile(null)
      setPendingFileName("")
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setUploading(false)
    }
  }

  function removeDocument(attachment: Attachment) {
    confirmDelete(async () => {
      try {
        await apiFetch(`/tutoring/profile/attachments/${attachment.id}`, { method: "DELETE" })
        await load()
      } catch (error) {
        toast.error(error instanceof ApiError ? error.message : "")
      }
    }, attachment.name)
  }

  async function openImport() {
    setImportOpen(true)
    if (employeeDocs === null) {
      try {
        const res = await apiFetch<{ data: EmployeeDoc[] }>("/tutoring/profile/employee-attachments")
        setEmployeeDocs(res.data)
      } catch {
        setEmployeeDocs([])
      }
    }
  }

  async function runImport() {
    setImporting(true)
    try {
      await apiFetch("/tutoring/profile/import-employee-attachments", {
        method: "POST",
        body: JSON.stringify({ attachment_ids: importSelection }),
      })
      setImportOpen(false)
      setImportSelection([])
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setImporting(false)
    }
  }

  const usedSubjectIds = useMemo(
    () => new Set(subjects.map((row) => row.subject_id).filter(Boolean)),
    [subjects],
  )

  if (loading) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("apply.title")} backHref="/tutoring" />
        <div className="page-gutter space-y-3">
          <Skeleton className="h-64 rounded-2xl" />
          <Skeleton className="h-64 rounded-2xl" />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t("apply.title")} description={t("apply.subtitle")} backHref="/tutoring" />

      {profile?.status === "pending" && (
        <div className="page-gutter">
          <div className="rounded-2xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning">
            {t("apply.pendingBanner")}
          </div>
        </div>
      )}
      {profile?.status === "declined" && profile.decline_reason && (
        <div className="page-gutter">
          <div className="rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
            {t("apply.declinedBanner", { reason: profile.decline_reason })} {t("apply.resubmitHint")}
          </div>
        </div>
      )}

      <div className="page-gutter mx-auto w-full max-w-4xl space-y-6">
        {/* ── Profile ── */}
        <section className="space-y-4 rounded-2xl border bg-card p-5 shadow-xs">
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {t("apply.stepProfile")}
          </h2>
          <div className="space-y-2">
            <Label htmlFor="headline">{t("apply.headline")}</Label>
            <Input
              id="headline"
              value={headline}
              maxLength={120}
              placeholder={t("apply.headlinePlaceholder")}
              disabled={!businessEditable}
              onChange={(e) => setHeadline(e.target.value)}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="bio">{t("apply.bio")}</Label>
            <textarea
              id="bio"
              rows={5}
              className={TEXTAREA_CLASS}
              value={bio}
              placeholder={t("apply.bioPlaceholder")}
              disabled={!businessEditable}
              onChange={(e) => setBio(e.target.value)}
            />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t("apply.modeLabel")}</Label>
              <Select value={mode} onValueChange={setMode} disabled={!businessEditable}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="online">{t("mode.online")}</SelectItem>
                  <SelectItem value="in_person">{t("mode.in_person")}</SelectItem>
                  <SelectItem value="both">{t("mode.both")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="video">{t("apply.videoUrl")}</Label>
              <Input
                id="video"
                value={videoUrl}
                disabled={!businessEditable}
                onChange={(e) => setVideoUrl(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="region">{t("apply.region")}</Label>
              <Input id="region" value={region} disabled={!businessEditable} onChange={(e) => setRegion(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="city">{t("apply.city")}</Label>
              <Input id="city" value={city} disabled={!businessEditable} onChange={(e) => setCity(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="subcity">{t("apply.subCity")}</Label>
              <Input id="subcity" value={subCity} disabled={!businessEditable} onChange={(e) => setSubCity(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="education">{t("apply.educationLevel")}</Label>
              <Input
                id="education"
                value={educationLevel}
                disabled={!businessEditable}
                onChange={(e) => setEducationLevel(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="experience">{t("apply.experienceYears")}</Label>
              <Input
                id="experience"
                type="number"
                min={0}
                max={60}
                className="no-spinner"
                value={experienceYears}
                disabled={!businessEditable}
                onChange={(e) => setExperienceYears(e.target.value)}
              />
            </div>
          </div>
          <div className="space-y-2">
            <Label>{t("apply.languages")}</Label>
            <div className="flex flex-wrap gap-2">
              {Object.entries(languageOptions).map(([code, label]) => {
                const active = languages.includes(code)
                return (
                  <button
                    key={code}
                    type="button"
                    disabled={!businessEditable}
                    onClick={() =>
                      setLanguages((prev) =>
                        active ? prev.filter((c) => c !== code) : [...prev, code],
                      )
                    }
                    className={cn(
                      "touch-target rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors",
                      active
                        ? "border-primary bg-primary/10 text-primary"
                        : "bg-muted/30 text-muted-foreground hover:bg-accent/50",
                    )}
                  >
                    {label}
                  </button>
                )
              })}
            </div>
          </div>
        </section>

        {/* ── Subjects & rate ── */}
        <section className="space-y-4 rounded-2xl border bg-card p-5 shadow-xs">
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {t("apply.stepSubjects")}
          </h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="rate">{t("apply.hourlyRate")}</Label>
              <Input
                id="rate"
                type="number"
                min={20}
                className="no-spinner"
                value={hourlyRate}
                disabled={!businessEditable}
                onChange={(e) => setHourlyRate(e.target.value)}
              />
              <p className="text-xs text-muted-foreground">
                {t("apply.hourlyRateHint", { percent: profile?.commission_percent ?? 10 })}
              </p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="sibling-rate">{t("apply.additionalChildRate")}</Label>
              <Input
                id="sibling-rate"
                type="number"
                min={0}
                className="no-spinner"
                value={additionalChildRate}
                disabled={!businessEditable}
                onChange={(e) => setAdditionalChildRate(e.target.value)}
              />
            </div>
          </div>

          <div className="space-y-3">
            <Label>{t("apply.subjectsTitle")}</Label>
            {subjects.map((row, index) => (
              <div key={index} className="space-y-3 rounded-xl border bg-muted/20 p-3">
                <div className="flex items-center gap-2">
                  <Select
                    value={row.subject_id !== null ? String(row.subject_id) : ""}
                    disabled={!businessEditable}
                    onValueChange={(value) =>
                      setSubjects((prev) =>
                        prev.map((r, i) => (i === index ? { ...r, subject_id: Number(value) } : r)),
                      )
                    }
                  >
                    <SelectTrigger className="w-full">
                      <SelectValue placeholder={t("dir.subject")} />
                    </SelectTrigger>
                    <SelectContent>
                      {catalogSubjects
                        .filter((s) => s.id === row.subject_id || !usedSubjectIds.has(s.id))
                        .map((subject) => (
                          <SelectItem key={subject.id} value={String(subject.id)}>
                            {subject.name}
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                  {businessEditable && subjects.length > 1 && (
                    <Button
                      variant="ghost"
                      size="icon"
                      aria-label={tc("actions.delete")}
                      title={tc("actions.delete")}
                      onClick={() => setSubjects((prev) => prev.filter((_, i) => i !== index))}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  )}
                </div>
                {row.subject_id !== null && (
                  <div className="flex flex-wrap gap-1.5">
                    {grades.map((grade) => {
                      const active = row.grade_sorts.includes(grade.sort_order)
                      return (
                        <button
                          key={grade.id}
                          type="button"
                          disabled={!businessEditable}
                          onClick={() =>
                            setSubjects((prev) =>
                              prev.map((r, i) =>
                                i === index
                                  ? {
                                      ...r,
                                      grade_sorts: active
                                        ? r.grade_sorts.filter((s) => s !== grade.sort_order)
                                        : [...r.grade_sorts, grade.sort_order],
                                    }
                                  : r,
                              ),
                            )
                          }
                          className={cn(
                            "rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
                            active
                              ? "border-primary bg-primary/10 text-primary"
                              : "bg-background text-muted-foreground hover:bg-accent/50",
                          )}
                        >
                          {grade.name}
                        </button>
                      )
                    })}
                    <span className="self-center text-xs text-muted-foreground">
                      {row.grade_sorts.length === 0 ? t("profile.allGrades") : ""}
                    </span>
                  </div>
                )}
              </div>
            ))}
            {businessEditable && subjects.length < 12 && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setSubjects((prev) => [...prev, { subject_id: null, grade_sorts: [] }])}
              >
                <Plus data-slot="icon" />
                {t("apply.addSubject")}
              </Button>
            )}
          </div>
        </section>

        {/* ── Verification ── */}
        <section className="space-y-4 rounded-2xl border bg-card p-5 shadow-xs">
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {t("apply.stepVerification")}
          </h2>
          <div className="space-y-2">
            <Label htmlFor="fayda">{t("apply.faydaId")}</Label>
            {profile?.has_fayda && faydaId === "" ? (
              <div className="flex items-center gap-2">
                <Badge variant="outline" className="border-success/30 bg-success/10 text-success">
                  {t("dir.verified")}
                </Badge>
                {editable && (
                  <Button variant="ghost" size="sm" onClick={() => setFaydaId(" ")}>
                    {tc("actions.edit")}
                  </Button>
                )}
              </div>
            ) : (
              <Input
                id="fayda"
                inputMode="numeric"
                value={faydaId.trim()}
                disabled={!editable}
                onChange={(e) => setFaydaId(e.target.value)}
              />
            )}
            <p className="text-xs text-muted-foreground">{t("apply.faydaHint")}</p>
          </div>

          <div
            {...documentDrop.dropProps}
            className={cn("space-y-3 rounded-2xl", documentDrop.dragOver && DROP_ACTIVE)}
          >
            <div>
              <Label>{t("apply.documents")}</Label>
              <p className="text-xs text-muted-foreground">{t("apply.documentsHint")}</p>
            </div>

            {(profile?.attachments ?? []).map((attachment) => (
              <div
                key={attachment.id}
                className="flex items-center justify-between gap-3 rounded-xl border bg-muted/20 px-3 py-2.5"
              >
                <a
                  href={attachment.url ?? "#"}
                  target="_blank"
                  rel="noreferrer"
                  className="flex min-w-0 items-center gap-2 text-sm font-medium hover:underline"
                >
                  <FileText className="size-4 shrink-0 text-muted-foreground" strokeWidth={1.75} />
                  <span className="truncate">{attachment.name}</span>
                  {attachment.imported && (
                    <Badge variant="outline" className="shrink-0 text-xs">
                      {t("apply.importFromEmployee")}
                    </Badge>
                  )}
                </a>
                {editable && (
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label={tc("actions.delete")}
                    title={tc("actions.delete")}
                    onClick={() => removeDocument(attachment)}
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ))}

            {pendingFile && (
              <div className="space-y-2 rounded-xl border border-primary/30 bg-primary/5 p-3">
                <Input
                  value={pendingFileName}
                  onChange={(e) => setPendingFileName(e.target.value)}
                  placeholder={pendingFile.name}
                />
                <div className="flex gap-2">
                  <Button size="sm" loading={uploading} onClick={uploadDocument}>
                    <Upload data-slot="icon" />
                    {t("apply.uploadDocument")}
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    disabled={uploading}
                    onClick={() => {
                      setPendingFile(null)
                      setPendingFileName("")
                    }}
                  >
                    {tc("actions.cancel")}
                  </Button>
                </div>
              </div>
            )}

            {editable && (
              <div className="flex flex-wrap gap-2">
                <input
                  ref={fileInput}
                  type="file"
                  accept={CREDENTIAL_ACCEPT}
                  className="hidden"
                  onChange={(e) => {
                    documentDrop.takeFiles(e.target.files)
                    e.target.value = ""
                  }}
                />
                <Button variant="outline" size="sm" onClick={() => fileInput.current?.click()}>
                  <Upload data-slot="icon" />
                  {t("apply.uploadDocument")}
                </Button>
                <Button variant="outline" size="sm" onClick={openImport}>
                  <Import data-slot="icon" />
                  {t("apply.importFromEmployee")}
                </Button>
              </div>
            )}
          </div>
        </section>

        {/* ── Actions ── */}
        {(businessEditable || editable) && (
          <div className="flex flex-col gap-2 pb-8 sm:flex-row">
            <Button
              variant="outline"
              className="h-11 flex-1"
              loading={saving && !submitting}
              disabled={submitting}
              onClick={() => void save()}
            >
              {t("apply.saveDraft")}
            </Button>
            {editable && (
              <Button className="h-11 flex-1" loading={submitting} disabled={saving} onClick={submitApplication}>
                {t("apply.submit")}
              </Button>
            )}
          </div>
        )}
      </div>

      {confirmDialog}

      {/* Employee-file import sheet */}
      <ResponsiveSheet open={importOpen} onOpenChange={(open) => !open && !importing && setImportOpen(false)}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("apply.importFromEmployee")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-2">
            <p className="text-sm text-muted-foreground">{t("apply.importPick")}</p>
            {employeeDocs === null ? (
              <Skeleton className="h-24 rounded-xl" />
            ) : employeeDocs.length === 0 ? (
              <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground">
                {t("dir.empty")}
              </p>
            ) : (
              employeeDocs.map((doc) => {
                const checked = importSelection.includes(doc.id)
                return (
                  <label
                    key={doc.id}
                    className="flex cursor-pointer items-center gap-3 rounded-xl border bg-muted/20 px-3 py-2.5"
                  >
                    <Checkbox
                      checked={checked}
                      onCheckedChange={(value) =>
                        setImportSelection((prev) =>
                          value ? [...prev, doc.id] : prev.filter((id) => id !== doc.id),
                        )
                      }
                    />
                    <span className="min-w-0 truncate text-sm font-medium">{doc.name}</span>
                  </label>
                )
              })
            )}
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button variant="outline" className="h-11 flex-1" disabled={importing} onClick={() => setImportOpen(false)}>
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              loading={importing}
              disabled={importSelection.length === 0}
              onClick={runImport}
            >
              {t("apply.importSelected")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
