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

    public ?int $classId = null;

    public ?int $termId = null;

    public ?int $subjectId = null;

    public ?int $marklistId = null;

    public array $rows = [];

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
        return 'Education Management';
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
        Notification::make()->title('Draft saved')->success()->send();
        $this->syncRows($marklist->fresh(['items.member']));
    }

    public function submit(MarklistService $service): void
    {
        $marklist = Marklist::query()->findOrFail($this->marklistId);
        $service->saveItems($marklist, $this->rows, Auth::user());
        $service->submit($marklist, Auth::user());
        Notification::make()->title('Marklist submitted')->success()->send();
        $this->syncRows($marklist->fresh(['items.member']));
    }

    protected function syncRows(Marklist $marklist): void
    {
        $this->rows = $marklist->items()->with('member')->get()->map(function ($item) {
            return [
                'member_id' => $item->member_id,
                'name' => $item->member?->full_name,
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
            'terms' => Term::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'rubric' => RubricScore::cases(),
        ];
    }
}
