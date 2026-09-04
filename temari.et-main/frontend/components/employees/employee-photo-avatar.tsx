"use client"

import { Camera, Loader2 } from "lucide-react"
import { useRef, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/**
 * The profile-rail avatar with an in-place photo uploader — mirrors the
 * student rail: tap the camera badge, pick an image, done. Gated by the
 * caller's canUpdate; read-only viewers get the plain avatar.
 */
export function EmployeePhotoAvatar({
  employeeId,
  name,
  photoUrl,
  canUpdate,
  onUpdated,
}: {
  employeeId: number
  name: string
  photoUrl: string | null
  canUpdate: boolean
  onUpdated: (photoUrl: string | null) => void
}) {
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")
  const inputRef = useRef<HTMLInputElement>(null)
  const [busy, setBusy] = useState(false)

  async function upload(file: File) {
    setBusy(true)
    try {
      const body = new FormData()
      body.append("photo", file)
      const res = await apiFetch<{ data: { photo_url: string | null } }>(
        `/employees/${employeeId}/photo`,
        { method: "POST", body },
      )
      onUpdated(res.data.photo_url)
      toast.success(t("photo.updated"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(false)
    }
  }

  // A photo dragged onto the avatar uploads immediately — same as the picker.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: "image/*",
    disabled: !canUpdate || busy,
    onFiles: ([file]) => void upload(file),
  })

  if (!canUpdate) {
    return <PersonAvatar name={name} photoUrl={photoUrl} className="size-24 rounded-3xl text-2xl" />
  }

  return (
    <div {...dropProps} className={cn("relative rounded-3xl", dragOver && DROP_ACTIVE)}>
      <PersonAvatar name={name} photoUrl={photoUrl} className="size-24 rounded-3xl text-2xl" />
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={(e) => {
          takeFiles(e.target.files)
          e.target.value = ""
        }}
      />
      <Button
        variant="secondary"
        size="icon"
        className="absolute -bottom-1 -right-1 size-8 rounded-full border shadow-xs"
        title={photoUrl ? t("photo.change") : t("photo.addPhoto")}
        disabled={busy}
        onClick={() => inputRef.current?.click()}
      >
        {busy ? <Loader2 className="size-3.5 animate-spin" /> : <Camera className="size-3.5" />}
      </Button>
    </div>
  )
}
