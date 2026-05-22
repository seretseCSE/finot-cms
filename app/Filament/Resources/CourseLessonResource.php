<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseLessonResource\Pages;
use Filament\Schemas\Schema;
use App\Models\CourseLesson;
use Filament\Actions;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CourseLessonResource extends BaseResource
{
    protected static ?string $model = CourseLesson::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationLabel(): string
    {
        return 'Course Lessons';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Course Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['superadmin', 'admin', 'education_head']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Lesson Information')
                ->schema([
                    Forms\Components\Select::make('course_id')
                        ->label('Course')
                        ->relationship('course', 'title')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('title')
                        ->label('Title (English)')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('title_am')
                        ->label('Title (አማርኛ)')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('display_order')
                        ->label('Display Order')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'Draft' => 'Draft',
                            'Published' => 'Published',
                        ])
                        ->required()
                        ->default('Draft'),
                ])->columns(2),
            \Filament\Schemas\Components\Section::make('Content')
                ->schema([
                    Forms\Components\RichEditor::make('content')
                        ->label('Content (English)')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('course-lessons'),
                    Forms\Components\RichEditor::make('content_am')
                        ->label('Content (አማርኛ)')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('course-lessons'),
                ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('course.title')
                    ->label('Course')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\SelectColumn::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Published' => 'Published',
                    ])
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('display_order')
            ->reorderable('display_order')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseLessons::route('/'),
            'create' => Pages\CreateCourseLesson::route('/create'),
            'edit' => Pages\EditCourseLesson::route('/{record}/edit'),
        ];
    }
}
