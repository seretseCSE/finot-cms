<?php

namespace App\Filament\Pages;

use App\Enums\RubricScore;
use App\Models\ClassModel;
use App\Models\Marklist;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Academics\MarklistService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class RecordMarklist extends Page
{
    protected string $view = 'filament.pages.record-marklist';

    protected static ?string $title = 'Record Marks';

    public ?int $classId = null;

    public ?int $termId = null;

    public ?int $subjectId = null;

    public ?int $marklistId = null;

    public ?string $marklistStatus = null;

    public array $rows = [];

    public function getSubheading(): ?string
    {
        return 'Record numeric scores (and optional rubric) for a class roster. Rankings are calculated from total averages.';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationLabel(): string
    {
        return 'Record Marks';
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
        return \App\Support\RoleGate::can('results.record');
    }

    public function loadRoster(MarklistService $service): void
    {
        $this->validate([
            'classId' => 'required|exists:classes,id',
            'termId' => 'required|exists:terms,id',
            'subjectId' => 'required|exists:subjects,id',
        ]);

        $marklist = $service->ensure((int) $this->classId, (int) $this->termId, (int) $this->subjectId, Auth::user());
        $this->marklistId = $marklist->id;
        $this->syncRows($marklist);
    }

    public function saveDraft(MarklistService $service): void
    {
        $marklist = Marklist::query()->findOrFail($this->marklistId);
        $service->saveItems($marklist, $this->rows, Auth::user());
        Notification::make()->title('Marks saved')->success()->send();
        $this->syncRows($marklist->fresh(['items.member']));
    }

    public function isLocked(): bool
    {
        if (! $this->termId) {
            return false;
        }

        $term = Term::query()->find($this->termId);
        if (! $term) {
            return false;
        }

        return $term->status === 'closed' || (! $term->is_active && $term->status !== 'active');
    }

    protected function syncRows(Marklist $marklist): void
    {
        $this->marklistStatus = $marklist->status?->value;

        $this->rows = $marklist->items()->with('member')->get()->map(function ($item) {
            return [
                'member_id' => $item->member_id,
                'name' => $item->member?->full_name,
                'code' => $item->member?->member_code,
                'score' => $item->score,
                'max_score' => $item->max_score ?: $marklist->subject?->max_score ?: 100,
                'rank' => $item->rank,
                'conduct' => $item->conduct?->value,
                'memorization' => $item->memorization?->value,
                'participation' => $item->participation?->value,
                'remarks' => $item->remarks,
            ];
        })->all();
    }

    public function getViewData(): array
    {
        return [
            'classes' => ClassModel::query()->orderBy('name')->pluck('name', 'id'),
            'terms' => Term::query()->where('is_active', true)->orderBy('semester_number')->orderBy('name')->pluck('name', 'id'),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'rubric' => RubricScore::cases(),
        ];
    }
}
