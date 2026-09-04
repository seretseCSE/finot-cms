/**
 * The client half of the platform's ONE search rule (the server half is
 * `App\Support\SearchTerm`).
 *
 * A query is matched WORD BY WORD against everything the row exposes, joined
 * into a single haystack. Ethiopian names live in separate fields
 * (first / father / grandfather), so a whole-phrase match against any one
 * field can never find a full name: typing "Abdi Fikre Gemeda" used to return
 * nothing while "Abdi" alone worked. Word-by-word also means the words may be
 * typed in any order, and a name can be mixed with a phone or an ID.
 */

/** Split what the user typed into lowercased words. */
export function searchWords(query: string): string[] {
  return query.trim().toLowerCase().split(/\s+/).filter(Boolean)
}

/**
 * Does this row match? `parts` are the row's searchable values (nullish and
 * non-string values are fine — they are stringified). An empty query matches
 * everything.
 */
export function matchesSearch(parts: readonly unknown[], query: string): boolean {
  const words = searchWords(query)
  if (!words.length) return true

  const haystack = parts.map((p) => String(p ?? "")).join(" ").toLowerCase()

  return words.every((word) => haystack.includes(word))
}
