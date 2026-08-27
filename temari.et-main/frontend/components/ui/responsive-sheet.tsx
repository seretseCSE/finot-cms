"use client"

import * as React from "react"

import { useMediaQuery } from "@/hooks/use-media-query"
import {
  Drawer,
  DrawerBody,
  DrawerClose,
  DrawerContent,
  DrawerDescription,
  DrawerFooter,
  DrawerHeader,
  DrawerTitle,
  DrawerTrigger,
} from "@/components/ui/drawer"
import {
  Sheet,
  SheetBody,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet"

type RootProps = {
  open?: boolean
  onOpenChange?: (open: boolean) => void
  children: React.ReactNode
}

function ResponsiveSheet({ open, onOpenChange, children }: RootProps) {
  const isDesktop = useMediaQuery("(min-width: 640px)")
  const Root = isDesktop ? Sheet : Drawer
  return (
    <Root open={open} onOpenChange={onOpenChange}>
      {children}
    </Root>
  )
}

function ResponsiveSheetTrigger({ children, asChild }: { children: React.ReactNode; asChild?: boolean }) {
  const isDesktop = useMediaQuery("(min-width: 640px)")
  const Trigger = isDesktop ? SheetTrigger : DrawerTrigger
  return <Trigger asChild={asChild}>{children}</Trigger>
}

function ResponsiveSheetClose({ children, asChild }: { children: React.ReactNode; asChild?: boolean }) {
  const isDesktop = useMediaQuery("(min-width: 640px)")
  const Close = isDesktop ? SheetClose : DrawerClose
  return <Close asChild={asChild}>{children}</Close>
}

function ResponsiveSheetContent({ children, className }: { children: React.ReactNode; className?: string }) {
  const isDesktop = useMediaQuery("(min-width: 640px)")
  if (isDesktop) {
    return <SheetContent className={className}>{children}</SheetContent>
  }
  return <DrawerContent className={className}>{children}</DrawerContent>
}

function ResponsiveSheetHeader({ children, className }: { children: React.ReactNode; className?: string }) {
  const isDesktop = useMediaQuery("(min-width: 640px)")
  if (isDesktop) {
    return <SheetHeader className={className}>{children}</SheetHeader>
  }
  return <DrawerHeader className={className}>{children}</DrawerHeader>
}

function ResponsiveSheetBody({ children, className }: { children: React.ReactNode; className?: string }) {
  const isDesktop = useMediaQuery("(min-width: 640px)")
  if (isDesktop) {
    return <SheetBody className={className}>{children}</SheetBody>
  }
  return <DrawerBody className={className}>{children}</DrawerBody>
}

function ResponsiveSheetFooter({ children, className }: { children: React.ReactNode; className?: string }) {
  const isDesktop = useMediaQuery("(min-width: 640px)")
  if (isDesktop) {
    return <SheetFooter className={className}>{children}</SheetFooter>
  }
  return <DrawerFooter className={className}>{children}</DrawerFooter>
}

function ResponsiveSheetTitle({ children, className }: { children: React.ReactNode; className?: string }) {
  const isDesktop = useMediaQuery("(min-width: 640px)")
  if (isDesktop) {
    return <SheetTitle className={className}>{children}</SheetTitle>
  }
  return <DrawerTitle className={className}>{children}</DrawerTitle>
}

function ResponsiveSheetDescription({ children, className }: { children: React.ReactNode; className?: string }) {
  const isDesktop = useMediaQuery("(min-width: 640px)")
  if (isDesktop) {
    return <SheetDescription className={className}>{children}</SheetDescription>
  }
  return <DrawerDescription className={className}>{children}</DrawerDescription>
}

export {
  ResponsiveSheet,
  ResponsiveSheetTrigger,
  ResponsiveSheetClose,
  ResponsiveSheetContent,
  ResponsiveSheetHeader,
  ResponsiveSheetBody,
  ResponsiveSheetFooter,
  ResponsiveSheetTitle,
  ResponsiveSheetDescription,
}
