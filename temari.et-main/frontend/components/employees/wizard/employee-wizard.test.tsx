import { fireEvent, render, screen, waitFor, within } from "@testing-library/react"
import userEvent from "@testing-library/user-event"
import { beforeEach, describe, expect, it, vi } from "vitest"

import { I18nProvider } from "@/lib/i18n"

/**
 * Behavioural tests for the staff hire/edit wizard.
 *
 * These exist to pin the wizard's behaviour down BEFORE it is split into
 * per-step components, so the refactor has something to be judged against.
 * They therefore assert on what a user can see and do — visible fields, which
 * step is reachable, what gets POSTed — and never on internal structure, so
 * that moving a step into its own file cannot break them.
 *
 * Note on step isolation: the wizard keeps every step mounted and hides the
 * inactive ones with Tailwind's `hidden`. vitest.setup.ts loads that one rule
 * into jsdom, so Testing Library's default "ignore what the user cannot see"
 * behaviour is what makes `getByRole` mean "on the current step".
 */

// ── Module boundaries ────────────────────────────────────────────────────
// Everything the wizard reaches for outside itself is stubbed: the network,
// the router, the school-context-backed branch picker and the toaster. The
// form, the schema and every field control stay REAL — they are the subject.

const apiFetch = vi.fn()
const push = vi.fn()

vi.mock("@/lib/api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api")>()),
  apiFetch: (...args: unknown[]) => apiFetch(...args),
}))

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push, replace: vi.fn(), back: vi.fn() }),
}))

vi.mock("@/components/ui/branch-select", () => ({
  useBranchScope: () => ({
    needsBranch: false,
    branches: [],
    activeBranchId: 1,
  }),
  BranchField: () => null,
}))

const toastError = vi.fn()
const toastSuccess = vi.fn()
vi.mock("sonner", () => ({
  toast: {
    error: (...args: unknown[]) => toastError(...args),
    success: (...args: unknown[]) => toastSuccess(...args),
  },
}))

const { EmployeeWizard } = await import("@/components/employees/wizard/employee-wizard")

// ── Fixtures & helpers ───────────────────────────────────────────────────

/** Account policy as returned by GET /employees/account-policy. */
interface Policy {
  account_job_titles: string[]
  required_job_titles: string[]
}

let policy: Policy

/** Route the wizard's API calls; individual tests override what they care about. */
function stubApi() {
  apiFetch.mockImplementation(async (path: string) => {
    if (path.startsWith("/employees/account-policy")) return { data: policy }
    if (path.startsWith("/subjects")) return { data: [] }
    if (path.startsWith("/grade-levels")) return { data: [] }
    throw new Error(`unstubbed request: ${path}`)
  })
}

function renderWizard(props: React.ComponentProps<typeof EmployeeWizard> = {}) {
  return render(
    <I18nProvider>
      <EmployeeWizard {...props} />
    </I18nProvider>
  )
}

/** The step whose pill is currently selected, by its visible label. */
function currentStep(): string {
  const active = document.querySelector("ol button.bg-primary")
  return active?.textContent?.trim() ?? ""
}

const user = () => userEvent.setup()

async function clickNext() {
  await user().click(screen.getByRole("button", { name: "Next" }))
}

async function clickBack() {
  await user().click(screen.getByRole("button", { name: "Back" }))
}

/**
 * Fields are looked up by their accessible name — the label a screen-reader
 * user hears. That is both the robust query (placeholders are decoration and
 * change freely) and a live assertion that `FormControl` still wires its label
 * to the real control rather than to a wrapper.
 */
const firstNameInput = () => screen.getByRole("textbox", { name: "First name" })
const phoneInput = () => screen.getByRole("textbox", { name: "Phone" })

/** Fill the identity step with the minimum the schema accepts. */
async function fillIdentity(name = "Kalkidan") {
  const u = user()
  await u.type(firstNameInput(), name)
  await u.type(phoneInput(), "0911234567")
}

/**
 * The job-title combobox trigger for a position row. A position row repeats,
 * so these are indexed; the name comes from the row's own `<FormLabel>`.
 */
const jobTitleTrigger = (index = 0) => screen.getAllByRole("combobox", { name: "Job title" })[index]

/** Pick a job title through the real combobox on the positions step. */
async function pickJobTitle(label: string, index = 0) {
  const u = user()
  await u.click(jobTitleTrigger(index))
  await u.click(await screen.findByRole("option", { name: label }))
}

/**
 * Pick a hire date through the real DatePicker (required — leave entitlement
 * grows with service). The Ethiopian grid opens on the current month with
 * future days disabled, so day 1 is always selectable.
 */
async function pickHireDate(index = 0) {
  const u = user()
  await u.click(screen.getAllByRole("button", { name: "Hired on" })[index])
  await u.click(await screen.findByRole("button", { name: "1" }))
}

/** The request body the wizard sent to `path` (throws if it never called it). */
function sentBody(path: string): Record<string, unknown> {
  const call = apiFetch.mock.calls.find((args: unknown[]) => args[0] === path)
  if (!call) throw new Error(`no request was made to ${path}`)
  return (call[1] as { body: Record<string, unknown> }).body
}

/** Advance from identity to the positions step (valid identity assumed). */
async function goToPositions() {
  await clickNext() // → Address
  await clickNext() // → Jobs & account
  await waitFor(() => expect(currentStep()).toBe("Jobs & account"))
}

beforeEach(() => {
  vi.clearAllMocks()
  policy = { account_job_titles: [], required_job_titles: [] }
  // The nationality combobox loads its catalog over plain fetch.
  vi.stubGlobal(
    "fetch",
    vi.fn(async () => new Response("[]"))
  )
  stubApi()
})

// ── Step navigation ──────────────────────────────────────────────────────

describe("step navigation", () => {
  it("starts on the identity step and shows only its fields", async () => {
    renderWizard()

    expect(await screen.findByRole("heading", { name: "Add employee" })).toBeInTheDocument()
    expect(currentStep()).toBe("Person")
    expect(firstNameInput()).toBeVisible()
    // A positions-step control must not be reachable from here. Every step
    // stays mounted, so this asserts on visibility, not presence — and it has
    // to pick out the job-title combobox specifically, since the identity step
    // has comboboxes of its own (gender, nationality).
    expect(screen.getByText("Select job title")).not.toBeVisible()
  })

  it("moves forward through the steps and back again", async () => {
    renderWizard()
    await fillIdentity()

    await clickNext()
    await waitFor(() => expect(currentStep()).toBe("Address"))

    await clickNext()
    await waitFor(() => expect(currentStep()).toBe("Jobs & account"))

    await clickBack()
    await waitFor(() => expect(currentStep()).toBe("Address"))
  })

  it("disables Back on the first step", async () => {
    renderWizard()
    expect(screen.getByRole("button", { name: "Back" })).toBeDisabled()
  })

  it("locks stepper pills for steps the user has not reached yet", async () => {
    renderWizard()

    // Create mode is linear: you may not jump ahead.
    expect(screen.getByRole("button", { name: "Documents" })).toBeDisabled()

    await fillIdentity()
    await clickNext()
    await waitFor(() => expect(currentStep()).toBe("Address"))

    // …but a completed step stays clickable, and jumping back works.
    const personPill = screen.getByRole("button", { name: "Person" })
    expect(personPill).toBeEnabled()
    await user().click(personPill)
    await waitFor(() => expect(currentStep()).toBe("Person"))
  })

  it("reveals the teaching step only once a teacher position exists", async () => {
    policy = { account_job_titles: [], required_job_titles: ["teacher"] }
    renderWizard()

    expect(screen.queryByRole("button", { name: "Teaching" })).not.toBeInTheDocument()

    await fillIdentity()
    await goToPositions()
    await pickJobTitle("Teacher")

    await waitFor(() =>
      expect(screen.getByRole("button", { name: "Teaching" })).toBeInTheDocument()
    )
  })
})

// ── Validation ───────────────────────────────────────────────────────────

describe("validation", () => {
  it("blocks Next while the identity step is incomplete", async () => {
    renderWizard()

    await clickNext()

    expect(await screen.findByText("First name is required")).toBeVisible()
    expect(currentStep()).toBe("Person")
  })

  it("blocks Next on an invalid phone number", async () => {
    renderWizard()
    const u = user()

    await u.type(firstNameInput(), "Kalkidan")
    await u.type(phoneInput(), "123")
    await clickNext()

    await waitFor(() => expect(currentStep()).toBe("Person"))
  })

  it("lets the user through once the step is valid", async () => {
    renderWizard()

    await clickNext()
    expect(await screen.findByText("First name is required")).toBeVisible()

    await fillIdentity()
    await clickNext()

    await waitFor(() => expect(currentStep()).toBe("Address"))
  })

  it("blocks Next until a job title is picked", async () => {
    renderWizard()
    await fillIdentity()
    await goToPositions()

    await clickNext()

    expect(await screen.findByText("Pick a job title")).toBeVisible()
    expect(currentStep()).toBe("Jobs & account")

    await pickJobTitle("Registrar")
    await pickHireDate()
    await clickNext()

    await waitFor(() => expect(currentStep()).not.toBe("Jobs & account"))
  })
})

// ── Portal-account policy ────────────────────────────────────────────────
// Accounts are gated per job title: the four role-mapped titles always get
// one, the school's policy list makes it optional, anything else gets none.

describe("account policy", () => {
  it("forces an account for a role-mapped job title, with no choice offered", async () => {
    policy = { account_job_titles: [], required_job_titles: ["teacher"] }
    renderWizard()
    await fillIdentity()
    await goToPositions()

    await pickJobTitle("Teacher")

    expect(await screen.findByText("A portal account will be created")).toBeVisible()
    expect(
      screen.queryByRole("checkbox", { name: /create a portal account/i })
    ).not.toBeInTheDocument()
  })

  it("offers the choice for a title the school's policy allows", async () => {
    policy = { account_job_titles: ["librarian"], required_job_titles: [] }
    renderWizard()
    await fillIdentity()
    await goToPositions()

    await pickJobTitle("Librarian")

    const checkbox = await screen.findByRole("checkbox")
    expect(checkbox).toBeVisible()
    // Opt-in by default — the wizard pre-checks it.
    expect(checkbox).toBeChecked()
    expect(screen.queryByText("A portal account will be created")).not.toBeInTheDocument()
  })

  it("offers nothing for a title outside the policy", async () => {
    policy = { account_job_titles: ["librarian"], required_job_titles: [] }
    renderWizard()
    await fillIdentity()
    await goToPositions()

    await pickJobTitle("Security guard")

    // Both the trigger and the row header echo the picked title.
    await waitFor(() => expect(screen.getAllByText("Security guard").length).toBeGreaterThan(0))
    expect(screen.queryByRole("checkbox")).not.toBeInTheDocument()
    expect(screen.queryByText("A portal account will be created")).not.toBeInTheDocument()
  })
})

// ── Draft attachments ────────────────────────────────────────────────────

describe("draft documents", () => {
  /** The shared picker for staff documents (the photo input is separate). */
  function documentInput(): HTMLInputElement {
    const input = document.querySelector<HTMLInputElement>('input[type="file"][accept*=".pdf"]')
    if (!input) throw new Error("document file input not found")
    return input
  }

  function attach(...files: File[]) {
    fireEvent.change(documentInput(), { target: { files } })
  }

  async function goToDocuments() {
    await fillIdentity()
    await goToPositions()
    await pickJobTitle("Librarian")
    await pickHireDate()
    await clickNext() // → Qualifications
    await clickNext() // → Pay & schedule
    await clickNext() // → Documents
    await waitFor(() => expect(currentStep()).toBe("Documents"))
  }

  it("stages a picked file as a draft with an editable name", async () => {
    renderWizard()
    await goToDocuments()

    attach(new File(["cv"], "contract.pdf", { type: "application/pdf" }))

    // The extension is stripped so the name reads as a title, and it is
    // editable before anything is uploaded.
    const nameInput = await screen.findByDisplayValue("contract")
    expect(nameInput).toBeVisible()
    expect(screen.getByText(/contract\.pdf/)).toBeVisible()

    await user().clear(nameInput)
    await user().type(nameInput, "Employment contract")
    expect(screen.getByDisplayValue("Employment contract")).toBeVisible()

    // Staged only — nothing is uploaded until the wizard is submitted.
    expect(apiFetch).not.toHaveBeenCalledWith(
      expect.stringContaining("/attachments"),
      expect.anything()
    )
  })

  it("stages several files at once and removes one", async () => {
    renderWizard()
    await goToDocuments()

    attach(
      new File(["a"], "id.png", { type: "image/png" }),
      new File(["b"], "degree.pdf", { type: "application/pdf" })
    )

    expect(await screen.findByDisplayValue("id")).toBeVisible()
    expect(screen.getByDisplayValue("degree")).toBeVisible()

    const row = screen.getByDisplayValue("id").closest("div.border-dashed") as HTMLElement
    await user().click(within(row).getByRole("button", { name: /remove/i }))

    await waitFor(() => expect(screen.queryByDisplayValue("id")).not.toBeInTheDocument())
    expect(screen.getByDisplayValue("degree")).toBeVisible()
  })

  it("rejects a file over the size limit", async () => {
    renderWizard()
    await goToDocuments()

    const huge = new File(["x"], "huge.pdf", { type: "application/pdf" })
    Object.defineProperty(huge, "size", { value: 11 * 1024 * 1024 })
    attach(huge)

    await waitFor(() => expect(toastError).toHaveBeenCalled())
    expect(screen.queryByDisplayValue("huge")).not.toBeInTheDocument()
  })
})

// ── Submit ───────────────────────────────────────────────────────────────

describe("submit", () => {
  async function goToReview() {
    await fillIdentity()
    await goToPositions()
    await pickJobTitle("Librarian")
    await pickHireDate()
    await clickNext() // → Qualifications
    await clickNext() // → Pay & schedule
    await clickNext() // → Documents
    await clickNext() // → Review
    await waitFor(() => expect(currentStep()).toBe("Review"))
  }

  function created(id = 42) {
    apiFetch.mockImplementation(async (path: string, options?: { method?: string }) => {
      if (path.startsWith("/employees/account-policy")) return { data: policy }
      if (path === "/employees" && options?.method === "POST") {
        return {
          data: {
            id,
            full_name: "Kalkidan",
            positions: [],
            qualifications: [],
          },
        }
      }
      if (path.startsWith("/employees/") && path.endsWith("/attachments")) return { data: {} }
      throw new Error(`unstubbed request: ${path} ${options?.method ?? ""}`)
    })
  }

  it("posts the employee and navigates to the new profile", async () => {
    policy = { account_job_titles: ["librarian"], required_job_titles: [] }
    renderWizard()
    await goToReview()
    created()

    await user().click(screen.getByRole("button", { name: "Add employee" }))

    await waitFor(() => expect(push).toHaveBeenCalledWith("/employees/42"))

    expect(apiFetch).toHaveBeenCalledWith("/employees", expect.objectContaining({ method: "POST" }))
    expect(sentBody("/employees")).toMatchObject({
      first_name: "Kalkidan",
      phone: "0911234567",
      positions: [expect.objectContaining({ job_title: "librarian", is_primary: true })],
    })
    expect(toastSuccess).toHaveBeenCalled()
  })

  it("sends the account choice only where the user actually had one", async () => {
    policy = { account_job_titles: ["librarian"], required_job_titles: [] }
    renderWizard()
    await fillIdentity()
    await goToPositions()
    await pickJobTitle("Librarian")
    await pickHireDate()

    // Opt out of the offered account.
    await user().click(await screen.findByRole("checkbox"))

    await clickNext()
    await clickNext()
    await clickNext()
    await clickNext()
    await waitFor(() => expect(currentStep()).toBe("Review"))
    created()

    await user().click(screen.getByRole("button", { name: "Add employee" }))

    await waitFor(() => expect(push).toHaveBeenCalled())
    expect(sentBody("/employees").create_user_account).toBe(false)
  })

  it("omits the account flag for a role-mapped title, which the server decides", async () => {
    policy = { account_job_titles: [], required_job_titles: ["registrar"] }
    renderWizard()
    await fillIdentity()
    await goToPositions()
    await pickJobTitle("Registrar")
    await pickHireDate()

    await clickNext()
    await clickNext()
    await clickNext()
    await clickNext()
    await waitFor(() => expect(currentStep()).toBe("Review"))
    created()

    await user().click(screen.getByRole("button", { name: "Add employee" }))

    await waitFor(() => expect(push).toHaveBeenCalled())
    expect(sentBody("/employees")).not.toHaveProperty("create_user_account")
  })

  it("uploads staged documents after the employee is created", async () => {
    policy = { account_job_titles: ["librarian"], required_job_titles: [] }
    renderWizard()
    await fillIdentity()
    await goToPositions()
    await pickJobTitle("Librarian")
    await pickHireDate()
    await clickNext()
    await clickNext()
    await clickNext()
    await waitFor(() => expect(currentStep()).toBe("Documents"))

    const input = document.querySelector<HTMLInputElement>('input[type="file"][accept*=".pdf"]')!
    fireEvent.change(input, {
      target: {
        files: [new File(["cv"], "contract.pdf", { type: "application/pdf" })],
      },
    })
    await screen.findByDisplayValue("contract")

    await clickNext()
    await waitFor(() => expect(currentStep()).toBe("Review"))
    created()

    await user().click(screen.getByRole("button", { name: "Add employee" }))

    await waitFor(() =>
      expect(apiFetch).toHaveBeenCalledWith(
        "/employees/42/attachments",
        expect.objectContaining({ method: "POST" })
      )
    )
    expect(push).toHaveBeenCalledWith("/employees/42")
  })

  it("keeps the user in the wizard and surfaces server field errors", async () => {
    policy = { account_job_titles: ["librarian"], required_job_titles: [] }
    const { ApiError } = await import("@/lib/api")
    renderWizard()
    await goToReview()

    apiFetch.mockImplementation(async (path: string, options?: { method?: string }) => {
      if (path.startsWith("/employees/account-policy")) return { data: policy }
      if (path === "/employees" && options?.method === "POST") {
        throw new ApiError("Validation failed", 422, {
          phone: ["This phone number is already registered."],
        })
      }
      throw new Error(`unstubbed request: ${path}`)
    })

    await user().click(screen.getByRole("button", { name: "Add employee" }))

    // The wizard jumps back to the step that owns the broken field.
    await waitFor(() => expect(currentStep()).toBe("Person"))
    expect(push).not.toHaveBeenCalled()
    expect(await screen.findByText("This phone number is already registered.")).toBeVisible()
  })
})

// ── Edit mode ────────────────────────────────────────────────────────────

describe("edit mode", () => {
  const employee = {
    id: 7,
    full_name: "Selam Bekele",
    first_name: "Selam",
    father_name: "Bekele",
    grandfather_name: "",
    phone: "0911234567",
    branch_id: 1,
    user_id: 99,
    user: { public_id: "U-123" },
    positions: [
      {
        id: 3,
        job_title: "librarian",
        employment_type: "full_time",
        hired_on: "2024-09-01",
        is_primary: true,
      },
    ],
    qualifications: [],
    allowances: [],
    deductions: [],
    teacher_subjects: [],
    attachments: [],
  } as unknown as Parameters<typeof EmployeeWizard>[0]["employee"]

  it("prefills the form and unlocks every step", async () => {
    renderWizard({ employee })

    expect(await screen.findByRole("heading", { name: "Selam Bekele" })).toBeInTheDocument()
    expect(firstNameInput()).toHaveValue("Selam")

    // Edit mode is non-linear: any step may be jumped to directly.
    const documents = screen.getByRole("button", { name: "Documents" })
    expect(documents).toBeEnabled()
    await user().click(documents)
    await waitFor(() => expect(currentStep()).toBe("Documents"))
  })

  it("has no review step and saves from wherever the user is", async () => {
    renderWizard({ employee })

    expect(screen.queryByRole("button", { name: "Review" })).not.toBeInTheDocument()

    apiFetch.mockImplementation(async (path: string, options?: { method?: string }) => {
      if (path.startsWith("/employees/account-policy")) return { data: policy }
      if (path === "/employees/7" && options?.method === "PUT") {
        return { data: { id: 7, positions: [], qualifications: [] } }
      }
      throw new Error(`unstubbed request: ${path}`)
    })

    await user().click(screen.getByRole("button", { name: "Save" }))

    await waitFor(() => expect(push).toHaveBeenCalledWith("/employees/7"))
  })

  it("reports an existing portal account instead of offering to create one", async () => {
    renderWizard({ employee })

    await user().click(screen.getByRole("button", { name: "Jobs & account" }))
    await waitFor(() => expect(currentStep()).toBe("Jobs & account"))

    expect(screen.getByText("Has a portal account")).toBeVisible()
    expect(screen.queryByRole("checkbox")).not.toBeInTheDocument()
  })
})
