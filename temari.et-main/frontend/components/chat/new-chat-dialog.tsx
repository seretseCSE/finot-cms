"use client"

import { GraduationCap, Megaphone, Search, Users, UsersRound } from "lucide-react"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
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
import { Switch } from "@/components/ui/switch"
import { apiFetch, ApiError } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type {
  ChatChannelOptions,
  ChatConversation,
  ChatFamilyPartnerCard,
  ChatPartnerStaff,
  ChatPartnerStudent,
} from "@/lib/types"
import { cn } from "@/lib/utils"

import { useChatBase } from "./use-chat"

type Tab = "staff" | "families" | "group" | "channel"

/**
 * New conversation picker. Staff hats: colleagues, their students' families
 * (family thread = pick the STUDENT), plus group / announcement-channel
 * creation for the roles that hold it. Family hats: the per-child partner
 * cards (teachers, homeroom, office) — parent↔parent does not exist.
 */
export function NewChatDialog({
  open,
  onOpenChange,
  onCreated,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onCreated: (conversation: ChatConversation) => void
}) {
  const { t } = useTranslation("chat")
  const { user } = useAuth()
  const permissions = useEffectivePermissions()
  const base = useChatBase()
  const isStaff = base === "/chat"
  const canAnnounce = isStaff && permissions.includes("chat.announce")

  const [tab, setTab] = useState<Tab>(isStaff ? "staff" : "families")
  const [busy, setBusy] = useState(false)

  async function create(body: Record<string, unknown>) {
    if (busy) return
    setBusy(true)
    try {
      const res = await apiFetch<{ data: ChatConversation }>(`${base}/conversations`, {
        method: "POST",
        body,
      })
      onCreated(res.data)
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("thread.sendFailed"))
    } finally {
      setBusy(false)
    }
  }

  const tabs: { key: Tab; icon: typeof Users; show: boolean }[] = [
    { key: "staff", icon: Users, show: isStaff },
    { key: "families", icon: GraduationCap, show: true },
    { key: "group", icon: UsersRound, show: isStaff },
    { key: "channel", icon: Megaphone, show: canAnnounce },
  ]

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-md">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("newChat.title")}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{t("newChat.subtitle")}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody>
          <div className="space-y-4">
            <div className="flex gap-1.5 overflow-x-auto">
              {tabs
                .filter((item) => item.show)
                .map(({ key, icon: Icon }) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setTab(key)}
                    className={cn(
                      "flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors",
                      tab === key
                        ? "border-primary bg-primary text-primary-foreground"
                        : "bg-card text-muted-foreground hover:bg-accent",
                    )}
                  >
                    <Icon className="size-3.5" /> {t(`newChat.tabs.${key}`)}
                  </button>
                ))}
            </div>

            {tab === "staff" && isStaff && <StaffPicker onPick={(id) => void create({ kind: "direct", user_id: id })} busy={busy} />}
            {tab === "families" &&
              (isStaff ? (
                <StudentPicker onPick={(id) => void create({ kind: "direct", student_id: id })} busy={busy} />
              ) : (
                <FamilyPartnerPicker
                  selfUserId={user?.id ?? 0}
                  onPick={(studentId, userId) => void create({ kind: "direct", student_id: studentId, user_id: userId })}
                  busy={busy}
                />
              ))}
            {tab === "group" && isStaff && <GroupForm onCreate={(body) => void create(body)} busy={busy} />}
            {tab === "channel" && canAnnounce && <ChannelForm onCreate={(body) => void create(body)} busy={busy} />}
          </div>
        </ResponsiveSheetBody>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}

function useDebouncedPartners(q: string) {
  const [data, setData] = useState<{ staff: ChatPartnerStaff[]; students: ChatPartnerStudent[] } | null>(null)
  const [loading, setLoading] = useState(true)
  const timerRef = useRef<number | null>(null)

  useEffect(() => {
    if (timerRef.current) window.clearTimeout(timerRef.current)
    timerRef.current = window.setTimeout(() => {
      const controller = new AbortController()
      setLoading(true)
      apiFetch<{ data: { staff: ChatPartnerStaff[]; students: ChatPartnerStudent[] } }>(
        `/chat/partners${q ? `?q=${encodeURIComponent(q)}` : ""}`,
        { signal: controller.signal },
      )
        .then((res) => setData(res.data))
        .catch(() => undefined)
        .finally(() => setLoading(false))
    }, 250)
    return () => {
      if (timerRef.current) window.clearTimeout(timerRef.current)
    }
  }, [q])

  return { data, loading }
}

function SearchBox({ value, onChange, placeholder }: { value: string; onChange: (v: string) => void; placeholder: string }) {
  return (
    <div className="relative">
      <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
      <input
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        className="h-11 w-full rounded-xl border bg-muted/30 pl-9 pr-3 text-sm outline-none placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/30"
      />
    </div>
  )
}

function StaffPicker({ onPick, busy }: { onPick: (userId: number) => void; busy: boolean }) {
  const { t } = useTranslation("chat")
  const [q, setQ] = useState("")
  const { data, loading } = useDebouncedPartners(q)

  return (
    <div className="space-y-2">
      <SearchBox value={q} onChange={setQ} placeholder={t("newChat.searchStaff")} />
      {loading && !data ? (
        <PickerSkeleton />
      ) : (
        <ul className="max-h-80 space-y-0.5 overflow-y-auto">
          {(data?.staff ?? []).map((person) => (
            <li key={person.user_id}>
              <button
                type="button"
                disabled={busy}
                onClick={() => onPick(person.user_id)}
                className="pressable flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left hover:bg-accent/60"
              >
                <PersonAvatar name={person.name} photoUrl={person.avatar_url} className="size-9" />
                <span className="truncate text-sm">{person.name}</span>
              </button>
            </li>
          ))}
          {data !== null && data.staff.length === 0 && (
            <p className="px-2 py-6 text-center text-xs text-muted-foreground">{t("list.noResults")}</p>
          )}
        </ul>
      )}
    </div>
  )
}

function StudentPicker({ onPick, busy }: { onPick: (studentId: number) => void; busy: boolean }) {
  const { t } = useTranslation("chat")
  const [q, setQ] = useState("")
  const { data, loading } = useDebouncedPartners(q)

  return (
    <div className="space-y-2">
      <p className="text-xs text-muted-foreground">{t("newChat.familyHint")}</p>
      <SearchBox value={q} onChange={setQ} placeholder={t("newChat.searchStudents")} />
      {loading && !data ? (
        <PickerSkeleton />
      ) : (
        <ul className="max-h-80 space-y-0.5 overflow-y-auto">
          {(data?.students ?? []).map((student) => (
            <li key={student.student_id}>
              <button
                type="button"
                disabled={busy}
                onClick={() => onPick(student.student_id)}
                className="pressable flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left hover:bg-accent/60"
              >
                <PersonAvatar name={student.name} className="size-9" />
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm">{student.name}</span>
                  <span className="text-[11px] text-muted-foreground">
                    {t("newChat.guardianCount", { count: student.guardians })}
                  </span>
                </span>
              </button>
            </li>
          ))}
          {data !== null && data.students.length === 0 && (
            <p className="px-2 py-6 text-center text-xs text-muted-foreground">{t("list.noResults")}</p>
          )}
        </ul>
      )}
    </div>
  )
}

function FamilyPartnerPicker({
  selfUserId,
  onPick,
  busy,
}: {
  selfUserId: number
  onPick: (studentId: number, userId: number) => void
  busy: boolean
}) {
  const { t } = useTranslation("chat")
  const [cards, setCards] = useState<ChatFamilyPartnerCard[] | null>(null)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: ChatFamilyPartnerCard[] }>("/me/chat/partners")
      .then((res) => {
        if (!cancelled) setCards(res.data)
      })
      .catch(() => undefined)
    return () => {
      cancelled = true
    }
  }, [])

  if (cards === null) return <PickerSkeleton />
  if (cards.length === 0) {
    return <p className="px-2 py-8 text-center text-xs text-muted-foreground">{t("newChat.noFamilyPartners")}</p>
  }

  return (
    <div className="max-h-96 space-y-4 overflow-y-auto">
      {cards.map((card) => (
        <div key={`${card.student_id}-${card.is_self}`}>
          <p className="mb-1 px-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {card.is_self ? t("newChat.myTeachers") : (card.student_name ?? "")}
            {card.branch_name ? ` · ${card.branch_name}` : ""}
          </p>
          <ul className="space-y-0.5">
            {card.partners
              .filter((partner) => partner.user_id !== selfUserId)
              .map((partner) => (
                <li key={partner.user_id}>
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => onPick(card.student_id, partner.user_id)}
                    className="pressable flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left hover:bg-accent/60"
                  >
                    <PersonAvatar name={partner.name} photoUrl={partner.avatar_url} className="size-9" />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm">{partner.name}</span>
                      <span className="text-[11px] text-muted-foreground">
                        {partner.role === "office" ? t("newChat.roleOffice") : partner.subject ?? t("newChat.roleTeacher")}
                        {partner.role === "homeroom" && ` · ${t("newChat.roleHomeroom")}`}
                      </span>
                    </span>
                  </button>
                </li>
              ))}
          </ul>
        </div>
      ))}
    </div>
  )
}

function GroupForm({ onCreate, busy }: { onCreate: (body: Record<string, unknown>) => void; busy: boolean }) {
  const { t } = useTranslation("chat")
  const [title, setTitle] = useState("")
  const [selected, setSelected] = useState<ChatPartnerStaff[]>([])
  const [q, setQ] = useState("")
  const { data } = useDebouncedPartners(q)

  const candidates = useMemo(
    () => (data?.staff ?? []).filter((person) => !selected.some((s) => s.user_id === person.user_id)),
    [data, selected],
  )

  return (
    <div className="space-y-3">
      <div className="space-y-1.5">
        <Label htmlFor="group-title">{t("newChat.groupName")}</Label>
        <Input id="group-title" value={title} onChange={(event) => setTitle(event.target.value)} placeholder={t("newChat.groupNamePlaceholder")} className="h-11 rounded-xl" />
      </div>

      {selected.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {selected.map((person) => (
            <button
              key={person.user_id}
              type="button"
              className="flex items-center gap-1.5 rounded-full border bg-accent px-2.5 py-1 text-xs"
              onClick={() => setSelected((current) => current.filter((s) => s.user_id !== person.user_id))}
            >
              {person.name} ×
            </button>
          ))}
        </div>
      )}

      <SearchBox value={q} onChange={setQ} placeholder={t("newChat.searchStaff")} />
      <ul className="max-h-48 space-y-0.5 overflow-y-auto">
        {candidates.map((person) => (
          <li key={person.user_id}>
            <button
              type="button"
              onClick={() => setSelected((current) => [...current, person])}
              className="pressable flex w-full items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-left hover:bg-accent/60"
            >
              <PersonAvatar name={person.name} photoUrl={person.avatar_url} className="size-7 text-[10px]" />
              <span className="truncate text-sm">{person.name}</span>
            </button>
          </li>
        ))}
      </ul>

      <Button
        className="h-11 w-full rounded-xl"
        loading={busy} disabled={!title.trim() || selected.length === 0}
        onClick={() => onCreate({ kind: "group", title: title.trim(), user_ids: selected.map((s) => s.user_id) })}
      >
        {t("newChat.createGroup")}
      </Button>
    </div>
  )
}

/** A tap-to-toggle chip (the shared look for roles / grades / sections). */
function Chip({
  label,
  on,
  onClick,
  disabled,
}: {
  label: string
  on: boolean
  onClick: () => void
  disabled?: boolean
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      aria-pressed={on}
      className={cn(
        "pressable inline-flex min-h-9 items-center rounded-full border px-3 text-xs font-medium transition-colors",
        on ? "border-primary bg-primary/10 text-primary" : "bg-card text-muted-foreground hover:bg-accent",
        disabled && "pointer-events-none opacity-50",
      )}
    >
      {label}
    </button>
  )
}

type Audience = "staff" | "parents" | "students"

/**
 * Channel / mass-message composer. An audience is turned on, then NARROWED:
 * staff by role (defaults to every role that has a login), families by grade
 * and by classroom. The heavy lifting stays on the backend — every selection
 * just becomes conversation_targets rows the audience resolver already
 * understands. Options load once from /chat/channel-options.
 */
function ChannelForm({ onCreate, busy }: { onCreate: (body: Record<string, unknown>) => void; busy: boolean }) {
  const { t } = useTranslation("chat")
  const { t: te } = useTranslation("employees")

  const [options, setOptions] = useState<ChatChannelOptions | null>(null)
  const [title, setTitle] = useState("")
  const [adminsOnly, setAdminsOnly] = useState(true)
  // Nothing is pre-selected — the creator explicitly chooses who the channel
  // reaches (staff and/or families).
  const [audiences, setAudiences] = useState<Record<Audience, boolean>>({ staff: false, parents: false, students: false })

  const [branchId, setBranchId] = useState<number | null>(null)
  const [roleSel, setRoleSel] = useState<string[]>([])
  const [gradeSel, setGradeSel] = useState<number[]>([])
  const [sectionSel, setSectionSel] = useState<number[]>([])

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: ChatChannelOptions }>("/chat/channel-options")
      .then((res) => {
        if (cancelled) return
        setOptions(res.data)
        setRoleSel(res.data.roles) // default: every role with a login
      })
      .catch(() => setOptions({ roles: [], grades: [], sections: [], branches: [], needs_branch: false }))
    return () => {
      cancelled = true
    }
  }, [])

  const familyOn = audiences.parents || audiences.students

  // In the school-wide workspace, grade/section options narrow to the chosen
  // branch (families always land in one branch).
  const grades = useMemo(() => {
    if (!options) return []
    if (branchId === null) return options.grades
    const gradeIds = new Set(options.sections.filter((s) => s.branch_id === branchId).map((s) => s.grade_level_id))
    return options.grades.filter((g) => gradeIds.has(g.id))
  }, [options, branchId])

  const sections = useMemo(() => {
    if (!options) return []
    return options.sections.filter((s) => branchId === null || s.branch_id === branchId)
  }, [options, branchId])

  // Sections grouped under their grade, in ladder order — the classroom picker.
  const sectionGroups = useMemo(
    () =>
      grades
        .map((g) => ({ grade: g, list: sections.filter((s) => s.grade_level_id === g.id) }))
        .filter((group) => group.list.length > 0),
    [grades, sections],
  )

  function toggleIn<T>(list: T[], value: T): T[] {
    return list.includes(value) ? list.filter((v) => v !== value) : [...list, value]
  }

  function setAudience(audience: Audience, checked: boolean) {
    setAudiences((current) => ({ ...current, [audience]: checked }))
  }

  /** conversation_targets rows from the current selection (minimal + non-redundant). */
  function buildTargets(): Record<string, unknown>[] {
    if (!options) return []
    const branch = branchId ?? undefined
    const targets: Record<string, unknown>[] = []

    if (audiences.staff) {
      const allRoles = roleSel.length === 0 || roleSel.length === options.roles.length
      if (allRoles) {
        targets.push({ audience: "staff", branch_id: branch })
      } else {
        for (const jobTitle of roleSel) targets.push({ audience: "staff", branch_id: branch, job_title: jobTitle })
      }
    }

    // Parents + students share the grade/classroom scope.
    const dims: Record<string, unknown>[] =
      gradeSel.length === 0 && sectionSel.length === 0
        ? [{}]
        : [
            ...gradeSel.map((id) => ({ grade_level_id: id })),
            // A section whose whole grade is already picked is redundant.
            ...sectionSel
              .filter((id) => {
                const sec = sections.find((s) => s.id === id)
                return sec && !gradeSel.includes(sec.grade_level_id)
              })
              .map((id) => ({ section_id: id })),
          ]

    for (const audience of ["parents", "students"] as const) {
      if (!audiences[audience]) continue
      for (const dim of dims) targets.push({ audience, branch_id: branch, ...dim })
    }

    return targets
  }

  const needsBranch = options?.needs_branch && options.branches.length > 0
  const canSubmit =
    !!title.trim() &&
    Object.values(audiences).some(Boolean) &&
    (!needsBranch || branchId !== null) &&
    !(audiences.staff && roleSel.length === 0 && options !== null && options.roles.length > 0)

  if (options === null) {
    return (
      <div className="space-y-2 pt-1">
        <Skeleton className="h-11 w-full rounded-xl" />
        <Skeleton className="h-24 w-full rounded-xl" />
      </div>
    )
  }

  return (
    <div className="space-y-3">
      <div className="space-y-1.5">
        <Label htmlFor="channel-title">{t("newChat.channelName")}</Label>
        <Input
          id="channel-title"
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          placeholder={t("newChat.channelNamePlaceholder")}
          className="h-11 rounded-xl"
        />
      </div>

      {needsBranch && (
        <div className="space-y-1.5">
          <Label>{t("newChat.channelBranch")}</Label>
          <Select
            value={branchId != null ? String(branchId) : "0"}
            onValueChange={(v) => {
              setBranchId(v && v !== "0" ? Number(v) : null)
              setGradeSel([])
              setSectionSel([])
            }}
          >
            <SelectTrigger className="h-11 w-full rounded-xl">
              <SelectValue placeholder={t("newChat.channelBranchAll")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="0">{t("newChat.channelBranchAll")}</SelectItem>
              {options.branches.map((b) => (
                <SelectItem key={b.id} value={String(b.id)}>
                  {b.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}

      <div className="space-y-2 rounded-xl border p-3">
        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t("newChat.audience")}</p>

        {/* Staff — narrowed by role. */}
        <label className="flex items-center justify-between py-0.5 text-sm">
          {t("newChat.audiences.staff")}
          <Switch checked={audiences.staff} onCheckedChange={(c) => setAudience("staff", c)} aria-label={t("newChat.audiences.staff")} />
        </label>
        {audiences.staff && options.roles.length > 0 && (
          <div className="space-y-2 rounded-lg bg-muted/40 p-2.5">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-medium text-muted-foreground">{t("newChat.rolesLabel")}</span>
              <button
                type="button"
                className="text-[11px] font-medium text-primary"
                onClick={() => setRoleSel(roleSel.length === options.roles.length ? [] : options.roles)}
              >
                {roleSel.length === options.roles.length ? t("newChat.clearAll") : t("newChat.selectAll")}
              </button>
            </div>
            <div className="flex flex-wrap gap-1.5">
              {options.roles.map((role) => (
                <Chip
                  key={role}
                  label={te(`jobTitles.${role}`)}
                  on={roleSel.includes(role)}
                  onClick={() => setRoleSel((current) => toggleIn(current, role))}
                />
              ))}
            </div>
          </div>
        )}

        {/* Families. */}
        {(["parents", "students"] as const).map((audience) => (
          <label key={audience} className="flex items-center justify-between py-0.5 text-sm">
            {t(`newChat.audiences.${audience}`)}
            <Switch checked={audiences[audience]} onCheckedChange={(c) => setAudience(audience, c)} aria-label={t(`newChat.audiences.${audience}`)} />
          </label>
        ))}
        {familyOn && (
          <div className="space-y-3 rounded-lg bg-muted/40 p-2.5">
            <p className="text-[11px] text-muted-foreground">{t("newChat.familyScopeHint")}</p>

            {grades.length > 0 && (
              <div className="space-y-1.5">
                <span className="text-[11px] font-medium text-muted-foreground">{t("newChat.gradesLabel")}</span>
                <div className="flex flex-wrap gap-1.5">
                  {grades.map((g) => (
                    <Chip key={g.id} label={g.name} on={gradeSel.includes(g.id)} onClick={() => setGradeSel((current) => toggleIn(current, g.id))} />
                  ))}
                </div>
              </div>
            )}

            {sectionGroups.length > 0 && (
              <div className="space-y-1.5">
                <span className="text-[11px] font-medium text-muted-foreground">{t("newChat.sectionsLabel")}</span>
                {sectionGroups.map(({ grade, list }) => (
                  <div key={grade.id} className="flex flex-wrap items-center gap-1.5">
                    <span className={cn("text-[11px]", gradeSel.includes(grade.id) ? "text-primary/60" : "text-muted-foreground")}>{grade.name}</span>
                    {list.map((s) => (
                      <Chip
                        key={s.id}
                        label={s.name}
                        on={sectionSel.includes(s.id) || gradeSel.includes(grade.id)}
                        disabled={gradeSel.includes(grade.id)}
                        onClick={() => setSectionSel((current) => toggleIn(current, s.id))}
                      />
                    ))}
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </div>

      <label className="flex items-center justify-between rounded-xl border p-3 text-sm">
        <span>
          {t("newChat.adminsOnly")}
          <span className="block text-[11px] text-muted-foreground">{t("newChat.adminsOnlyHint")}</span>
        </span>
        <Switch checked={adminsOnly} onCheckedChange={setAdminsOnly} aria-label={t("newChat.adminsOnly")} />
      </label>

      <Button
        className="h-11 w-full rounded-xl"
        loading={busy}
        disabled={!canSubmit}
        onClick={() =>
          onCreate({
            kind: "channel",
            title: title.trim(),
            posting: adminsOnly ? "admins" : "all",
            targets: buildTargets(),
          })
        }
      >
        {t("newChat.createChannel")}
      </Button>
    </div>
  )
}

function PickerSkeleton() {
  return (
    <div className="space-y-1.5 pt-1">
      {[...Array(4)].map((_, i) => (
        <div key={i} className="flex items-center gap-2.5 px-2 py-1.5">
          <Skeleton className="size-9 rounded-full" />
          <Skeleton className="h-3.5 w-40" />
        </div>
      ))}
    </div>
  )
}
