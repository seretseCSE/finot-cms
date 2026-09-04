"use client"

import { CirclePlay, Download, ExternalLink, FileText, Link2, Pin, StickyNote } from "lucide-react"
import { useState } from "react"

import { QuestionStem } from "@/components/lms/question-content"
import { formatDateTime, formatFileSize } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useTranslation } from "@/lib/i18n"
import type { CourseMaterial, MeMaterial } from "@/lib/types"

const TYPE_ICONS = { file: FileText, link: Link2, youtube: CirclePlay, text: StickyNote } as const

/**
 * One material rendered per type: file = signed download, link = open,
 * youtube = tap-to-load embed (never auto-loads the iframe on 3G),
 * text = inline note.
 */
export function MaterialCard({
  material,
  actions,
}: {
  material: Pick<CourseMaterial, "title" | "description" | "type" | "content" | "is_pinned"> &
    Partial<Pick<CourseMaterial, "subject_name" | "created_at" | "created_by_name">> &
    Partial<Pick<MeMaterial, "posted_at">>
  actions?: React.ReactNode
}) {
  const { t } = useTranslation("lms")
  const [showVideo, setShowVideo] = useState(false)
  const Icon = TYPE_ICONS[material.type]
  const postedAt = material.posted_at ?? material.created_at

  return (
    <div className="rounded-2xl border bg-card p-4 shadow-xs">
      <div className="flex items-start gap-3">
        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent">
          <Icon className="size-4 text-muted-foreground" strokeWidth={1.75} />
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <p className="min-w-0 truncate text-sm font-medium">{material.title}</p>
            {material.is_pinned && <Pin className="size-3.5 shrink-0 text-primary" />}
          </div>
          <p className="text-xs text-muted-foreground">
            {[material.subject_name, postedAt ? formatDateTime(postedAt) : null, material.created_by_name]
              .filter(Boolean)
              .join(" · ")}
          </p>
        </div>
        {actions}
      </div>

      {material.description && (
        <QuestionStem html={material.description} className="mt-2.5 text-sm text-muted-foreground" />
      )}

      {material.type === "text" && material.content.body && (
        <QuestionStem html={material.content.body} className="mt-2.5 rounded-xl bg-muted/40 px-3.5 py-3 text-sm" />
      )}

      {material.type === "youtube" && material.content.video_id && (
        <div className="mt-3 overflow-hidden rounded-xl border">
          {showVideo ? (
            <div className="aspect-video">
              <iframe
                src={`https://www.youtube-nocookie.com/embed/${material.content.video_id}?autoplay=1`}
                title={material.title}
                allow="accelerometer; autoplay; encrypted-media; picture-in-picture"
                allowFullScreen
                className="size-full"
              />
            </div>
          ) : (
            <button
              type="button"
              onClick={() => setShowVideo(true)}
              className="pressable relative block aspect-video w-full bg-muted"
            >
              {/* eslint-disable-next-line @next/next/no-img-element -- YouTube thumbnail CDN */}
              <img
                src={`https://i.ytimg.com/vi/${material.content.video_id}/hqdefault.jpg`}
                alt={material.title}
                loading="lazy"
                className="size-full object-cover"
              />
              <span className="absolute inset-0 flex items-center justify-center">
                <span className="flex h-12 w-16 items-center justify-center rounded-2xl bg-background/90 shadow-md backdrop-blur">
                  <CirclePlay className="size-6 text-destructive" />
                </span>
              </span>
            </button>
          )}
        </div>
      )}

      {(material.type === "file" || material.type === "link") && (
        <div className="mt-3 flex items-center gap-2">
          {material.type === "file" ? (
            <Button asChild variant="outline" size="sm" disabled={!material.content.url}>
              <a href={material.content.url ?? "#"} target="_blank" rel="noreferrer">
                <Download className="size-3.5" /> {t("materials.download")}
                {material.content.size ? (
                  <span className="text-muted-foreground">({formatFileSize(material.content.size)})</span>
                ) : null}
              </a>
            </Button>
          ) : (
            <Button asChild variant="outline" size="sm">
              <a href={material.content.url ?? "#"} target="_blank" rel="noreferrer">
                <ExternalLink className="size-3.5" /> {t("materials.open")}
              </a>
            </Button>
          )}
          {material.type === "file" && material.content.mime_type ? (
            <Badge variant="secondary" className="uppercase">
              {material.content.mime_type.split("/").pop()?.slice(0, 4)}
            </Badge>
          ) : null}
        </div>
      )}
    </div>
  )
}
