<?php

namespace App\Filament\Resources\TourResource\RelationManagers;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TourAttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    protected static ?string $title = 'Attendance Records';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\Radio::make('status')
                    ->label('Attendance Status')
                    ->options([
                        'Present' => 'Present',
                        'Not Present' => 'Not Present',
                    ])
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->helperText('e.g., "Called at 9:15 AM, on the way"'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('passenger.full_name')
            ->columns([
                Tables\Columns\TextColumn::make('passenger.full_name')
                    ->label('Passenger Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('passenger.phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('passenger.passenger_count')
                    ->label('Passengers')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Present' => 'success',
                        'Not Present' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('marked_at')
                    ->label('Marked At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('markedBy.name')
                    ->label('Marked By')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Present' => 'Present',
                        'Not Present' => 'Not Present',
                    ]),
            ])
            ->headerActions([
                Actions\Action::make('mark_all_present')
                    ->label('Mark All Present')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function () {
                        $count = 0;
                        foreach ($this->getRecords() as $record) {
                            if ($record->status === 'Not Present') {
                                $record->markPresent();
                                $count++;
                            }
                        }
                        Notification::make()
                            ->title("Marked {$count} passenger(s) as Present")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('mark_all_not_present')
                    ->label('Mark All Not Present')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->action(function () {
                        $count = 0;
                        foreach ($this->getRecords() as $record) {
                            if ($record->status === 'Present') {
                                $record->markNotPresent();
                                $count++;
                            }
                        }
                        Notification::make()
                            ->title("Marked {$count} passenger(s) as Not Present")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('complete_attendance')
                    ->label('Complete Attendance')
                    ->icon('heroicon-o-check')
                    ->color('primary')
                    ->visible(fn () => $this->ownerRecord->status === 'In Progress')
                    ->action(function () {
                        $this->ownerRecord->complete();
                        Notification::make()
                            ->title('Attendance session completed')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Actions\Action::make('mark_present')
                    ->label('Present')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => $record && $record->status === 'Not Present')
                    ->action(function ($record) {
                        $record->markPresent();
                        Notification::make()
                            ->title("{$record->passenger->full_name} marked as Present")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('mark_not_present')
                    ->label('Not Present')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record && $record->status === 'Present')
                    ->action(function ($record) {
                        $record->markNotPresent();
                        Notification::make()
                            ->title("{$record->passenger->full_name} marked as Not Present")
                            ->warning()
                            ->send();
                    }),

                Actions\EditAction::make()
                    ->modalHeading('Edit Attendance')
                    ->mutateFormDataUsing(function (array $data, $record) {
                        $oldStatus = $record->status;
                        $newStatus = $data['status'];

                        if ($oldStatus !== $newStatus) {
                            if ($newStatus === 'Present') {
                                $record->markPresent($data['notes'] ?? null);
                            } else {
                                $record->markNotPresent($data['notes'] ?? null);
                            }
                        } else {
                            $record->update(['notes' => $data['notes'] ?? null]);
                        }

                        return $data;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_mark_present')
                        ->label('Mark Present')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'Not Present') {
                                    $record->markPresent();
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("Marked {$count} passenger(s) as Present")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_mark_not_present')
                        ->label('Mark Not Present')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'Present') {
                                    $record->markNotPresent();
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("Marked {$count} passenger(s) as Not Present")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No attendance records')
            ->emptyStateDescription('Attendance records will appear here once generated.')
            ->emptyStateIcon('heroicon-o-users');
    }

    protected function getTableSummary(): array
    {
        $records = $this->getRecords();

        $presentCount = $records->where('status', 'Present')->count();
        $notPresentCount = $records->where('status', 'Not Present')->count();
        $totalCount = $records->count();

        return [
            Tables\Columns\TextColumn::make('summary')
                ->label('Summary')
                ->formatStateUsing(function () use ($presentCount, $notPresentCount, $totalCount) {
                    return "{$presentCount} Present / {$notPresentCount} Not Present (Total: {$totalCount})";
                }),
        ];
    }
}
