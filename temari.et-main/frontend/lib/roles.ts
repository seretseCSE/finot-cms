/** Role catalog shared by the users page and its edit/assign sheets. */
export interface RoleOption {
  value: string
  label: string
  /** Mirrors the backend Role scope. "relationship" roles (student/parent/
   * tutor/vendor) are derived from relationships and can NEVER be assigned. */
  scope: "platform" | "school" | "branch" | "relationship"
}

export const ROLE_OPTIONS: RoleOption[] = [
  { value: "super_admin", label: "Super Admin", scope: "platform" },
  { value: "support_agent", label: "Support Agent", scope: "platform" },
  { value: "finance_admin", label: "Finance Admin", scope: "platform" },
  { value: "sales_agent", label: "Sales Agent", scope: "platform" },
  { value: "content_admin", label: "Content Admin", scope: "platform" },
  { value: "principal", label: "Principal", scope: "school" },
  { value: "school_admin", label: "School Admin", scope: "school" },
  { value: "director", label: "Director", scope: "branch" },
  { value: "registrar", label: "Registrar", scope: "branch" },
  { value: "finance_officer", label: "Finance Officer", scope: "branch" },
  { value: "teacher", label: "Teacher", scope: "branch" },
  { value: "student", label: "Student", scope: "relationship" },
  { value: "parent", label: "Parent", scope: "relationship" },
  { value: "tutor", label: "Tutor", scope: "relationship" },
  { value: "vendor", label: "Vendor", scope: "relationship" },
]

/** Roles that may be assigned at branch level (principal / director). */
export const BRANCH_ROLE_OPTIONS: RoleOption[] = ROLE_OPTIONS.filter(
  (r) => r.scope === "branch",
)

export function roleLabel(value: string): string {
  return ROLE_OPTIONS.find((r) => r.value === value)?.label ?? value.replace(/_/g, " ")
}
