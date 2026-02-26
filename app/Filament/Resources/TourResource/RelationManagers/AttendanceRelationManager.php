<?php

namespace App\Filament\Resources\TourResource\RelationManagers;

use App\Models\TourAttendanceSession;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceSessions';

    protected static ?string $title = 'Attendance';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->color(fn ($record) => match($record->status) {
                        'Open' => 'yellow',
                        'Completed' => 'green',
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
                    ->formatStateUsing(fn ($record) => ($record->attendance_summary['present_percentage'] ?? 0) . '%'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Open' => 'Open',
                        'Completed' => 'Completed',
                    ]),
            ])
            ->headerActions([
                Actions\Action::make('generate_attendance')
                    ->label('Generate Attendance List')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->visible(fn () => $this->ownerRecord->confirmedPassengers->isNotEmpty() && !$this->ownerRecord->attendanceSessions()->exists())
                    ->form([
                        Forms\Components\Placeholder::make('confirmation')
                            ->label('Confirmation')
                            ->content(function () {
                                $confirmedCount = $this->ownerRecord->confirmedPassengers->sum('passenger_count');
                                return "Generate attendance list from {$confirmedCount} confirmed passengers?";
                            }),
                    ])
                    ->action(function () {
                        // Create attendance session
                        $session = TourAttendanceSession::create([
                            'tour_id' => $this->ownerRecord->id,
                            'session_date' => $this->ownerRecord->tour_date,
                            'status' => 'Open',
                            'created_by' => auth()->id(),
                        ]);

                        // Create attendance records for all confirmed passengers
                        foreach ($this->ownerRecord->confirmedPassengers as $passenger) {
                            $session->attendanceRecords()->create([
                                'passenger_id' => $passenger->id,
                                'status' => 'Not Present',
                            ]);
                        }

                        // Log to audit trail
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
                    }),

                Actions\Action::make('view_attendance')
                    ->label('View Attendance')
                    ->icon('heroicon-eye')
                    ->url(fn ($record) => route('filament.admin.resources.tour-attendances.index', ['tour' => $this->ownerRecord->id, 'session' => $record->id]))
                    ->visible(fn () => $this->ownerRecord->attendanceSessions()->exists()),
            ])
            ->actions([
                Actions\Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-eye')
                    ->url(fn ($record) => route('filament.admin.resources.tour-attendances.index', ['tour' => $this->ownerRecord->id, 'session' => $record->id])),

                Actions\Action::make('complete_attendance')
                    ->label('Complete Attendance')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'Open')
                    ->action(function ($record) {
                        $record->complete();
                    }),
            ])
            ->emptyStateActions([
                Actions\Action::make('generate_attendance')
                    ->label('Generate Attendance List')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->visible(fn () => $this->ownerRecord->confirmedPassengers->isNotEmpty())
                    ->action(function () {
                        // Create attendance session
                        $session = TourAttendanceSession::create([
                            'tour_id' => $this->ownerRecord->id,
                            'session_date' => $this->ownerRecord->tour_date,
                            'status' => 'Open',
                            'created_by' => auth()->id(),
                        ]);

                        // Create attendance records for all confirmed passengers
                        foreach ($this->ownerRecord->confirmedPassengers as $passenger) {
                            $session->attendanceRecords()->create([
                                'passenger_id' => $passenger->id,
                                'status' => 'Not Present',
                            ]);
                        }
                    }),
            ])
            ->emptyStateHeading('No attendance sessions')
            ->emptyStateDescription('Generate attendance from confirmed passengers to get started.')
            ->emptyStateIcon('heroicon-o-users');
    }
}

