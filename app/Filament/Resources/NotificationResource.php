<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use Filament\Schemas\Schema;
use App\Models\Notification;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationResource extends BaseResource
{
    protected static ?string $model = Notification::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bell-alert';
    }

    public static function getNavigationLabel(): string
    {
        return 'In-App Notifications';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Notification Details')
                    ->schema([
                        TextInput::make('type')
                            ->label('Type')
                            ->required()
                            ->maxLength(191)
                            ->placeholder('e.g., announcement, reminder, alert'),

                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('message')
                            ->label('Message')
                            ->required()
                            ->rows(4),

                        TextInput::make('action_url')
                            ->label('Action URL')
                            ->url()
                            ->nullable()
                            ->placeholder('e.g., /admin/members/123'),

                        Select::make('notifiable_type')
                            ->label('Recipient Type')
                            ->options([
                                User::class => 'User',
                            ])
                            ->required()
                            ->default(User::class),

                        Select::make('notifiable_id')
                            ->label('Recipient')
                            ->options(User::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\KeyValue::make('context_data')
                            ->label('Context Data')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notifiable.name')
                    ->label('Recipient')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('read_at')
                    ->label('Read')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(function () {
                        return Notification::query()->distinct()->pluck('type', 'type')->toArray();
                    })
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('read_at')
                    ->label('Read Status')
                    ->placeholder('All')
                    ->trueLabel('Read')
                    ->falseLabel('Unread'),

                Tables\Filters\SelectFilter::make('notifiable')
                    ->relationship('notifiable', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'create' => Pages\CreateNotification::route('/create'),
            'edit' => Pages\EditNotification::route('/{record}/edit'),
        ];
    }
}
