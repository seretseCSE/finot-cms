<?php

namespace App\Actions;

use App\Enums\ConcessionStatus;
use App\Enums\DiscountType;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\FeeConcession;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Analytics\Analytics;
use App\Services\RegistrationNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers a permanent student identity at a branch and, when an academic
 * year plus a grade level or section are supplied, enrolls them in one step —
 * optionally linking guardians, syncing health conditions, issuing the
 * selected fee invoices (with inline payments) and provisioning the student's
 * own login, all in the same transaction. Fayda national IDs are hashed
 * before storage and never persisted in plaintext.
 */
class RegisterStudentAction
{
    public function __construct(
        private readonly EnrollStudentAction $enroll,
        private readonly AddGuardianAction $addGuardian,
        private readonly GenerateInvoicesAction $generateInvoices,
        private readonly RecordPaymentAction $recordPayment,
        private readonly ApplyInvoiceDiscountAction $applyDiscount,
        private readonly RegistrationNotifier $notifier,
        private readonly LinkStudentLoginAction $linkStudentLogin,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  Validated by StoreStudentRequest:
     *                                      identity/name fields, contact, addresses, languages, health fields,
     *                                      plus optional `health_conditions` rows, `guardians` rows, the
     *                                      one-step enrollment fields (academic_year_id, grade_level_id,
     *                                      section_id, school_program_id, previous_school_id),
     *                                      `fee_structure_ids` + `pay_now` rows and `create_user_account`.
     */
    public function execute(Branch $branch, array $data, ?int $recordedBy = null): Student
    {
        return DB::transaction(function () use ($branch, $data, $recordedBy): Student {
            $student = $branch->students()->create([
                'school_id' => $branch->school_id,
                'first_name' => $data['first_name'],
                'father_name' => $data['father_name'],
                'grandfather_name' => $data['grandfather_name'] ?? null,
                'mother_name' => $data['mother_name'] ?? null,
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'national_student_id' => $data['national_student_id'] ?? null,
                'fayda_hash' => ! empty($data['fayda_id']) ? hash('sha256', $data['fayda_id']) : null,
                'primary_phone' => $data['primary_phone'] ?? null,
                'email' => $data['email'] ?? null,
                'citizenship' => $data['citizenship'] ?? 'Ethiopian',
                'marital_status' => $data['marital_status'] ?? null,
                'languages' => $data['languages'] ?? ['am'],
                'blood_type' => $data['blood_type'] ?? null,
                'health_notes' => $data['health_notes'] ?? null,
                'birth_country' => $data['birth_country'] ?? null,
                'birth_state' => $data['birth_state'] ?? null,
                'birth_city' => $data['birth_city'] ?? null,
                'birth_sub_city' => $data['birth_sub_city'] ?? null,
                'birth_woreda' => $data['birth_woreda'] ?? null,
                'country' => $data['country'] ?? null,
                'state' => $data['state'] ?? null,
                'city' => $data['city'] ?? null,
                'sub_city' => $data['sub_city'] ?? null,
                'woreda' => $data['woreda'] ?? null,
                'house_no' => $data['house_no'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (! empty($data['health_conditions'])) {
                $student->healthConditions()->sync(
                    self::healthConditionSync($data['health_conditions']),
                );
            }

            // The standing concession must exist BEFORE any invoice below is
            // generated — the resolver only stamps concessions it can see at
            // generation time, so ordering is what puts the discount on the
            // student's very first bill.
            if (! empty($data['concession'])) {
                $this->grantConcession($student, $branch, $data['concession'], $data, $recordedBy);
            }

            $enrollment = null;

            if (! empty($data['academic_year_id'])
                && (! empty($data['section_id']) || ! empty($data['grade_level_id']))) {
                $enrollment = $this->enroll->execute($student, [
                    'academic_year_id' => (int) $data['academic_year_id'],
                    'section_id' => ! empty($data['section_id']) ? (int) $data['section_id'] : null,
                    'grade_level_id' => ! empty($data['grade_level_id']) ? (int) $data['grade_level_id'] : null,
                    'school_program_id' => ! empty($data['school_program_id']) ? (int) $data['school_program_id'] : null,
                    'previous_school_id' => ! empty($data['previous_school_id']) ? (int) $data['previous_school_id'] : null,
                    'enrolled_on' => $data['enrolled_on'] ?? null,
                ]);
            }

            if (! empty($data['fee_structure_ids'])) {
                $this->issueFees(
                    $enrollment,
                    array_map('intval', $data['fee_structure_ids']),
                    $data['pay_now'] ?? [],
                    $data['scholarships'] ?? [],
                    $recordedBy,
                );
            }

            foreach ($data['guardians'] ?? [] as $index => $guardian) {
                try {
                    $this->addGuardian->execute($student, $guardian);
                } catch (ValidationException $e) {
                    // Re-key row errors so the wizard highlights the right
                    // guardian card (guardians.2.email, not a bare "email").
                    throw ValidationException::withMessages(
                        collect($e->errors())
                            ->mapWithKeys(fn (array $messages, string $key) => ["guardians.{$index}.{$key}" => $messages])
                            ->all(),
                    );
                }
            }

            if (! empty($data['create_user_account'])) {
                try {
                    // No student phone → phone-less ID-login account (student
                    // ID + PIN, guardian gets the setup SMS).
                    $this->linkStudentLogin->execute($student, $data['primary_phone'] ?? null, $data['email'] ?? null);
                } catch (ValidationException $e) {
                    // Surface under the checkbox the registrar actually ticked.
                    throw ValidationException::withMessages([
                        'create_user_account' => collect($e->errors())->flatten()->all(),
                    ]);
                }
            }

            Analytics::capture(Auth::user(), 'student.registered', [
                'student_id' => $student->id,
                'with_enrollment' => $enrollment !== null,
                'with_account' => ! empty($data['create_user_account']),
                'guardians' => count($data['guardians'] ?? []),
            ], $branch->school_id, $branch->id);

            return $student->load([
                'currentEnrollment.section', 'currentEnrollment.gradeLevel',
                'guardians.parentProfile.user', 'healthConditions',
            ]);
        });
    }

    /**
     * Issue the selected fee structures' invoices to the new enrollment,
     * validating each applies to the enrollment's year + grade (empty grade
     * pivot = all grades), then record any inline "paid now" rows and apply
     * any scholarships.
     *
     * @param  list<int>  $feeStructureIds
     * @param  list<array{fee_structure_id: int|string, amount?: float|string|null, method?: ?string, reference?: ?string}>  $payNow
     * @param  list<array{fee_structure_id: int|string, reason: string}>  $scholarships
     */
    private function issueFees(?StudentEnrollment $enrollment, array $feeStructureIds, array $payNow, array $scholarships, ?int $recordedBy): void
    {
        if ($enrollment === null) {
            throw ValidationException::withMessages([
                'fee_structure_ids' => ['Fees can only be assigned together with an enrollment.'],
            ]);
        }

        $invoices = [];

        foreach ($feeStructureIds as $feeStructureId) {
            $structure = FeeStructure::with('gradeLevels')->findOrFail($feeStructureId);

            $applies = $structure->academic_year_id === $enrollment->academic_year_id
                && $structure->is_active
                && ($structure->gradeLevels->isEmpty()
                    || $structure->gradeLevels->contains('id', $enrollment->grade_level_id));

            if (! $applies) {
                throw ValidationException::withMessages([
                    'fee_structure_ids' => ["The fee \"{$structure->name}\" does not apply to this enrollment."],
                ]);
            }

            $invoices[$structure->id] = $this->generateInvoices->executeForEnrollment($structure, $enrollment);
        }

        // Scholarships first, so a partial-scholarship + pay-now combination judges
        // payments against the discounted net.
        foreach ($scholarships as $row) {
            $invoice = $invoices[(int) $row['fee_structure_id']] ?? null;

            if ($invoice === null) {
                throw ValidationException::withMessages([
                    'scholarships' => ['Scholarships may only be applied to fees selected above.'],
                ]);
            }

            $invoices[(int) $row['fee_structure_id']] = $this->applyDiscount->execute($invoice, [
                'discount_type' => 'full_scholarship',
                'scholarship_reason' => $row['reason'],
            ]);
        }

        foreach ($payNow as $row) {
            $invoice = $invoices[(int) $row['fee_structure_id']] ?? null;

            if ($invoice === null) {
                throw ValidationException::withMessages([
                    'pay_now' => ['Payments may only be recorded for fees selected above.'],
                ]);
            }

            // The named collection account must be usable by the enrolling
            // branch — same rule as the standalone payment endpoint.
            $accountId = isset($row['bank_account_id']) ? (int) $row['bank_account_id'] : null;

            if ($accountId !== null && ! BankAccount::query()->whereKey($accountId)->usableByBranch($invoice->branch_id)->exists()) {
                throw ValidationException::withMessages([
                    'pay_now' => ['Pick a bank account that is active for this branch.'],
                ]);
            }

            $this->recordPayment->execute($invoice, [
                'amount' => $row['amount'] ?? $invoice->netAmount(),
                'method' => $row['method'] ?? 'cash',
                'bank_account_id' => $accountId,
                'reference' => $row['reference'] ?? null,
            ], $recordedBy);
        }
    }

    /**
     * File the standing concession granted at registration time. Scoped to
     * the enrollment's academic year when one is being created (a wizard-time
     * grant is about THIS year's bills), open-ended otherwise. Born active —
     * the caller already held fees.manage.
     *
     * @param  array{category: string, discount_type: string, discount_value?: float|string|null, fee_types?: ?array<int, string>, reason?: ?string}  $concession
     * @param  array<string, mixed>  $data
     */
    private function grantConcession(Student $student, Branch $branch, array $concession, array $data, ?int $recordedBy): void
    {
        $type = DiscountType::from($concession['discount_type']);
        $value = round((float) ($concession['discount_value'] ?? 0), 2);

        if ($type === DiscountType::Percentage && ($value <= 0 || $value > 100)) {
            throw ValidationException::withMessages([
                'concession.discount_value' => ['A percentage discount must be between 0 and 100.'],
            ]);
        }

        FeeConcession::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'student_id' => $student->id,
            'category' => $concession['category'],
            'discount_type' => $type->value,
            'discount_value' => $type === DiscountType::FullScholarship ? 0 : $value,
            'fee_types' => $concession['fee_types'] ?? null,
            'academic_year_id' => ! empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
            'status' => ConcessionStatus::Active->value,
            'source' => 'manual',
            'reason' => $concession['reason'] ?? null,
            'requested_by' => $recordedBy,
            'approved_by' => $recordedBy,
            'approved_at' => now(),
        ]);
    }

    /**
     * @param  list<array{health_condition_id: int|string, severity?: ?string, notes?: ?string, medication?: ?string}>  $rows
     * @return array<int, array{severity: ?string, notes: ?string, medication: ?string}>
     */
    public static function healthConditionSync(array $rows): array
    {
        $sync = [];

        foreach ($rows as $row) {
            $sync[(int) $row['health_condition_id']] = [
                'severity' => $row['severity'] ?? null,
                'notes' => $row['notes'] ?? null,
                'medication' => $row['medication'] ?? null,
            ];
        }

        return $sync;
    }
}
