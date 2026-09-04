"use client"

import { useEffect, useMemo, useState } from "react"

import { useBranchScope } from "@/components/ui/branch-select"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { Branch, Paginated, School, Section } from "@/lib/types"

interface Option {
  id: number
  name: string
}

/**
 * Cascading location scope for LMS list pages (materials today, exams /
 * assignments can adopt the same hook later): each level only appears once
 * its parent is chosen —
 *   - Temari.et admins: school → branch → grade → section (a "platform
 *     library" pseudo-option stands in for school when `allowLibrary`).
 *   - Principals (school-wide workspace, no branch context): branch → grade → section.
 *   - Directors/teachers (already inside one branch): grade → section.
 * All levels are optional narrowing except the platform school pick itself
 * (there is no valid "everything, everywhere" list for platform staff).
 */
export function useLmsScope({ allowLibrary = false }: { allowLibrary?: boolean } = {}) {
  const { isPlatform } = useSchoolContext()
  const { needsBranch, branches: contextBranches } = useBranchScope()

  const [library, setLibrary] = useState(allowLibrary)
  const [schoolId, setSchoolId] = useState<number | null>(null)
  const [branchId, setBranchId] = useState<number | null>(null)
  const [gradeLevelId, setGradeLevelId] = useState<number | null>(null)
  const [sectionId, setSectionId] = useState<number | null>(null)

  const [schools, setSchools] = useState<Option[] | null>(null)
  const [platformBranches, setPlatformBranches] = useState<Option[] | null>(null)
  const [sections, setSections] = useState<Section[]>([])

  // Platform: the full school catalog, fetched once.
  useEffect(() => {
    if (!isPlatform) return
    apiFetch<{ data: School[] }>("/schools/export")
      .then((res) => setSchools(res.data.map((s) => ({ id: s.id, name: s.name }))))
      .catch(() => setSchools([]))
  }, [isPlatform])

  // Platform: branches of the chosen school.
  useEffect(() => {
    if (!isPlatform || schoolId === null) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while (re)loading
      setPlatformBranches(null)
      return
    }
    setPlatformBranches(null)
    apiFetch<Paginated<Branch>>(`/schools/${schoolId}/branches?per_page=100`)
      .then((res) => setPlatformBranches(res.data.map((b) => ({ id: b.id, name: b.name }))))
      .catch(() => setPlatformBranches([]))
  }, [isPlatform, schoolId])

  const branchOptions = isPlatform ? (platformBranches ?? []) : contextBranches

  // Sections of the effective branch, to derive grade + section options.
  // Cascades strictly: platform needs a school AND branch, a school-wide
  // principal needs a branch, a director/teacher's branch is already fixed
  // by their workspace context.
  useEffect(() => {
    if (isPlatform) {
      if (library || schoolId === null || branchId === null) {
        // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while (re)loading
        setSections([])
        return
      }
    } else if (needsBranch && branchId === null) {
      setSections([])
      return
    }

    const branchParam = branchId !== null ? `&branch_id=${branchId}` : ""
    apiFetch<Paginated<Section>>(`/sections?per_page=100${branchParam}`)
      .then((res) => setSections(res.data))
      .catch(() => setSections([]))
  }, [isPlatform, needsBranch, library, schoolId, branchId])

  // Cascading resets: picking a shallower level invalidates deeper ones.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- cascading reset
    setBranchId(null)
  }, [schoolId, library])
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- cascading reset
    setGradeLevelId(null)
  }, [branchId])
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- cascading reset
    setSectionId(null)
  }, [gradeLevelId])

  const gradeOptions = useMemo(() => {
    const seen = new Map<number, { id: number; name: string; sort: number }>()
    for (const section of sections) {
      const grade = section.grade_level
      if (grade && !seen.has(grade.id)) {
        seen.set(grade.id, { id: grade.id, name: grade.name, sort: grade.sort_order ?? 0 })
      }
    }
    return [...seen.values()].sort((a, b) => a.sort - b.sort)
  }, [sections])

  const sectionOptions = useMemo(
    () => (gradeLevelId !== null ? sections.filter((s) => s.grade_level?.id === gradeLevelId) : []),
    [sections, gradeLevelId],
  )

  const ready = !isPlatform || library || schoolId !== null

  const params = useMemo(() => {
    if (isPlatform && library) return "&platform=1"
    let p = ""
    if (isPlatform && schoolId !== null) p += `&school_id=${schoolId}`
    if (branchId !== null) p += `&branch_id=${branchId}`
    if (gradeLevelId !== null) p += `&grade_level_id=${gradeLevelId}`
    if (sectionId !== null) p += `&section_id=${sectionId}`
    return p
  }, [isPlatform, library, schoolId, branchId, gradeLevelId, sectionId])

  return {
    isPlatform,
    needsBranch,
    allowLibrary,
    library,
    setLibrary,
    schoolId,
    setSchoolId,
    branchId,
    setBranchId,
    gradeLevelId,
    setGradeLevelId,
    sectionId,
    setSectionId,
    schools,
    branchOptions,
    gradeOptions,
    sectionOptions,
    ready,
    params,
  }
}

export type LmsScope = ReturnType<typeof useLmsScope>

const LIBRARY = "__library__"
const ANY = "__any__"

/** Toolbar rendering of useLmsScope() — mount unconditionally, it hides levels that don't apply yet. */
export function LmsScopeFilterBar(scope: LmsScope & { hideGrade?: boolean }) {
  const { t } = useTranslation("lms")

  const showGradeSelect = !scope.hideGrade && scope.gradeOptions.length > 0

  if (!scope.isPlatform && !scope.needsBranch && !showGradeSelect && scope.sectionOptions.length === 0) return null

  return (
    <div className="flex flex-wrap items-center gap-2">
      {scope.isPlatform && (
        <Select
          value={scope.library ? LIBRARY : scope.schoolId !== null ? String(scope.schoolId) : ""}
          onValueChange={(v) => {
            if (v === LIBRARY) {
              scope.setLibrary(true)
              scope.setSchoolId(null)
            } else {
              scope.setLibrary(false)
              scope.setSchoolId(Number(v))
            }
          }}
        >
          <SelectTrigger className="h-9 w-full md:w-52">
            <SelectValue placeholder={t("scope.school")} />
          </SelectTrigger>
          <SelectContent>
            {scope.allowLibrary && <SelectItem value={LIBRARY}>{t("scope.platformLibrary")}</SelectItem>}
            {(scope.schools ?? []).map((school) => (
              <SelectItem key={school.id} value={String(school.id)}>
                {school.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      {(scope.isPlatform ? scope.schoolId !== null && !scope.library : scope.needsBranch) && (
        <Select
          value={scope.branchId !== null ? String(scope.branchId) : ANY}
          onValueChange={(v) => scope.setBranchId(v === ANY ? null : Number(v))}
        >
          <SelectTrigger className="h-9 w-full md:w-48">
            <SelectValue placeholder={t("scope.branch")} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ANY}>{t("scope.allBranches")}</SelectItem>
            {scope.branchOptions.map((branch) => (
              <SelectItem key={branch.id} value={String(branch.id)}>
                {branch.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      {showGradeSelect && (
        <Select
          value={scope.gradeLevelId !== null ? String(scope.gradeLevelId) : ANY}
          onValueChange={(v) => scope.setGradeLevelId(v === ANY ? null : Number(v))}
        >
          <SelectTrigger className="h-9 w-full md:w-44">
            <SelectValue placeholder={t("scope.grade")} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ANY}>{t("scope.allGrades")}</SelectItem>
            {scope.gradeOptions.map((grade) => (
              <SelectItem key={grade.id} value={String(grade.id)}>
                {grade.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      {scope.gradeLevelId !== null && scope.sectionOptions.length > 0 && (
        <Select
          value={scope.sectionId !== null ? String(scope.sectionId) : ANY}
          onValueChange={(v) => scope.setSectionId(v === ANY ? null : Number(v))}
        >
          <SelectTrigger className="h-9 w-full md:w-44">
            <SelectValue placeholder={t("scope.section")} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ANY}>{t("scope.allSections")}</SelectItem>
            {scope.sectionOptions.map((section) => (
              <SelectItem key={section.id} value={String(section.id)}>
                {section.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </div>
  )
}
