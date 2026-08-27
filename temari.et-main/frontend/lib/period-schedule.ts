/**
 * Time arithmetic for the period-schedule (bell schedule) editor.
 *
 * A school day is a chain: one block ends, the next begins. So editing a time
 * anywhere in the chain slides everything after it instead of leaving the
 * operator to retype the rest of the day by hand.
 */

/** One block of the day, as the editor holds it (HH:mm strings). */
export type TimeBlock = { starts_at: string; ends_at: string }

const DAY_END = 23 * 60 + 59

/** Minutes since midnight for an HH:mm string. */
export function minutesOf(time: string): number {
  const [h, m] = time.split(":").map(Number)
  return h * 60 + m
}

/** HH:mm plus (or minus) a number of minutes, clamped to the same day. */
export function addMinutes(time: string, minutes: number): string {
  const total = Math.max(0, Math.min(minutesOf(time) + minutes, DAY_END))
  return `${String(Math.floor(total / 60)).padStart(2, "0")}:${String(total % 60).padStart(2, "0")}`
}

/**
 * Slide every block after `index` by `shift` minutes. Each one keeps its own
 * length and its distance from the block before it, so a schedule that was
 * contiguous stays contiguous and a deliberate gap survives.
 */
export function shiftFrom<T extends TimeBlock>(rows: T[], index: number, shift: number): T[] {
  if (shift === 0) return rows

  return rows.map((row, i) =>
    i <= index
      ? row
      : {
          ...row,
          starts_at: addMinutes(row.starts_at, shift),
          ends_at: addMinutes(row.ends_at, shift),
        },
  )
}

/**
 * Apply an edit to one block and carry the rest of the day with it.
 *
 * - a new end time pushes/pulls every later block by the same amount, so the
 *   next block starts exactly where this one now ends;
 * - a new start time moves the block itself (its length is preserved) and the
 *   rest of the day with it.
 */
export function editBlock<T extends TimeBlock>(rows: T[], index: number, changes: Partial<T>): T[] {
  const current = rows[index]
  if (!current) return rows

  const next = { ...current, ...changes }

  // Moving a block's start carries its own length with it.
  if (changes.starts_at !== undefined && changes.ends_at === undefined) {
    next.ends_at = addMinutes(current.ends_at, minutesOf(changes.starts_at) - minutesOf(current.starts_at))
  }

  const shift = minutesOf(next.ends_at) - minutesOf(current.ends_at)

  return shiftFrom(
    rows.map((row, i) => (i === index ? next : row)),
    index,
    shift,
  )
}

/**
 * Remove a block and close the hole it leaves: the following block starts when
 * the removed one would have, and the rest of the day follows.
 */
export function removeBlock<T extends TimeBlock>(rows: T[], index: number): T[] {
  const removed = rows[index]
  const next = rows[index + 1]
  const shift = removed && next ? minutesOf(removed.starts_at) - minutesOf(next.starts_at) : 0

  return shiftFrom(rows, index, shift).filter((_, i) => i !== index)
}
