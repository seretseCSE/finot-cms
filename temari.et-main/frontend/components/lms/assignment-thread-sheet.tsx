"use client"

import { AssignmentChat } from "@/components/lms/assignment-chat"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"

/** The minimum identity needed to open a chat with one student. */
export interface ThreadStudent {
  student_id: number
  student_name: string
  student_public_id?: string | null
  student_photo_url?: string | null
}

interface Props {
  assignmentId: number
  thread: ThreadStudent | null
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Lets the table refresh its message counts after the teacher writes. */
  onReplied: () => void
}

/**
 * The teacher's side of one student's private assignment thread — a chat
 * CONTEXT conversation (ADR-019) mounted in a sheet, opened from the
 * Messages inbox on the assignment page. The full chat engine applies:
 * attachments, voice notes, reactions, replies, realtime.
 */
export function AssignmentThreadSheet({ assignmentId, thread, open, onOpenChange, onReplied }: Props) {
  if (thread === null) return null

  return (
    <ResponsiveSheet
      open={open}
      onOpenChange={(next) => {
        onOpenChange(next)
        if (!next) onReplied()
      }}
    >
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-lg">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            <span className="flex items-center gap-2.5">
              <PersonAvatar name={thread.student_name} photoUrl={thread.student_photo_url ?? null} />
              <span className="min-w-0">
                <span className="block truncate">{thread.student_name}</span>
                {thread.student_public_id && (
                  <span className="block text-xs font-normal text-muted-foreground">
                    {thread.student_public_id}
                  </span>
                )}
              </span>
            </span>
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="flex min-h-0 flex-1 flex-col p-0">
          <AssignmentChat key={thread.student_id} assignmentId={assignmentId} studentId={thread.student_id} lane="staff" />
        </ResponsiveSheetBody>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
