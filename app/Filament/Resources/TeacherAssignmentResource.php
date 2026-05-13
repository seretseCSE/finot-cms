<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherAssignmentResource\Pages;
use Filament\Schemas\Schema;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TeacherAssignmentResource extends Resource
{
    protected static ?string $model = TeacherAssignment::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationLabel(): string
    {
        return 'Teacher Assignments';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Assignment Details')
                    ->schema([
                        Select::make('teacher_id')
                            ->label('Teacher')
                            ->options(Teacher::query()->where('status', 'Active')->pluck('full_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('class_id')
                            ->label('Class')
                            ->options(ClassModel::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('subject_id')
                            ->label('Subject')
                            ->options(Subject::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Duration')
                    ->schema([
                        DatePicker::make('assigned_date')
                            ->label('Assigned Date')
                            ->required()
                            ->default(now()),

                        DatePicker::make('effective_from')
                            ->label('Effective From')
                            ->required()
                            ->default(now()),

                        DatePicker::make('effective_to')
                            ->label('Effective To')
                            ->nullable()
                            ->helperText('Leave blank for ongoing assignment.'),

                        Select::make('assignment_status')
                            ->label('Status')
                            ->options([
                                'Active' => 'Active',
                                'Inactive' => 'Inactive',
                                'On Leave' => 'On Leave',
                                'Completed' => 'Completed',
                            ])
                            ->required()
                            ->default('Active'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $activeAcademicYearId = AcademicYear::where('status', 'Active')
            ->where('phase', 'current')
            ->value('id')
            ?? AcademicYear::where('status', 'Active')->orderBy('start_date', 'desc')->value('id');

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($activeAcademicYearId): void {
                if ($activeAcademicYearId) {
                    $query->where('academic_year_id', $activeAcademicYearId);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('teacher.full_name')
                    ->label('Teacher')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('class.name')
                    ->label('Class')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Subject')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('assigned_date')
                    ->label('Assigned')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_from')
                    ->label('From')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('effective_to')
                    ->label('To')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('M j, Y') : 'Ongoing')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assignment_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Active' => 'success',
                        'On Leave' => 'warning',
                        'Inactive' => 'danger',
                        'Completed' => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('teacher')
                    ->relationship('teacher', 'full_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('class')
                    ->relationship('class', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('subject')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('assignment_status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                        'On Leave' => 'On Leave',
                        'Completed' => 'Completed',
                    ])
                    ->default('Active'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\BulkAction::make('end_assignment')
                        ->label('End Assignment')
                        ->icon('heroicon-o-check-circle')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $records->each(function (TeacherAssignment $record): void {
                                $record->update([
                                    'assignment_status' => 'Completed',
                                    'effective_to' => $record->effective_to ?? now(),
                                ]);
                            });
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('End Assignments')
                        ->modalDescription('Are you sure you want to mark the selected assignments as completed?')
                        ->modalSubmitActionLabel('Yes, end assignments'),
                ]),
            ])
            ->defaultSort('assigned_date', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        TeacherAssignment::whereNotNull('effective_to')
            ->where('effective_to', '<', now())
            ->where('assignment_status', '!=', 'Completed')
            ->update(['assignment_status' => 'Completed']);

        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeacherAssignments::route('/'),
            'create' => Pages\CreateTeacherAssignment::route('/create'),
            'edit' => Pages\EditTeacherAssignment::route('/{record}/edit'),
        ];
    }
}
