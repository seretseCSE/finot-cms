"use client"

import { ArrowLeft, ArrowRight, Check, Download, FileSpreadsheet, Loader2, UploadCloud, X } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import {
  autoMapColumns,
  buildRowPayload,
  mappingToSave,
  parseDateCell,
  rowHasContent,
  TARGET_FIELDS,
  unresolvedGradeTexts,
  type DateInterpretation,
  type TargetFieldKey,
} from "@/components/students/import/fields"
import { downloadTemplate, parseSpreadsheet, type ParsedSheet } from "@/components/students/import/sheet"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { useFileDrop } from "@/components/ui/dropzone"
import { PageHeader } from "@/components/ui/page-header"
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  AcademicYear,
  GradeLevel,
  Paginated,
  Section,
  StudentImport,
  StudentImportRow,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const SPREADSHEET_ACCEPT = ".xlsx,.xls,.csv"
const CHUNK_SIZE = 300

const DATE_MODES: DateInterpretation[] = ["auto", "gc-dmy", "gc-mdy", "gc-ymd", "ec-dmy", "ec-ymd"]

/** The registrar's required minimum before validation can start. */
function mappingProblems(
  mapping: (TargetFieldKey | null)[],
  defaultGradeId: string,
): ("name" | "gender" | "grade")[] {
  const has = (key: TargetFieldKey) => mapping.includes(key)
  const problems: ("name" | "gender" | "grade")[] = []

  if (!has("full_name") && !(has("first_name") && has("father_name"))) problems.push("name")
  if (!has("gender")) problems.push("gender")
  if (!has("grade") && !defaultGradeId) problems.push("grade")

  return problems
}

export default function StudentImportStudioPage() {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  const canCreate = permissions.includes("students.create")
  const hasBranch = active.branchId != null
  const { needsBranch } = useBranchScope()
  const [targetBranchId, setTargetBranchId] = useState<number | null>(null)
  const branchReady = hasBranch || (needsBranch && targetBranchId != null)

  const [step, setStep] = useState(0)

  // Step 1 — target + file.
  const [academicYears, setAcademicYears] = useState<AcademicYear[]>([])
  const [gradeLevels, setGradeLevels] = useState<GradeLevel[]>([])
  const [sections, setSections] = useState<Section[]>([])
  const [yearId, setYearId] = useState("")
  const [defaultGradeId, setDefaultGradeId] = useState("")
  const [defaultSectionId, setDefaultSectionId] = useState("")
  const [file, setFile] = useState<File | null>(null)
  const [sheet, setSheet] = useState<ParsedSheet | null>(null)
  const [parsing, setParsing] = useState(false)
  const fileInput = useRef<HTMLInputElement>(null)

  // Step 2 — mapping.
  const [mapping, setMapping] = useState<(TargetFieldKey | null)[]>([])
  const [dateMode, setDateMode] = useState<DateInterpretation>("auto")
  const [gradeOverrides, setGradeOverrides] = useState<Record<string, number>>({})
  const [savedMap, setSavedMap] = useState<Record<string, string> | null>(null)

  // Validation upload.
  const [uploading, setUploading] = useState(false)
  const [progress, setProgress] = useState(0)

  useEffect(() => {
    if (!branchReady) return
    let cancelled = false
    const branchParam = !hasBranch && targetBranchId != null ? `branch_id=${targetBranchId}` : ""
    const withBranch = (path: string) =>
      branchParam ? `${path}${path.includes("?") ? "&" : "?"}${branchParam}` : path

    apiFetch<Paginated<AcademicYear>>(withBranch("/academic-years"))
      .then((res) => {
        if (cancelled) return
        setAcademicYears(res.data)
        const current = res.data.find((year) => year.is_current)
        if (current) setYearId((prev) => prev || String(current.id))
      })
      .catch(() => {})
    apiFetch<{ data: GradeLevel[] }>(withBranch("/grade-levels"))
      .then((res) => !cancelled && setGradeLevels(res.data))
      .catch(() => {})
    apiFetch<Paginated<Section>>(withBranch("/sections"))
      .then((res) => !cancelled && setSections(res.data))
      .catch(() => {})
    // The school's last mapping — headers it has seen before map themselves.
    apiFetch<Paginated<StudentImport>>(withBranch("/student-imports?per_page=1"))
      .then((res) => !cancelled && setSavedMap(res.data[0]?.column_map ?? null))
      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [branchReady, hasBranch, targetBranchId, active.branchId])

  const rowContext = useMemo(
    () => ({ mapping, dateMode, gradeLevels, sections, gradeOverrides }),
    [mapping, dateMode, gradeLevels, sections, gradeOverrides],
  )

  const contentRows = useMemo(
    () => (sheet ? sheet.rows.filter((cells) => rowHasContent(cells, mapping)) : []),
    [sheet, mapping],
  )

  const unresolvedGrades = useMemo(
    () => (sheet ? unresolvedGradeTexts(contentRows, rowContext) : []),
    [sheet, contentRows, rowContext],
  )

  const problems = mappingProblems(mapping, defaultGradeId)

  // Date preview: first parsed birth dates under the current interpretation.
  const datePreview = useMemo(() => {
    const dobIndex = mapping.indexOf("date_of_birth")
    if (dobIndex === -1 || !sheet) return []
    const samples: { raw: string; parsed: string | null }[] = []
    for (const cells of contentRows) {
      const raw = cells[dobIndex]
      const text = String(raw ?? "").trim()
      if (!text) continue
      samples.push({
        raw: text,
        parsed: parseDateCell(typeof raw === "number" ? raw : text, dateMode),
      })
      if (samples.length >= 3) break
    }
    return samples
  }, [mapping, sheet, contentRows, dateMode])

  // Picked or dropped, the workbook is parsed here in the browser — it never
  // uploads.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: SPREADSHEET_ACCEPT,
    disabled: parsing,
    onFiles: ([picked]) => void handleFile(picked),
  })

  async function handleFile(picked: File) {
    setParsing(true)
    try {
      const parsed = await parseSpreadsheet(picked)
      if (parsed.headers.length === 0 || parsed.rows.length === 0) {
        toast.error(t("import.emptyFile"))
        return
      }
      setFile(picked)
      setSheet(parsed)
      setMapping(autoMapColumns(parsed.headers, savedMap))
      setGradeOverrides({})
    } catch {
      toast.error(t("import.parseFailed"))
    } finally {
      setParsing(false)
    }
  }

  function setColumnMapping(index: number, key: TargetFieldKey | null) {
    setMapping((prev) => {
      const next = [...prev]
      // A target field maps to at most one column — picking it here releases
      // any other column that held it.
      if (key !== null) {
        const existing = next.indexOf(key)
        if (existing !== -1 && existing !== index) next[existing] = null
      }
      next[index] = key
      return next
    })
  }

  async function startValidation() {
    if (!sheet || !file || !yearId) return
    setUploading(true)
    setProgress(0)

    try {
      const session = await apiFetch<{ data: StudentImport }>("/student-imports", {
        method: "POST",
        body: {
          ...(targetBranchId != null ? { branch_id: targetBranchId } : {}),
          academic_year_id: Number(yearId),
          grade_level_id: defaultGradeId ? Number(defaultGradeId) : undefined,
          section_id: defaultSectionId ? Number(defaultSectionId) : undefined,
          file_name: file.name,
          column_map: mappingToSave(sheet.headers, mapping),
        },
      })

      const payloads = contentRows.map((cells, index) => ({
        row_number: index + 1,
        data: buildRowPayload(cells, rowContext),
      }))

      for (let offset = 0; offset < payloads.length; offset += CHUNK_SIZE) {
        await apiFetch<{ data: StudentImportRow[] }>(
          `/student-imports/${session.data.id}/rows`,
          { method: "POST", body: { rows: payloads.slice(offset, offset + CHUNK_SIZE) } },
        )
        setProgress(Math.min(100, Math.round(((offset + CHUNK_SIZE) / payloads.length) * 100)))
      }

      router.push(`/students/import/${session.data.id}`)
    } catch (error) {
      setUploading(false)
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  function templateDownload() {
    const headers = [
      t("import.template.firstName"), t("import.template.fatherName"), t("import.template.grandfatherName"),
      t("import.template.motherName"), t("import.template.gender"), t("import.template.dateOfBirth"),
      t("import.template.grade"), t("import.template.section"), t("import.template.studentPhone"),
      t("import.template.guardianName"), t("import.template.guardianPhone"), t("import.template.relationship"),
      t("import.template.guardian2Name"), t("import.template.guardian2Phone"), t("import.template.guardian2Relationship"),
    ]
    const example = [
      "Abel", "Tesfaye", "Lemma", "Aster", "M", "12/03/2009", "Grade 5", "A",
      "", "Almaz Bekele Ergano", "0911223344", "Mother", "", "", "",
    ]
    void downloadTemplate(headers, example, "temari-students-template.xlsx")
  }

  if (!canCreate || (!hasBranch && !needsBranch)) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("import.title")} backHref="/students" backLabel={t("title")} />
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {!canCreate ? tc("errors.forbidden") : t("noBranch")}
          </div>
        </div>
      </div>
    )
  }

  if (needsBranch && targetBranchId == null) {
    return (
      <div className="space-y-6">
        <PageHeader
          title={t("import.title")}
          description={t("import.subtitle")}
          backHref="/students"
          backLabel={t("title")}
        />
        <div className="page-gutter">
          <div className="max-w-md space-y-3 rounded-2xl border p-4">
            <p className="text-sm text-muted-foreground">{t("wizard.pickBranch")}</p>
            <BranchField value={targetBranchId} onChange={setTargetBranchId} />
          </div>
        </div>
      </div>
    )
  }

  const stepKeys = ["file", "map"] as const

  return (
    <div className="space-y-6 pb-6">
      <PageHeader
        title={t("import.title")}
        description={t("import.subtitle")}
        backHref="/students"
        backLabel={t("title")}
      />

      {/* Stepper — same visual grammar as the registration wizard. */}
      <div className="page-gutter">
        <ol className="flex items-center gap-1.5 overflow-x-auto pb-1">
          {[...stepKeys, "review" as const].map((key, index) => (
            <li key={key} className="flex shrink-0 items-center gap-1.5">
              <button
                type="button"
                onClick={() => index < step && setStep(index)}
                disabled={index > step}
                className={cn(
                  "flex h-8 items-center gap-1.5 rounded-full px-3 text-xs font-medium transition-colors",
                  index === step
                    ? "bg-primary text-primary-foreground"
                    : index < step
                      ? "bg-primary/10 text-primary"
                      : "bg-muted text-muted-foreground",
                )}
              >
                {index < step ? <Check className="size-3" /> : <span>{index + 1}</span>}
                <span className={cn(index !== step && "hidden sm:inline")}>
                  {t(`import.steps.${key}`)}
                </span>
              </button>
              {index < 2 ? <span className="h-px w-3 shrink-0 bg-border" aria-hidden /> : null}
            </li>
          ))}
        </ol>
      </div>

      <div className="page-gutter">
        <div className="mx-auto flex min-h-[calc(100svh-21rem)] max-w-3xl flex-col gap-6 md:min-h-[calc(100svh-16rem)]">
          {step === 0 ? (
            <div className="space-y-6">
              {/* Target */}
              <section className="space-y-4 rounded-2xl border p-4">
                <h2 className="text-sm font-semibold">{t("import.target.heading")}</h2>
                <div className="grid gap-4 sm:grid-cols-3">
                  <label className="space-y-1.5 text-sm">
                    <span className="font-medium">{t("import.target.year")}</span>
                    <Select value={yearId} onValueChange={setYearId}>
                      <SelectTrigger className="h-11 w-full">
                        <SelectValue placeholder={t("import.target.yearPlaceholder")} />
                      </SelectTrigger>
                      <SelectContent>
                        {academicYears.map((year) => (
                          <SelectItem key={year.id} value={String(year.id)}>
                            {year.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </label>
                  <label className="space-y-1.5 text-sm">
                    <span className="font-medium">{t("import.target.grade")}</span>
                    <Select
                      value={defaultGradeId || "none"}
                      onValueChange={(value) => {
                        setDefaultGradeId(value === "none" ? "" : value)
                        setDefaultSectionId("")
                      }}
                    >
                      <SelectTrigger className="h-11 w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">{t("import.target.fromColumn")}</SelectItem>
                        {gradeLevels.map((grade) => (
                          <SelectItem key={grade.id} value={String(grade.id)}>
                            {grade.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </label>
                  <label className="space-y-1.5 text-sm">
                    <span className="font-medium">{t("import.target.section")}</span>
                    <Select
                      value={defaultSectionId || "none"}
                      onValueChange={(value) => setDefaultSectionId(value === "none" ? "" : value)}
                      disabled={!defaultGradeId}
                    >
                      <SelectTrigger className="h-11 w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">{t("import.target.noSection")}</SelectItem>
                        {sections
                          .filter((section) => String(section.grade_level_id) === defaultGradeId)
                          .map((section) => (
                            <SelectItem key={section.id} value={String(section.id)}>
                              {section.name}
                            </SelectItem>
                          ))}
                      </SelectContent>
                    </Select>
                  </label>
                </div>
                <p className="text-xs text-muted-foreground">{t("import.target.hint")}</p>
              </section>

              {/* File */}
              <section
                {...dropProps}
                className={cn(
                  "flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed p-8 text-center transition-colors",
                  dragOver && "border-primary bg-primary/5",
                )}
              >
                <input
                  ref={fileInput}
                  type="file"
                  accept={SPREADSHEET_ACCEPT}
                  className="hidden"
                  onChange={(event) => {
                    takeFiles(event.target.files)
                    event.target.value = ""
                  }}
                />
                {file && sheet ? (
                  <>
                    <FileSpreadsheet className="size-10 text-primary" />
                    <div>
                      <p className="text-sm font-medium">{file.name}</p>
                      <p className="text-xs text-muted-foreground">
                        {t("import.fileSummary", {
                          rows: sheet.rows.length,
                          columns: sheet.headers.length,
                        })}
                      </p>
                    </div>
                    <Button
                      type="button"
                      variant="outline"
                      className="h-9"
                      onClick={() => {
                        setFile(null)
                        setSheet(null)
                        setMapping([])
                      }}
                    >
                      <X className="size-4" />
                      {t("import.changeFile")}
                    </Button>
                  </>
                ) : (
                  <>
                    <UploadCloud className="size-10 text-muted-foreground" />
                    <div>
                      <p className="text-sm font-medium">{t("import.dropHere")}</p>
                      <p className="text-xs text-muted-foreground">{t("import.formats")}</p>
                    </div>
                    <Button
                      type="button"
                      className="h-11"
                      disabled={parsing}
                      onClick={() => fileInput.current?.click()}
                    >
                      {parsing ? <Loader2 className="size-4 animate-spin" /> : <UploadCloud className="size-4" />}
                      {t("import.chooseFile")}
                    </Button>
                  </>
                )}
              </section>

              <button
                type="button"
                onClick={templateDownload}
                className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
              >
                <Download className="size-4" />
                {t("import.downloadTemplate")}
              </button>
            </div>
          ) : null}

          {step === 1 && sheet ? (
            <div className="space-y-6">
              <section className="space-y-1 rounded-2xl border p-4">
                <h2 className="text-sm font-semibold">{t("import.map.heading")}</h2>
                <p className="text-xs text-muted-foreground">{t("import.map.hint")}</p>

                <div className="mt-3 divide-y">
                  {sheet.headers.map((header, index) => {
                    const samples = contentRows
                      .map((cells) => String(cells[index] ?? "").trim())
                      .filter(Boolean)
                      .slice(0, 3)
                    return (
                      <div
                        key={`${header}-${index}`}
                        className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:gap-4"
                      >
                        <div className="min-w-0 sm:w-1/2">
                          <p className="truncate text-sm font-medium">{header || t("import.map.unnamed")}</p>
                          <p className="truncate text-xs text-muted-foreground">
                            {samples.join(" · ") || t("import.map.emptyColumn")}
                          </p>
                        </div>
                        <div className="sm:w-1/2">
                          <Select
                            value={mapping[index] ?? "ignore"}
                            onValueChange={(value) =>
                              setColumnMapping(index, value === "ignore" ? null : (value as TargetFieldKey))
                            }
                          >
                            <SelectTrigger
                              className={cn("h-10 w-full", mapping[index] && "border-primary/40")}
                            >
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="ignore">{t("import.map.ignore")}</SelectItem>
                              {(["student", "enrollment", "guardians"] as const).map((group) => (
                                <SelectGroup key={group}>
                                  <SelectLabel>{t(`import.map.groups.${group}`)}</SelectLabel>
                                  {TARGET_FIELDS.filter((field) => field.group === group).map((field) => (
                                    <SelectItem key={field.key} value={field.key}>
                                      {t(`import.fields.${field.labelKey}`)}
                                    </SelectItem>
                                  ))}
                                </SelectGroup>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>
                      </div>
                    )
                  })}
                </div>
              </section>

              {mapping.includes("date_of_birth") ? (
                <section className="space-y-3 rounded-2xl border p-4">
                  <h3 className="text-sm font-semibold">{t("import.map.dates.heading")}</h3>
                  <Select value={dateMode} onValueChange={(value) => setDateMode(value as DateInterpretation)}>
                    <SelectTrigger className="h-10 w-full sm:w-72">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {DATE_MODES.map((mode) => (
                        <SelectItem key={mode} value={mode}>
                          {t(`import.map.dates.${mode}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {datePreview.length > 0 ? (
                    <p className="text-xs text-muted-foreground">
                      {datePreview
                        .map(({ raw, parsed }) => `${raw} → ${parsed ?? t("import.map.dates.invalid")}`)
                        .join("   ·   ")}
                    </p>
                  ) : null}
                </section>
              ) : null}

              {unresolvedGrades.length > 0 ? (
                <section className="space-y-3 rounded-2xl border border-amber-300/60 bg-amber-50/50 p-4 dark:border-amber-500/30 dark:bg-amber-500/5">
                  <h3 className="text-sm font-semibold">{t("import.map.grades.heading")}</h3>
                  <p className="text-xs text-muted-foreground">{t("import.map.grades.hint")}</p>
                  <div className="grid gap-2 sm:grid-cols-2">
                    {unresolvedGrades.map((text) => (
                      <div key={text} className="flex items-center gap-2">
                        <span className="min-w-0 flex-1 truncate rounded-md bg-muted px-2 py-1.5 text-sm">
                          {text}
                        </span>
                        <Select
                          value={
                            gradeOverrides[text.toLowerCase()] != null
                              ? String(gradeOverrides[text.toLowerCase()])
                              : ""
                          }
                          onValueChange={(value) =>
                            setGradeOverrides((prev) => ({ ...prev, [text.toLowerCase()]: Number(value) }))
                          }
                        >
                          <SelectTrigger className="h-9 w-36 shrink-0">
                            <SelectValue placeholder={t("import.map.grades.pick")} />
                          </SelectTrigger>
                          <SelectContent>
                            {gradeLevels.map((grade) => (
                              <SelectItem key={grade.id} value={String(grade.id)}>
                                {grade.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    ))}
                  </div>
                </section>
              ) : null}

              {problems.length > 0 ? (
                <div className="rounded-xl border border-destructive/40 bg-destructive/5 p-3 text-sm">
                  <ul className="list-inside list-disc space-y-0.5">
                    {problems.map((problem) => (
                      <li key={problem}>{t(`import.map.problems.${problem}`)}</li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </div>
          ) : null}

          {/* Upload progress overlay-ish card */}
          {uploading ? (
            <div className="space-y-3 rounded-2xl border p-6 text-center">
              <Loader2 className="mx-auto size-6 animate-spin text-primary" />
              <p className="text-sm font-medium">{t("import.validating")}</p>
              <div className="mx-auto h-2 w-full max-w-sm overflow-hidden rounded-full bg-muted">
                <div
                  className="h-full rounded-full bg-primary transition-all"
                  style={{ width: `${progress}%` }}
                />
              </div>
            </div>
          ) : null}

          {/* Footer nav */}
          <div className="mt-auto flex items-center gap-3 border-t pt-4">
            {step > 0 ? (
              <Button
                type="button"
                variant="outline"
                className="h-11 flex-1"
                disabled={uploading}
                onClick={() => setStep(0)}
              >
                <ArrowLeft className="size-4" />
                {tc("actions.back")}
              </Button>
            ) : null}
            {step === 0 ? (
              <Button
                type="button"
                className="h-11 flex-1"
                loading={parsing} disabled={!sheet || !yearId}
                onClick={() => setStep(1)}
              >
                {tc("actions.next")}
                <ArrowRight className="size-4" />
              </Button>
            ) : (
              <Button
                type="button"
                className="h-11 flex-1"
                disabled={uploading || problems.length > 0 || contentRows.length === 0}
                onClick={() => void startValidation()}
              >
                {uploading ? <Loader2 className="size-4 animate-spin" /> : <ArrowRight className="size-4" />}
                {t("import.validate", { count: contentRows.length })}
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
