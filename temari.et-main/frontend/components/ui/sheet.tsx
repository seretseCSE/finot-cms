"use client"

import * as React from "react"
import { Dialog as SheetPrimitive } from "radix-ui"

import { cn, isEventFromOverlay } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { XIcon } from "lucide-react"

function Sheet({ ...props }: React.ComponentProps<typeof SheetPrimitive.Root>) {
  return <SheetPrimitive.Root data-slot="sheet" {...props} />
}

function SheetTrigger({
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Trigger>) {
  return <SheetPrimitive.Trigger data-slot="sheet-trigger" {...props} />
}

function SheetClose({
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Close>) {
  return <SheetPrimitive.Close data-slot="sheet-close" {...props} />
}

function SheetPortal({
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Portal>) {
  return <SheetPrimitive.Portal data-slot="sheet-portal" {...props} />
}

function SheetOverlay({
  className,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Overlay>) {
  return (
    <SheetPrimitive.Overlay
      data-slot="sheet-overlay"
      className={cn(
        "fixed inset-0 z-50 bg-black/30 duration-200 supports-backdrop-filter:backdrop-blur-xs data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0",
        className
      )}
      {...props}
    />
  )
}

function SheetContent({
  className,
  children,
  side = "right",
  showCloseButton = true,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Content> & {
  side?: "top" | "right" | "bottom" | "left"
  showCloseButton?: boolean
}) {
  return (
    <SheetPortal>
      <SheetOverlay />
      <SheetPrimitive.Content
        data-slot="sheet-content"
        data-side={side}
        onInteractOutside={(e) => {
          if (isEventFromOverlay()) e.preventDefault()
        }}
        className={cn(
          "fixed z-50 flex flex-col overflow-hidden bg-card text-sm text-card-foreground shadow-xl transition duration-300 ease-in-out",
          "data-[side=right]:inset-y-3 data-[side=right]:right-3 data-[side=right]:h-[calc(100dvh-1.5rem)] data-[side=right]:w-full data-[side=right]:rounded-2xl data-[side=right]:border data-[side=right]:border-border/40 data-[side=right]:shadow-2xl data-[side=right]:sm:max-w-md",
          "data-[side=left]:inset-y-3 data-[side=left]:left-3 data-[side=left]:h-[calc(100dvh-1.5rem)] data-[side=left]:w-full data-[side=left]:rounded-2xl data-[side=left]:border data-[side=left]:border-border/40 data-[side=left]:shadow-2xl data-[side=left]:sm:max-w-md",
          "data-[side=top]:inset-x-0 data-[side=top]:top-0 data-[side=top]:h-auto data-[side=top]:border-b",
          "data-[side=bottom]:inset-x-4 data-[side=bottom]:bottom-4 data-[side=bottom]:h-auto data-[side=bottom]:max-h-[90dvh] data-[side=bottom]:rounded-3xl data-[side=bottom]:border data-[side=bottom]:border-border/40 data-[side=bottom]:shadow-2xl",
          "data-open:animate-in data-open:fade-in-0",
          "data-[side=left]:data-open:slide-in-from-left-full data-[side=right]:data-open:slide-in-from-right-full",
          "data-[side=bottom]:data-open:slide-in-from-bottom-full data-[side=top]:data-open:slide-in-from-top-full",
          "data-closed:animate-out data-closed:fade-out-0",
          "data-[side=left]:data-closed:slide-out-to-left-full data-[side=right]:data-closed:slide-out-to-right-full",
          "data-[side=bottom]:data-closed:slide-out-to-bottom-full data-[side=top]:data-closed:slide-out-to-top-full",
          className
        )}
        {...props}
      >
        {children}
        {showCloseButton && (
          <SheetPrimitive.Close data-slot="sheet-close" asChild>
            <Button
              variant="ghost"
              className="absolute top-4 right-4"
              size="icon-sm"
            >
              <XIcon />
              <span className="sr-only">Close</span>
            </Button>
          </SheetPrimitive.Close>
        )}
      </SheetPrimitive.Content>
    </SheetPortal>
  )
}

function SheetHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="sheet-header"
      className={cn(
        "flex flex-col gap-1 border-b border-border/60 px-6 pt-6 pr-12 pb-4",
        className
      )}
      {...props}
    />
  )
}

function SheetBody({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="sheet-body"
      className={cn(
        "flex-1 overflow-x-hidden overflow-y-auto px-6 py-6",
        className
      )}
      {...props}
    />
  )
}

function SheetFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="sheet-footer"
      className={cn(
        "flex gap-3 border-t border-border/60 px-6 py-4",
        className
      )}
      {...props}
    />
  )
}

function SheetTitle({
  className,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Title>) {
  return (
    <SheetPrimitive.Title
      data-slot="sheet-title"
      className={cn(
        "font-heading text-lg font-semibold text-foreground",
        className
      )}
      {...props}
    />
  )
}

function SheetDescription({
  className,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Description>) {
  return (
    <SheetPrimitive.Description
      data-slot="sheet-description"
      className={cn("text-sm text-muted-foreground", className)}
      {...props}
    />
  )
}

export {
  Sheet,
  SheetTrigger,
  SheetClose,
  SheetContent,
  SheetHeader,
  SheetBody,
  SheetFooter,
  SheetTitle,
  SheetDescription,
}
