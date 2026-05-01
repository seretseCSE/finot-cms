<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TourPassengerResource\Pages;
use Filament\Schemas\Schema;
use App\Models\Member;
use App\Models\Tour;
use App\Models\TourPassenger;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TourPassengerResource extends Resource
{
    protected static ?string $model = TourPassenger::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationLabel(): string
    {
        return 'Tour Passengers';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tour Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'tour_manager', 'revenue_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'tour_manager', 'revenue_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'tour_manager', 'revenue_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Tour & Registration')
                    ->schema([
                        Select::make('tour_id')
                            ->label('Tour')
                            ->options(Tour::query()->orderBy('tour_date', 'desc')->pluck('place', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('registration_type')
                            ->label('Registration Type')
                            ->options([
                                'Public' => 'Public',
                                'Internal' => 'Internal',
                            ])
                            ->required()
                            ->default('Internal')
                            ->reactive(),

                        Select::make('member_id')
                            ->label('Member')
                            ->options(Member::query()->whereIn('status', ['Active', 'Member'])->get()->pluck('full_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->visible(fn (callable $get) => $get('registration_type') === 'Internal'),
                    ])
                    ->columns(2),

                Section::make('Passenger Details')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(20)
                            ->nullable(),

                        TextInput::make('passenger_count')
                            ->label('Passenger Count')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->default(1)
                            ->minValue(1),

                        DatePicker::make('registration_date')
                            ->label('Registration Date')
                            ->required()
                            ->default(now()),
                    ])
                    ->columns(2),

                Section::make('Status & Receipt')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'Pending' => 'Pending',
                                'Confirmed' => 'Confirmed',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('Pending'),

                        FileUpload::make('receipt_image')
                            ->label('Receipt Image')
                            ->image()
                            ->disk('public')
                            ->directory(fn (callable $get) => 'receipts/tours/'.$get('tour_id'))
                            ->formatStateUsing(function ($state, $record) {
                                if (! $state) {
                                    return null;
                                }

                                // If already a full path, return as-is
                                if (str_starts_with($state, 'receipts/tours/')) {
                                    return $state;
                                }

                                // Backward compatibility: old records stored only the filename
                                return 'receipts/tours/'.($record?->tour_id ?? '0').'/'.$state;
                            })
                            ->maxSize(5120)
                            ->nullable(),

                        Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->rows(2)
                            ->nullable()
                            ->visible(fn (callable $get) => $get('status') === 'Cancelled'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('passenger_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tour.place')
                    ->label('Tour')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('passenger_count')
                    ->label('Count')
                    ->sortable(),

                Tables\Columns\TextColumn::make('registration_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'Public' => 'primary',
                        'Internal' => 'success',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'Pending' => 'warning',
                        'Confirmed' => 'success',
                        'Cancelled' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Registered')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('registeredBy.name')
                    ->label('Registered By')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tour')
                    ->relationship('tour', 'place')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Confirmed' => 'Confirmed',
                        'Cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('registration_type')
                    ->options([
                        'Public' => 'Public',
                        'Internal' => 'Internal',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until')
                            ->afterOrEqual('from'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('registration_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('registration_date', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Actions\EditAction::make(),

                Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (TourPassenger $record) => $record->status === 'Pending')
                    ->action(function (TourPassenger $record): void {
                        $record->confirm();
                        Notification::make()->title('Passenger confirmed')->success()->send();
                    }),

                Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TourPassenger $record) => $record->status !== 'Cancelled')
                    ->action(function (TourPassenger $record): void {
                        $record->cancel('Cancelled from passenger list');
                        Notification::make()->title('Passenger cancelled')->success()->send();
                    }),

                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('confirm')
                        ->label('Confirm Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                if ($record->status === 'Pending') {
                                    $record->confirm();
                                }
                            }
                            Notification::make()->title('Selected passengers confirmed')->success()->send();
                        }),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('registration_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTourPassengers::route('/'),
            'create' => Pages\CreateTourPassenger::route('/create'),
            'edit' => Pages\EditTourPassenger::route('/{record}/edit'),
        ];
    }
}
