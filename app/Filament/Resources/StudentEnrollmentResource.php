<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Filament\Resources\StudentEnrollmentResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Member;
use App\Models\StudentEnrollment;
use App\Rules\EnrollmentUniquePerYear;
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

class StudentEnrollmentResource extends Resource
{
    protected static ?string $model = StudentEnrollment::class;

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-user-plus'; }

    public static function getNavigationLabel(): string { return 'Enrollments'; }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        $hasRole = (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
        $hasActiveYear = AcademicYear::query()->where('is_active', true)->exists();
        return $hasRole && $hasActiveYear;
    }

    public static function canEdit($record): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        if (! $activeYear) {
            Notification::make()
                ->title('No active academic year')
                ->danger()
                ->send();
        }

        return $schema
            ->components([
                Forms\Components\Select::make('member_id')
                    ->label('Student')
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
                    ->getOptionLabelUsing(fn ($value): ?string => Member::query()->find($value)?->full_name)
                    ->required()
                    ->rules([
                        fn (?StudentEnrollment $record) => new EnrollmentUniquePerYear($activeYear?->id ?? 0, $record?->id),
                    ]),

                Forms\Components\Select::make('class_id')
                    ->label('Class')
                    ->options(fn () => ClassModel::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(fn () => $activeYear ? [$activeYear->id => $activeYear->name] : [])
                    ->default($activeYear?->id)
                    ->disabled()
                    ->dehydrated()
                    ->required(),

                EthiopianDatePicker::make('enrolled_date')
                    ->label('Enrolled Date')
                    ->default(now())
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'Enrolled' => 'Enrolled',
                        'Withdrawn' => 'Withdrawn',
                        'Completed' => 'Completed',
                        'Promoted' => 'Promoted',
                    ])
                    ->disabled()
                    ->dehydrated(false)
                    ->default('Enrolled'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(StudentEnrollment::query()->with(['member', 'class', 'academicYear']))
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')->label('Student Name')->searchable(),
                Tables\Columns\TextColumn::make('member.member_code')->label('Member Code')->searchable(),
                Tables\Columns\TextColumn::make('class.name')->label('Class')->sortable(),
                Tables\Columns\TextColumn::make('academicYear.name')->label('Academic Year')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'Enrolled',
                        'danger' => 'Withdrawn',
                        'gray' => 'Completed',
                        'warning' => 'Promoted',
                    ]),
                Tables\Columns\TextColumn::make('enrolled_date')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : ''),
                Tables\Columns\TextColumn::make('completion_date')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : ''),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Enrolled' => 'Enrolled',
                        'Withdrawn' => 'Withdrawn',
                        'Completed' => 'Completed',
                        'Promoted' => 'Promoted',
                    ]),
                Tables\Filters\SelectFilter::make('class_id')
                    ->label('Class')
                    ->options(fn () => ClassModel::query()->orderBy('name')->pluck('name', 'id')->all()),
                Tables\Filters\SelectFilter::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(fn () => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all()),
            ])
            ->actions([
                Actions\EditAction::make(),

                Actions\Action::make('withdraw')
                    ->label('Withdraw')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (StudentEnrollment $record): bool => $record->status === 'Enrolled')
                    ->form([
                        \App\Filament\Forms\Components\CustomOptionSelect::makeWithOther('withdrawal_reason', 'withdrawal_reason', [
                                'Moved Away' => 'Moved Away',
                                'Transferred' => 'Transferred',
                                'Graduated' => 'Graduated',
                            ], true),
                        Forms\Components\Textarea::make('withdrawal_notes')
                            ->label('Notes')
                            ->maxLength(500),
                    ])
                    ->action(function (StudentEnrollment $record, array $data): void {
                        $record->update([
                            'status' => 'Withdrawn',
                            'completion_date' => now()->toDateString(),
                            'completed_by' => Auth::id(),
                            'withdrawal_reason' => $data['withdrawal_reason'] ?? null,
                            'withdrawal_notes' => $data['withdrawal_notes'] ?? null,
                        ]);

                        \Log::channel('audit')->warning('Tier 2 Audit Log', [
                            'tier' => 2,
                            'action' => 'withdrawn',
                            'entity' => 'student_enrollment',
                            'enrollment_id' => $record->getKey(),
                            'member_id' => $record->member_id,
                            'academic_year_id' => $record->academic_year_id,
                            'new_value' => [
                                'reason' => $data['withdrawal_reason'] ?? null,
                                'notes' => $data['withdrawal_notes'] ?? null,
                                'completion_date' => now()->toDateString(),
                            ],
                            'performed_by' => Auth::id(),
                            'timestamp' => now()->toDateTimeString(),
                        ]);

                        Notification::make()->title('Student withdrawn')->success()->send();
                    }),

                Actions\Action::make('promote')
                    ->label('Promote')
                    ->icon('heroicon-o-arrow-up')
                    ->color('warning')
                    ->visible(function (StudentEnrollment $record): bool {
                        if ($record->status !== 'Enrolled') {
                            return false;
                        }

                        $year = $record->academicYear;

                        return (bool) ($year?->is_active && $year?->status === 'Active');
                    })
                    ->form([
                        Forms\Components\Select::make('target_class_id')
                            ->label('Target Class')
                            ->options(function (StudentEnrollment $record): array {
                                return ClassModel::query()
                                    ->active()
                                    ->whereKeyNot($record->class_id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->maxLength(500),
                    ])
                    ->action(function (StudentEnrollment $record, array $data): void {
                        $activeYear = AcademicYear::query()->where('is_active', true)->first();

                        if (! $activeYear || $activeYear->status !== 'Active') {
                            Notification::make()->title('No active academic year')->danger()->send();
                            return;
                        }

                        if ($record->academic_year_id !== $activeYear->id) {
                            Notification::make()->title('Cannot promote outside active academic year')->danger()->send();
                            return;
                        }

                        $targetClassId = (int) $data['target_class_id'];

                        DB::transaction(function () use ($record, $targetClassId, $activeYear, $data): void {
                            $fromClassId = $record->class_id;

                            $record->update([
                                'status' => 'Promoted',
                                'completion_date' => now()->toDateString(),
                                'completed_by' => Auth::id(),
                            ]);

                            $new = StudentEnrollment::create([
                                'member_id' => $record->member_id,
                                'class_id' => $targetClassId,
                                'academic_year_id' => $activeYear->id,
                                'enrolled_date' => now()->toDateString(),
                                'status' => 'Enrolled',
                                'enrolled_by' => Auth::id(),
                            ]);

                            \Log::channel('audit')->warning('Tier 2 Audit Log', [
                                'tier' => 2,
                                'action' => 'promoted',
                                'entity' => 'student_enrollment',
                                'enrollment_id' => $record->getKey(),
                                'member_id' => $record->member_id,
                                'academic_year_id' => $activeYear->id,
                                'old_value' => [
                                    'from_class' => $fromClassId,
                                ],
                                'new_value' => [
                                    'to_class' => $targetClassId,
                                    'notes' => $data['notes'] ?? null,
                                ],
                                'performed_by' => Auth::id(),
                                'timestamp' => now()->toDateTimeString(),
                            ]);

                            \Log::channel('audit')->warning('Tier 2 Audit Log', [
                                'tier' => 2,
                                'action' => 'enrolled',
                                'entity' => 'student_enrollment',
                                'enrollment_id' => $new->getKey(),
                                'new_value' => [
                                    'member_id' => $new->member_id,
                                    'class_id' => $new->class_id,
                                    'academic_year_id' => $new->academic_year_id,
                                ],
                                'performed_by' => Auth::id(),
                                'timestamp' => now()->toDateTimeString(),
                            ]);
                        });

                        Notification::make()->title('Student promoted')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentEnrollments::route('/'),
            'create' => Pages\CreateStudentEnrollment::route('/create'),
            'edit' => Pages\EditStudentEnrollment::route('/{record}/edit'),
        ];
    }
}

