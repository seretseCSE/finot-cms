<?php

namespace App\Filament\Resources;

use App\Filament\Support\HidesFromNavigation;
use App\Filament\Resources\StudentEnrollmentResource\Pages;
use Filament\Schemas\Schema;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\StudentEnrollment;
use App\Rules\EnrollmentUniquePerYear;
use App\Services\Academics\BatchPromotionService;
use App\Services\Academics\PromotionBoardService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentEnrollmentResource extends BaseResource
{
    use HidesFromNavigation;

    protected static ?string $model = StudentEnrollment::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-plus';
    }

    public static function getNavigationLabel(): string
    {
        return 'Enrollments';
    }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->can('student_enrollments.view');
    }

    public static function canCreate(): bool
    {
        $hasActiveYear = AcademicYear::query()->where('status', 'Active')->exists();

        return Auth::user()?->can('student_enrollments.create') && $hasActiveYear;
    }

    public static function canEdit($record): bool
    {
        return (bool) Auth::user()?->can('student_enrollments.update');
    }

    public static function form(Schema $schema): Schema
    {
        $activeYear = AcademicYear::query()->where('status', 'Active')->first();

        if (! $activeYear) {
            Notification::make()
                ->title('No Active Academic Year')
                ->body('Please activate an academic year before creating enrollments.')
                ->warning()
                ->persistent()
                ->send();
        }

        return $schema->components([
                Forms\Components\Select::make('group_id')
                    ->label('Member Group')
                    ->options(fn () => MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($set) {
                        $set('member_id', null);
                    })
                    ->dehydrated(false),

                Forms\Components\Select::make('member_id')
                    ->label('Student')
                    ->searchable()
                    ->preload()
                    ->options(function ($get): array {
                        $groupId = $get('group_id');
                        $activeYear = AcademicYear::query()->where('status', 'Active')->first();

                        if (! $activeYear) {
                            return [];
                        }

                        $alreadyEnrolled = StudentEnrollment::query()
                            ->where('academic_year_id', $activeYear->id)
                            ->where('status', 'Enrolled')
                            ->pluck('member_id')
                            ->toArray();

                        return Member::withoutDepartmentScope()
                            ->whereIn('status', ['Active', 'Member'])
                            ->when($groupId, fn ($q) => $q->whereHas('groupAssignments', fn ($g) => $g->where('member_group_assignments.group_id', $groupId)->whereNull('effective_to')))
                            ->whereNotIn('id', $alreadyEnrolled)
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn ($m) => [$m->id => $m->full_name.' ('.$m->member_code.')'])
                            ->all();
                    })
                    ->required()
                    ->rules(function (?StudentEnrollment $record): array {
                        $activeYear = AcademicYear::query()->where('status', 'Active')->first();

                        if (! $activeYear) {
                            return [];
                        }

                        return [
                            new EnrollmentUniquePerYear($activeYear->id, $record?->id),
                        ];
                    }),

                Forms\Components\Select::make('class_id')
                    ->label('Class')
                    ->options(fn () => ClassModel::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(fn () => $activeYear ? [$activeYear->id => $activeYear->name] : [])
                    ->default(fn () => $activeYear?->id)
                    ->disabled()
                    ->dehydrated()
                    ->required(),

                Forms\Components\Select::make('batch_id')
                    ->label('Batch')
                    ->relationship('batch', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->nullable(),

                Forms\Components\Select::make('batch_year_id')
                    ->label('Batch year')
                    ->options(function (callable $get): array {
                        $batchId = $get('batch_id');
                        if (! $batchId) {
                            return [];
                        }

                        return \App\Models\BatchYear::query()
                            ->where('batch_id', $batchId)
                            ->orderBy('program_year')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->nullable(),

                Forms\Components\TextInput::make('enrolled_date')
                    ->label('Enrolled Date')
                    ->type('date')
                    ->default(now()->toDateString())
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'Enrolled' => 'Enrolled',
                        'Withdrawn' => 'Withdrawn',
                        'Completed' => 'Completed',
                        'Promoted' => 'Promoted',
                    ])
                    ->default('Enrolled')
                    ->disabled()
            ]);
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        // Format enrolled_date for HTML date input
        if (isset($data['enrolled_date']) && $data['enrolled_date']) {
            $data['enrolled_date'] = date('Y-m-d', strtotime($data['enrolled_date']));
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                StudentEnrollment::query()
                    ->select('student_enrollments.*')
                    ->with(['member', 'class', 'academicYear', 'batch', 'batchYear'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('student_full_name')
                    ->label('Student Name')
                    ->searchable()
                    ->formatStateUsing(fn ($record) => $record->student_full_name),
                Tables\Columns\TextColumn::make('class.name')->label('Class')->sortable(),
                Tables\Columns\TextColumn::make('batch.name')->label('Batch')->sortable(),
                Tables\Columns\TextColumn::make('batchYear.name')->label('Batch year')->sortable(),
                Tables\Columns\TextColumn::make('academicYear.name')->label('Academic Year')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Enrolled' => 'success',
                        'Withdrawn' => 'danger',
                        'Completed' => 'gray',
                        'Promoted' => 'warning',
                    }),
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
                    ])
                    ->default('Enrolled'),
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
                    ->label('Pass (next class)')
                    ->icon('heroicon-o-arrow-up')
                    ->color('warning')
                    ->visible(function (StudentEnrollment $record): bool {
                        if ($record->status !== 'Enrolled' || ! $record->batch_year_id) {
                            return false;
                        }

                        $year = $record->academicYear;

                        return (bool) ($year?->is_active && $year?->status === 'Active');
                    })
                    ->form(function (StudentEnrollment $record): array {
                        $boards = app(PromotionBoardService::class);
                        $options = $boards->nextClassOptions($record->class);

                        return [
                            Forms\Components\Select::make('target_class_id')
                                ->label('Next class')
                                ->options($options)
                                ->default(array_key_first($options))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Same batch — moves to the next program year class.'),
                            Forms\Components\Textarea::make('notes')
                                ->label('Notes')
                                ->maxLength(500),
                        ];
                    })
                    ->action(function (StudentEnrollment $record, array $data): void {
                        try {
                            app(BatchPromotionService::class)->promote(
                                $record,
                                (int) $data['target_class_id'],
                                Auth::user(),
                            );

                            Notification::make()
                                ->title('Student passed to next class')
                                ->body('Batch unchanged. Use Promotion board to pass a whole class at once.')
                                ->success()
                                ->send();
                        } catch (\Illuminate\Validation\ValidationException $e) {
                            Notification::make()
                                ->title('Could not pass student')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        }
                    }),

                Actions\Action::make('fail_transfer')
                    ->label('Fail (leave batch)')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn (StudentEnrollment $record): bool => $record->status === 'Enrolled' && $record->batch_year_id)
                    ->form([
                        Forms\Components\Select::make('target_batch_year_id')
                            ->label('Target batch year (same program year)')
                            ->options(function (StudentEnrollment $record): array {
                                $programYear = $record->batchYear?->program_year;

                                return \App\Models\BatchYear::query()
                                    ->with('batch')
                                    ->when($programYear, fn ($q) => $q->where('program_year', $programYear))
                                    ->where('batch_id', '!=', $record->batch_id)
                                    ->get()
                                    ->mapWithKeys(fn ($y) => [$y->id => ($y->batch?->name.' — '.$y->name)])
                                    ->all();
                            })
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('target_class_id')
                            ->label('Target class')
                            ->options(fn () => ClassModel::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (StudentEnrollment $record, array $data): void {
                        app(\App\Services\Academics\BatchPromotionService::class)->failTransfer(
                            $record,
                            (int) $data['target_batch_year_id'],
                            (int) $data['target_class_id'],
                            Auth::user(),
                        );
                        Notification::make()->title('Student moved to new batch (passed credits kept)')->success()->send();
                    }),

                Actions\Action::make('undo_promotion')
                    ->label('Undo Promotion')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (StudentEnrollment $record): bool => $record->status === 'Promoted' && $record->promoted_to_enrollment_id !== null)
                    ->requiresConfirmation()
                    ->action(function (StudentEnrollment $record): void {
                        DB::transaction(function () use ($record): void {
                            $newEnrollment = StudentEnrollment::find($record->promoted_to_enrollment_id);

                            if ($newEnrollment) {
                                $newEnrollment->delete();
                            }

                            $record->update([
                                'status' => 'Enrolled',
                                'completion_date' => null,
                                'completed_by' => null,
                                'promoted_to_enrollment_id' => null,
                            ]);

                            \Log::channel('audit')->warning('Tier 2 Audit Log', [
                                'tier' => 2,
                                'action' => 'promotion_undone',
                                'entity' => 'student_enrollment',
                                'enrollment_id' => $record->getKey(),
                                'member_id' => $record->member_id,
                                'performed_by' => Auth::id(),
                                'timestamp' => now()->toDateTimeString(),
                            ]);
                        });

                        Notification::make()->title('Promotion undone')->success()->send();
                    }),

                Actions\Action::make('cancel_enrollment')
                    ->label('Cancel Enrollment')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (StudentEnrollment $record): bool => $record->status === 'Enrolled')
                    ->requiresConfirmation()
                    ->action(function (StudentEnrollment $record): void {
                        $record->delete();

                        \Log::channel('audit')->warning('Tier 2 Audit Log', [
                            'tier' => 2,
                            'action' => 'enrollment_cancelled',
                            'entity' => 'student_enrollment',
                            'enrollment_id' => $record->getKey(),
                            'member_id' => $record->member_id,
                            'performed_by' => Auth::id(),
                            'timestamp' => now()->toDateTimeString(),
                        ]);

                        Notification::make()->title('Enrollment cancelled')->success()->send();
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
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
