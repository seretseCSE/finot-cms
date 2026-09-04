"use client"

import { Camera, Loader2, X } from "lucide-react"
import { useRef, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { School } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The school identity tile on the profile hero: the official logo when set,
 * else the letter tile. Editing is TEMARI.ET PLATFORM STAFF ONLY (the logo
 * prints on official documents — schools request changes, never self-serve);
 * pass `canManage` from a platform permission check, never a school role.
 */
export function SchoolLogoTile({
  school,
  canManage,
  onUpdated,
}: {
  school: School | null
  canManage: boolean
  onUpdated: (logoUrl: string | null) => void
}) {
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const inputRef = useRef<HTMLInputElement>(null)
  const [busy, setBusy] = useState(false)

  async function upload(file: File) {
    if (!school) return
    setBusy(true)
    try {
      const body = new FormData()
      body.append("logo", file)
      const res = await apiFetch<{ data: { logo_url: string | null } }>(
        `/schools/${school.id}/logo`,
        { method: "POST", body },
      )
      onUpdated(res.data.logo_url)
      toast.success(t("logo.updated"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(false)
    }
  }

  function remove() {
    if (!school) return
    confirmDelete(async () => {
      try {
        await apiFetch(`/schools/${school.id}/logo`, { method: "DELETE" })
        onUpdated(null)
        toast.success(t("logo.removed"))
      } catch (error) {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      }
    }, t("logo.confirmRemove"))
  }

  // Platform staff can drop a logo straight onto the tile.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: "image/*",
    disabled: !canManage || !school || busy,
    onFiles: ([file]) => void upload(file),
  })

  return (
    <div
      {...dropProps}
      className={cn("group relative shrink-0 rounded-2xl", dragOver && DROP_ACTIVE)}
    >
      {school?.logo_url ? (
        // eslint-disable-next-line @next/next/no-img-element -- signed R2 URL
        <img
          src={school.logo_url}
          alt=""
          className="bg-card size-14 rounded-2xl border object-contain"
        />
      ) : (
        <div className="brand-tile flex size-14 items-center justify-center rounded-2xl text-xl font-semibold text-white">
          {school ? school.name.slice(0, 1) : "…"}
        </div>
      )}

      {canManage && school && (
        <>
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
            className="absolute -bottom-1.5 -right-1.5 size-6 rounded-full border shadow-xs"
            title={t("logo.upload")}
            disabled={busy}
            onClick={() => inputRef.current?.click()}
          >
            {busy ? <Loader2 className="size-3 animate-spin" /> : <Camera className="size-3" />}
          </Button>
          {school.logo_url && (
            <Button
              variant="secondary"
              size="icon"
              className="absolute -right-1.5 -top-1.5 size-5 rounded-full border shadow-xs"
              title={t("logo.remove")}
              loading={busy}
              onClick={remove}
            >
              <X className="size-3" />
            </Button>
          )}
        </>
      )}
      {confirmDialog}
    </div>
  )
}
