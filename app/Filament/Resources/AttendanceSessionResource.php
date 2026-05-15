<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceSessionResource\Pages;
use Filament\Schemas\Schema;
use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\ClassModel;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use App\Enums\Roles;

class AttendanceSessionResource extends BaseResource
{
    protected static ?string $model = AttendanceSession::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationLabel(): string
    {
        return 'Attendance Sessions';
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->can('attendance_sessions.view');
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->can('attendance_sessions.create');
    }

    public static function canEdit($record): bool
    {
        return (bool) Auth::user()?->can('attendance_sessions.update');
    }

    public static function canDelete($record): bool
    {
        return (bool) Auth::user()?->can('attendance_sessions.delete');
    }

    public static function canMarkAttendance($record): bool
    {
        if ($record->status === 'Locked') {
            return false;
        }

        $activeYear = AcademicYear::query()->where('status', 'Active')->first();

        return $activeYear && $record->academic_year_id === $activeYear->id;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\CheckboxList::make('class_ids')
                    ->label('Classes')
                    ->options(fn () => ClassModel::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->columns(2)
                    ->bulkToggleable(),

                EthiopianDatePicker::make('session_date')
                    ->label('Session Date')
                    ->required()
                    ->helperText('One session per day. Multiple classes can be included in a single session.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(AttendanceSession::query()->with(['classes', 'academicYear']))
            ->columns([
                Tables\Columns\TextColumn::make('session_date')
                    ->label('Session Date')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : '')
                    ->sortable(),
                Tables\Columns\TextColumn::make('classes_names')
                    ->label('Classes')
                    ->state(fn (AttendanceSession $record): string => $record->classes->pluck('name')->join(', ') ?: '—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Open' => 'success',
                        'Completed' => 'warning',
                        'Locked' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('student_attendance_summary')
                    ->label('Student Attendance')
                    ->state(function (AttendanceSession $record): string {
                        $present = $record->studentAttendance()->where('status', 'Present')->count();
                        $total = $record->studentAttendance()->count();

                        return $total > 0 ? "{$present}/{$total}" : '-';
                    }),
                Tables\Columns\TextColumn::make('teacher_attendance_summary')
                    ->label('Teacher Attendance')
                    ->state(function (AttendanceSession $record): string {
                        $present = $record->teacherAttendance()->where('attendance_status', 'Present')->count();
                        $total = $record->teacherAttendance()->count();

                        return $total > 0 ? "{$present}/{$total}" : '-';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('classes')
                    ->label('Class')
                    ->relationship('classes', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Open' => 'Open',
                        'Completed' => 'Completed',
                        'Locked' => 'Locked',
                    ]),
            ])
            ->actions([
                Actions\Action::make('mark_attendance')
                    ->label('Mark Attendance')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (AttendanceSession $record): string => static::getUrl('mark', ['record' => $record->getKey()]))
                    ->visible(fn (AttendanceSession $record): bool => static::canMarkAttendance($record)),

                Actions\EditAction::make(),

                Actions\DeleteAction::make()
                    ->visible(fn (AttendanceSession $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                Actions\BulkAction::make('lock')
                    ->label('Lock Selected')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Lock Selected Sessions')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                        foreach ($records as $record) {
                            if (! in_array($record->status, ['Open', 'Completed'])) {
                                continue;
                            }
                            $record->update([
                                'status' => 'Locked',
                                'locked_at' => now(),
                                'locked_by' => Auth::id(),
                            ]);
                        }

                        Notification::make()->title('Sessions locked')->success()->send();
                    })
                    ->visible(fn (): bool => Auth::user()?->can('attendance_sessions.update') ?? false),

                Actions\BulkAction::make('unlock')
                    ->label('Unlock Selected')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('justification')
                            ->label('Justification')
                            ->required()
                            ->minLength(20)
                            ->rows(3),
                    ])
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                        foreach ($records as $record) {
                            if (! $record->isLocked()) {
                                continue;
                            }
                            $record->update([
                                'status' => 'Open',
                                'unlock_justification' => $data['justification'],
                                'unlocked_at' => now(),
                                'unlocked_by' => Auth::id(),
                            ]);

                            \Log::channel('audit')->warning('Tier 2 Audit Log', [
                                'tier' => 2,
                                'action' => 'session_unlocked',
                                'entity' => 'attendance_session',
                                'session_id' => $record->getKey(),
                                'new_value' => [
                                    'justification' => $data['justification'],
                                    'unlocked_by' => Auth::id(),
                                    'unlocked_at' => now()->toDateTimeString(),
                                ],
                                'performed_by' => Auth::id(),
                                'timestamp' => now()->toDateTimeString(),
                            ]);
                        }

                        Notification::make()->title('Sessions unlocked')->success()->send();
                    })
                    ->visible(fn (): bool => Auth::user()?->can('attendance_sessions.update') ?? false),

                Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => Auth::user()?->can('attendance_sessions.delete') ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceSessions::route('/'),
            'create' => Pages\CreateAttendanceSession::route('/create'),
            'edit' => Pages\EditAttendanceSession::route('/{record}/edit'),
            'view' => Pages\ViewAttendanceSession::route('/{record}'),
            'mark' => Pages\MarkAttendance::route('/{record}/mark'),
        ];
    }
}
