<?php

namespace App\Filament\Pages\Education;

use App\Models\Assessment;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Services\Academics\AssessmentScoreService;
use App\Support\RoleGate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class RecordAssessments extends Page
{
    protected string $view = 'filament.pages.education.record-assessments';

    protected static ?string $title = 'Record assessments';

    public ?int $termId = null;

    public ?int $offeringId = null;

    public ?int $assessmentId = null;

    public array $rows = [];

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function getNavigationSort(): ?int
    {
        return 12;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return RoleGate::can('results.record');
    }

    public function loadRoster(AssessmentScoreService $service): void
    {
        $this->validate([
            'assessmentId' => 'required|exists:assessments,id',
        ]);

        $assessment = Assessment::query()->with('offering')->findOrFail($this->assessmentId);
        $this->offeringId = $assessment->subject_offering_id;
        $this->termId = $assessment->offering?->term_id;

        $memberIds = $service->rosterMemberIds($assessment->offering);
        $existing = $assessment->scores()->whereIn('member_id', $memberIds)->get()->keyBy('member_id');

        $this->rows = \App\Models\Member::query()
            ->whereIn('id', $memberIds)
            ->orderBy('first_name')
            ->get()
            ->map(function ($member) use ($existing) {
                $score = $existing->get($member->id);

                return [
                    'member_id' => $member->id,
                    'name' => $member->full_name,
                    'code' => $member->member_code,
                    'score' => $score?->score,
                    'is_absent' => (bool) ($score?->is_absent),
                ];
            })->all();
    }

    public function save(AssessmentScoreService $service): void
    {
        $assessment = Assessment::query()->findOrFail($this->assessmentId);
        $service->saveScores($assessment, $this->rows, Auth::user());
        Notification::make()->title('Scores saved')->success()->send();
        $this->loadRoster($service);
    }

    public function getViewData(): array
    {
        return [
            'terms' => Term::query()->where(function ($q) {
                $q->where('status', 'active')->orWhere('is_active', true);
            })->orderBy('name')->pluck('name', 'id'),
            'offerings' => $this->termId
                ? SubjectOffering::query()->with('subject')->where('term_id', $this->termId)->get()
                    ->mapWithKeys(fn ($o) => [$o->id => ($o->subject?->name ?? 'Subject').($o->class_id ? ' (class '.$o->class_id.')' : '')])
                : collect(),
            'assessments' => $this->offeringId
                ? Assessment::query()->where('subject_offering_id', $this->offeringId)->where('is_open', true)->orderBy('sort_order')->pluck('name', 'id')
                : ($this->termId
                    ? Assessment::query()->whereHas('offering', fn ($q) => $q->where('term_id', $this->termId))->where('is_open', true)->orderBy('sort_order')->pluck('name', 'id')
                    : collect()),
        ];
    }
}
