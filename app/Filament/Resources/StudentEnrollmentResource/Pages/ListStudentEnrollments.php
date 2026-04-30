<?php

namespace App\Filament\Resources\StudentEnrollmentResource\Pages;

use App\Exports\StudentEnrollmentExport;
use App\Filament\Resources\StudentEnrollmentResource;
use App\Jobs\ProcessExportJob;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\StudentEnrollment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListStudentEnrollments extends ListRecords
{
    protected static string $resource = StudentEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulk_enroll')
                ->label('Bulk Enroll Students')
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->visible(fn (): bool => (bool) Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']))
                ->form([
                    Forms\Components\Select::make('group_id')
                        ->label('Member Group')
                        ->options(fn () => MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),

                    Forms\Components\Select::make('student_ids')
                        ->label('Students')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function (Get $get): array {
                            $groupId = $get('group_id');
                            $activeYear = AcademicYear::query()->where('status', 'Active')->first();

                            if (! $groupId || ! $activeYear) {
                                return [];
                            }

                            $alreadyEnrolled = StudentEnrollment::query()
                                ->where('academic_year_id', $activeYear->id)
                                ->where('status', 'Enrolled')
                                ->pluck('member_id')
                                ->toArray();

                            return Member::withoutDepartmentScope()
                                ->whereIn('status', ['Active', 'Member'])
                                ->whereHas('groupAssignments', fn ($g) => $g->where('member_group_assignments.group_id', $groupId)->whereNull('effective_to'))
                                ->whereNotIn('id', $alreadyEnrolled)
                                ->orderBy('first_name')
                                ->get()
                                ->mapWithKeys(fn ($m) => [$m->id => $m->full_name.' ('.$m->member_code.')'])
                                ->all();
                        })
                        ->required(),

                    Forms\Components\Select::make('class_id')
                        ->label('Class')
                        ->options(fn () => ClassModel::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('academic_year_id')
                        ->label('Academic Year')
                        ->options(function () {
                            $activeYear = AcademicYear::query()->where('status', 'Active')->first();

                            return $activeYear ? [$activeYear->id => $activeYear->name] : [];
                        })
                        ->default(fn () => AcademicYear::query()->where('status', 'Active')->first()?->id)
                        ->disabled()
                        ->dehydrated()
                        ->required(),

                    Forms\Components\TextInput::make('enrolled_date')
                        ->label('Enrolled Date')
                        ->type('date')
                        ->default(now()->toDateString())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $activeYear = AcademicYear::query()->where('status', 'Active')->first();

                    if (! $activeYear) {
                        Notification::make()->title('No active academic year')->danger()->send();

                        return;
                    }

                    $studentIds = $data['student_ids'] ?? [];
                    $classId = $data['class_id'];
                    $enrolledDate = $data['enrolled_date'];
                    $enrolledCount = 0;
                    $skippedCount = 0;

                    DB::transaction(function () use ($studentIds, $classId, $activeYear, $enrolledDate, &$enrolledCount, &$skippedCount): void {
                        foreach ($studentIds as $memberId) {
                            $exists = StudentEnrollment::query()
                                ->where('member_id', $memberId)
                                ->where('academic_year_id', $activeYear->id)
                                ->where('status', 'Enrolled')
                                ->exists();

                            if ($exists) {
                                $skippedCount++;

                                continue;
                            }

                            StudentEnrollment::create([
                                'member_id' => $memberId,
                                'class_id' => $classId,
                                'academic_year_id' => $activeYear->id,
                                'enrolled_date' => $enrolledDate,
                                'status' => 'Enrolled',
                                'enrolled_by' => Auth::id(),
                            ]);

                            $enrolledCount++;
                        }
                    });

                    $message = "{$enrolledCount} students enrolled";
                    if ($skippedCount > 0) {
                        $message .= ", {$skippedCount} already enrolled and skipped";
                    }

                    Notification::make()->title($message)->success()->send();
                }),

            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    CheckboxList::make('columns')
                        ->label('Columns')
                        ->options(StudentEnrollmentExport::availableColumns())
                        ->default(array_keys(StudentEnrollmentExport::availableColumns()))
                        ->columns(2)
                        ->required(),
                    Radio::make('format')
                        ->label('Format')
                        ->options(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)'])
                        ->default('xlsx')
                        ->required(),
                ])
                ->action(function (array $data) {
                    ProcessExportJob::dispatchSync(
                        exportClass: StudentEnrollmentExport::class,
                        columns: $data['columns'],
                        format: $data['format'],
                        userId: auth()->id(),
                    );

                    Notification::make()
                        ->title('Export queued')
                        ->body('Your export is being processed. You will be notified when it is ready.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
