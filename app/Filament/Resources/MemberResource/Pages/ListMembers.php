<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Exports\MemberExport;
use App\Exports\StudentImportTemplateExport;
use App\Filament\Resources\MemberResource;
use App\Filament\Resources\Pages\ListRecords;
use App\Jobs\ProcessExportJob;
use App\Models\MemberGroup;
use App\Services\Members\StudentExcelImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_student_template')
                ->label('Download Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => MemberResource::canCreate())
                ->extraAttributes(['data-tour' => 'download-student-template'])
                ->action(fn () => Excel::download(
                    new StudentImportTemplateExport(),
                    'student-import-template.xlsx'
                )),
            Action::make('import_students')
                ->label('Import Students')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => MemberResource::canCreate())
                ->extraAttributes(['data-tour' => 'import-students'])
                ->modalHeading('Import students from Excel')
                ->modalSubmitActionLabel('Import')
                ->registerModalActions([
                    Action::make('downloadExample')
                        ->label('Download Excel template')
                        ->link()
                        ->action(fn () => Excel::download(
                            new StudentImportTemplateExport(),
                            'student-import-template.xlsx'
                        )),
                ])
                ->modalDescription(fn (Action $action) => $action->getModalAction('downloadExample'))
                ->form([
                    FileUpload::make('file')
                        ->label('Excel file')
                        ->acceptedFileTypes([
                            '.xlsx',
                            '.xls',
                            '.csv',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                            'application/csv',
                        ])
                        ->helperText('Upload the filled template (.xlsx). Existing phone numbers are skipped.')
                        ->storeFiles(false)
                        ->visibility('private')
                        ->required(),
                    Select::make('group_id')
                        ->label('Default group')
                        ->options(fn () => MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->helperText('Used when a row does not specify a Group.'),
                ])
                ->action(function (array $data): void {
                    $result = app(StudentExcelImporter::class)->importUploadedFile($data['file'] ?? null, [
                        'user_id' => auth()->id(),
                        'department_id' => auth()->user()?->department_id,
                        'group_id' => $data['group_id'] ?? null,
                    ]);

                    $notification = Notification::make()
                        ->title($result->title())
                        ->body($result->body());

                    if ($result->isSuccess()) {
                        $notification->success();
                    } elseif ($result->isWarning()) {
                        $notification->warning();
                    } else {
                        $notification->danger();
                    }

                    $notification->send();

                    $this->resetTable();
                }),
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    CheckboxList::make('columns')
                        ->label('Columns')
                        ->options(MemberExport::availableColumns())
                        ->default(array_keys(MemberExport::availableColumns()))
                        ->columns(2)
                        ->required(),
                    Radio::make('format')
                        ->label('Format')
                        ->options(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)'])
                        ->default('xlsx')
                        ->required(),
                ])
                ->action(function (array $data) {
                    ProcessExportJob::dispatch(
                        exportClass: MemberExport::class,
                        columns: $data['columns'],
                        format: $data['format'],
                        userId: auth()->id(),
                    );

                    Notification::make()
                        ->title('Export queued')
                        ->body('Your export is being processed. You will be notified when it is ready.')
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->label('New Member')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
