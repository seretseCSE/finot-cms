<?php

namespace App\Rules;

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EnrollmentUniquePerYear implements ValidationRule
{
    public function __construct(protected int $academicYearId, protected ?int $ignoreEnrollmentId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $memberId = (int) $value;

        $existing = StudentEnrollment::query()
            ->with(['class', 'academicYear'])
            ->where('member_id', $memberId)
            ->where('academic_year_id', $this->academicYearId)
            ->where('status', 'Enrolled')
            ->when($this->ignoreEnrollmentId, fn ($q) => $q->whereKeyNot($this->ignoreEnrollmentId))
            ->first();

        if (! $existing) {
            return;
        }

        $className = $existing->class?->name ?? 'Unknown Class';
        $yearName = $existing->academicYear?->name ?? AcademicYear::query()->find($this->academicYearId)?->name ?? 'Unknown Year';

        $fail("This member is already enrolled in {$className} for {$yearName}");
    }
}
