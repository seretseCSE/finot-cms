<?php

namespace App\Filament\Resources;


use App\Filament\Support\HidesFromNavigation;
use App\Filament\Resources\EventResource\Pages;
use Filament\Schemas\Schema;
use App\Models\Event;
use Filament\Actions;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends BaseResource
{
    use HidesFromNavigation;

    protected static ?string $model = Event::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calendar-days';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tour Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('date_time')
                    ->required()
                    ->label('Date & Time')
                    ->format('Y-m-d H:i'),
                Forms\Components\TextInput::make('location')
                    ->required()
                    ->maxLength(500),
                Forms\Components\RichEditor::make('description')
                    ->label('Description')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('featured_image')
                    ->label('Featured Image')
                    ->image()
                    ->directory('events')
                    ->disk('public')
                    ->visibility('public')
                    ->maxSize(2048),
                Forms\Components\Toggle::make('registration_required')
                    ->label('Registration Required')
                    ->reactive(),
                Forms\Components\TextInput::make('max_capacity')
                    ->label('Max Capacity')
                    ->numeric()
                    ->visible(fn ($get) => $get('registration_required')),
                Forms\Components\DatePicker::make('registration_deadline')
                    ->label('Registration Deadline')
                    ->visible(fn ($get) => $get('registration_required')),
                Forms\Components\Select::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Published' => 'Published',
                        'Full' => 'Full',
                        'Ongoing' => 'Ongoing',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\Select::make('recurrence_type')
                    ->options([
                        'None' => 'None',
                        'Weekly' => 'Weekly',
                        'Monthly' => 'Monthly',
                        'Custom' => 'Custom',
                    ]),
                Forms\Components\DatePicker::make('recurrence_end_date')
                    ->label('Recurrence End Date')
                    ->visible(fn ($get) => $get('recurrence_type') !== 'None'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_time')
                    ->label('Date & Time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),
                Tables\Columns\TextColumn::make('registration_count')
                    ->label('Registrations')
                    ->getStateUsing(fn ($record) => $record->registration_count),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Published' => 'Published',
                        'Full' => 'Full',
                        'Ongoing' => 'Ongoing',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ]),
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
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }


}
