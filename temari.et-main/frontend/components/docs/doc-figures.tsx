"use client"

import { Check, ChevronDown, GitBranch, School } from "lucide-react"

import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

import type { FigureId } from "./content"

/**
 * Screenshot-style illustrations for the help center: tiny, non-interactive
 * mockups of the real screens, built from the same tokens so they stay true
 * in both themes. Person names and amounts are sample DATA (not UI strings);
 * every label goes through the docs i18n domain.
 */

/* ---------------------------------------------------------------- */
/* Shared frame                                                      */
/* ---------------------------------------------------------------- */

function Frame({
  title,
  caption,
  children,
}: {
  title: string
  caption: string
  children: React.ReactNode
}) {
  return (
    <figure className="overflow-hidden rounded-2xl border bg-card shadow-xs" aria-hidden>
      <div className="flex items-center gap-2 border-b bg-muted/50 px-4 py-2.5">
        <span className="flex gap-1.5">
          <span className="size-2.5 rounded-full bg-border" />
          <span className="size-2.5 rounded-full bg-border" />
          <span className="size-2.5 rounded-full bg-border" />
        </span>
        <span className="text-xs font-medium text-muted-foreground">{title}</span>
      </div>
      <div className="space-y-2.5 p-4">{children}</div>
      <figcaption className="border-t bg-muted/30 px-4 py-2 text-xs text-muted-foreground">
        {caption}
      </figcaption>
    </figure>
  )
}

function Chip({
  tone = "muted",
  children,
}: {
  tone?: "muted" | "success" | "warning" | "destructive" | "info" | "primary"
  children: React.ReactNode
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium",
        tone === "muted" && "bg-muted text-muted-foreground",
        tone === "success" && "bg-success/10 text-success",
        tone === "warning" && "bg-warning/10 text-warning",
        tone === "destructive" && "bg-destructive/10 text-destructive",
        tone === "info" && "bg-info/10 text-info",
        tone === "primary" && "bg-primary/10 text-primary",
      )}
    >
      {children}
    </span>
  )
}

function FieldMock({ label, value }: { label: string; value: string }) {
  return (
    <div className="space-y-1">
      <p className="text-[11px] font-medium text-muted-foreground">{label}</p>
      <div className="flex h-8 items-center rounded-xl border bg-muted/30 px-3 text-xs">
        {value}
      </div>
    </div>
  )
}

function PillButton({ children }: { children: React.ReactNode }) {
  return (
    <span className="inline-flex h-8 items-center rounded-full bg-primary px-4 text-xs font-medium text-primary-foreground">
      {children}
    </span>
  )
}

function FlowPills({ labels, activeIndex }: { labels: string[]; activeIndex: number }) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      {labels.map((label, i) => (
        <div key={label} className="flex items-center gap-2">
          {i > 0 && <span className="text-muted-foreground/60">→</span>}
          <Chip tone={i === activeIndex ? "primary" : i < activeIndex ? "success" : "muted"}>
            {label}
          </Chip>
        </div>
      ))}
    </div>
  )
}

/* ---------------------------------------------------------------- */
/* Figures                                                           */
/* ---------------------------------------------------------------- */

function WorkspaceFigure() {
  const { t } = useTranslation("docs")
  return (
    <Frame title={t("figures.workspace.title")} caption={t("figures.workspace.caption")}>
      <div className="flex items-center justify-between rounded-xl border bg-muted/30 px-3 py-2">
        <div className="flex items-center gap-2">
          <span className="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <School className="size-4" strokeWidth={1.75} />
          </span>
          <div>
            <p className="text-xs font-medium">{t("figures.workspace.school")}</p>
            <p className="text-[11px] text-muted-foreground">{t("figures.workspace.branchA")}</p>
          </div>
        </div>
        <ChevronDown className="size-3.5 text-muted-foreground" />
      </div>
      <div className="space-y-1 rounded-xl border p-1.5">
        {[
          { name: t("figures.workspace.branchA"), active: true },
          { name: t("figures.workspace.branchB"), active: false },
        ].map((branch) => (
          <div
            key={branch.name}
            className={cn(
              "flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs",
              branch.active ? "bg-accent font-medium" : "text-muted-foreground",
            )}
          >
            <span className="flex items-center gap-2">
              <GitBranch className="size-3.5" strokeWidth={1.75} />
              {branch.name}
            </span>
            {branch.active && <Check className="size-3.5 text-primary" />}
          </div>
        ))}
      </div>
    </Frame>
  )
}

function RolesFigure() {
  const { t } = useTranslation("docs")
  const rows: { role: string; scope: string; tone: "primary" | "info" | "success" }[] = [
    { role: t("figures.roles.principal"), scope: t("figures.roles.schoolScope"), tone: "info" },
    { role: t("figures.roles.director"), scope: t("figures.roles.branchScope"), tone: "primary" },
    { role: t("figures.roles.teacher"), scope: t("figures.roles.branchScope"), tone: "primary" },
    { role: t("figures.roles.parent"), scope: t("figures.roles.relationshipScope"), tone: "success" },
  ]
  return (
    <Frame title={t("figures.roles.title")} caption={t("figures.roles.caption")}>
      {rows.map((row) => (
        <div
          key={row.role}
          className="flex items-center justify-between rounded-xl border px-3 py-2"
        >
          <span className="text-xs font-medium">{row.role}</span>
          <Chip tone={row.tone}>{row.scope}</Chip>
        </div>
      ))}
    </Frame>
  )
}

function YearLifecycleFigure() {
  const { t } = useTranslation("docs")
  return (
    <Frame title={t("figures.yearLifecycle.title")} caption={t("figures.yearLifecycle.caption")}>
      <div className="rounded-xl border px-3 py-2.5">
        <p className="text-xs font-medium">2018 (2025/26)</p>
        <p className="mt-0.5 text-[11px] text-muted-foreground">
          {t("figures.yearLifecycle.terms")}
        </p>
      </div>
      <FlowPills
        labels={[
          t("figures.yearLifecycle.planned"),
          t("figures.yearLifecycle.active"),
          t("figures.yearLifecycle.closed"),
        ]}
        activeIndex={1}
      />
    </Frame>
  )
}

function MatrixFigure() {
  const { t } = useTranslation("docs")
  const subjects = [
    { name: t("figures.matrix.maths"), a: "Abebe K.", b: "Abebe K." },
    { name: t("figures.matrix.english"), a: "Sara T.", b: null },
    { name: t("figures.matrix.amharic"), a: "Hana G.", b: "Hana G." },
  ]
  return (
    <Frame title={t("figures.matrix.title")} caption={t("figures.matrix.caption")}>
      <div className="overflow-hidden rounded-xl border text-[11px]">
        <div className="grid grid-cols-3 border-b bg-muted/50 font-medium">
          <span className="px-2.5 py-1.5">{t("figures.matrix.subject")}</span>
          <span className="px-2.5 py-1.5">5A</span>
          <span className="px-2.5 py-1.5">5B</span>
        </div>
        {subjects.map((row) => (
          <div key={row.name} className="grid grid-cols-3 border-b last:border-b-0">
            <span className="px-2.5 py-1.5 font-medium">{row.name}</span>
            <span className="px-2.5 py-1.5">{row.a}</span>
            <span className="px-2.5 py-1.5">
              {row.b ?? (
                <Chip tone="warning">{t("figures.matrix.unassigned")}</Chip>
              )}
            </span>
          </div>
        ))}
      </div>
    </Frame>
  )
}

function AttendanceFigure() {
  const { t } = useTranslation("docs")
  const rows: { name: string; status: string; tone: "success" | "destructive" | "warning" }[] = [
    { name: "Abebe Kebede", status: t("figures.attendance.present"), tone: "success" },
    { name: "Sara Tesfaye", status: t("figures.attendance.present"), tone: "success" },
    { name: "Hana Girma", status: t("figures.attendance.late"), tone: "warning" },
    { name: "Dawit Alemu", status: t("figures.attendance.absent"), tone: "destructive" },
  ]
  return (
    <Frame title={t("figures.attendance.title")} caption={t("figures.attendance.caption")}>
      {rows.map((row) => (
        <div
          key={row.name}
          className="flex items-center justify-between rounded-xl border px-3 py-2"
        >
          <span className="text-xs font-medium">{row.name}</span>
          <Chip tone={row.tone}>{row.status}</Chip>
        </div>
      ))}
      <div className="flex justify-end pt-1">
        <PillButton>{t("figures.attendance.save")}</PillButton>
      </div>
    </Frame>
  )
}

function InvoiceFigure() {
  const { t } = useTranslation("docs")
  return (
    <Frame title={t("figures.invoice.title")} caption={t("figures.invoice.caption")}>
      <div className="space-y-2.5 rounded-xl border p-3">
        <div className="flex items-start justify-between gap-2">
          <div>
            <p className="text-xs font-medium">Abebe Kebede</p>
            <p className="text-[11px] text-muted-foreground">{t("figures.invoice.itemName")}</p>
          </div>
          <Chip tone="warning">{t("figures.invoice.partial")}</Chip>
        </div>
        <div className="grid grid-cols-3 gap-2 text-[11px]">
          <div>
            <p className="text-muted-foreground">{t("figures.invoice.amount")}</p>
            <p className="font-mono font-medium tabular-nums">1,500</p>
          </div>
          <div>
            <p className="text-muted-foreground">{t("figures.invoice.paid")}</p>
            <p className="font-mono font-medium tabular-nums text-success">1,000</p>
          </div>
          <div>
            <p className="text-muted-foreground">{t("figures.invoice.balance")}</p>
            <p className="font-mono font-medium tabular-nums text-destructive">500</p>
          </div>
        </div>
        <div className="flex justify-end">
          <PillButton>{t("figures.invoice.recordPayment")}</PillButton>
        </div>
      </div>
    </Frame>
  )
}

function PayrollLifecycleFigure() {
  const { t } = useTranslation("docs")
  const totals = [
    { label: t("figures.payroll.gross"), value: "184,000" },
    { label: t("figures.payroll.tax"), value: "31,240" },
    { label: t("figures.payroll.pension"), value: "12,880" },
    { label: t("figures.payroll.net"), value: "139,880" },
  ]
  return (
    <Frame title={t("figures.payroll.title")} caption={t("figures.payroll.caption")}>
      <FlowPills
        labels={[
          t("figures.payroll.draft"),
          t("figures.payroll.approved"),
          t("figures.payroll.paid"),
        ]}
        activeIndex={0}
      />
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {totals.map((item) => (
          <div key={item.label} className="rounded-xl border px-2.5 py-2">
            <p className="text-[11px] text-muted-foreground">{item.label}</p>
            <p className="font-mono text-xs font-medium tabular-nums">{item.value}</p>
          </div>
        ))}
      </div>
    </Frame>
  )
}

function EmployeeFormFigure() {
  const { t } = useTranslation("docs")
  return (
    <Frame title={t("figures.employeeForm.title")} caption={t("figures.employeeForm.caption")}>
      <div className="grid grid-cols-2 gap-2.5">
        <FieldMock label={t("figures.form.firstName")} value="Abebe" />
        <FieldMock label={t("figures.form.fatherName")} value="Kebede" />
        <FieldMock label={t("figures.form.phone")} value="09 11 22 33 44" />
        <FieldMock label={t("figures.employeeForm.jobTitle")} value={t("figures.roles.teacher")} />
      </div>
      <div className="flex justify-end pt-1">
        <PillButton>{t("figures.form.save")}</PillButton>
      </div>
    </Frame>
  )
}

function StudentFormFigure() {
  const { t } = useTranslation("docs")
  return (
    <Frame title={t("figures.studentForm.title")} caption={t("figures.studentForm.caption")}>
      <div className="grid grid-cols-2 gap-2.5">
        <FieldMock label={t("figures.form.firstName")} value="Sara" />
        <FieldMock label={t("figures.form.fatherName")} value="Tesfaye" />
        <FieldMock label={t("figures.form.grandfatherName")} value="Lemma" />
        <FieldMock label={t("figures.studentForm.grade")} value={t("figures.studentForm.gradeValue")} />
      </div>
      <div className="flex justify-end pt-1">
        <PillButton>{t("figures.form.save")}</PillButton>
      </div>
    </Frame>
  )
}

const FIGURES: Record<FigureId, () => React.ReactNode> = {
  workspace: WorkspaceFigure,
  roles: RolesFigure,
  yearLifecycle: YearLifecycleFigure,
  matrix: MatrixFigure,
  attendance: AttendanceFigure,
  invoice: InvoiceFigure,
  payrollLifecycle: PayrollLifecycleFigure,
  employeeForm: EmployeeFormFigure,
  studentForm: StudentFormFigure,
}

export function DocFigure({ id }: { id: FigureId }) {
  const Figure = FIGURES[id]
  return <Figure />
}
