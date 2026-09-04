<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassMaterialResource\Pages;
use App\Models\ClassMaterial;
use App\Services\Learning\ClassContentNotifier;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ClassMaterialResource extends BaseResource
{
    protected static ?string $model = ClassMaterial::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Class Work';
    }

    public static function getNavigationSort(): ?int
    {
        return 22;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder-open';
    }

    public static function getNavigationLabel(): string
    {
        return 'Class materials';
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
            Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
            Forms\Components\FileUpload::make('file_path')
                ->label('File')
                ->disk('public')
                ->directory('class-materials')
                ->required(),
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
                Tables\Columns\IconColumn::make('is_published')->boolean(),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('publish')
                    ->visible(fn (ClassMaterial $record) => ! $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (ClassMaterial $record) {
                        $record->update(['is_published' => true, 'published_at' => now()]);
                        app(ClassContentNotifier::class)->materialPublished($record->fresh());
                        Notification::make()->title('Published & notified')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassMaterials::route('/'),
            'create' => Pages\CreateClassMaterial::route('/create'),
            'edit' => Pages\EditClassMaterial::route('/{record}/edit'),
        ];
    }
}
