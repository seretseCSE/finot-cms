# ADR-017 — Enrollment-Bounded Custody: What a Former School May See and Do

Status: **accepted** (July 2026, approved by Abdul)

## Problem

Students and parents are GLOBAL persons (ADR-011); staff authority over them flows
through scopes derived from enrollments. The original `Student::adminScopes()` treated
**every enrollment ever** as a live authority grant: after a transfer, the sending school
could still edit the student, see and even delete documents uploaded by the receiving
school, change health data and guardian links — forever. That is a privacy failure and
contradicts how every serious SIS bounds "legitimate educational interest" to the
enrollment window (PowerSchool, Infinite Campus; FERPA's model).

## Decision

Authority over a global person is split into two tiers:

1. **Live custody — `Student::activeAdminScopes()`** (mirrored by
   `ParentProfile::activeAdminScopes()`): the only scopes that may MUTATE the record
   (profile, photo, documents, health data, guardian links, new enrollments).
   Custody resolution, in order:
   - every branch holding a `pending`/`active` enrollment;
   - else the branch of the **most recent** enrollment (a withdrawn/graduated/mid-rollover
     student stays with the last school to hold them);
   - else (never enrolled) the registration branch;
   - else (true B2C learner) platform staff only.

2. **Archive view — `adminScopes()`** (unchanged set): every branch the student ever
   touched keeps `students.view`, served ERA-BOUNDED for archive-only viewers: the
   **handover snapshot** (`student_transfer_requests.handover_snapshot`, JSONB frozen by
   `App\Services\StudentHandoverSnapshot` inside `ApproveTransferAction` BEFORE the
   enrollment closes) carries the file exactly as the student left — address/contact,
   health (blood type, notes, conditions), guardians (GuardianResource-shaped, served by
   the guardians endpoint with `meta.access: "archive"`), and the ids of documents on
   file (the detail endpoint filters live attachment rows to those ids). Own-school
   enrollment rows only, no portal account, never the receiving school's live enrollment
   (list rows swap in the viewer's own closed enrollment and are flagged
   `access: "archive"`). `Student::archiveSnapshotFor()` picks the viewer's snapshot.

Consequences baked in:

- **Documents travel forward, never backward.** The receiving school sees everything the
  sending school collected (a core advantage of digital transfers); the sending school
  sees nothing added after the handover. `student_attachments`/`parent_attachments`
  carry provenance (`school_id`, `branch_id`, `uploaded_by`) — the UI shows
  "Added by {branch}".
- Guardian data is live family data: `viewGuardians`/`manageGuardians` and parent file
  management require live custody of a linked child; a former school gets the bare
  parent profile only.
- The former school's OWN era is untouched: branch-scoped rows (term results, invoices,
  payments, attendance, activity logs) remain theirs, and transcripts/report cards stay
  printable — that is their legal archive.
- **Document retention** (the "photocopy" guarantee, mirroring how per-district SIS
  storage makes the sending school's copy physically untouchable): student attachments
  are soft-deletable; a document referenced by ANY handover snapshot is only ever
  HIDDEN from the live file (row + R2 object survive; era archives read `withTrashed`),
  while unreferenced documents still hard-delete with their storage. And provenance
  guards deletion: only the school whose `school_id` stamp is on a document (or
  platform staff) may remove it — a custody school cannot destroy another school's
  certified copies (same rule on parent attachments; frontend hides the delete button
  for other-school files).
- **Transfer files stay with the PARTICIPANTS**: a transfer request's supporting
  documents surface on the student detail (`transfer_files`) for the sender and receiver
  of that request only — never for unrelated or future schools. Uploads take a per-file
  display name (`documents[i][file]` + `documents[i][name]`).
- **Returning students** (A → B → A): custody follows the live enrollment, so school A
  automatically regains the full live record — including everything collected at B —
  and B drops to the archive of ITS handover snapshot.
- Employees were already correct (per-branch HR files) — no change.

## Guard rail

`tests/Feature/PostTransferAccessTest.php` — old-school write lockout (profile,
documents, photo, guardians), forward-blind archive payloads, list masking, custody
fallback for withdrawn students, parent-file custody. `CrossTenantIsolationTest`
continues to cover never-linked schools.

## Frontend

`students/[id]` reads `data.access`: archive mode shows a read-only banner ("File
frozen as of {date}") and renders ALL tabs read-only from the snapshot — Address/Health
overlay `data.archive.profile`/`.health`, Guardians read the frozen endpoint payload,
Documents show the era-filtered attachments plus the "Transfer files" group
(`data.transfer_files`). Every edit affordance is hidden; the register shows a
"Transferred out" badge and hides enroll/delete row actions. In-app docs: Transfers →
"What your school keeps after a student leaves" (en/am/om).
