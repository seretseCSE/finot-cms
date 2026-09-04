<?php

namespace App\Filament\Pages\Education;

use App\Models\BatchYear;
use App\Models\ClassModel;
use App\Models\Term;
use App\Services\Academics\AcademicReportDownloader;
use App\Services\Academics\AcademicReportService;
use App\Services\Academics\ComputeTermResultsService;
use App\Support\RoleGate;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RosterReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.education.roster-report';

    protected static ?string $title = 'Roster report';

    public ?int $term_id = null;

    public ?int $batch_year_id = null;

    public ?int $class_id = null;

    public ?array $report = null;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-table-cells';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return RoleGate::can('results.view') || RoleGate::can('page.report.class-performance');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('term_id')
                ->label('Semester')
                ->options(Term::query()->orderByDesc('id')->pluck('name', 'id'))
                ->required()
                ->live(),
            Select::make('batch_year_id')
                ->label('Batch year')
                ->options(BatchYear::query()->orderBy('name')->pluck('name', 'id'))
                ->nullable(),
            Select::make('class_id')
                ->label('Class')
                ->options(ClassModel::query()->orderBy('name')->pluck('name', 'id'))
                ->nullable(),
        ]);
    }

    public function generate(AcademicReportService $reports): void
    {
        $this->validate(['term_id' => 'required|exists:terms,id']);
        $term = Term::query()->findOrFail($this->term_id);
        $this->report = $this->withMeta(
            $reports->rosterReport($term, $this->batch_year_id, $this->class_id),
            $term,
        );
    }

    public function compute(ComputeTermResultsService $compute, AcademicReportService $reports): void
    {
        $this->validate(['term_id' => 'required|exists:terms,id']);
        $term = Term::query()->findOrFail($this->term_id);
        $result = $compute->compute($term, Auth::user(), $this->class_id);
        Notification::make()->title('Computed '.$result['students'].' students')->success()->send();
        $this->report = $this->withMeta(
            $reports->rosterReport($term, $this->batch_year_id, $this->class_id),
            $term,
        );
    }

    public function export(string $format): ?StreamedResponse
    {
        if (! $this->report) {
            Notification::make()->title('Generate the report first')->warning()->send();

            return null;
        }

        return app(AcademicReportDownloader::class)->downloadRoster($this->report, $format);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function withMeta(array $report, Term $term): array
    {
        $report['meta'] = [
            'term' => $term->name,
            'class' => $this->class_id ? ClassModel::query()->find($this->class_id)?->name : null,
            'batch' => $this->batch_year_id
                ? BatchYear::query()->find($this->batch_year_id)?->name
                : $term->batchYear?->name,
        ];

        return $report;
    }
}
