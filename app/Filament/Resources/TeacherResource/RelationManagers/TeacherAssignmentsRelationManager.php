<?php

namespace App\Filament\Resources\TeacherResource\RelationManagers;

use App\Models\AcademicYear;
use Filament\Schemas\Schema;
use App\Models\ClassModel;
use App\Models\Subject;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TeacherAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Assignments';

    public function form(Schema $schema): Schema
    {
        $activeYear = AcademicYear::query()->where('status', 'Active')->first();

        return $schema->components([
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

                Forms\Components\Select::make('assignment_status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                        'On Leave' => 'On Leave',
                    ])
                    ->default('Active')
                    ->required(),

                Forms\Components\DatePicker::make('effective_from')
                    ->label('Effective From')
                    ->default(now())
                    ->required(),

                Forms\Components\DatePicker::make('effective_to')
                    ->label('Effective To')
                    ->nullable()
                    ->helperText('Leave empty for ongoing assignment'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('class.name')
            ->columns([
                Tables\Columns\TextColumn::make('class.name')->label('Class')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Subject')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('assignment_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'Active' => 'success',
                        'Inactive' => 'danger',
                        'On Leave' => 'warning',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('class_id')
                    ->label('Class')
                    ->options(fn () => ClassModel::query()->orderBy('name')->pluck('name', 'id')->all()),
                Tables\Filters\SelectFilter::make('subject_id')
                    ->label('Subject')
                    ->options(fn () => Subject::query()->orderBy('name')->pluck('name', 'id')->all()),
                Tables\Filters\SelectFilter::make('assignment_status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                        'On Leave' => 'On Leave',
                    ]),
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
