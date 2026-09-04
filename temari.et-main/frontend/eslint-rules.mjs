/**
 * Temari invariant rules.
 *
 * These are the HARD rules from CLAUDE.md that a computer can check. A rule
 * written only in prose holds exactly as long as every future contributor (human
 * or agent) reads the whole document; a rule written here holds always.
 *
 * Each violation below was found in the codebase at least once before the rule
 * existed, so none of them are hypothetical.
 *
 * To add a rule: prefer a `no-restricted-syntax` selector in eslint.config.mjs
 * for anything expressible as an AST shape. Write a rule here only when it needs
 * file-level state (like `require-delete-confirmation` does).
 */

/**
 * PROJECT RULE: deleting data ALWAYS requires a confirmation dialog.
 *
 * Any module that issues a DELETE must also reference `confirmDelete`. This is
 * deliberately file-scoped rather than tracing each call to its own callback —
 * proving "this specific DELETE is inside a confirm callback" needs data-flow
 * analysis and breaks on indirection (a hook that deletes, confirmed by its
 * caller). File scope catches the regression that actually happens: someone
 * wires a DELETE straight to an onClick.
 *
 * When a module legitimately deletes without its own confirmation — because a
 * caller confirms it — disable the rule on that line WITH the reason.
 */
const requireDeleteConfirmation = {
  meta: {
    type: "problem",
    docs: {
      description:
        "DELETE requests must go through useConfirmDelete() — deleting data always requires confirmation",
    },
    schema: [],
    messages: {
      unconfirmed:
        "This module issues a DELETE but never references confirmDelete. Deleting data always requires a confirmation dialog — use useConfirmDelete() from @/components/ui/confirm-delete. If a caller confirms it, add an eslint-disable-next-line with that reason.",
    },
  },

  create(context) {
    const deleteCalls = []
    let mentionsConfirm = false

    return {
      // apiFetch(url, { method: "DELETE" })
      Property(node) {
        const key = node.key
        const name = key?.name ?? key?.value
        if (name !== "method") return
        if (node.value?.type !== "Literal") return
        if (String(node.value.value).toUpperCase() !== "DELETE") return
        deleteCalls.push(node)
      },

      Identifier(node) {
        if (node.name === "confirmDelete" || node.name === "useConfirmDelete") {
          mentionsConfirm = true
        }
      },

      "Program:exit"() {
        if (mentionsConfirm) return
        for (const node of deleteCalls) {
          context.report({ node, messageId: "unconfirmed" })
        }
      },
    }
  },
}

export default {
  rules: {
    "require-delete-confirmation": requireDeleteConfirmation,
  },
}
