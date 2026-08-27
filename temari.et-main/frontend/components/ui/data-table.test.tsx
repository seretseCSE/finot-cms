import { fireEvent, render, screen, within } from "@testing-library/react"
import { beforeEach, describe, expect, it, vi } from "vitest"

import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { I18nProvider } from "@/lib/i18n"
import { __resetPageSizeCache } from "@/lib/use-page-size"

/**
 * Behavioural tests for the table footer every list in the app shares.
 *
 * Paging is the one DataTable behaviour that silently changes what a user can
 * see and act on, so the rules asserted here — how many rows render, what
 * "select all" promises, and that the rows-per-page choice is remembered — hold
 * for every register at once.
 */

interface Row extends Record<string, unknown> {
  id: number
  name: string
}

const rows = (count: number): Row[] =>
  Array.from({ length: count }, (_, i) => ({ id: i + 1, name: `Student ${i + 1}` }))

const columns: DataTableColumn<Row>[] = [{ key: "name", label: "Name" }]

function renderTable(props: Partial<React.ComponentProps<typeof DataTable<Row>>> = {}) {
  return render(
    <I18nProvider>
      <DataTable columns={columns} data={rows(60)} {...props} />
    </I18nProvider>,
  )
}

/** Desktop table cells only — the mobile card layout renders the same rows a
 *  second time, and jsdom applies no CSS, so both layouts are in the DOM. */
const renderedNames = () =>
  Array.from(document.querySelectorAll('[data-slot="table-cell"]'))
    .map((cell) => cell.textContent ?? "")
    .filter((text) => text.startsWith("Student "))

/**
 * The header "select all" box lives in the desktop table, which the jsdom
 * setup shim hides (`.hidden { display: none }` stands in for Tailwind's
 * responsive classes). Reach it explicitly rather than exercising the mobile
 * card layout, which has no select-all at all.
 */
function selectAllOnPage() {
  fireEvent.click(screen.getAllByRole("checkbox", { name: "Select all", hidden: true })[0])
}

function openPageSizePicker() {
  fireEvent.click(screen.getByRole("button", { name: "Rows per page" }))
}

beforeEach(() => {
  window.localStorage.clear()
  __resetPageSizeCache()
})

describe("client-side paging", () => {
  it("renders only the first page — a long register costs 25 rows of DOM, not 60", () => {
    renderTable()

    const names = renderedNames()
    expect(names).toHaveLength(25)
    expect(names[0]).toBe("Student 1")
    expect(names.at(-1)).toBe("Student 25")
  })

  it("counts every matching row in the footer range, not just the rendered ones", () => {
    renderTable()

    expect(screen.getByText("1–25 of 60")).toBeInTheDocument()
  })

  it("walks to the next page", () => {
    renderTable()

    fireEvent.click(screen.getByRole("button", { name: "Next page" }))

    expect(renderedNames()[0]).toBe("Student 26")
    expect(screen.getByText("26–50 of 60")).toBeInTheDocument()
  })

  it("stays out of the way when everything already fits", () => {
    renderTable({ data: rows(12) })

    expect(screen.queryByRole("button", { name: "Rows per page" })).not.toBeInTheDocument()
    expect(screen.queryByRole("button", { name: "Next page" })).not.toBeInTheDocument()
    expect(renderedNames()).toHaveLength(12)
  })

  it("never pages a table that opts out", () => {
    renderTable({ paginated: false })

    expect(renderedNames()).toHaveLength(60)
    expect(screen.queryByRole("button", { name: "Next page" })).not.toBeInTheDocument()
  })

  it("returns to page 1 when the search narrows the result set", () => {
    renderTable({ searchKeys: ["name"], searchable: true })

    fireEvent.click(screen.getByRole("button", { name: "Next page" }))
    expect(renderedNames()[0]).toBe("Student 26")

    fireEvent.change(screen.getByPlaceholderText("Search…"), { target: { value: "Student 1" } })

    // Student 1, 10–19, 100+ → one page again, from the top.
    expect(renderedNames()[0]).toBe("Student 1")
    expect(screen.queryByRole("button", { name: "Next page" })).not.toBeInTheDocument()
  })
})

describe("the rows-per-page picker", () => {
  it("offers 25 / 50 / 75 / 100 — never more than the API will serve", () => {
    renderTable()
    openPageSizePicker()

    const options = ["25 rows", "50 rows", "75 rows", "100 rows"]
    for (const option of options) {
      expect(screen.getByRole("button", { name: new RegExp(`^${option}$`) })).toBeInTheDocument()
    }
    expect(screen.queryByRole("button", { name: /200 rows/ })).not.toBeInTheDocument()
  })

  it("shows more rows once a bigger size is picked", () => {
    renderTable()
    openPageSizePicker()
    fireEvent.click(screen.getByRole("button", { name: "100 rows" }))

    expect(renderedNames()).toHaveLength(60)
    expect(screen.getByText("1–60 of 60")).toBeInTheDocument()
  })

  it("remembers the choice for the next table the user opens", () => {
    const first = renderTable()
    openPageSizePicker()
    fireEvent.click(screen.getByRole("button", { name: "50 rows" }))
    first.unmount()

    renderTable()

    expect(renderedNames()).toHaveLength(50)
  })

  it("appears on a single-page table that could still be split smaller", () => {
    window.localStorage.setItem("temari:rows-per-page", "100")
    __resetPageSizeCache()
    renderTable({ data: rows(40) })

    expect(renderedNames()).toHaveLength(40)
    expect(screen.getByRole("button", { name: "Rows per page" })).toBeInTheDocument()
    expect(screen.queryByRole("button", { name: "Next page" })).not.toBeInTheDocument()
  })
})

describe("selection across pages", () => {
  it("selects the page the user is looking at, never rows they cannot see", () => {
    const onBulk = vi.fn()
    renderTable({
      bulkActions: [{ label: "Archive", onClick: onBulk }],
    })

    selectAllOnPage()
    fireEvent.click(screen.getByRole("button", { name: "Archive" }))

    expect(onBulk).toHaveBeenCalledTimes(1)
    expect(onBulk.mock.calls[0][0]).toHaveLength(25)
    expect(onBulk.mock.calls[0][0][0].name).toBe("Student 1")
  })

  it("clears a stale selection when the page changes", () => {
    renderTable({ bulkActions: [{ label: "Archive", onClick: vi.fn() }] })

    selectAllOnPage()
    expect(screen.getByText("25 selected")).toBeInTheDocument()

    fireEvent.click(screen.getByRole("button", { name: "Next page" }))

    expect(screen.queryByText(/selected/)).not.toBeInTheDocument()
  })
})

describe("server-driven paging", () => {
  const pagination = {
    page: 2,
    pageCount: 4,
    total: 97,
    from: 26,
    to: 50,
    onPageChange: vi.fn(),
    pageSize: 25,
    onPageSizeChange: vi.fn(),
  }

  it("renders exactly the rows the API returned — no second slice on top", () => {
    renderTable({ serverMode: true, data: rows(25), pagination })

    expect(renderedNames()).toHaveLength(25)
    expect(screen.getByText("26–50 of 97")).toBeInTheDocument()
  })

  it("hands the picked size back to the page instead of paging locally", () => {
    renderTable({ serverMode: true, data: rows(25), pagination })
    openPageSizePicker()
    fireEvent.click(screen.getByRole("button", { name: "100 rows" }))

    expect(pagination.onPageSizeChange).toHaveBeenCalledWith(100)
  })

  it("keeps the picker out of a table whose page never wired one up", () => {
    const { onPageSizeChange, pageSize, ...withoutPicker } = pagination
    void onPageSizeChange
    void pageSize
    renderTable({ serverMode: true, data: rows(25), pagination: withoutPicker })

    expect(screen.queryByRole("button", { name: "Rows per page" })).not.toBeInTheDocument()
    expect(within(screen.getByText("26–50 of 97")).queryByRole("button")).toBeNull()
  })
})
