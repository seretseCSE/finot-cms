<?php

namespace App\Filament\Resources\TourResource\RelationManagers;

use App\Models\TourAttendanceSession;
use App\Filament\Resources\TourAttendanceResource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceSessions';

    protected static ?string $title = 'Attendance';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\Hidden::make('tour_id')
                    ->default(fn () => $this->ownerRecord->id),

                Forms\Components\Hidden::make('session_date')
                    ->default(fn () => $this->ownerRecord->tour_date),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Session ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ethiopian_session_date')
                    ->label('Session Date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => match ($record->status) {
                        'Open' => 'yellow',
                        'Completed' => 'green',
                        'Locked' => 'red',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('attendance_summary.present')
                    ->label('Present')
                    ->formatStateUsing(fn ($record) => $record->attendance_summary['present'] ?? 0),

                Tables\Columns\TextColumn::make('attendance_summary.not_present')
                    ->label('Not Present')
                    ->formatStateUsing(fn ($record) => $record->attendance_summary['not_present'] ?? 0),

                Tables\Columns\TextColumn::make('attendance_summary.total')
                    ->label('Total')
                    ->formatStateUsing(fn ($record) => $record->attendance_summary['total'] ?? 0),

                Tables\Columns\TextColumn::make('attendance_summary.present_percentage')
                    ->label('Present %')
                    ->formatStateUsing(fn ($record) => ($record->attendance_summary['present_percentage'] ?? 0).'%'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Open' => 'Open',
                        'Completed' => 'Completed',
                        'Locked' => 'Locked',
                    ]),
            ])
            ->headerActions([
                Actions\Action::make('generate_attendance')
                    ->label('Generate Attendance List')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->visible(fn () => $this->ownerRecord->confirmedPassengers->isNotEmpty() && ! $this->ownerRecord->attendanceSessions()->exists())
                    ->form([
                        Forms\Components\Placeholder::make('confirmation')
                            ->label('Confirmation')
                            ->content(function () {
                                $confirmedCount = $this->ownerRecord->confirmedPassengers->sum('passenger_count');

                                return "Generate attendance list from {$confirmedCount} confirmed passengers?";
                            }),
                    ])
                    ->action(function () {
                        $session = TourAttendanceSession::create([
                            'tour_id' => $this->ownerRecord->id,
                            'session_date' => $this->ownerRecord->tour_date,
                            'status' => 'Open',
                            'created_by' => auth()->id(),
                        ]);

                        foreach ($this->ownerRecord->confirmedPassengers as $passenger) {
                            $session->attendanceRecords()->create([
                                'passenger_id' => $passenger->id,
                                'status' => 'Not Present',
                            ]);
                        }

                        \Log::channel('audit')->info('Tier 1 Audit Log', [
                            'tier' => 1,
                            'action' => 'tour_attendance_generated',
                            'entity_id' => $session->id,
                            'entity_type' => 'tour_attendance_session',
                            'old_value' => null,
                            'new_value' => json_encode([
                                'tour_id' => $this->ownerRecord->id,
                                'passenger_count' => $this->ownerRecord->confirmedPassengers->count(),
                            ]),
                            'user_id' => auth()->id(),
                            'timestamp' => now()->toDateTimeString(),
                        ]);

                        Notification::make()
                            ->title('Attendance list generated')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('mark_attendance')
                    ->label('Mark Attendance')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->visible(fn ($record): bool => $record && $record->status === 'Open')
                    ->url(fn ($record) => TourAttendanceResource::getUrl('index', [
                        'session' => $record->id,
                    ])),

                Action::make('complete_attendance')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => $record && $record->status === 'Open')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->complete();
                        Notification::make()
                            ->title('Attendance session completed')
                            ->success()
                            ->send();
                    }),

                Action::make('lock_session')
                    ->label('Lock')
                    ->icon('heroicon-o-lock')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record && $record->status === 'Open')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Lock Reason')
                            ->required()
                            ->minLength(10)
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->lock($data['reason']);
                        Notification::make()
                            ->title('Attendance session locked')
                            ->success()
                            ->send();
                    }),

                Action::make('unlock_session')
                    ->label('Unlock')
                    ->icon('heroicon-o-unlock')
                    ->color('warning')
                    ->visible(fn ($record): bool => $record && $record->status === 'Locked')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Unlock Reason')
                            ->required()
                            ->minLength(10)
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->unlock($data['reason']);
                        Notification::make()
                            ->title('Attendance session unlocked')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('lock_selected')
                        ->label('Lock Selected')
                        ->icon('heroicon-o-lock')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Lock Reason')
                                ->required()
                                ->minLength(10)
                                ->rows(3),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                if ($record->status === 'Open') {
                                    $record->lock($data['reason']);
                                }
                            }
                            Notification::make()
                                ->title('Selected sessions locked')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('unlock_selected')
                        ->label('Unlock Selected')
                        ->icon('heroicon-o-unlock')
                        ->color('warning')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Unlock Reason')
                                ->required()
                                ->minLength(10)
                                ->rows(3),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                if ($record->status === 'Locked') {
                                    $record->unlock($data['reason']);
                                }
                            }
                            Notification::make()
                                ->title('Selected sessions unlocked')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateActions([
                Actions\Action::make('generate_attendance')
                    ->label('Generate Attendance List')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->visible(fn () => $this->ownerRecord->confirmedPassengers->isNotEmpty())
                    ->action(function () {
                        $session = TourAttendanceSession::create([
                            'tour_id' => $this->ownerRecord->id,
                            'session_date' => $this->ownerRecord->tour_date,
                            'status' => 'Open',
                            'created_by' => auth()->id(),
                        ]);

                        foreach ($this->ownerRecord->confirmedPassengers as $passenger) {
                            $session->attendanceRecords()->create([
                                'passenger_id' => $passenger->id,
                                'status' => 'Not Present',
                            ]);
                        }

                        Notification::make()
                            ->title('Attendance list generated')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No attendance sessions')
            ->emptyStateDescription('Generate attendance from confirmed passengers to get started.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
