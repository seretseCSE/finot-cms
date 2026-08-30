<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Filament\Resources\CourseResource\RelationManagers;
use Filament\Schemas\Schema;
use App\Models\Course;
use App\Models\CourseCategory;
use Filament\Actions;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CourseResource extends BaseResource
{
    protected static ?string $model = Course::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationLabel(): string
    {
        return 'Courses';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Course Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function canViewAny(): bool
    {
        return \App\Support\RoleGate::isAny(['superadmin', 'education_head', 'education_monitor']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\RoleGate::isAny(['education_head', 'education_monitor']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Course Information')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Title (English)')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('title_am')
                        ->label('Title (አማርኛ)')
                        ->maxLength(255),
                    Forms\Components\Select::make('category_id')
                        ->label('Category')
                        ->options(fn () => CourseCategoryResource::getTreeOptions())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Textarea::make('description')
                        ->label('Description (English)')
                        ->rows(3),
                    Forms\Components\Textarea::make('description_am')
                        ->label('Description (አማርኛ)')
                        ->rows(3),
                ])->columns(2),
            \Filament\Schemas\Components\Section::make('Metadata')
                ->schema([
                    Forms\Components\TextInput::make('instructor')
                        ->label('Instructor')
                        ->maxLength(255),
                    Forms\Components\Select::make('difficulty')
                        ->label('Difficulty')
                        ->options([
                            'Beginner' => 'Beginner',
                            'Intermediate' => 'Intermediate',
                            'Advanced' => 'Advanced',
                            'All Levels' => 'All Levels',
                        ]),
                    Forms\Components\TextInput::make('duration')
                        ->label('Duration (e.g. "8 weeks")')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('featured_image')
                        ->label('Featured Image URL')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icon')
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'Draft' => 'Draft',
                            'Published' => 'Published',
                            'Archived' => 'Archived',
                        ])
                        ->required()
                        ->default('Draft'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lesson_count')
                    ->label('Lessons')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('difficulty')
                    ->badge()
                    ->colors([
                        'success' => 'Beginner',
                        'warning' => 'Intermediate',
                        'danger' => 'Advanced',
                        'gray' => 'All Levels',
                    ]),
                Tables\Columns\SelectColumn::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Published' => 'Published',
                        'Archived' => 'Archived',
                    ])
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LessonsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
