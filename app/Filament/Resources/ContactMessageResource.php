<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use Filament\Schemas\Schema;
use App\Models\ContactMessage;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ContactMessageResource extends BaseResource
{
    protected static ?string $model = ContactMessage::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-envelope';
    }

    public static function getNavigationLabel(): string
    {
        return 'Contact Messages';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('contact_messages.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('contact_messages.delete');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Message Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->disabled(),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->disabled(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->disabled(),

                        Forms\Components\TextInput::make('subject')
                            ->label('Subject')
                            ->disabled(),

                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->rows(6)
                            ->disabled(),

                        Forms\Components\Toggle::make('is_read')
                            ->label('Read')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('message_snippet')
                    ->label('Message')
                    ->wrap()
                    ->limit(80),

                Tables\Columns\TextColumn::make('ethiopian_created_at')
                    ->label('Received')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('created_at', $direction)),

                Tables\Columns\IconColumn::make('is_read')
                    ->label('Read')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_read')
                    ->label('Read Status')
                    ->placeholder('All')
                    ->trueLabel('Read')
                    ->falseLabel('Unread'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('markAsRead')
                    ->label('Mark as Read')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ContactMessage $record): bool => ! $record->is_read)
                    ->action(function (ContactMessage $record): void {
                        $record->markAsRead();
                    }),
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
            'index' => Pages\ListContactMessages::route('/'),
            'view' => Pages\ViewContactMessage::route('/{record}'),
        ];
    }
}
