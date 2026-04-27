<?php

namespace App\Filament\Resources\SchoolClassResource\RelationManagers;

use App\Models\AcademicYear;
use Filament\Schemas\Schema;
use App\Models\Subject;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TeachersRelationManager extends RelationManager
{
    protected static string $relationship = 'teacherAssignments';

    protected static ?string $title = 'Teachers';

    public function form(Schema $schema): Schema
    {
        $activeYear = AcademicYear::query()->where('status', 'Active')->first();

        return $schema->components([
                Forms\Components\Select::make('teacher_id')
                    ->label('Teacher')
                    ->options(fn () => \App\Models\Teacher::query()->active()->orderBy('full_name')->pluck('full_name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('subject_id')
                    ->label('Subject')
                    ->options(fn () => Subject::query()->active()->orderBy('name')->pluck('name', 'id')->all())
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

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('teacher.full_name')
            ->columns([
                Tables\Columns\TextColumn::make('teacher.full_name')->label('Teacher')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Subject')->sortable()->searchable(),
                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subject_id')
                    ->label('Subject')
                    ->options(fn () => Subject::query()->orderBy('name')->pluck('name', 'id')->all()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->visible(fn () => Auth::user()?->hasRole(['education_head', 'admin', 'superadmin'])),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn () => Auth::user()?->hasRole(['education_head', 'admin', 'superadmin'])),
                Actions\DeleteAction::make()
                    ->visible(fn () => Auth::user()?->hasRole(['admin', 'superadmin'])),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->hasRole(['admin', 'superadmin'])),
                ]),
            ]);
    }
}
