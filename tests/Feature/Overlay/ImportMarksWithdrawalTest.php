<?php

namespace Tests\Feature\Overlay;

use App\Enums\MarklistStatus;
use App\Enums\RubricScore;
use App\Enums\WithdrawalRequestStatus;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\MarklistService;
use App\Services\Movement\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportMarksWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function encoder_records_marks_and_education_head_can_approve_even_if_assisted(): void
    {
        $encoder = User::factory()->dataEncoder()->create();
        $head = User::factory()->educationHead()->create();
        $enrollment = StudentEnrollment::factory()->enrolled()->create();
        $subject = Subject::query()->create([
            'name' => 'Catechism-'.uniqid(),
            'is_active' => true,
            'created_by' => $head->id,
        ]);
        $term = Term::query()->create([
            'academic_year_id' => $enrollment->academic_year_id,
            'name' => 'Term 1',
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonth(),
            'is_active' => true,
        ]);

        $service = app(MarklistService::class);
        $marklist = $service->ensure($enrollment->class_id, $term->id, $subject->id, $encoder);
        $this->assertSame($encoder->id, $marklist->assisted_by);
        $this->assertSame(MarklistStatus::Draft, $marklist->status);

        $service->saveItems($marklist, [[
            'member_id' => $enrollment->member_id,
            'conduct' => RubricScore::Excellent->value,
            'memorization' => RubricScore::Good->value,
            'participation' => RubricScore::NeedsWork->value,
            'remarks' => 'ok',
        ]], $encoder);

        $service->submit($marklist->fresh(['items']), $encoder);
        $this->assertSame(MarklistStatus::Submitted, $marklist->fresh()->status);

        $this->assertFalse($encoder->can('results.approve'));
        $approved = $service->approve($marklist->fresh(), $head);
        $this->assertSame(MarklistStatus::Approved, $approved->status);
        $this->assertSame($head->id, $approved->approved_by);
    }

    #[Test]
    public function withdrawal_is_three_steps_and_sets_removed_at(): void
    {
        $enrollment = StudentEnrollment::factory()->enrolled()->create();
        $studentUser = User::query()->where('member_id', $enrollment->member_id)->first();
        $head = User::factory()->educationHead()->create();
        $hr = User::factory()->hrHead()->create();

        $service = app(WithdrawalService::class);
        $request = $service->apply($studentUser, $enrollment, 'Moving to another parish for family reasons.');
        $this->assertSame(WithdrawalRequestStatus::Pending, $request->status);

        $request = $service->approve($request, $head);
        $this->assertSame(WithdrawalRequestStatus::EducationApproved, $request->status);

        $request = $service->finalize($request, $hr);
        $this->assertSame(WithdrawalRequestStatus::Finalized, $request->status);
        $this->assertNotNull($enrollment->fresh()->removed_at);
        $this->assertSame('Withdrawn', $enrollment->fresh()->status);
        $this->assertDatabaseHas('student_enrollments', ['id' => $enrollment->id]);
    }
}
