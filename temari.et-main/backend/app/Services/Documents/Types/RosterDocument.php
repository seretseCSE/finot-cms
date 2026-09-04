<?php

namespace App\Services\Documents\Types;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\GeneratedDocument;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\Reports\RosterReportService;
use Illuminate\Database\Eloquent\Model;

/**
 * The official roster sheet (semester or yearly) as a printable PDF — the same
 * frozen student_term_results the on-screen roster reads, laid out as the
 * classic Ethiopian grid with the school header and a verification QR.
 *
 * No model subject: the anchor lives in params (scope + term/year + optional
 * grade/section). Gated on the SUPERVISORY grades.view so the whole-grade
 * sheet is never generated (and cache-shared) under a homeroom teacher's
 * narrower section scope — teachers still export CSV from their scoped view.
 */
class RosterDocument extends DocumentType
{
    public function __construct(private readonly RosterReportService $rosters)
    {
    }

    public function view(): string
    {
        return 'roster';
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', 'in:term,year'],
            'term_id' => ['required_if:scope,term', 'integer', 'exists:terms,id'],
            'academic_year_id' => ['required_if:scope,year', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ];
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return null;
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        $anchor = $this->anchor($subject, $params);

        if ($anchor['school_id'] === null) {
            return false;
        }

        return $user->hasPermissionForScope('grades.view', $anchor['school_id'], $anchor['branch_id']);
    }

    public function anchor(?Model $subject, array $params): array
    {
        $model = ($params['scope'] ?? null) === 'year'
            ? AcademicYear::query()->find($params['academic_year_id'] ?? null)
            : Term::query()->find($params['term_id'] ?? null);

        return [
            'school_id' => $model?->school_id,
            'branch_id' => $model?->branch_id,
        ];
    }

    public function payload(?Model $subject, array $params): array
    {
        $sectionId = isset($params['section_id']) ? (int) $params['section_id'] : null;
        $gradeLevelId = isset($params['grade_level_id']) ? (int) $params['grade_level_id'] : null;

        $anchor = $this->anchor($subject, $params);

        if (($params['scope'] ?? null) === 'year') {
            $year = AcademicYear::query()->findOrFail($params['academic_year_id']);
            // Supervisory scope only (grades.view gate) — no section restriction.
            $roster = $this->rosters->yearRoster($year, $sectionId, $gradeLevelId, null);
            $periodLabel = $year->name;
        } else {
            $term = Term::query()->with('academicYear:id,name')->findOrFail($params['term_id']);
            $roster = $this->rosters->termRoster($term, $sectionId, $gradeLevelId, null);
            $periodLabel = $term->academicYear?->name
                ? $term->name.' · '.$term->academicYear->name
                : $term->name;
        }

        $section = $sectionId ? Section::query()->with('gradeLevel:id,name')->find($sectionId) : null;
        $grade = $gradeLevelId ? GradeLevel::query()->find($gradeLevelId) : null;

        $scopeLabel = $section !== null
            ? trim(($section->gradeLevel?->name ?? '').' '.$section->name)
            : ($grade !== null ? $grade->name : null);

        return [
            'scope' => $params['scope'],
            'roster' => $roster,
            'period_label' => $periodLabel,
            'scope_label' => $scopeLabel,
            'show_section' => $sectionId === null,
            'school_name' => $anchor['school_id'] ? School::query()->whereKey($anchor['school_id'])->value('name') : null,
            'branch_name' => $anchor['branch_id'] ? Branch::query()->whereKey($anchor['branch_id'])->value('name') : null,
        ];
    }

    /** The wide subject grid prints landscape, like the transcript. */
    public function landscape(): bool
    {
        return true;
    }

    public function templateVersion(): int
    {
        return 1;
    }

    /** Possession of the printed sheet = possession of the record (transcript precedent). */
    public function publiclyDownloadable(): bool
    {
        return true;
    }

    /**
     * The QR opens the LIVE roster page (public, token-scoped, killed by
     * revoke) — the actual sheet, not a bare verify summary.
     */
    public function qrTarget(GeneratedDocument $document): string
    {
        return rtrim((string) config('sms.frontend_url'), '/').'/rosters/'.$document->public_token;
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        return array_filter([
            'school' => $document->school?->name,
            'branch' => $document->branch?->name,
            'issued_on' => $document->created_at?->toDateString(),
        ], fn ($v) => $v !== null);
    }
}
