<?php

namespace App\Filament\Resources\SyncConflictsResource\Pages;

use App\Filament\Resources\SyncConflictsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

class ListSyncConflicts extends ListRecords
{
    protected static string $resource = SyncConflictsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\BulkAction::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->deselectRecordsAfterCompletion()
                ->action(function (Collection $records) {
                    $csv = $records->map(function ($record) {
                        return [
                            'Student' => $record->student->full_name,
                            'Class' => $record->session->class->name,
                            'Date' => $record->session->session_date,
                            'First Value' => $record->first_value,
                            'Second Value (Winner)' => $record->second_value,
                            'First User' => $record->firstUser->name,
                            'Second User' => $record->secondUser->name,
                            'Conflict Time' => $record->created_at,
                        ];
                    })->toArray();

                    $filename = 'sync-conflicts-' . now()->format('Y-m-d') . '.csv';
                    $headers = [
                        'Student',
                        'Class',
                        'Date',
                        'First Value',
                        'Second Value (Winner)',
                        'First User',
                        'Second User',
                        'Conflict Time',
                    ];

                    return response()->streamDownload($csv, $filename, $headers);
                }),
        ];
    }
}

