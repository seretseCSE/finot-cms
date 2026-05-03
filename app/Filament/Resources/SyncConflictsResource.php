<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SyncConflictsResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Models\AttendanceSyncConflict;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SyncConflictsResource extends Resource
{
    protected static ?string $model = AttendanceSyncConflict::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function getNavigationLabel(): string
    {
        return 'Sync Conflicts';
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin']);
    }

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(AttendanceSyncConflict::query()->with(['student', 'session', 'firstUser', 'secondUser']))
            ->columns([
                Tables\Columns\TextColumn::make('student.full_name')
                    ->label('Student')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('session.class.name')
                    ->label('Class')
                    ->sortable(),
                Tables\Columns\TextColumn::make('session.session_date')
                    ->label('Date')
                    ->state(fn ($record) => $record->session?->session_date ? app(EthiopianDateHelper::class)->toString($record->session->session_date) : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_value')
                    ->label('First Value')
                    ->sortable(),
                Tables\Columns\TextColumn::make('second_value')
                    ->label('Second Value (Winner)')
                    ->sortable(),
                Tables\Columns\TextColumn::make('firstUser.name')
                    ->label('First User')
                    ->sortable(),
                Tables\Columns\TextColumn::make('secondUser.name')
                    ->label('Second User')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Conflict Time')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->required(),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until')
                            ->required()
                            ->afterOrEqual('from'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;
                        if ($from && $until) {
                            $query->whereHas('session', fn ($q) => $q->whereBetween('session_date', [$from, $until]));
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => is_null($record->winner_value) && Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']))
                    ->form([
                        Forms\Components\Radio::make('chosen_value')
                            ->label('Choose winning value')
                            ->options(fn ($record) => [
                                $record->first_value => "First: {$record->first_value}",
                                $record->second_value => "Second: {$record->second_value}",
                            ])
                            ->required(),
                    ])
                    ->action(function ($record, array $data): void {
                        $chosen = $data['chosen_value'];

                        $record->update(['winner_value' => $chosen]);

                        if ($record->studentAttendance) {
                            $record->studentAttendance->update([
                                'status' => $chosen,
                                'marked_by' => Auth::id(),
                                'marked_at' => now(),
                            ]);
                        }

                        \Log::channel('audit')->warning('Tier 2 Audit Log', [
                            'tier' => 2,
                            'action' => 'conflict_resolved',
                            'entity' => 'attendance_sync_conflict',
                            'conflict_id' => $record->getKey(),
                            'session_id' => $record->session_id,
                            'student_id' => $record->student_id,
                            'winner_value' => $chosen,
                            'performed_by' => Auth::id(),
                            'timestamp' => now()->toDateTimeString(),
                        ]);

                        \Filament\Notifications\Notification::make()->title('Conflict resolved')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSyncConflicts::route('/'),
        ];
    }
}
