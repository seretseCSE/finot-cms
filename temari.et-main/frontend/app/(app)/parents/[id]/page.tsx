"use client"

import {
  BriefcaseBusiness,
  Building2,
  Camera,
  FileText,
  GraduationCap,
  Loader2,
  MapPin,
  MessageCircleMore,
  Paperclip,
  Star,
  UserRound,
  Users,
} from "lucide-react"
import Link from "next/link"
import { useParams } from "next/navigation"
import { useCallback, useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import {
  DocumentCategoryBadge,
  DocumentCategorySelect,
} from "@/components/students/document-category"
import { PortalAccountSection } from "@/components/students/portal-account-section"
import { AttachmentTile } from "@/components/ui/attachment"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { useMediaPreview } from "@/components/ui/media-preview"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import { useChatLauncher, type ChatLaunchTarget } from "@/components/chat/chat-launcher"
import type { ParentRow, StudentAttachment } from "@/lib/types"
import { cn } from "@/lib/utils"

const TABS = [
  ["overview", UserRound],
  ["children", Users],
  ["documents", FileText],
] as const

type TabKey = (typeof TABS)[number][0]

const ACCEPTED_FILES = ".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
const MAX_FILE_BYTES = 10 * 1024 * 1024

function Fact({
  icon: Icon,
  label,
  value,
}: {
  icon: React.ComponentType<{ className?: string }>
  label: string
  value?: React.ReactNode
}) {
  if (!value) return null
  return (
    <div className="flex items-start gap-3 py-2">
      <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-muted-foreground">
        <Icon className="size-4" />
      </span>
      <span className="min-w-0">
        <span className="block text-xs text-muted-foreground">{label}</span>
        <span className="block truncate text-sm font-medium">{value}</span>
      </span>
    </div>
  )
}

function addressOf(parent: ParentRow): string {
  return [parent.house_no, parent.woreda, parent.sub_city, parent.city, parent.state]
    .filter(Boolean)
    .join(", ")
}

export default function ParentDetailPage() {
  const params = useParams<{ id: string }>()
  const parentId = Number(params.id)
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const { t: tChat } = useTranslation("chat")
  const { openChat, available: chatAvailable } = useChatLauncher()
  const permissions = useEffectivePermissions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { openPreview, previewDialog } = useMediaPreview()

  const canManage = permissions.includes("guardians.manage")

  const [parent, setParent] = useState<ParentRow | null>(null)
  const [tab, setTab] = useState<TabKey>("overview")
  const [uploadingPhoto, setUploadingPhoto] = useState(false)
  const [pendingFile, setPendingFile] = useState<File | null>(null)
  const [pendingName, setPendingName] = useState("")
  const [pendingCategory, setPendingCategory] = useState("")
  const [uploading, setUploading] = useState(false)
  const photoInput = useRef<HTMLInputElement>(null)
  const fileInput = useRef<HTMLInputElement>(null)

  // Documents tab: picked or dropped, the file lands in the same pending card.
  const documentDrop = useFileDrop({
    accept: ACCEPTED_FILES,
    maxSize: MAX_FILE_BYTES,
    disabled: !canManage,
    onFiles: ([file]) => {
      setPendingFile(file)
      setPendingName(file.name.replace(/\.[^.]+$/, ""))
    },
  })

  // The avatar accepts a dragged photo too — it uploads straight away.
  const photoDrop = useFileDrop({
    accept: "image/jpeg,image/png,image/webp",
    disabled: !canManage || uploadingPhoto,
    onFiles: ([file]) => void uploadPhoto(file),
  })

  const load = useCallback(() => {
    apiFetch<{ data: ParentRow }>(`/parents/${parentId}`)
      .then((res) => setParent(res.data))
      .catch((error) =>
        toast.error(error instanceof ApiError ? error.message : t("parents.loadFailed")),
      )
  }, [parentId, t])

  useEffect(() => {
    load()
  }, [load])

  async function uploadPhoto(file: File) {
    if (!parent) return
    setUploadingPhoto(true)
    try {
      const body = new FormData()
      body.append("photo", file)
      const res = await apiFetch<{ data: { photo_url: string | null } }>(
        `/parents/${parent.id}/photo`,
        { method: "POST", body },
      )
      setParent((prev) => (prev ? { ...prev, photo_url: res.data.photo_url } : prev))
      toast.success(t("guardians.photoUpdated"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setUploadingPhoto(false)
    }
  }

  async function uploadAttachment() {
    if (!parent || !pendingFile) return
    setUploading(true)
    try {
      const body = new FormData()
      body.append("name", pendingName || pendingFile.name)
      if (pendingCategory) body.append("category", pendingCategory)
      body.append("file", pendingFile)
      const res = await apiFetch<{ data: StudentAttachment }>(
        `/parents/${parent.id}/attachments`,
        { method: "POST", body },
      )
      setParent((prev) =>
        prev ? { ...prev, attachments: [...(prev.attachments ?? []), res.data] } : prev,
      )
      setPendingFile(null)
      setPendingName("")
      setPendingCategory("")
      toast.success(t("documents.uploaded"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setUploading(false)
    }
  }

  async function removeAttachment(attachment: StudentAttachment) {
    if (!parent) return
    try {
      await apiFetch(`/parents/${parent.id}/attachments/${attachment.id}`, {
        method: "DELETE",
      })
      setParent((prev) =>
        prev
          ? {
              ...prev,
              attachments: (prev.attachments ?? []).filter((a) => a.id !== attachment.id),
            }
          : prev,
      )
      toast.success(t("documents.removed"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  // Messaging a parent = a child's family thread (ADR-019); the children are
  // already on the detail payload, so the launcher never refetches here.
  const chatTarget: ChatLaunchTarget | undefined =
    parent && (parent.children ?? []).length > 0
      ? {
          kind: "parent",
          parentId: parent.id,
          name: parent.name,
          children: (parent.children ?? []).map((child) => ({
            student_id: child.student_id,
            full_name: child.full_name,
            grade_level: child.grade_level,
            branch: child.branch,
          })),
        }
      : undefined

  return (
    <div className="space-y-6">
      {confirmDialog}
      {previewDialog}
      <PageHeader
        title={parent ? (parent.name ?? "—") : <Skeleton className="h-8 w-48" />}
        description={t("parents.detailSubtitle")}
        backHref="/parents"
        backLabel={t("parents.title")}
        actions={
          chatAvailable && chatTarget ? (
            <Button className="h-10 rounded-full" onClick={() => void openChat(chatTarget)}>
              <MessageCircleMore className="size-4" />
              {tChat("launcher.chatFamily")}
            </Button>
          ) : undefined
        }
      />

      <div className="page-gutter">
        {parent === null ? (
          <div className="grid gap-4 lg:grid-cols-[300px_1fr]">
            <Skeleton className="h-80 w-full rounded-2xl" />
            <div className="space-y-3">
              <Skeleton className="h-10 w-2/3 rounded-full" />
              <Skeleton className="h-64 w-full rounded-2xl" />
            </div>
          </div>
        ) : (
          <div className="grid gap-4 lg:grid-cols-[300px_1fr] lg:items-start">
            {/* Identity rail */}
            <aside className="space-y-4 lg:sticky lg:top-20">
              <div className="rounded-2xl border bg-card p-5 shadow-xs">
                <div className="flex flex-col items-center gap-3 text-center">
                  <div
                    {...photoDrop.dropProps}
                    className={cn("relative rounded-3xl", photoDrop.dragOver && DROP_ACTIVE)}
                  >
                    <PersonAvatar
                      name={parent.name ?? "?"}
                      photoUrl={parent.photo_url}
                      className="size-24 rounded-3xl text-2xl"
                    />
                    {canManage ? (
                      <Button
                        variant="secondary"
                        size="icon"
                        className="absolute -right-1 -bottom-1 size-8 rounded-full border shadow-xs"
                        title={parent.photo_url ? t("photo.change") : t("photo.add")}
                        disabled={uploadingPhoto}
                        onClick={() => photoInput.current?.click()}
                      >
                        {uploadingPhoto ? (
                          <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                          <Camera className="size-3.5" />
                        )}
                      </Button>
                    ) : null}
                  </div>
                  <div className="min-w-0 space-y-1">
                    <p className="truncate font-display text-lg font-semibold">{parent.name}</p>
                    {parent.public_id ? (
                      <p>
                        <CopyableId value={parent.public_id} className="text-xs" />
                      </p>
                    ) : null}
                  </div>
                  <div className="flex flex-wrap items-center justify-center gap-1.5">
                    {/* is_verified is a dormant KYC flag nothing sets yet —
                        surface it only when it IS set; the login chip below
                        answers the everyday "can they sign in?" question. */}
                    {parent.is_verified ? (
                      <Badge variant="secondary" className="bg-success/10 text-success">
                        {t("parents.verified")}
                      </Badge>
                    ) : null}
                    <Badge variant="secondary" className="bg-primary/10 text-primary">
                      {t("parents.childrenCount")}: {parent.children?.length ?? parent.children_count ?? 0}
                    </Badge>
                  </div>
                </div>

                <input
                  ref={photoInput}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  className="hidden"
                  onChange={(e) => {
                    photoDrop.takeFiles(e.target.files)
                    e.target.value = ""
                  }}
                />

                {/* Portal account — parents are always provisioned; the chip
                    says whether the setup link was ever used. */}
                <div className="mt-4 border-t pt-3">
                  <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {t("detail.portalAccount")}
                  </p>
                  <div className="mt-2">
                    <PortalAccountSection
                      kind="parent"
                      personId={parent.id}
                      personName={parent.name ?? "?"}
                      account={parent.account}
                      canManage={canManage}
                    />
                  </div>
                </div>

                {(parent.phone || parent.email) && (
                  <div className="mt-4 flex flex-col gap-2 space-y-1 border-t pt-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                      {t("detail.contact")}
                    </p>
                    {parent.phone ? (
                      <ContactActionCell
                        value={parent.phone}
                        kind="phone"
                        name={parent.name ?? ""}
                        chat={chatTarget}
                        triggerClassName="px-0"
                      />
                    ) : null}
                    {parent.secondary_phone ? (
                      <ContactActionCell
                        value={parent.secondary_phone}
                        kind="phone"
                        name={parent.name ?? ""}
                        chat={chatTarget}
                        triggerClassName="px-0"
                      />
                    ) : null}
                    {parent.email ? (
                      <ContactActionCell
                        value={parent.email}
                        kind="email"
                        name={parent.name ?? ""}
                        chat={chatTarget}
                        triggerClassName="px-0"
                      />
                    ) : null}
                  </div>
                )}

                <div className="mt-4 border-t pt-2">
                  <Fact
                    icon={UserRound}
                    label={t("fields.gender")}
                    value={parent.gender ? t(`fields.${parent.gender}`) : undefined}
                  />
                  <Fact
                    icon={BriefcaseBusiness}
                    label={t("wizard.occupation")}
                    value={parent.occupation}
                  />
                  <Fact icon={Building2} label={t("parents.employer")} value={parent.employer} />
                </div>
              </div>
            </aside>

            {/* Content */}
            <section className="min-w-0 space-y-4">
              <div className="flex gap-1.5 overflow-x-auto pb-1">
                {TABS.map(([key, Icon]) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setTab(key)}
                    className={cn(
                      "flex h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-sm font-medium transition-colors",
                      tab === key
                        ? "bg-primary text-primary-foreground"
                        : "bg-muted text-muted-foreground hover:bg-muted/70",
                    )}
                  >
                    <Icon className="size-4" />
                    {t(`parents.tabs.${key}`)}
                  </button>
                ))}
              </div>

              {tab === "overview" ? (
                <div className="rounded-2xl border bg-card p-5 shadow-xs">
                  <h3 className="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    <MapPin className="size-3.5" />
                    {t("wizard.currentAddress")}
                  </h3>
                  <p className="text-sm">
                    {addressOf(parent) || (
                      <span className="text-muted-foreground">—</span>
                    )}
                  </p>
                </div>
              ) : null}

              {tab === "children" ? (
                <div className="space-y-2">
                  {(parent.children ?? []).length === 0 ? (
                    <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
                      {t("parents.noChildren")}
                    </div>
                  ) : (
                    (parent.children ?? []).map((child) => (
                      <Link
                        key={child.student_id}
                        href={`/students/${child.student_id}`}
                        className="flex items-center gap-3 rounded-2xl border bg-card px-4 py-3 text-sm shadow-xs transition-colors hover:bg-muted/50"
                      >
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-accent text-muted-foreground">
                          <GraduationCap className="size-4" />
                        </span>
                        <span className="min-w-0 flex-1">
                          <span className="block truncate font-medium">{child.full_name}</span>
                          <span className="block truncate text-xs text-muted-foreground">
                            {t(`guardians.relationships.${child.relationship}`)}
                            {child.grade_level ? ` · ${child.grade_level}` : ""}
                            {child.school ? ` · ${child.school}` : ""}
                          </span>
                        </span>
                        {child.is_primary ? (
                          <Star
                            className="size-4 shrink-0 fill-warning text-warning"
                            aria-label={t("guardians.primary")}
                          />
                        ) : null}
                      </Link>
                    ))
                  )}
                </div>
              ) : null}

              {tab === "documents" ? (
                <div
                  {...documentDrop.dropProps}
                  className={cn("space-y-2 rounded-2xl", documentDrop.dragOver && DROP_ACTIVE)}
                >
                  {(parent.attachments ?? []).length === 0 && !canManage ? (
                    <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
                      {t("documents.empty")}
                    </div>
                  ) : null}
                  {(parent.attachments ?? []).map((attachment, index) => (
                    <AttachmentTile
                      key={attachment.id}
                      file={attachment}
                      description={<DocumentCategoryBadge category={attachment.category} />}
                      onPreview={() => openPreview(parent.attachments ?? [], index)}
                      onDelete={
                        canManage
                          ? () =>
                              confirmDelete(
                                () => removeAttachment(attachment),
                                tc("confirmDelete.named", { name: attachment.name }),
                              )
                          : undefined
                      }
                    />
                  ))}

                  {canManage ? (
                    pendingFile ? (
                      <div className="space-y-2 rounded-2xl border border-dashed p-3">
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                          <Input
                            value={pendingName}
                            onChange={(e) => setPendingName(e.target.value)}
                            placeholder={t("wizard.documentNamePlaceholder")}
                            className="h-9"
                          />
                          <DocumentCategorySelect
                            value={pendingCategory}
                            onChange={setPendingCategory}
                          />
                        </div>
                        <div className="flex justify-end gap-2">
                          <Button
                            type="button"
                            variant="outline"
                            className="h-9 rounded-full"
                            onClick={() => {
                              setPendingFile(null)
                              setPendingCategory("")
                            }}
                          >
                            {tc("actions.cancel")}
                          </Button>
                          <Button
                            type="button"
                            className="h-9 rounded-full"
                            onClick={uploadAttachment}
                            loading={uploading}
                          >
                            {t("documents.upload")}
                          </Button>
                        </div>
                      </div>
                    ) : (
                      <div className="flex flex-wrap items-center gap-2">
                        <Button
                          type="button"
                          variant="outline"
                          className="h-9 rounded-full"
                          onClick={() => fileInput.current?.click()}
                        >
                          <Paperclip className="size-4" />
                          {t("wizard.attachDocument")}
                        </Button>
                        <DropHint />
                      </div>
                    )
                  ) : null}
                  <input
                    ref={fileInput}
                    type="file"
                    accept={ACCEPTED_FILES}
                    className="hidden"
                    onChange={(e) => {
                      documentDrop.takeFiles(e.target.files)
                      e.target.value = ""
                    }}
                  />
                </div>
              ) : null}
            </section>
          </div>
        )}
      </div>
    </div>
  )
}
