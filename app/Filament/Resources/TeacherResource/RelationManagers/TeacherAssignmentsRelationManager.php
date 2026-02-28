<?php

namespace App\Filament\Resources\TeacherResource\RelationManagers;

use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TeacherAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Assignments';

    public function form(Schema $schema): Schema
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
            ->recordTitleAttribute('class.name')
            ->columns([
                Tables\Columns\TextColumn::make('class.name')->label('Class')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Subject')->sortable()->searchable(),
                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->is_active),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('class_id')
                    ->label('Class')
                    ->options(fn () => ClassModel::query()->orderBy('name')->pluck('name', 'id')->all()),
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
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->hasRole(['admin', 'superadmin'])),
                ]),
            ]);
    }
}
