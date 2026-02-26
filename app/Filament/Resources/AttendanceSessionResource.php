<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Filament\Resources\AttendanceSessionResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\ClassModel;
use App\Models\StudentAttendance;
use App\Models\TeacherAttendance;
use App\Models\TeacherAssignment;
use App\Models\Teacher;
use App\Models\StudentEnrollment;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceSessionResource extends Resource
{
    protected static ?string $model = AttendanceSession::class;

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-clipboard-document-list'; }

    public static function getNavigationLabel(): string { return 'Attendance Sessions'; }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->hasRole(['education_monitor', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin']);
    }

    public static function canMarkAttendance($record): bool
    {
        if ($record->status === 'Locked') {
            return false;
        }

        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        return $activeYear && $record->academic_year_id === $activeYear->id;
    }

    public static function form(Schema $schema): Schema
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        return $schema
            ->components([
                Forms\Components\Select::make('class_id')
                    ->label('Class')
                    ->options(fn () => ClassModel::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),

                EthiopianDatePicker::make('session_date')
                    ->label('Session Date')
                    ->required()
                    ->unique(
                        ignoreRecord: fn ($record) => $record,
                        modifyRuleUsing: function ($rule) use ($activeYear) {
                            return $rule->where('academic_year_id', $activeYear?->id ?? 0);
                        }
                    )
                    ->validationMessages([
                        'unique' => 'Session already exists for this class on this date.',
                    ]),

                Forms\Components\Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(fn () => $activeYear ? [$activeYear->id => $activeYear->name] : [])
                    ->default($activeYear?->id)
                    ->disabled()
                    ->dehydrated()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(AttendanceSession::query()->with(['class', 'academicYear']))
            ->columns([
                Tables\Columns\TextColumn::make('class.name')->label('Class')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('session_date')
                    ->label('Session Date')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : '')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'Open',
                        'warning' => 'Completed',
                        'danger' => 'Locked',
                    ]),
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
                Tables\Filters\SelectFilter::make('class_id')
                    ->label('Class')
                    ->options(fn () => ClassModel::query()->orderBy('name')->pluck('name', 'id')->all()),
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
                    ->url(fn (AttendanceSession $record): string => static::getUrl('mark', ['record' => $record]))
                    ->visible(fn (AttendanceSession $record): bool => static::canMarkAttendance($record)),

                Actions\Action::make('lock')
                    ->label('Lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->visible(fn (AttendanceSession $record): bool => in_array($record->status, ['Open', 'Completed']))
                    ->requiresConfirmation()
                    ->action(function (AttendanceSession $record): void {
                        $record->update([
                            'status' => 'Locked',
                            'locked_at' => now(),
                            'locked_by' => Auth::id(),
                        ]);

                        Notification::make()->title('Session locked')->success()->send();
                    }),

                Actions\Action::make('unlock')
                    ->label('Unlock')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->visible(fn (AttendanceSession $record): bool => $record->isLocked() && Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']))
                    ->form([
                        Forms\Components\Textarea::make('justification')
                            ->label('Justification')
                            ->required()
                            ->minLength(20)
                            ->rows(3),
                    ])
                    ->action(function (AttendanceSession $record, array $data): void {
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

                        Notification::make()->title('Session unlocked')->success()->send();
                    }),
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
