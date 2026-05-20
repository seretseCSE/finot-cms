<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventRegistrationResource\Pages;
use Filament\Schemas\Schema;
use App\Models\EventRegistration;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;

class EventRegistrationResource extends BaseResource
{
    protected static ?string $model = EventRegistration::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Events';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\Select::make('event_id')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->required(),

                Section::make('Registration Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('Full Name')
                            ->placeholder('Enter your full name'),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->label('Email Address')
                            ->placeholder('your.email@example.com'),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->label('Phone Number')
                            ->placeholder('+1234567890'),

                        Forms\Components\DateTimePicker::make('registration_date')
                            ->required()
                            ->default(now())
                            ->label('Registration Date'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'Pending' => 'Pending',
                                'Confirmed' => 'Confirmed',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->label('Registration Status'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->label('Additional Notes'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Registrant Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Registration Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => match ($record->status) {
                        'Pending' => 'warning',
                        'Confirmed' => 'success',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Confirmed' => 'Confirmed',
                        'Cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('event')
                    ->relationship('event', 'name'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventRegistrations::route('/'),
            'create' => Pages\CreateEventRegistration::route('/create'),
            'edit' => Pages\EditEventRegistration::route('/{record}/edit'),
        ];
    }


}
