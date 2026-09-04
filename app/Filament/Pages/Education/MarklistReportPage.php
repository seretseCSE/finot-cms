<?php

namespace App\Filament\Pages\Education;

use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Academics\AcademicReportDownloader;
use App\Services\Academics\AcademicReportService;
use App\Support\RoleGate;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarklistReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.education.marklist-report';

    protected static ?string $title = 'Marklist report';

    public ?int $term_id = null;

    public ?int $class_id = null;

    public ?int $subject_id = null;

    public ?array $report = null;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function getNavigationSort(): ?int
    {
        return 19;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return RoleGate::can('results.view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('term_id')
                ->label('Semester')
                ->options(fn () => Term::query()->where(function ($q) {
                    $q->where('status', 'active')->orWhere('is_active', true);
                })->orderBy('name')->pluck('name', 'id')->all() ?: Term::query()->orderByDesc('id')->pluck('name', 'id')->all())
                ->required(),
            Select::make('class_id')
                ->options(ClassModel::query()->orderBy('name')->pluck('name', 'id'))
                ->nullable(),
            Select::make('subject_id')
                ->options(Subject::query()->orderBy('name')->pluck('name', 'id'))
                ->nullable(),
        ]);
    }

    public function generate(AcademicReportService $reports): void
    {
        $this->validate(['term_id' => 'required|exists:terms,id']);
        $term = Term::query()->findOrFail($this->term_id);
        $data = $reports->marklistReport($term, $this->class_id, $this->subject_id);
        $this->report = [
            'offerings' => $data['offerings']->map(fn ($o) => [
                'id' => $o->id,
                'subject_id' => $o->subject_id,
                'subject' => $o->subject?->name,
                'assessments' => $o->assessments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                ])->all(),
            ])->all(),
            'rows' => $data['rows'],
            'meta' => [
                'term' => $term->name,
                'class' => $this->class_id ? ClassModel::query()->find($this->class_id)?->name : null,
                'batch' => $term->batchYear?->name,
            ],
        ];
    }

    public function export(string $format): ?StreamedResponse
    {
        if (! $this->report) {
            Notification::make()->title('Generate the report first')->warning()->send();

            return null;
        }

        return app(AcademicReportDownloader::class)->downloadMarklist($this->report, $format);
    }
}
