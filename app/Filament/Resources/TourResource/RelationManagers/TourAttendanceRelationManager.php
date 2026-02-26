<?php

namespace App\Filament\Resources\TourResource\RelationManagers;

use App\Models\TourAttendance;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TourAttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    protected static ?string $title = 'Attendance Records';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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

                Tables\Columns\ToggleColumn::make('status')
                    ->label('Status')
                    ->onColor('success')
                    ->offColor('danger')
                    ->afterStateUpdated(function ($record, $state) {
                        if ($state === 'Present') {
                            $record->markPresent();
                        } else {
                            $record->markNotPresent();
                        }
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
                        foreach ($this->getRecords() as $record) {
                            if ($record->status === 'Not Present') {
                                $record->markPresent();
                            }
                        }
                    }),

                Actions\Action::make('complete_attendance')
                    ->label('Complete Attendance')
                    ->icon('heroicon-o-check')
                    ->color('primary')
                    ->visible(fn () => $this->ownerRecord->status === 'Open')
                    ->action(function () {
                        $this->ownerRecord->complete();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
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
                            // Update notes if status didn't change
                            $record->update(['notes' => $data['notes'] ?? null]);
                        }

                        return $data;
                    }),

                Actions\Action::make('call_passenger')
                    ->label('Call')
                    ->icon('heroicon-o-phone')
                    ->color('primary')
                    ->visible(fn ($record) => $record->status === 'Not Present')
                    ->url(fn ($record) => 'tel:' . $record->passenger->phone)
                    ->openUrlInNewTab(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_present')
                        ->label('Mark Present')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->status === 'Not Present') {
                                    $record->markPresent();
                                }
                            }
                        }),

                    Tables\Actions\BulkAction::make('mark_not_present')
                        ->label('Mark Not Present')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->status === 'Present') {
                                    $record->markNotPresent();
                                }
                            }
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

