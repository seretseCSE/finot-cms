<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherResource\Pages;
use App\Models\Member;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherAssignment;
use App\Models\AcademicYear;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TeacherResource extends Resource
{
    protected static ?string $model = Teacher::class;

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-academic-cap'; }

    public static function getNavigationLabel(): string { return 'Teachers'; }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']) && $record->canDelete();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('teacher_mode')
                    ->tabs([
                        Tabs\Tab::make('External Teacher')
                            ->schema([
                                Forms\Components\Hidden::make('member_id')
                                    ->dehydrated(true),

                                Forms\Components\TextInput::make('full_name')
                                    ->label('Full Name')
                                    ->required(fn (callable $get): bool => blank($get('member_id')))
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Phone')
                                    ->required(fn (callable $get): bool => blank($get('member_id')))
                                    ->maxLength(20)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Textarea::make('qualifications')
                                    ->label('Qualifications')
                                    ->rows(3),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Inactive' => 'Inactive',
                                        'On Leave' => 'On Leave',
                                        'Former' => 'Former',
                                    ])
                                    ->default('Active'),
                            ]),

                        Tabs\Tab::make('Member Teacher')
                            ->schema([
                                Forms\Components\Select::make('member_id')
                                    ->label('Member')
                                    ->searchable()
                                    ->preload()
                                    ->getSearchResultsUsing(function (string $search): array {
                                        return Member::query()
                                            ->whereIn('status', ['Active', 'Member'])
                                            ->where(function (Builder $q) use ($search): void {
                                                $q->where('first_name', 'like', "%{$search}%")
                                                    ->orWhere('father_name', 'like', "%{$search}%")
                                                    ->orWhere('phone', 'like', "%{$search}%")
                                                    ->orWhere('member_code', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn (Member $m) => [$m->id => $m->full_name . ' (' . $m->member_code . ')'])
                                            ->all();
                                    })
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        $member = Member::query()->find($state);

                                        if (! $member) {
                                            return;
                                        }

                                        $set('full_name', $member->full_name);
                                        $set('phone', $member->phone);
                                    })
                                    ->required(fn () => true),

                                Forms\Components\TextInput::make('full_name')
                                    ->label('Full Name')
                                    ->disabled()
                                    ->dehydrated(),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Phone')
                                    ->disabled()
                                    ->dehydrated(),

                                Forms\Components\Textarea::make('qualifications')
                                    ->label('Qualifications')
                                    ->rows(3),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Inactive' => 'Inactive',
                                        'On Leave' => 'On Leave',
                                        'Former' => 'Former',
                                    ])
                                    ->default('Active'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Teacher::query())
            ->columns([
                Tables\Columns\TextColumn::make('teacher_code')->label('Teacher Code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('full_name')->label('Full Name')->sortable()->searchable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'Member',
                        'gray' => 'External',
                    ]),
                Tables\Columns\TextColumn::make('phone')->label('Phone')->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'Active',
                        'gray' => 'Inactive',
                        'warning' => 'On Leave',
                        'danger' => 'Former',
                    ]),
                Tables\Columns\TextColumn::make('assigned_classes_count')
                    ->label('Assigned Classes')
                    ->state(fn () => 0),

                Tables\Columns\TextColumn::make('attendance_rate')
                    ->label('Attendance Rate')
                    ->state(function (Teacher $record): string {
                        if (! Auth::user()?->hasRole(['education_head', 'admin', 'superadmin'])) {
                            return '-';
                        }

                        $activeYear = AcademicYear::query()->where('is_active', true)->first();
                        if (! $activeYear) return 'N/A';

                        $total = TeacherAttendance::query()
                            ->join('attendance_sessions', 'teacher_attendance.session_id', '=', 'attendance_sessions.id')
                            ->where('attendance_sessions.academic_year_id', $activeYear->id)
                            ->where('teacher_attendance.teacher_id', $record->getKey())
                            ->where('teacher_attendance.attendance_status', '!=', 'Absent')
                            ->where('teacher_attendance.session_outcome', '!=', 'Cancelled')
                            ->count();

                        $present = TeacherAttendance::query()
                            ->join('attendance_sessions', 'teacher_attendance.session_id', '=', 'attendance_sessions.id')
                            ->where('attendance_sessions.academic_year_id', $activeYear->id)
                            ->where('teacher_attendance.teacher_id', $record->getKey())
                            ->where('teacher_attendance.attendance_status', 'Present')
                            ->where('teacher_attendance.session_outcome', '!=', 'Cancelled')
                            ->count();

                        $rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                        $color = $rate >= 90 ? 'success' : ($rate >= 70 ? 'warning' : 'danger');

                        return "<span class=\"text-{$color}-600 font-semibold\">{$rate}%</span>";
                    })
                    ->html(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'External' => 'External',
                        'Member' => 'Member',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (blank($value)) {
                            return $query;
                        }
                        if ($value === 'Member') {
                            return $query->whereNotNull('member_id');
                        }
                        return $query->whereNull('member_id');
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                        'On Leave' => 'On Leave',
                        'Former' => 'Former',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn (Teacher $record) => $record->canDelete()),
                Actions\Action::make('set_status')
                    ->label('Change Status')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options([
                                'Active' => 'Active',
                                'Inactive' => 'Inactive',
                                'On Leave' => 'On Leave',
                                'Former' => 'Former',
                            ])
                            ->required(),
                    ])
                    ->action(function (Teacher $record, array $data): void {
                        $old = $record->status;
                        $new = $data['status'];

                        if ($new === 'Former') {
                            $record->update(['status' => 'Former']);
                            $record->delete();
                        } else {
                            if ($record->trashed()) {
                                $record->restore();
                            }
                            $record->update(['status' => $new]);
                        }

                        \Log::channel('audit')->warning('Tier 2 Audit Log', [
                            'tier' => 2,
                            'action' => 'teacher_status_changed',
                            'teacher_id' => $record->getKey(),
                            'old_value' => ['status' => $old],
                            'new_value' => ['status' => $new],
                            'performed_by' => Auth::id(),
                            'timestamp' => now()->toDateTimeString(),
                        ]);

                        Notification::make()->title('Status updated')->success()->send();
                    }),
                Actions\RestoreAction::make()
                    ->visible(fn (Teacher $record) => $record->trashed()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeachers::route('/'),
            'create' => Pages\CreateTeacher::route('/create'),
            'edit' => Pages\EditTeacher::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TeacherAssignmentsRelationManager::class,
        ];
    }
}

