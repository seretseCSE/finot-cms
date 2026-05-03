<?php

namespace App\Filament\Resources\OfflineAttendanceSyncs;

use App\Filament\Resources\OfflineAttendanceSyncs\Pages\ListOfflineAttendanceSyncs;
use App\Models\OfflineAttendanceSync;
use App\Services\OfflineSyncService;
use App\Filament\Resources\BaseResource;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;

class OfflineAttendanceSyncResource extends BaseResource
{
    protected static ?string $model = OfflineAttendanceSync::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-signal-slash';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Mobile & Offline';
    }

    public static function getNavigationLabel(): string
    {
        return 'Offline Attendance Sync';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Marked By')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('session.session_date')
                    ->label('Session Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'Present' => 'success',
                        'Absent' => 'danger',
                        'Late' => 'warning',
                        'Excused' => 'info',
                        'Permission' => 'gray',
                    }),

                Tables\Columns\TextColumn::make('sync_status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'pending' => 'warning',
                        'synced' => 'success',
                        'conflict' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('marked_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('synced_at')
                    ->dateTime()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('conflict_reason')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sync_status')
                    ->options([
                        'pending' => 'Pending',
                        'synced' => 'Synced',
                        'conflict' => 'Conflict',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Present' => 'Present',
                        'Absent' => 'Absent',
                        'Excused' => 'Excused',
                        'Late' => 'Late',
                        'Permission' => 'Permission',
                    ]),
            ])
            ->headerActions([
                Action::make('process_syncs')
                    ->label('Process Pending Syncs')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function (): void {
                        $service = new OfflineSyncService();
                        $results = $service->processPendingSyncs();

                        \Filament\Notifications\Notification::make()
                            ->title('Sync Complete')
                            ->body("Synced: {$results['synced']}, Conflicts: {$results['conflicts']}")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Process Offline Attendance')
                    ->modalDescription('This will process all pending offline attendance records and sync them with the main database.'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOfflineAttendanceSyncs::route('/'),
        ];
    }
}
