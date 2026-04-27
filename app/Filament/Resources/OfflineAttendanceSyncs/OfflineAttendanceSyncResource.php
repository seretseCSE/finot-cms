<?php

namespace App\Filament\Resources\OfflineAttendanceSyncs;

use App\Filament\Resources\OfflineAttendanceSyncs\Pages\CreateOfflineAttendanceSync;
use Filament\Schemas\Schema;
use App\Filament\Resources\OfflineAttendanceSyncs\Pages\EditOfflineAttendanceSync;
use App\Filament\Resources\OfflineAttendanceSyncs\Pages\ListOfflineAttendanceSyncs;
use App\Models\OfflineAttendanceSync;
use App\Services\OfflineSyncService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Filament\Resources\BaseResource;
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->disabled(),

                Select::make('session_id')
                    ->relationship('session', 'id')
                    ->disabled(),

                Select::make('status')
                    ->options([
                        'Present' => 'Present',
                        'Absent' => 'Absent',
                        'Excused' => 'Excused',
                        'Late' => 'Late',
                        'Permission' => 'Permission',
                    ])
                    ->disabled(),

                DateTimePicker::make('marked_at')
                    ->disabled(),

                Select::make('sync_status')
                    ->options([
                        'pending' => 'Pending',
                        'synced' => 'Synced',
                        'conflict' => 'Conflict',
                    ]),

                Textarea::make('conflict_reason')
                    ->rows(2)
                    ->disabled(),
            ]);
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

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'Present',
                        'danger' => 'Absent',
                        'warning' => 'Late',
                        'info' => 'Excused',
                        'gray' => 'Permission',
                    ]),

                Tables\Columns\BadgeColumn::make('sync_status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'synced',
                        'danger' => 'conflict',
                    ]),

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

                        Notification::make()
                            ->title('Sync Complete')
                            ->body("Synced: {$results['synced']}, Conflicts: {$results['conflicts']}")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Process Offline Attendance')
                    ->modalDescription('This will process all pending offline attendance records and sync them with the main database.'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'create' => CreateOfflineAttendanceSync::route('/create'),
            'edit' => EditOfflineAttendanceSync::route('/{record}/edit'),
        ];
    }
}
