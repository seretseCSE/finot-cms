"use client"

import { Command as CommandPrimitive } from "cmdk"
import { Loader2, SearchIcon, XIcon } from "lucide-react"
import * as React from "react"
import { Dialog as DialogPrimitive } from "radix-ui"

import { Dialog, DialogOverlay, DialogPortal } from "@/components/ui/dialog"
import { cn, isEventFromOverlay } from "@/lib/utils"

/**
 * cmdk primitives styled to the Temari design system — the base of the ⌘K
 * global search palette. The dialog positions itself as a floating command
 * box near the top of the viewport (never a bottom sheet) on every screen.
 */
function Command({ className, ...props }: React.ComponentProps<typeof CommandPrimitive>) {
  return (
    <CommandPrimitive
      data-slot="command"
      className={cn(
        "flex h-full w-full flex-col overflow-hidden bg-popover text-popover-foreground",
        className,
      )}
      {...props}
    />
  )
}

function CommandDialog({
  title,
  children,
  ...props
}: React.ComponentProps<typeof Dialog> & { title: string }) {
  return (
    <Dialog {...props}>
      <DialogPortal>
        <DialogOverlay />
        <DialogPrimitive.Content
          data-slot="command-dialog"
          onInteractOutside={(e) => {
            if (isEventFromOverlay()) e.preventDefault()
          }}
          className={cn(
            // A floating palette anchored near the top — same geometry on
            // mobile (keyboard needs the lower half) and desktop.
            "fixed top-[8vh] left-1/2 z-50 w-[calc(100vw-1.5rem)] max-w-xl -translate-x-1/2 sm:top-[16vh]",
            "overflow-hidden rounded-2xl border bg-popover text-popover-foreground shadow-2xl outline-none",
            "duration-150 data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-open:slide-in-from-top-2 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95",
          )}
        >
          <DialogPrimitive.Title className="sr-only">{title}</DialogPrimitive.Title>
          <Command
            // Keep groups in the server's order; cmdk's own scoring would
            // reshuffle the async results on every keystroke.
            shouldFilter={false}
          >
            {children}
          </Command>
        </DialogPrimitive.Content>
      </DialogPortal>
    </Dialog>
  )
}

function CommandInput({
  className,
  loading = false,
  onClose,
  closeLabel,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Input> & {
  loading?: boolean
  /** Renders a ✕ at the input's right edge — the palette's touch escape hatch. */
  onClose?: () => void
  closeLabel?: string
}) {
  return (
    <div className="p-3 pb-2" data-slot="command-input-wrapper">
      <div className="flex h-11 items-center gap-2.5 rounded-xl border border-input/60 bg-muted/50 px-3.5 transition-colors focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/30">
        <SearchIcon className="size-4 shrink-0 text-muted-foreground" />
        <CommandPrimitive.Input
          data-slot="command-input"
          className={cn(
            "h-full w-full min-w-0 flex-1 bg-transparent text-base outline-none placeholder:text-muted-foreground md:text-sm",
            className,
          )}
          {...props}
        />
        {loading && (
          <Loader2 className="size-4 shrink-0 animate-spin text-muted-foreground" aria-hidden />
        )}
        {onClose && (
          <button
            type="button"
            onClick={onClose}
            aria-label={closeLabel}
            // Full input height and bleeding into the right padding: a 44px
            // touch target that reads as a small quiet ✕.
            className="pressable -mr-3.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-r-xl text-muted-foreground transition-colors hover:text-foreground"
          >
            <XIcon className="size-4" />
          </button>
        )}
      </div>
    </div>
  )
}

function CommandList({ className, ...props }: React.ComponentProps<typeof CommandPrimitive.List>) {
  return (
    <CommandPrimitive.List
      data-slot="command-list"
      className={cn(
        "max-h-[min(420px,55vh)] scroll-py-2 overflow-y-auto overscroll-contain px-2 pb-2",
        className,
      )}
      {...props}
    />
  )
}

function CommandEmpty(props: React.ComponentProps<typeof CommandPrimitive.Empty>) {
  return (
    <CommandPrimitive.Empty
      data-slot="command-empty"
      className="py-10 text-center text-sm text-muted-foreground"
      {...props}
    />
  )
}

function CommandGroup({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Group>) {
  return (
    <CommandPrimitive.Group
      data-slot="command-group"
      className={cn(
        "overflow-hidden text-foreground [&_[cmdk-group-heading]]:px-2.5 [&_[cmdk-group-heading]]:pt-2.5 [&_[cmdk-group-heading]]:pb-1.5 [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground",
        className,
      )}
      {...props}
    />
  )
}

function CommandSeparator({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Separator>) {
  return (
    <CommandPrimitive.Separator
      data-slot="command-separator"
      className={cn("-mx-2 my-1.5 h-px bg-border/70", className)}
      {...props}
    />
  )
}

function CommandItem({ className, ...props }: React.ComponentProps<typeof CommandPrimitive.Item>) {
  return (
    <CommandPrimitive.Item
      data-slot="command-item"
      className={cn(
        "relative flex min-h-10 cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm outline-none select-none data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground [&_svg]:size-4 [&_svg]:shrink-0 [&_svg]:text-muted-foreground",
        className,
      )}
      {...props}
    />
  )
}

/** Right-aligned hint (kbd shortcut / entity id) inside an item. */
function CommandShortcut({ className, ...props }: React.ComponentProps<"span">) {
  return (
    <span
      data-slot="command-shortcut"
      className={cn("ml-auto shrink-0 text-xs tracking-wider text-muted-foreground", className)}
      {...props}
    />
  )
}

/** Bottom hint bar: ↑↓ navigate · ↵ open · esc close. Desktop only. */
function CommandFooter({ className, children, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="command-footer"
      className={cn(
        "hidden items-center gap-4 border-t bg-muted/30 px-4 py-2 text-[11px] text-muted-foreground sm:flex",
        className,
      )}
      {...props}
    >
      {children}
    </div>
  )
}

function CommandKbd({ className, ...props }: React.ComponentProps<"kbd">) {
  return (
    <kbd
      className={cn(
        "inline-flex min-w-4.5 items-center justify-center rounded border bg-background px-1 py-0.5 font-sans text-[10px] text-muted-foreground",
        className,
      )}
      {...props}
    />
  )
}

export {
  Command,
  CommandDialog,
  CommandEmpty,
  CommandFooter,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandKbd,
  CommandList,
  CommandSeparator,
  CommandShortcut,
}
