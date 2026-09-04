<?php

namespace App\Filament\Pages\Education;

use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\BatchYear;
use App\Models\ClassModel;
use App\Services\Academics\BatchPromotionService;
use App\Services\Academics\PromotionBoardService;
use App\Support\RoleGate;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PromotionBoardPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.education.promotion-board';

    protected static ?string $title = 'Promotion board';

    protected static ?string $navigationLabel = 'Promotion board';

    public ?int $academic_year_id = null;

    public ?int $batch_id = null;

    public ?int $class_id = null;

    public ?array $board = null;

    /** @var array<int, string|null> */
    public array $decisions = [];

    public ?int $pass_target_class_id = null;

    public ?int $fail_target_batch_year_id = null;

    public ?int $fail_target_class_id = null;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-trending-up';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function getNavigationSort(): ?int
    {
        return 18;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return RoleGate::can('students.promote')
            || RoleGate::can('students.bulk_promote')
            || RoleGate::can('student_enrollments.update');
    }

    public function mount(): void
    {
        $this->academic_year_id = AcademicYear::query()
            ->where('status', 'Active')
            ->orderByDesc('start_date')
            ->value('id');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('academic_year_id')
                ->label('Academic year')
                ->options(fn () => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all())
                ->required()
                ->live()
                ->afterStateUpdated(function (): void {
                    $this->board = null;
                    $this->decisions = [];
                }),
            Select::make('batch_id')
                ->label('Batch')
                ->options(fn () => Batch::query()->orderByDesc('start_year')->pluck('name', 'id')->all())
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(function (): void {
                    $this->board = null;
                    $this->decisions = [];
                }),
            Select::make('class_id')
                ->label('Class')
                ->options(fn () => ClassModel::query()->active()->orderBy('program_year')->orderBy('name')->pluck('name', 'id')->all())
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(function (): void {
                    $this->board = null;
                    $this->decisions = [];
                }),
        ])->columns(3);
    }

    public function loadBoard(PromotionBoardService $boards): void
    {
        $this->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'batch_id' => 'required|exists:batches,id',
            'class_id' => 'required|exists:classes,id',
        ]);

        $this->board = $boards->build(
            (int) $this->academic_year_id,
            (int) $this->batch_id,
            (int) $this->class_id,
        );

        $this->pass_target_class_id = $this->board['default_next_class_id'];
        $this->decisions = [];
        $this->fail_target_batch_year_id = null;
        $this->fail_target_class_id = null;

        if (empty($this->board['rows'])) {
            Notification::make()
                ->title('No enrolled students')
                ->body('This batch and class have no active enrollments for the selected year.')
                ->warning()
                ->send();
        }
    }

    public function acceptSuggestions(PromotionBoardService $boards): void
    {
        if (! $this->board) {
            return;
        }

        $this->decisions = $boards->decisionsFromSuggestions($this->board['rows']);

        Notification::make()
            ->title('Suggestions applied')
            ->body('Review each row, then click Apply promotions.')
            ->success()
            ->send();
    }

    public function applyAll(BatchPromotionService $promotions): void
    {
        if (! $this->board) {
            Notification::make()->title('Load the class list first')->warning()->send();

            return;
        }

        $this->validate([
            'pass_target_class_id' => 'required|exists:classes,id',
            'decisions' => 'array',
        ]);

        $result = $promotions->applyBoard(
            $this->decisions,
            (int) $this->pass_target_class_id,
            $this->fail_target_batch_year_id,
            $this->fail_target_class_id,
            Auth::user(),
        );

        $message = "{$result['passed']} passed, {$result['failed']} moved to another batch";
        if ($result['skipped'] > 0) {
            $message .= ", {$result['skipped']} skipped";
        }

        if ($result['errors'] !== []) {
            $detail = collect($result['errors'])
                ->take(5)
                ->map(fn (array $e) => "{$e['name']}: {$e['message']}")
                ->implode('; ');

            Notification::make()
                ->title($message)
                ->body($detail.($result['errors'] !== [] && count($result['errors']) > 5 ? '…' : ''))
                ->warning()
                ->send();
        } else {
            Notification::make()->title($message)->success()->send();
        }

        $this->loadBoard(app(PromotionBoardService::class));
    }

    /**
     * @return array<int, string>
     */
    public function failBatchYearOptions(): array
    {
        if (! $this->batch_id) {
            return [];
        }

        $programYear = null;
        if ($this->class_id) {
            $programYear = ClassModel::query()->find($this->class_id)?->program_year;
        }

        return BatchYear::query()
            ->with('batch')
            ->when($programYear, fn ($q) => $q->where('program_year', $programYear))
            ->where('batch_id', '!=', $this->batch_id)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (BatchYear $y) => [$y->id => ($y->batch?->name.' — '.$y->name)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function failClassOptions(): array
    {
        $programYear = $this->board['program_year'] ?? ClassModel::query()->find($this->class_id)?->program_year;

        return app(PromotionBoardService::class)->sameYearClassOptions($programYear);
    }

    public function hasFailDecisions(): bool
    {
        return collect($this->decisions)->contains(fn ($d) => $d === 'fail');
    }
}
