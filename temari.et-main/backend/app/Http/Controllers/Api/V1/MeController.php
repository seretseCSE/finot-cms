<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\VerifyInvoicePaymentAction;
use App\Enums\AbsenceExcuseStatus;
use App\Enums\AcademicYearStatus;
use App\Enums\AssignmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Bank;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\StudentTermResult;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Services\Notify\Notifier;
use App\Services\RecurringBillingService;
use App\Services\Reports\FamilyCalendarService;
use App\Services\Reports\StudentReportService;
use App\Support\Ethiopia;
use App\Support\Languages;
use App\Support\NotificationCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * THE RELATIONSHIP LANE (ADR-012). Access here is derived from relationships —
 * a guardian link, being the student — never from memberships, roles or
 * context headers. It is a separate namespace from the staff endpoints so
 * admin queries and self queries never share a code path:
 *
 *  - a parent sees exactly their linked children, gated per-link by the
 *    student_guardians permission flags (can_view_grades / attendance / fees);
 *  - a student (linked user) sees their own record;
 *  - a school role at ANY school grants nothing here, and vice versa.
 */
class MeController extends Controller
{
    /**
     * The children linked to the authenticated user as a guardian, with the
     * per-link permission flags and each child's current enrollment.
     */
    public function children(Request $request): JsonResponse
    {
        $links = $this->guardianLinks($request)
            ->with([
                'student.currentEnrollment.section',
                'student.currentEnrollment.gradeLevel',
                'student.currentEnrollment.branch.school',
                'student.currentEnrollment.academicYear',
            ])
            ->get();

        // One aggregate query for the dashboard's outstanding-fees chips.
        $unpaid = Invoice::query()
            ->whereIn('student_id', $links->pluck('student_id'))
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->selectRaw('student_id, count(*) as open_count')
            ->groupBy('student_id')
            ->pluck('open_count', 'student_id');

        $data = $links->map(function (StudentGuardian $link) use ($unpaid): array {
            $student = $link->student;
            $enrollment = $student?->currentEnrollment;

            return [
                'student_id' => $student?->id,
                'full_name' => $student?->full_name,
                'public_id' => $student?->public_id,
                'photo_url' => $student?->photo_url,
                'gender' => $student?->gender,
                'unpaid_invoices' => $link->can_pay_fees ? (int) ($unpaid[$student?->id] ?? 0) : null,
                'relationship' => $link->relationship,
                'is_primary' => $link->is_primary,
                'permissions' => [
                    'can_view_grades' => $link->can_view_grades,
                    'can_view_attendance' => $link->can_view_attendance,
                    'can_pay_fees' => $link->can_pay_fees,
                ],
                'current_enrollment' => $enrollment ? [
                    'school' => $enrollment->branch?->school?->name,
                    'branch' => $enrollment->branch?->name,
                    // How this child's school writes dates/times — drives the
                    // family portal's calendar & clock display.
                    'calendar_mode' => $enrollment->branch?->effectiveCalendarMode() ?? 'ethiopian',
                    'clock_mode' => $enrollment->branch?->effectiveClockMode() ?? 'ethiopian',
                    'grade_level' => $enrollment->gradeLevel?->name,
                    'section' => $enrollment->section?->name,
                    'academic_year' => $enrollment->academicYear?->name,
                    'status' => $enrollment->status,
                    'terms' => $enrollment->academicYear?->terms()
                        ->orderBy('sequence')
                        ->get(['id', 'name', 'sequence', 'is_current', 'status']),
                ] : null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /** Result card for a linked child (requires the can_view_grades flag). */
    public function childResultCard(Request $request, Student $student, StudentReportService $reports): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_view_grades, 403, 'You are not permitted to view this student\'s grades.');

        return response()->json(['data' => $reports->resultCard($student, $request->integer('term_id'))]);
    }

    /** Frozen report-card index for a linked child (requires can_view_grades). */
    public function childReportCards(Request $request, Student $student, StudentReportService $reports): JsonResponse
    {
        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_view_grades, 403, 'You are not permitted to view this student\'s grades.');

        return response()->json(['data' => $reports->reportCardIndex($student)]);
    }

    /** Own frozen report-card index (student accounts). */
    public function ownReportCards(Request $request, StudentReportService $reports): JsonResponse
    {
        $student = Student::where('user_id', $request->user()->id)->first();
        abort_if($student === null, 404, 'No student record is linked to your account.');

        return response()->json(['data' => $reports->reportCardIndex($student)]);
    }

    /** Official frozen report card for a linked child (requires can_view_grades). */
    public function childReportCard(Request $request, Student $student, StudentReportService $reports): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_view_grades, 403, 'You are not permitted to view this student\'s grades.');

        return response()->json(['data' => $reports->reportCard($student, $request->integer('term_id'))]);
    }

    /** Multi-year transcript for a linked child (requires can_view_grades). */
    public function childTranscript(Request $request, Student $student, StudentReportService $reports): JsonResponse
    {
        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_view_grades, 403, 'You are not permitted to view this student\'s grades.');

        return response()->json(['data' => $reports->transcript($student, $this->transcriptYearIds($request))]);
    }

    /**
     * Optional `academic_year_ids[]` narrowing shared by the two /me
     * transcript lanes (partial transcripts, stamped as such).
     *
     * @return list<int>|null
     */
    private function transcriptYearIds(Request $request): ?array
    {
        $data = $request->validate([
            'academic_year_ids' => ['sometimes', 'array', 'min:1'],
            'academic_year_ids.*' => ['integer'],
        ]);

        return isset($data['academic_year_ids'])
            ? array_map('intval', $data['academic_year_ids'])
            : null;
    }

    /** Attendance summary for a linked child (requires can_view_attendance). */
    public function childAttendanceSummary(Request $request, Student $student, StudentReportService $reports): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_view_attendance, 403, 'You are not permitted to view this student\'s attendance.');

        return response()->json(['data' => $reports->attendanceSummary($student, $request->integer('term_id'))]);
    }

    /**
     * Day-by-day attendance for a linked child, one month at a time — status
     * plus the check-in/out times a device or teacher recorded. Check-out is
     * deliberately app-only (never texted): parents see "left school at 3:42"
     * here, not by SMS.
     */
    public function childAttendance(Request $request, Student $student): JsonResponse
    {
        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_view_attendance, 403, 'You are not permitted to view this student\'s attendance.');

        return response()->json($this->attendancePayload($request, $student));
    }

    /** Own day-by-day attendance (self lane). */
    public function ownAttendance(Request $request): JsonResponse
    {
        $student = Student::where('user_id', $request->user()->id)->first();
        abort_if($student === null, 404, 'No student record is linked to your account.');

        return response()->json($this->attendancePayload($request, $student));
    }

    /**
     * @return array<string, mixed>
     */
    private function attendancePayload(Request $request, Student $student): array
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $month = $request->input('month') ?: now(Ethiopia::TIMEZONE)->format('Y-m');
        $from = "{$month}-01";
        $to = date('Y-m-t', strtotime($from));

        $records = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->whereBetween('date', [$from, $to])
            ->orderByDesc('date')
            ->get()
            ->map(fn (AttendanceRecord $r): array => [
                'date' => $r->date,
                'status' => $r->status->value,
                'check_in' => $r->check_in ? substr((string) $r->check_in, 0, 5) : null,
                'check_out' => $r->check_out ? substr((string) $r->check_out, 0, 5) : null,
                'note' => $r->note,
            ]);

        return [
            'data' => $records,
            'meta' => [
                'month' => $month,
                'counts' => $records->countBy('status'),
                'summary' => $this->attendanceSummary($student),
            ],
        ];
    }

    /**
     * Year-to-date vitals for the family attendance header: status counts and
     * attended rate over the current academic year (falling back to all
     * history when no live enrollment anchors a year), plus the streak of
     * consecutive marked days without an absence. The streak spans month
     * boundaries, so it is computed here — a single month slice on the client
     * can't see it. Two small queries on the (student_id, …) index.
     *
     * @return array<string, mixed>
     */
    private function attendanceSummary(Student $student): array
    {
        $today = Ethiopia::today();
        $yearStart = $student->currentEnrollment?->academicYear?->starts_on?->toDateString();

        $counts = AttendanceRecord::query()
            ->toBase()
            ->where('student_id', $student->id)
            ->whereNull('deleted_at')
            ->when($yearStart !== null, fn ($q) => $q->where('date', '>=', $yearStart))
            ->where('date', '<=', $today)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = (int) $counts->sum();
        $attended = (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0);

        // Bounded walk backwards from the latest marked day — a school year
        // has at most ~220 school days, so one page of dates covers it.
        $recent = AttendanceRecord::query()
            ->toBase()
            ->where('student_id', $student->id)
            ->whereNull('deleted_at')
            ->where('date', '<=', $today)
            ->orderByDesc('date')
            ->limit(240)
            ->pluck('status', 'date');

        $streak = 0;
        foreach ($recent as $status) {
            if ($status === 'absent') {
                break;
            }
            $streak++;
        }

        return [
            'from' => $yearStart,
            'total' => $total,
            'present' => (int) ($counts['present'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'excused' => (int) ($counts['excused'] ?? 0),
            'rate' => $total > 0 ? (int) round(($attended / $total) * 100) : null,
            'streak' => $streak,
        ];
    }

    /**
     * ONE aggregated status payload for a child's home tile grid — attendance
     * pulse, latest frozen result, open fees and due classwork, each section
     * nulled when the guardian link doesn't allow it. One request instead of
     * four keeps the parent home fast on 3G (ADR-012).
     */
    public function childHome(Request $request, Student $student): JsonResponse
    {
        $link = $this->guardianLinkFor($request, $student);

        // ── Attendance pulse ──
        $attendance = $link->can_view_attendance ? $this->attendanceSummary($student) : null;

        // ── Latest frozen result ──
        $results = null;
        if ($link->can_view_grades) {
            $latest = StudentTermResult::query()
                ->where('student_id', $student->id)
                ->with(['term:id,name,sequence', 'academicYear:id,name,starts_on'])
                ->get()
                ->sortByDesc([
                    fn ($r) => $r->academicYear?->starts_on?->timestamp ?? 0,
                    fn ($r) => $r->term?->sequence ?? 0,
                ])
                ->first();

            $results = $latest === null ? ['latest' => null] : ['latest' => [
                'term_id' => $latest->term_id,
                'term_name' => $latest->term?->name,
                'average' => $latest->average !== null ? (float) $latest->average : null,
                'rank' => $latest->rank,
                'rank_of' => $latest->rank_of,
            ]];
        }

        // ── Open fees ──
        $fees = null;
        if ($link->can_pay_fees) {
            $open = Invoice::query()
                ->where('student_id', $student->id)
                ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
                ->get(['id', 'amount', 'amount_paid', 'discount_type', 'discount_value', 'penalty_amount', 'due_date', 'status']);

            $fees = [
                'open_count' => $open->count(),
                'open_balance' => number_format($open->sum(fn (Invoice $i) => (float) $i->balance), 2, '.', ''),
                'next_due_date' => $open->pluck('due_date')->filter()->min()?->toDateString(),
                'overdue_count' => $open->filter(fn (Invoice $i) => $i->due_date !== null && $i->due_date->isPast())->count(),
            ];
        }

        // ── Due classwork (basic link access) ──
        $sectionIds = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereNotNull('section_id')
            ->pluck('section_id');

        $dueCount = 0;
        if ($sectionIds->isNotEmpty()) {
            $anchorIds = SubjectAssignment::query()
                ->whereIn('section_id', $sectionIds)
                ->pluck('id');

            $dueCount = Assignment::query()
                ->whereIn('subject_assignment_id', $anchorIds)
                ->where('status', AssignmentStatus::Published->value)
                ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now()))
                ->visibleToStudent($student->id)
                ->whereDoesntHave('submissions', fn ($q) => $q->where('student_id', $student->id))
                ->count();
        }

        return response()->json(['data' => [
            'attendance' => $attendance,
            'results' => $results,
            'fees' => $fees,
            'classwork' => ['due_count' => $dueCount],
        ]]);
    }

    /** Absence excuses the family filed for a linked child. */
    public function childAbsenceExcuses(Request $request, Student $student): JsonResponse
    {
        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_view_attendance, 403, 'You are not permitted to view this student\'s attendance.');

        $excuses = AbsenceExcuse::query()
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $excuses->map(fn (AbsenceExcuse $excuse): array => [
                'id' => $excuse->id,
                'starts_on' => $excuse->starts_on->toDateString(),
                'ends_on' => $excuse->ends_on->toDateString(),
                'reason' => $excuse->reason,
                'status' => $excuse->status->value,
                'decision_note' => $excuse->decision_note,
                'decided_at' => $excuse->decided_at?->toISOString(),
                'created_at' => $excuse->created_at?->toISOString(),
                'has_attachment' => $excuse->attachment_path !== null,
            ]),
        ]);
    }

    /**
     * File an absence excuse for a linked child: a date range, the reason and
     * an optional proof document. Lands as PENDING for the branch to decide —
     * approval retro-marks the range's absences as excused.
     */
    public function storeChildAbsenceExcuse(Request $request, Student $student, Notifier $notifier): JsonResponse
    {
        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_view_attendance, 403, 'You are not permitted to manage this student\'s attendance.');

        $data = $request->validate([
            'starts_on' => ['required', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on', 'before_or_equal:'.date('Y-m-d', strtotime('+30 days'))],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value])
            ->latest('academic_year_id')
            ->first();

        abort_if($enrollment === null, 422, 'This student has no live enrollment to file an excuse against.');

        $attachmentPath = $request->hasFile('attachment')
            ? $request->file('attachment')->store(
                "absence-excuses/{$student->id}",
                ['disk' => config('filesystems.default')],
            )
            : null;

        $excuse = AbsenceExcuse::create([
            'school_id' => $enrollment->school_id,
            'branch_id' => $enrollment->branch_id,
            'student_id' => $student->id,
            'requested_by' => $request->user()->id,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'reason' => $data['reason'],
            'attachment_path' => $attachmentPath,
            'status' => AbsenceExcuseStatus::Pending->value,
        ]);

        $notifier->toStaff($excuse->school_id, $excuse->branch_id, 'attendance.record', 'attendance.excuse_filed', [
            'student' => (string) $student->full_name,
            'from' => $excuse->starts_on->toDateString(),
            'to' => $excuse->ends_on->toDateString(),
        ], [
            'link' => '/attendance/excuses',
            'dedupeKey' => "excuse_filed:{$excuse->id}",
        ]);

        return response()->json([
            'data' => ['id' => $excuse->id, 'status' => $excuse->status->value],
            'message' => 'Excuse submitted — the school will review it.',
        ], 201);
    }

    /** Invoices for a linked child (requires can_pay_fees). */
    public function childInvoices(Request $request, Student $student): JsonResponse
    {
        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_pay_fees, 403, 'You are not permitted to view this student\'s fees.');

        $invoices = Invoice::query()
            ->where('student_id', $student->id)
            ->with([
                'verifications' => fn ($q) => $q->latest()->limit(5),
                'academicYear:id,name',
                'term:id,name',
                // Where the school expects the money to land — shown to the
                // family so they pay into the right account.
                'feeStructure.bankAccounts.bank:id,code,name,type,logo',
            ])
            ->orderByDesc('created_at')
            ->get([
                'id', 'title', 'amount', 'amount_paid',
                'discount_type', 'discount_value', 'scholarship_reason',
                'penalty_amount', 'penalty_waived', 'billing_year', 'billing_month',
                'status', 'due_date', 'academic_year_id', 'term_id',
                'fee_structure_id', 'created_at',
            ]);

        return response()->json([
            'data' => $invoices->map(fn (Invoice $invoice): array => [
                ...$invoice->makeHidden(['verifications', 'academicYear', 'term', 'feeStructure'])->toArray(),
                'number' => sprintf('INV-%06d', $invoice->id),
                'net_amount' => number_format($invoice->netAmount(), 2, '.', ''),
                'total_due' => number_format($invoice->totalDue(), 2, '.', ''),
                'balance' => $invoice->balance,
                'is_overdue' => $invoice->due_date !== null
                    && $invoice->due_date->isPast()
                    && in_array($invoice->status->value, ['unpaid', 'partial'], true),
                'academic_year_name' => $invoice->academicYear?->name,
                'term_name' => $invoice->term?->name,
                'collection_accounts' => ($invoice->feeStructure?->bankAccounts ?? collect())
                    ->map(fn ($account): array => [
                        'id' => $account->id,
                        'account_name' => $account->account_name,
                        'account_number' => $account->account_number,
                        'bank_name' => $account->bank?->name,
                        'bank_code' => $account->bank?->code,
                        'bank_type' => $account->bank?->type,
                        'bank_logo' => $account->bank?->logo,
                    ])->values(),
                'verifications' => $invoice->verifications->map(fn ($verification): array => [
                    'id' => $verification->id,
                    'status' => $verification->status->value,
                    'failure_reason' => $verification->failure_reason,
                    'bank_code' => $verification->bank_code,
                    'transaction_number' => $verification->transaction_number,
                    'created_at' => $verification->created_at,
                ])->values(),
            ]),
        ]);
    }

    /**
     * Payment HISTORY for a linked child (requires can_pay_fees): every
     * recorded payment, newest first, with its QR receipt token — the family
     * re-downloads any receipt without asking the school.
     */
    public function childPayments(Request $request, Student $student): JsonResponse
    {
        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_pay_fees, 403, 'You are not permitted to view this student\'s fees.');

        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->with([
                'invoice:id,title',
                'bankAccount:id,bank_id,account_name,account_number',
                'bankAccount.bank:id,code,name,type,logo',
            ])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get([
                'id', 'invoice_id', 'amount', 'method', 'bank_account_id',
                'reference', 'receipt_number', 'receipt_token', 'paid_at', 'note',
            ]);

        return response()->json([
            'data' => $payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'invoice_title' => $payment->invoice?->title,
                'amount' => $payment->amount,
                'method' => $payment->method?->value,
                'reference' => $payment->reference,
                'receipt_number' => $payment->receipt_number,
                'receipt_token' => $payment->receipt_token,
                'paid_at' => $payment->paid_at?->toDateString(),
                'bank_name' => $payment->bankAccount?->bank?->name,
                'bank_logo' => $payment->bankAccount?->bank?->logo,
                'account_number' => $payment->bankAccount?->account_number,
            ]),
        ]);
    }

    /**
     * UPCOMING recurring charges for a linked child (requires can_pay_fees):
     * the next Ethiopian-month billing periods of every auto-generating fee
     * that applies to the child's live enrollment — so the family can plan
     * before the invoice even exists. Amounts are the fee's base amount;
     * standing concessions apply when the real invoice is issued.
     */
    public function childUpcomingFees(
        Request $request,
        Student $student,
        RecurringBillingService $billing,
    ): JsonResponse {
        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_pay_fees, 403, 'You are not permitted to view this student\'s fees.');

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value])
            ->whereHas('academicYear', fn ($q) => $q->where('status', AcademicYearStatus::Active->value))
            ->latest('academic_year_id')
            ->first();

        if ($enrollment === null) {
            return response()->json(['data' => []]);
        }

        $fees = FeeStructure::query()
            ->where('branch_id', $enrollment->branch_id)
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->where('is_active', true)
            ->where('auto_generate', true)
            ->whereIn('type', ['monthly', 'quarterly'])
            ->where(fn ($q) => $q
                ->whereDoesntHave('gradeLevels')
                ->orWhereHas('gradeLevels', fn ($qq) => $qq->where('grade_levels.id', $enrollment->grade_level_id)))
            ->with('academicYear')
            ->get();

        $today = CarbonImmutable::parse(Ethiopia::today());

        $rows = $fees
            ->flatMap(fn (FeeStructure $fee) => collect($billing->upcomingPeriods($fee, $today))
                ->map(fn ($period): array => [
                    'fee_structure_id' => $fee->id,
                    'fee' => $fee->name,
                    'type' => $fee->type->value,
                    'period' => $period->label,
                    'due_date' => $period->due->toDateString(),
                    'amount' => (string) $fee->amount,
                ]))
            ->sortBy('due_date')
            ->values()
            ->take(12);

        return response()->json(['data' => $rows]);
    }

    /**
     * The platform bank/wallet catalog (names + logos) for the family lane —
     * lets the payment-submission form show recognisable bank branding.
     */
    public function banks(): JsonResponse
    {
        $banks = Bank::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'logo']);

        return response()->json(['data' => $banks]);
    }

    /**
     * Verify a payment proof for a linked child's invoice against bank
     * records (check.et) — bank + transaction number, a receipt link, or an
     * uploaded receipt. A clean verification posts the payment immediately.
     */
    public function verifyChildInvoicePayment(
        Request $request,
        Student $student,
        Invoice $invoice,
        VerifyInvoicePaymentAction $action,
    ): JsonResponse {
        $link = $this->guardianLinkFor($request, $student);
        abort_unless($link->can_pay_fees, 403, 'You are not permitted to pay this student\'s fees.');
        abort_unless($invoice->student_id === $student->id, 404);

        $data = $request->validate([
            'bank' => ['nullable', 'string', 'max:30', 'required_with:transaction_number'],
            'transaction_number' => ['nullable', 'string', 'max:100', 'required_without_all:receipt_url,receipt_file'],
            'receipt_url' => ['nullable', 'url', 'max:2048'],
            'receipt_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        // Keep a private copy of the uploaded receipt for the audit trail.
        $receiptPath = $request->hasFile('receipt_file')
            ? $request->file('receipt_file')->store(
                "payment-receipts/{$invoice->id}",
                ['disk' => config('filesystems.default')],
            )
            : null;

        $verification = $action->execute($invoice, [
            'bank' => $data['bank'] ?? null,
            'transaction_number' => $data['transaction_number'] ?? null,
            'receipt_url' => $data['receipt_url'] ?? null,
            'receipt_file' => $request->file('receipt_file'),
            'receipt_path' => $receiptPath,
        ], $request->user());

        $invoice->refresh();

        return response()->json([
            'data' => [
                'id' => $verification->id,
                'status' => $verification->status->value,
                'failure_reason' => $verification->failure_reason,
                'amount' => $verification->payment?->amount,
                'invoice' => [
                    'id' => $invoice->id,
                    'status' => $invoice->status->value,
                    'amount_paid' => $invoice->amount_paid,
                    'net_amount' => number_format($invoice->netAmount(), 2, '.', ''),
                    'balance' => $invoice->balance,
                ],
            ],
        ], 201);
    }

    /**
     * The authenticated user's OWN student record (self lane), with enrollment
     * history — for students old enough to have their own login.
     */
    public function student(Request $request): JsonResponse
    {
        $student = Student::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'enrollments.section', 'enrollments.gradeLevel',
                'enrollments.academicYear', 'enrollments.branch.school',
            ])
            ->first();

        abort_if($student === null, 404, 'No student record is linked to your account.');

        // Terms of the live enrollment's year — drives the marks term picker.
        $activeEnrollment = $student->enrollments
            ->sortByDesc('academic_year_id')
            ->first(fn ($e) => in_array($e->status->value, ['active', 'pending'], true))
            ?? $student->enrollments->sortByDesc('academic_year_id')->first();

        return response()->json([
            'data' => [
                'student_id' => $student->id,
                'full_name' => $student->full_name,
                'public_id' => $student->public_id,
                'photo_url' => $student->photo_url,
                'gender' => $student->gender,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'terms' => $activeEnrollment?->academicYear?->terms()
                    ->orderBy('sequence')
                    ->get(['id', 'name', 'sequence', 'is_current', 'status']),
                'enrollments' => $student->enrollments->map(fn ($e): array => [
                    'id' => $e->id,
                    'school' => $e->branch?->school?->name,
                    'branch' => $e->branch?->name,
                    'grade_level' => $e->gradeLevel?->name,
                    'section' => $e->section?->name,
                    'academic_year' => $e->academicYear?->name,
                    'status' => $e->status,
                ])->values(),
            ],
        ]);
    }

    /** Weekly timetable for a linked child (basic link access). */
    public function childTimetable(Request $request, Student $student, StudentReportService $reports): JsonResponse
    {
        $this->guardianLinkFor($request, $student);

        return response()->json(['data' => $reports->timetable($student)]);
    }

    /** Upcoming school agenda for a linked child (basic link access). */
    public function childCalendar(Request $request, Student $student, FamilyCalendarService $calendar): JsonResponse
    {
        $this->guardianLinkFor($request, $student);

        return response()->json(['data' => $calendar->agenda($student)]);
    }

    /** Own upcoming school agenda (self lane). */
    public function ownCalendar(Request $request, FamilyCalendarService $calendar): JsonResponse
    {
        $student = Student::where('user_id', $request->user()->id)->first();
        abort_if($student === null, 404, 'No student record is linked to your account.');

        return response()->json(['data' => $calendar->agenda($student)]);
    }

    /** Subject teachers of a linked child's class (basic link access). */
    public function childTeachers(Request $request, Student $student, FamilyCalendarService $calendar): JsonResponse
    {
        $this->guardianLinkFor($request, $student);

        return response()->json(['data' => $calendar->teachers($student)]);
    }

    /** Own subject teachers (self lane). */
    public function ownTeachers(Request $request, FamilyCalendarService $calendar): JsonResponse
    {
        $student = Student::where('user_id', $request->user()->id)->first();
        abort_if($student === null, 404, 'No student record is linked to your account.');

        return response()->json(['data' => $calendar->teachers($student)]);
    }

    /** Own weekly timetable (self lane). */
    public function ownTimetable(Request $request, StudentReportService $reports): JsonResponse
    {
        $student = Student::where('user_id', $request->user()->id)->first();
        abort_if($student === null, 404, 'No student record is linked to your account.');

        return response()->json(['data' => $reports->timetable($student)]);
    }

    /** Own result card (self lane). */
    public function ownResultCard(Request $request, StudentReportService $reports): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        $student = Student::where('user_id', $request->user()->id)->first();
        abort_if($student === null, 404, 'No student record is linked to your account.');

        return response()->json(['data' => $reports->resultCard($student, $request->integer('term_id'))]);
    }

    /** Own official frozen report card (student accounts). */
    public function ownReportCard(Request $request, StudentReportService $reports): JsonResponse
    {
        $request->validate(['term_id' => ['required', 'integer', 'exists:terms,id']]);

        $student = Student::where('user_id', $request->user()->id)->first();
        abort_if($student === null, 404, 'No student record is linked to your account.');

        return response()->json(['data' => $reports->reportCard($student, $request->integer('term_id'))]);
    }

    /** Own multi-year transcript (student accounts). */
    public function ownTranscript(Request $request, StudentReportService $reports): JsonResponse
    {
        $student = Student::where('user_id', $request->user()->id)->first();
        abort_if($student === null, 404, 'No student record is linked to your account.');

        return response()->json(['data' => $reports->transcript($student, $this->transcriptYearIds($request))]);
    }

    /**
     * Notification & language preferences of the authenticated user. Available
     * to EVERY account (staff, parents, students) — preferences live on the
     * global person, not on any profile or membership.
     */
    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->preferencesPayload($request->user())]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferred_language' => ['sometimes', 'string', Rule::in(Languages::UI_LOCALES)],
            'notify_via_sms' => ['sometimes', 'boolean'],
            'notify_via_email' => ['sometimes', 'boolean'],
            'notify_via_push' => ['sometimes', 'boolean'],
            // Per-category channel mutes (deltas from catalog defaults) —
            // critical events ignore these; masters above always win.
            'notification_preferences' => ['sometimes', 'array'],
            'notification_preferences.*' => ['array'],
            'notification_preferences.*.sms' => ['sometimes', 'boolean'],
            'notification_preferences.*.email' => ['sometimes', 'boolean'],
            'notification_preferences.*.push' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['notification_preferences'])) {
            $data['notification_preferences'] = array_intersect_key(
                $data['notification_preferences'],
                array_flip(NotificationCatalog::CATEGORIES),
            );
        }

        $user = $request->user();
        $user->fill($data)->save();

        return response()->json([
            'data' => $this->preferencesPayload($user->refresh()),
            'message' => 'Preferences updated.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function preferencesPayload(User $user): array
    {
        return [
            'preferred_language' => $user->preferred_language,
            'notify_via_sms' => $user->notify_via_sms,
            'notify_via_email' => $user->notify_via_email,
            'notify_via_push' => $user->notify_via_push,
            'notification_preferences' => $user->notification_preferences ?? (object) [],
            'notification_categories' => NotificationCatalog::CATEGORIES,
            'phone' => $user->phone,
            'email' => $user->email,
            'public_id' => $user->public_id,
        ];
    }

    /**
     * Active guardian links of the authenticated user (via their parent
     * profile). Empty query when the user has no parent profile.
     *
     * @return Builder<StudentGuardian>
     */
    private function guardianLinks(Request $request)
    {
        $parentId = $request->user()->parentProfile()->value('id');

        return StudentGuardian::query()
            ->where('is_active', true)
            ->where('parent_id', $parentId ?? 0);
    }

    private function guardianLinkFor(Request $request, Student $student): StudentGuardian
    {
        $link = $this->guardianLinks($request)
            ->where('student_id', $student->id)
            ->first();

        abort_if($link === null, 403, 'This student is not linked to your account.');

        return $link;
    }
}
