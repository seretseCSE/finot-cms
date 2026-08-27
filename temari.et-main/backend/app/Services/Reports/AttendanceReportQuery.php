<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

/**
 * The resolved question an attendance-report request asks: the tenant scope
 * the caller is ALLOWED to see (school / branch / explicit section ids for the
 * teacher lane) plus the filters they chose. Built only by
 * AttendanceReportController::query() — the service trusts it blindly, so the
 * controller is the single authority gate.
 */
final readonly class AttendanceReportQuery
{
    /**
     * @param  list<int>|null  $allowedSectionIds  non-null = ownership lane
     *                                             (homeroom teacher): hard cap
     *                                             on visible sections
     */
    public function __construct(
        public ?int $schoolId,
        public ?int $branchId,
        public ?array $allowedSectionIds,
        public string $from,
        public string $to,
        public ?int $gradeLevelId = null,
        public ?int $sectionId = null,
        public ?string $source = null,
        public ?int $deviceId = null,
    ) {}

    /** The equal-length window ending the day before this one — for deltas. */
    public function previousWindow(): self
    {
        $from = CarbonImmutable::parse($this->from);
        $days = $from->diffInDays(CarbonImmutable::parse($this->to)) + 1;

        return new self(
            schoolId: $this->schoolId,
            branchId: $this->branchId,
            allowedSectionIds: $this->allowedSectionIds,
            from: $from->subDays($days)->toDateString(),
            to: $from->subDay()->toDateString(),
            gradeLevelId: $this->gradeLevelId,
            sectionId: $this->sectionId,
            source: $this->source,
            deviceId: $this->deviceId,
        );
    }
}
