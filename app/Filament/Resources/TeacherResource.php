<?php

namespace App\Filament\Resources;


use App\Filament\Support\HidesFromNavigation;
use App\Filament\Resources\TeacherResource\Pages;
use Filament\Schemas\Schema;
use App\Filament\Resources\TeacherResource\RelationManagers;
use App\Models\AcademicYear;
use App\Models\Member;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Filament\Actions;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeacherResource extends BaseResource
{
    use HidesFromNavigation;

    protected static ?string $model = Teacher::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationLabel(): string
    {
        return 'Teachers';
    }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->can('teachers.view');
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->can('teachers.create');
    }

    public static function canEdit($record): bool
    {
        return (bool) Auth::user()?->can('teachers.update');
    }

    public static function canDelete($record): bool
    {
        return (bool) Auth::user()?->can('teachers.delete') && $record->canDelete();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Radio::make('teacher_type')
                    ->label('Teacher Type')
                    ->options([
                        'external' => 'External (not a church member)',
                        'member' => 'Member (existing church member)',
                    ])
                    ->default('external')
                    ->inline()
                    ->live()
                    ->columnSpanFull(),

                // ── Member Teacher fields ─────────────────────────────────
                Select::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->preload()
                    ->hidden(fn (Get $get): bool => $get('teacher_type') !== 'member')
                    ->required(fn (Get $get): bool => $get('teacher_type') === 'member')
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
                            ->mapWithKeys(fn (Member $m) => [$m->id => $m->full_name.' ('.$m->member_code.')'])
                            ->all();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $member = Member::query()->find($value);

                        return $member ? $member->full_name.' ('.$member->member_code.')' : null;
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
                    }),

                // ── Shared / External fields ──────────────────────────────
                TextInput::make('full_name')
                    ->label('Full Name')
                    ->required(fn (Get $get): bool => $get('teacher_type') === 'external')
                    ->disabled(fn (Get $get): bool => $get('teacher_type') === 'member')
                    ->dehydrated()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->label('Phone')
                    ->required(fn (Get $get): bool => $get('teacher_type') === 'external')
                    ->disabled(fn (Get $get): bool => $get('teacher_type') === 'member')
                    ->dehydrated()
                    ->unique(ignoreRecord: true)
                    ->prefix(config('finot.phone_prefix', '+251'))
                    ->regex('/^[0-9]{9}$/')
                    ->placeholder('912345678')
                    ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                    ->maxLength(9)
                    ->formatStateUsing(function ($state) {
                        $prefix = config('finot.phone_prefix', '+251');

                        return $state ? preg_replace('/^(' . preg_quote($prefix, '/') . '|0)/', '', $state) : null;
                    })
                    ->dehydrateStateUsing(fn ($state) => $state ? config('finot.phone_prefix', '+251').$state : null),

                Textarea::make('qualifications')
                    ->label('Qualifications')
                    ->rows(3)
                    ->columnSpanFull(),

                Select::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                        'On Leave' => 'On Leave',
                        'Former' => 'Former',
                    ])
                    ->default('Active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Teacher::query()->withCount(['assignments as assigned_classes_count' => function ($query) {
                $query->select(DB::raw('count(distinct class_id)'))
                    ->where('assignment_status', 'Active')
                    ->where(function ($q) {
                        $q->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', now());
                    });
            }]))
            ->columns([
                Tables\Columns\TextColumn::make('teacher_code')->label('Teacher Code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('full_name')->label('Full Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Member' => 'primary',
                        'External' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Active' => 'success',
                        'Inactive' => 'gray',
                        'On Leave' => 'warning',
                        'Former' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('assigned_classes_count')
                    ->label('Assigned Classes'),

                Tables\Columns\TextColumn::make('attendance_rate')
                    ->label('Attendance Rate')
                    ->state(function (Teacher $record): string {
                        if (! Auth::user()?->can('teachers.update')) {
                            return '-';
                        }

                        $activeYear = AcademicYear::query()->where('status', 'Active')->first();
                        if (! $activeYear) {
                            return 'N/A';
                        }

                        $forTeacher = TeacherAttendance::query()
                            ->join('attendance_sessions', 'teacher_attendance.session_id', '=', 'attendance_sessions.id')
                            ->join('teacher_assignments', 'teacher_attendance.teacher_assignment_id', '=', 'teacher_assignments.id')
                            ->where('attendance_sessions.academic_year_id', $activeYear->id)
                            ->where('teacher_assignments.teacher_id', $record->getKey())
                            ->where('teacher_attendance.session_outcome', '!=', 'Cancelled');

                        $total = (clone $forTeacher)
                            ->where('teacher_attendance.attendance_status', '!=', 'Absent')
                            ->count();

                        $present = (clone $forTeacher)
                            ->where('teacher_attendance.attendance_status', 'Present')
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
                        Select::make('status')
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
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('New Teacher')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No teachers found')
            ->emptyStateDescription('Add your first teacher to get started.')
            ->emptyStateIcon('heroicon-o-academic-cap');
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
