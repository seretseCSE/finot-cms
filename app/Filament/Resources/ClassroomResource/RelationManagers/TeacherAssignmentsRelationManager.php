<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use App\Models\AcademicYear;
use Filament\Schemas\Schema;
use App\Models\Subject;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TeacherAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'teacherAssignments';

    protected static ?string $title = 'Teacher Assignments';

    public function form(Schema $schema): Schema
    {
        $activeYear = AcademicYear::query()->where('status', 'Active')->first();

        return $schema->components([
                Forms\Components\Select::make('teacher_id')
                    ->label('Teacher')
                    ->options(fn () => \App\Models\Teacher::query()->where('status', 'Active')->orderBy('full_name')->pluck('full_name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('subject_id')
                    ->label('Subject')
                    ->options(fn () => Subject::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
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

                Forms\Components\DatePicker::make('effective_from')
                    ->label('Effective From')
                    ->default(now())
                    ->required(),

                Forms\Components\DatePicker::make('effective_to')
                    ->label('Effective To')
                    ->helperText('Leave empty if assignment is ongoing')
                    ->after('effective_from'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('teacher.full_name')
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('teacher.full_name')->label('Teacher')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Subject')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('effective_from')->label('Effective From')->date()->sortable(),
                Tables\Columns\TextColumn::make('effective_to')->label('Effective To')->date()->sortable()->placeholder('Ongoing'),
                Tables\Columns\TextColumn::make('assignment_status')->label('Status')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subject_id')
                    ->label('Subject')
                    ->options(fn () => Subject::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->visible(fn () => Auth::user()?->can('teacher_assignments.create')),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn () => Auth::user()?->can('teacher_assignments.update')),
                Actions\DeleteAction::make()
                    ->visible(fn () => Auth::user()?->can('teacher_assignments.delete')),
                Actions\RestoreAction::make()
                    ->visible(fn () => Auth::user()?->can('teacher_assignments.delete')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->can('teacher_assignments.delete')),
                ]),
            ]);
    }
}
