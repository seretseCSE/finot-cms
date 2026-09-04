<?php

namespace App\Filament\Pages\Education;

use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Term;
use App\Services\Academics\RankingService;
use App\Support\RoleGate;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class AcademicResultsReport extends Page
{
    protected static ?string $title = 'Academic Results';

    protected string $view = 'filament.pages.education.academic-results-report';

    protected static ?int $navigationSort = 4;

    public ?int $academic_year_id = null;

    public ?int $term_id = null;

    public ?int $class_id = null;

    public ?array $reportData = null;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar-square';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function getNavigationLabel(): string
    {
        return 'Academic Results';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return RoleGate::can('page.report.academic-results');
    }

    public function mount(): void
    {
        $this->academic_year_id = AcademicYear::where('status', 'Active')->first()?->id;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('academic_year_id')
                ->label('Academic year')
                ->options(fn () => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all())
                ->live()
                ->afterStateUpdated(function (): void {
                    $this->term_id = null;
                    $this->reportData = null;
                }),
            Forms\Components\Select::make('term_id')
                ->label('Semester')
                ->options(function () {
                    if (! $this->academic_year_id) {
                        return [];
                    }

                    return Term::query()
                        ->where('academic_year_id', $this->academic_year_id)
                        ->orderBy('semester_number')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Term $term) => [
                            $term->id => $term->semester_number
                                ? "{$term->name} (Semester {$term->semester_number})"
                                : $term->name,
                        ])
                        ->all();
                })
                ->live()
                ->afterStateUpdated(fn () => $this->reportData = null),
            Forms\Components\Select::make('class_id')
                ->label('Year / class')
                ->options(fn () => ClassModel::query()->orderBy('program_year')->orderBy('name')->pluck('name', 'id')->all())
                ->live()
                ->afterStateUpdated(fn () => $this->reportData = null),
        ])->columns(3);
    }

    public function generateReport(RankingService $ranking): void
    {
        $this->reportData = $ranking->institutionReport(
            $this->academic_year_id,
            $this->term_id,
            $this->class_id,
        );
    }
}
