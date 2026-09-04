"use client"

import type { ChatConversation } from "@/lib/types"

type Translate = (key: string, vars?: Record<string, string | number>) => string

/**
 * Display naming for conversations. System channels render LOCALIZED names
 * (the stored title is only the fallback); direct threads show the person
 * the backend resolved for this viewer.
 */
export function conversationTitle(conversation: ChatConversation, t: Translate): string {
  if (conversation.system === "staff_room") return t("channels.staffRoom")
  if (conversation.system === "branch_announcements") {
    return conversation.branch_name
      ? t("channels.branchAnnouncements", { branch: conversation.branch_name })
      : t("channels.announcements")
  }
  if (conversation.system === "school_announcements") return t("channels.wholeSchool")
  return conversation.display.title ?? conversation.title ?? t("thread.conversation")
}

export function conversationSubtitle(conversation: ChatConversation, t: Translate): string | null {
  if (conversation.kind === "direct" && conversation.student) {
    // Family thread — anchored to the child on BOTH sides of the fence.
    return t("thread.familyOf", { name: conversation.student.name })
  }
  if (conversation.system === "classroom") return t("channels.classroom")
  if (conversation.system === "school_announcements") return t("channels.reachAllBranches")
  if (conversation.system === "branch_announcements") return t("channels.reachThisBranch")
  if (conversation.kind === "channel") {
    return conversation.posting === "admins" ? t("channels.announcementChannel") : t("channels.channel")
  }
  if (conversation.kind === "group") return t("channels.group")
  return conversation.display.subtitle
}
