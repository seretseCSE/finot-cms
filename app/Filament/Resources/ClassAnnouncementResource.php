<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassAnnouncementResource\Pages;
use App\Models\ClassAnnouncement;
use App\Services\Learning\ClassContentNotifier;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ClassAnnouncementResource extends BaseResource
{
    protected static ?string $model = ClassAnnouncement::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Class Work';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-megaphone';
    }

    public static function getNavigationLabel(): string
    {
        return 'Class announcements';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('class_id')
                ->label('Class')
                ->relationship('class', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('title')->required()->maxLength(200),
            Forms\Components\Textarea::make('body')->required()->rows(5)->columnSpanFull(),
            Forms\Components\DateTimePicker::make('event_at')->label('Event / exam time')->nullable(),
            Forms\Components\Toggle::make('is_published')->label('Publish now')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('class.name')->label('Class')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('event_at')->dateTime()->placeholder('—'),
                Tables\Columns\IconColumn::make('is_published')->boolean()->label('Published'),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('publish')
                    ->visible(fn (ClassAnnouncement $record) => ! $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (ClassAnnouncement $record) {
                        $record->update([
                            'is_published' => true,
                            'published_at' => now(),
                        ]);
                        app(ClassContentNotifier::class)->announcePublished($record->fresh());
                        Notification::make()->title('Published & notified')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassAnnouncements::route('/'),
            'create' => Pages\CreateClassAnnouncement::route('/create'),
            'edit' => Pages\EditClassAnnouncement::route('/{record}/edit'),
        ];
    }
}
