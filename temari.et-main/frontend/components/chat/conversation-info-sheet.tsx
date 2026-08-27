"use client"

import { Archive, BellOff, Bell, LogOut, Pin, PinOff, UserPlus, X } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { apiFetch, ApiError } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { ChatConversation, ChatPartnerStaff } from "@/lib/types"
import { cn } from "@/lib/utils"

import { conversationTitle, conversationSubtitle } from "./labels"
import { useChatBase } from "./use-chat"

/**
 * Conversation details: members with roles, notification mute, pin,
 * group membership management (owners), leave, and archive.
 */
export function ConversationInfoSheet({
  conversation,
  open,
  onOpenChange,
  onConversationChange,
  onListChanged,
}: {
  conversation: ChatConversation
  open: boolean
  onOpenChange: (open: boolean) => void
  onConversationChange: (conversation: ChatConversation) => void
  onListChanged?: () => void
}) {
  const { t } = useTranslation("chat")
  const { t: te } = useTranslation("employees")
  const { user } = useAuth()
  const base = useChatBase()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const [addOpen, setAddOpen] = useState(false)
  const [searchQ, setSearchQ] = useState("")
  const [candidates, setCandidates] = useState<ChatPartnerStaff[]>([])

  const members = conversation.members ?? []
  const me = members.find((m) => m.id === user?.id)
  const canManage = conversation.kind === "group" && (me?.role === "owner" || me?.role === "moderator")
  const isStaffLane = base === "/chat"

  async function toggle(path: string, body?: Record<string, unknown>) {
    try {
      await apiFetch(`${base}/conversations/${conversation.id}/${path}`, { method: "POST", body })
      onListChanged?.()
      return true
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("thread.sendFailed"))
      return false
    }
  }

  async function searchCandidates(q: string) {
    setSearchQ(q)
    if (q.length < 2) return setCandidates([])
    try {
      const res = await apiFetch<{ data: { staff: ChatPartnerStaff[] } }>(
        `/chat/partners?q=${encodeURIComponent(q)}`,
      )
      setCandidates(res.data.staff.filter((s) => !members.some((m) => m.id === s.user_id)))
    } catch {
      setCandidates([])
    }
  }

  const subtitle = conversationSubtitle(conversation, t)

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{conversationTitle(conversation, t)}</ResponsiveSheetTitle>
          {subtitle && <ResponsiveSheetDescription>{subtitle}</ResponsiveSheetDescription>}
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody>
          <div className="space-y-5 px-1 pb-4">
        {/* Quick actions */}
        <div className="grid grid-cols-2 gap-2">
          <Button
            variant="outline"
            className="h-11 justify-start gap-2 rounded-xl"
            onClick={async () => {
              const ok = await toggle("mute", { minutes: conversation.muted ? null : 8 * 60 })
              if (ok) onConversationChange({ ...conversation, muted: !conversation.muted })
            }}
          >
            {conversation.muted ? <Bell className="size-4" /> : <BellOff className="size-4" />}
            {conversation.muted ? t("info.unmute") : t("info.mute")}
          </Button>
          <Button
            variant="outline"
            className="h-11 justify-start gap-2 rounded-xl"
            onClick={async () => {
              const ok = await toggle("pin")
              if (ok) onConversationChange({ ...conversation, pinned: !conversation.pinned })
            }}
          >
            {conversation.pinned ? <PinOff className="size-4" /> : <Pin className="size-4" />}
            {conversation.pinned ? t("info.unpin") : t("info.pin")}
          </Button>
        </div>

        {/* Members */}
        {members.length > 0 && (
          <div>
            <div className="mb-2 flex items-center justify-between">
              <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t("info.members", { count: members.filter((m) => !m.left).length })}
              </h3>
              {canManage && isStaffLane && (
                <Button variant="ghost" size="sm" className="h-7 gap-1.5 rounded-full text-xs" onClick={() => setAddOpen((v) => !v)}>
                  <UserPlus className="size-3.5" /> {t("info.addMembers")}
                </Button>
              )}
            </div>

            {addOpen && (
              <div className="mb-3 rounded-xl border p-2">
                <input
                  value={searchQ}
                  onChange={(event) => void searchCandidates(event.target.value)}
                  placeholder={t("newChat.searchStaff")}
                  className="h-9 w-full rounded-lg border bg-muted/30 px-3 text-sm outline-none focus:border-ring"
                />
                {candidates.map((candidate) => (
                  <button
                    key={candidate.user_id}
                    type="button"
                    className="mt-1 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-accent"
                    onClick={async () => {
                      try {
                        await apiFetch(`/chat/conversations/${conversation.id}/participants`, {
                          method: "POST",
                          body: { user_ids: [candidate.user_id] },
                        })
                        onConversationChange({
                          ...conversation,
                          members: [...members, { id: candidate.user_id, name: candidate.name, avatar_url: candidate.avatar_url, role: "member" }],
                        })
                        setCandidates((current) => current.filter((c) => c.user_id !== candidate.user_id))
                        toast.success(t("info.memberAdded"))
                      } catch (error) {
                        toast.error(error instanceof ApiError ? error.message : t("thread.sendFailed"))
                      }
                    }}
                  >
                    <PersonAvatar name={candidate.name} photoUrl={candidate.avatar_url} className="size-6 text-[9px]" />
                    {candidate.name}
                  </button>
                ))}
              </div>
            )}

            <ul className="space-y-0.5">
              {members.map((member) => (
                <li key={member.id} className={cn("flex items-center gap-2.5 rounded-lg px-1.5 py-1.5", member.left && "opacity-50")}>
                  <PersonAvatar name={member.name} photoUrl={member.avatar_url} className="size-8" />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm">{member.name}</span>
                    <span className="text-[11px] text-muted-foreground">
                      {member.left ? t("info.leftGroup") : t(`info.roles.${member.role}`)}
                    </span>
                  </span>
                  {canManage && member.id !== user?.id && !member.left && (
                    <Button
                      variant="ghost"
                      size="icon"
                      className="size-7 rounded-full text-muted-foreground"
                      aria-label={t("info.removeMember")}
                      title={t("info.removeMember")}
                      onClick={() =>
                        confirmDelete(async () => {
                          try {
                            await apiFetch(`/chat/conversations/${conversation.id}/participants/${member.id}`, { method: "DELETE" })
                            onConversationChange({
                              ...conversation,
                              members: members.map((m) => (m.id === member.id ? { ...m, left: true } : m)),
                            })
                          } catch (error) {
                            toast.error(error instanceof ApiError ? error.message : t("thread.sendFailed"))
                          }
                        }, t("info.removeMemberConfirm", { name: member.name }))
                      }
                    >
                      <X className="size-3.5" />
                    </Button>
                  )}
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Who this channel reaches (rule-derived audience). */}
        {conversation.kind === "channel" && (conversation.targets?.length ?? 0) > 0 && (
          <div>
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t("info.reach")}</h3>
            <div className="space-y-2">
              {(["staff", "parents", "students"] as const).map((audience) => {
                const rows = (conversation.targets ?? []).filter((target) => target.audience === audience)
                if (rows.length === 0) return null
                return (
                  <div key={audience} className="flex flex-wrap items-center gap-1.5">
                    <span className="text-xs font-medium">{t(`newChat.audiences.${audience}`)}</span>
                    {rows.map((target, index) => {
                      const label =
                        target.job_title != null
                          ? te(`jobTitles.${target.job_title}`)
                          : (target.section_name ?? target.grade_name ?? target.branch_name ?? t("info.reachAll"))
                      return (
                        <span
                          key={index}
                          className="inline-flex items-center rounded-full border bg-muted/40 px-2.5 py-0.5 text-[11px] text-muted-foreground"
                        >
                          {label}
                        </span>
                      )
                    })}
                  </div>
                )
              })}
            </div>
          </div>
        )}

        {/* Leave / archive */}
        {(conversation.kind === "group" || conversation.can_moderate) && (
          <div className="space-y-2 border-t pt-4">
            {conversation.kind === "group" && me && !me.left && (
              <Button
                variant="outline"
                className="h-11 w-full justify-start gap-2 rounded-xl text-destructive"
                onClick={() =>
                  confirmDelete(async () => {
                    try {
                      await apiFetch(`/chat/conversations/${conversation.id}/participants/${user?.id}`, { method: "DELETE" })
                      onOpenChange(false)
                      onListChanged?.()
                    } catch (error) {
                      toast.error(error instanceof ApiError ? error.message : t("thread.sendFailed"))
                    }
                  }, t("info.leaveConfirm"))
                }
              >
                <LogOut className="size-4" /> {t("info.leave")}
              </Button>
            )}
            {((conversation.kind === "group" && canManage) ||
              (conversation.kind === "channel" && conversation.can_moderate && !conversation.system)) && (
              <Button
                variant="outline"
                className="h-11 w-full justify-start gap-2 rounded-xl"
                onClick={async () => {
                  const ok = await toggle("archive")
                  if (ok) {
                    onConversationChange({ ...conversation, archived: !conversation.archived })
                    onOpenChange(false)
                  }
                }}
              >
                <Archive className="size-4" />
                {conversation.archived ? t("info.reopen") : t("info.archive")}
              </Button>
            )}
          </div>
        )}
          </div>
        </ResponsiveSheetBody>
      </ResponsiveSheetContent>
      {confirmDialog}
    </ResponsiveSheet>
  )
}
