"use client"

import { DoorOpen, Plus } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Room, RoomType } from "@/lib/types"

const ROOM_TYPES: RoomType[] = [
  "classroom",
  "lab",
  "library",
  "ict",
  "gym",
  "music",
  "art",
  "hall",
  "other",
]

/** Shared special rooms (labs, gym…) the solver books exclusively.
 *  `branchId` names the working branch when the page runs from the
 *  school-wide workspace (list narrowing + create target); a concrete
 *  branch workspace passes null and the context scopes everything. */
export function RoomsTab({ canManage, branchId = null }: { canManage: boolean; branchId?: number | null }) {
  const { t } = useTranslation("timetable")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [rooms, setRooms] = useState<Room[] | null>(null)
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<Room | null>(null)
  const [name, setName] = useState("")
  const [type, setType] = useState<RoomType>("lab")
  const [capacity, setCapacity] = useState("")
  const [working, setWorking] = useState(false)

  function load() {
    apiFetch<{ data: Room[] }>(`/rooms${branchId != null ? `?branch_id=${branchId}` : ""}`)
      .then((res) => setRooms(res.data))
      .catch(() => setRooms([]))
  }

  useEffect(load, [branchId])

  function openEditor(room: Room | null) {
    setEditing(room)
    setName(room?.name ?? "")
    setType(room?.type ?? "lab")
    setCapacity(room?.capacity ? String(room.capacity) : "")
    setOpen(true)
  }

  async function save() {
    setWorking(true)
    try {
      const body = {
        name: name.trim(),
        type,
        capacity: capacity ? Number(capacity) : null,
        ...(branchId != null ? { branch_id: branchId } : {}),
      }
      if (editing) {
        await apiFetch(`/rooms/${editing.id}`, { method: "PUT", body })
        toast.success(t("rooms.updated"))
      } else {
        await apiFetch("/rooms", { method: "POST", body })
        toast.success(t("rooms.added"))
      }
      setOpen(false)
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  async function remove(room: Room) {
    try {
      await apiFetch(`/rooms/${room.id}`, { method: "DELETE" })
      toast.success(t("rooms.deleted"))
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  if (rooms === null) return <Skeleton className="h-48 rounded-2xl" />

  return (
    <div className="space-y-4">
      {confirmDialog}
      <div className="flex items-start justify-between gap-4">
        <p className="text-xs text-muted-foreground">{t("rooms.hint")}</p>
        {canManage && (
          <Button size="sm" onClick={() => openEditor(null)}>
            <Plus className="size-4" />
            {t("rooms.add")}
          </Button>
        )}
      </div>

      {rooms.length === 0 ? (
        <div className="rounded-2xl border bg-card shadow-xs">
          <EmptyState
            icon={DoorOpen}
            title={t("rooms.empty")}
            description={t("rooms.emptyDesc")}
            compact
          />
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {rooms.map((room) => (
            <div
              key={room.id}
              className="flex flex-col gap-3 rounded-2xl border bg-card p-3.5 shadow-xs"
            >
              <div className="flex items-center gap-3">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent">
                  <DoorOpen className="size-4.5" strokeWidth={1.75} />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{room.name}</p>
                  <p className="truncate text-xs text-muted-foreground">
                    {t(`rooms.types.${room.type}`)}
                    {room.capacity ? ` · ${room.capacity}` : ""}
                  </p>
                </div>
              </div>
              {canManage && (
                <div className="flex gap-1 border-t pt-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="flex-1"
                    onClick={() => openEditor(room)}
                  >
                    {tc("actions.edit")}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="flex-1 text-destructive"
                    onClick={() =>
                      confirmDelete(() => remove(room), tc("confirmDelete.named", { name: room.name }))
                    }
                  >
                    {tc("actions.delete")}
                  </Button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>{editing ? tc("actions.edit") : t("rooms.add")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>{t("rooms.name")}</Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} autoFocus />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label>{t("rooms.type")}</Label>
                <Select value={type} onValueChange={(v) => setType(v as RoomType)}>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {ROOM_TYPES.map((roomType) => (
                      <SelectItem key={roomType} value={roomType}>
                        {t(`rooms.types.${roomType}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t("rooms.capacity")}</Label>
                <Input
                  type="number"
                  min={1}
                  value={capacity}
                  onChange={(e) => setCapacity(e.target.value)}
                />
              </div>
            </div>
            <Button className="w-full" onClick={save} loading={working} disabled={!name.trim()}>
              {tc("actions.save")}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
