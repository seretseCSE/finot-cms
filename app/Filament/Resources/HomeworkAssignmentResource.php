<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HomeworkAssignmentResource\Pages;
use App\Models\HomeworkAssignment;
use App\Services\Learning\ClassContentNotifier;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class HomeworkAssignmentResource extends BaseResource
{
    protected static ?string $model = HomeworkAssignment::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Class Work';
    }

    public static function getNavigationSort(): ?int
    {
        return 21;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationLabel(): string
    {
        return 'Homework';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('class_id')
                ->relationship('class', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('subject_id')
                ->relationship('subject', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Forms\Components\TextInput::make('title')->required()->maxLength(200),
            Forms\Components\Textarea::make('instructions')->rows(4)->columnSpanFull(),
            Forms\Components\FileUpload::make('file_path')
                ->label('Attachment')
                ->disk('public')
                ->directory('homework')
                ->nullable(),
            Forms\Components\DateTimePicker::make('due_at')->nullable(),
            Forms\Components\Toggle::make('is_published')->label('Publish now')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('class.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('subject.name')->placeholder('—'),
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('due_at')->dateTime()->sortable(),
                Tables\Columns\IconColumn::make('is_published')->boolean(),
            ])
            ->defaultSort('due_at', 'desc')
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('publish')
                    ->visible(fn (HomeworkAssignment $record) => ! $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (HomeworkAssignment $record) {
                        $record->update(['is_published' => true, 'published_at' => now()]);
                        app(ClassContentNotifier::class)->homeworkPublished($record->fresh());
                        Notification::make()->title('Published & notified')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomeworkAssignments::route('/'),
            'create' => Pages\CreateHomeworkAssignment::route('/create'),
            'edit' => Pages\EditHomeworkAssignment::route('/{record}/edit'),
        ];
    }
}
