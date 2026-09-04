/** Whether a subject is taught at a grade — empty/absent set = every grade. */
export function subjectAppliesToSort(
  subject: { grade_sorts?: number[] },
  sortOrder: number | null | undefined,
): boolean {
  if (sortOrder == null) return true
  const sorts = subject.grade_sorts ?? []
  return sorts.length === 0 || sorts.includes(sortOrder)
}

/**
 * Compact human label for a grade set: consecutive sort runs collapse to
 * ranges ("KG-1–G8, G10"). Empty set → null (caller shows "every grade").
 */
export function gradeSetLabel(
  gradeSorts: number[],
  gradeLevels: { sort_order: number; code: string }[],
): string | null {
  if (gradeSorts.length === 0) return null
  const codeBySort = new Map(gradeLevels.map((g) => [g.sort_order, g.code]))
  const sorted = [...gradeSorts].sort((a, b) => a - b)

  const parts: string[] = []
  let start = sorted[0]
  let prev = sorted[0]
  const flush = () => {
    const from = codeBySort.get(start) ?? String(start)
    const to = codeBySort.get(prev) ?? String(prev)
    parts.push(start === prev ? from : `${from}–${to}`)
  }
  for (const sort of sorted.slice(1)) {
    if (sort !== prev + 1) {
      flush()
      start = sort
    }
    prev = sort
  }
  flush()
  return parts.join(", ")
}
