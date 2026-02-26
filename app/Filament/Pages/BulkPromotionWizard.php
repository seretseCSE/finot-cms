<?php

namespace App\Filament\Pages;

use App\Models\Member;
use App\Models\MemberEducationHistory;
use App\Models\ClassModel;
use App\Models\AcademicYear;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BulkPromotionWizard extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-arrow-up-tray'; }

    protected string $view = 'filament.pages.bulk-promotion-wizard';

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationSort(): ?int { return 4; }

    public ?array $wizardData = [];

    public int $currentStep = 1;

    public function mount(): void
    {
        $this->form->fill([
            'from_academic_year_id' => AcademicYear::where('is_active', true)->first()?->id,
            'to_academic_year_id' => AcademicYear::where('is_active', false)->orderBy('end_date', 'desc')->first()?->id,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('selection')
                        ->label('Select Students')
                        ->schema([
                            Forms\Components\Select::make('from_academic_year_id')
                                ->label('From Academic Year')
                                ->options(AcademicYear::pluck('name', 'id'))
                                ->required()
                                ->reactive(),

                            Forms\Components\Select::make('from_class_id')
                                ->label('From Class')
                                ->options(function (callable $get) {
                                    $yearId = $get('from_academic_year_id');
                                    if (!$yearId) return [];

                                    return ClassModel::where('academic_year_id', $yearId)
                                        ->with('subject')
                                        ->get()
                                        ->mapWithKeys(fn ($class) => [$class->id => "{$class->subject->name} - {$class->name}"]);
                                })
                                ->required()
                                ->reactive(),

                            Forms\Components\Select::make('to_academic_year_id')
                                ->label('To Academic Year')
                                ->options(AcademicYear::where('id', '!=', function ($query) {
                                    return $query->where('is_active', true)->first()?->id;
                                })->pluck('name', 'id'))
                                ->required()
                                ->reactive(),

                            Forms\Components\Select::make('to_class_id')
                                ->label('To Class')
                                ->options(function (callable $get) {
                                    $yearId = $get('to_academic_year_id');
                                    if (!$yearId) return [];

                                    return ClassModel::where('academic_year_id', $yearId)
                                        ->with('subject')
                                        ->get()
                                        ->mapWithKeys(fn ($class) => [$class->id => "{$class->subject->name} - {$class->name}"]);
                                })
                                ->required(),

                            Forms\Components\Checkbox::make('promote_all')
                                ->label('Promote all students in class')
                                ->default(false)
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $set('selected_students', []);
                                    }
                                }),

                            Forms\Components\CheckboxList::make('selected_students')
                                ->label('Select Students')
                                ->options(function (callable $get) {
                                    $classId = $get('from_class_id');
                                    $promoteAll = $get('promote_all');

                                    if (!$classId || $promoteAll) return [];

                                    return Member::whereHas('educationHistory', function ($query) use ($classId) {
                                        $query->where('class_id', $classId);
                                    })->pluck('full_name', 'id');
                                })
                                ->columns(3)
                                ->hidden(fn (callable $get) => $get('promote_all')),
                        ]),

                    Forms\Components\Wizard\Step::make('review')
                        ->label('Review Selection')
                        ->schema([
                            Forms\Components\Placeholder::make('selection_summary')
                                ->label('Summary')
                                ->content(function (callable $get) {
                                    $fromClass = ClassModel::find($get('from_class_id'));
                                    $toClass = ClassModel::find($get('to_class_id'));
                                    $promoteAll = $get('promote_all');

                                    $studentCount = $promoteAll
                                        ? Member::whereHas('educationHistory', function ($query) use ($get) {
                                            $query->where('class_id', $get('from_class_id'));
                                        })->count()
                                        : count($get('selected_students') ?? []);

                                    return "Promoting {$studentCount} students from {$fromClass->subject->name} - {$fromClass->name} to {$toClass->subject->name} - {$toClass->name}";
                                }),

                            Forms\Components\Repeater::make('preview_students')
                                ->label('Students to Promote')
                                ->schema([
                                    Forms\Components\Placeholder::make('student_info')
                                        ->content(fn ($record) => $record),
                                ])
                                ->disableItemCreation()
                                ->disableItemDeletion()
                                ->disableItemMovement()
                                ->items(function (callable $get) {
                                    $promoteAll = $get('promote_all');

                                    if ($promoteAll) {
                                        return Member::whereHas('educationHistory', function ($query) use ($get) {
                                            $query->where('class_id', $get('from_class_id'));
                                        })->pluck('full_name')->toArray();
                                    }

                                    $selectedIds = $get('selected_students') ?? [];
                                    return Member::whereIn('id', $selectedIds)->pluck('full_name')->toArray();
                                }),
                        ]),

                    Forms\Components\Wizard\Step::make('confirm')
                        ->label('Confirm & Promote')
                        ->schema([
                            Forms\Components\Placeholder::make('confirmation_message')
                                ->label('Confirmation')
                                ->content('Please confirm that you want to promote these students. This action cannot be undone.'),

                            Forms\Components\Checkbox::make('confirm_promotion')
                                ->label('I confirm that I want to proceed with the promotion')
                                ->required(),
                        ]),
                ])
                ->columnSpanFull()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    $this->wizardData = $state;
                }),
            ]);
    }

    public function promoteStudents()
    {
        $data = $this->form->getState();

        if (!$data['confirm_promotion']) {
            $this->notify('danger', 'Please confirm the promotion before proceeding.');
            return;
        }

        $promoteAll = $data['promote_all'];
        $fromClassId = $data['from_class_id'];
        $toClassId = $data['to_class_id'];
        $toAcademicYearId = $data['to_academic_year_id'];

        // Get students to promote
        if ($promoteAll) {
            $students = Member::whereHas('educationHistory', function ($query) use ($fromClassId) {
                $query->where('class_id', $fromClassId);
            })->get();
        } else {
            $selectedIds = $data['selected_students'] ?? [];
            $students = Member::whereIn('id', $selectedIds)->get();
        }

        $promotedCount = 0;
        $failedCount = 0;

        foreach ($students as $student) {
            try {
                // Check if student already has education history for the target academic year
                $existingRecord = MemberEducationHistory::where('member_id', $student->id)
                    ->where('academic_year_id', $toAcademicYearId)
                    ->first();

                if ($existingRecord) {
                    // Update existing record
                    $existingRecord->update([
                        'class_id' => $toClassId,
                        'start_date' => now(),
                    ]);
                } else {
                    // Create new education history record
                    MemberEducationHistory::create([
                        'member_id' => $student->id,
                        'class_id' => $toClassId,
                        'academic_year_id' => $toAcademicYearId,
                        'start_date' => now(),
                        'status' => 'active',
                    ]);
                }

                $promotedCount++;
            } catch (\Exception $e) {
                $failedCount++;
                \Log::error("Failed to promote student {$student->id}: " . $e->getMessage());
            }
        }

        // Reset form
        $this->form->fill([
            'from_academic_year_id' => AcademicYear::where('is_active', true)->first()?->id,
            'to_academic_year_id' => AcademicYear::where('is_active', false)->orderBy('end_date', 'desc')->first()?->id,
        ]);

        $this->notify('success', "Successfully promoted {$promotedCount} students. " . ($failedCount > 0 ? "Failed to promote {$failedCount} students." : ''));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Member::query()
                    ->when($this->wizardData['from_class_id'] ?? null, function ($query, $classId) {
                        $query->whereHas('educationHistory', function ($query) use ($classId) {
                            $query->where('class_id', $classId);
                        });
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Student Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('member_id')
                    ->label('Member ID'),
                Tables\Columns\TextColumn::make('currentClass')
                    ->label('Current Class')
                    ->getStateUsing(function ($record) {
                        $currentEducation = $record->educationHistory()
                            ->where('academic_year_id', $this->wizardData['from_academic_year_id'] ?? null)
                            ->with('class.subject')
                            ->first();

                        return $currentEducation
                            ? "{$currentEducation->class->subject->name} - {$currentEducation->class->name}"
                            : 'N/A';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_timeline')
                    ->label('View Timeline')
                    ->icon('heroicono-clock')
                    ->url(fn ($record) => route('filament.admin.resources.members.timeline', $record)),
            ]);
    }
}

