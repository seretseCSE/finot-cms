<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TourPassengerResource\Pages;
use App\Models\Member;
use App\Models\Tour;
use App\Models\TourPassenger;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TourPassengerResource extends BaseResource
{
    protected static ?string $model = TourPassenger::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tour & Registration')
                ->schema([
                    Select::make('tour_id')
                        ->label('Tour')
                        ->options(fn () => Tour::query()->orderBy('tour_date', 'desc')->pluck('place', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),

                    Select::make('registration_type')
                        ->label('Registration Type')
                        ->options([
                            'Public' => 'Public',
                            'Internal' => 'Internal',
                        ])
                        ->required()
                        ->default('Internal')
                        ->live(),

                    Select::make('member_id')
                        ->label('Member')
                        ->options(fn () => Member::query()->whereIn('status', ['Active', 'Member'])->get()->pluck('full_name', 'id'))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible(fn (callable $get) => $get('registration_type') === 'Internal'),
                ])
                ->columns(2),

            Section::make('Primary Passenger')
                ->schema([
                    TextInput::make('full_name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label('Phone')
                        ->prefix(config('finot.phone_prefix', '+251'))
                        ->maxLength(9)
                        ->placeholder('912345678')
                        ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                        ->live(onBlur: true)
                        ->dehydrateStateUsing(fn ($state) => \App\Services\PhoneFormattingService::dehydrateStateUsing($state))
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            if (! $state || strlen($state) !== 9) {
                                return;
                            }

                            $fullPhone = config('finot.phone_prefix', '+251') . $state;
                            $tourId = $get('tour_id');

                            if (! $tourId) {
                                return;
                            }

                            // Check duplicate on same tour
                            $exists = TourPassenger::where('tour_id', $tourId)
                                ->where('phone', $fullPhone)
                                ->exists();

                            if ($exists) {
                                Notification::make()
                                    ->title('Phone Already Registered')
                                    ->body('This phone number is already registered for this tour.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Lookup returning passenger from other tours
                            $previous = TourPassenger::where('phone', $fullPhone)
                                ->where('tour_id', '!=', $tourId)
                                ->latest('id')
                                ->first();

                            if ($previous) {
                                $set('full_name', $previous->full_name);

                                if ($previous->member_id) {
                                    $set('member_id', $previous->member_id);
                                }

                                Notification::make()
                                    ->title('Returning Passenger')
                                    ->body("Found from a previous tour. Name auto-filled as: {$previous->full_name}")
                                    ->info()
                                    ->send();
                            }
                        }),

                    TextInput::make('passenger_count')
                        ->label('Number of Passengers')
                        ->numeric()
                        ->integer()
                        ->required()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(20)
                        ->live()
                        ->afterStateUpdated(function (callable $set, callable $get, $state) {
                            $count = max(0, (int) $state - 1);
                            $current = $get('additional_passengers') ?? [];
                            $current = array_values($current);

                            if (count($current) < $count) {
                                for ($i = count($current); $i < $count; $i++) {
                                    $current[] = ['name' => '', 'phone' => ''];
                                }
                            } else {
                                $current = array_slice($current, 0, $count);
                            }

                            $set('additional_passengers', $current);
                        }),
                ])
                ->columns(2),

            Repeater::make('additional_passengers')
                ->schema([
                    TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label('Phone')
                        ->prefix(config('finot.phone_prefix', '+251'))
                        ->maxLength(9)
                        ->placeholder('912345678')
                        ->nullable()
                        ->live(onBlur: true)
                        ->dehydrateStateUsing(fn ($state) => $state ? \App\Services\PhoneFormattingService::dehydrateStateUsing($state) : null)
                        ->afterStateUpdated(function ($state, callable $get, callable $set) {
                            if (! $state || strlen($state) !== 9) {
                                return;
                            }

                            $fullPhone = config('finot.phone_prefix', '+251') . $state;
                            $tourId = $get('../../tour_id');

                            if (! $tourId) {
                                return;
                            }

                            $exists = TourPassenger::where('tour_id', $tourId)
                                ->where('phone', $fullPhone)
                                ->exists();

                            if ($exists) {
                                Notification::make()
                                    ->title('Phone Already Registered')
                                    ->body('This phone number is already registered for this tour.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                ->label('Additional Passengers')
                ->itemLabel(fn (array $state, ?int $index): string => 'Passenger ' . (($index ?? 0) + 2))
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->visible(fn (callable $get) => ($get('passenger_count') ?? 1) > 1)
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

                    DatePicker::make('registration_date')
                        ->label('Registration Date')
                        ->required()
                        ->default(now()),

                    FileUpload::make('receipt_image')
                        ->label('Receipt Image')
                        ->image()
                        ->disk('public')
                        ->directory(fn (callable $get) => 'receipts/tours/' . $get('tour_id'))
                        ->maxSize(5120)
                        ->formatStateUsing(function ($state, $record) {
                            if (! $state) {
                                return null;
                            }

                            if (str_starts_with($state, 'receipts/tours/')) {
                                return $state;
                            }

                            return 'receipts/tours/' . ($record?->tour_id ?? '0') . '/' . $state;
                        })
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

                Tables\Columns\TextColumn::make('total_tours')
                    ->label('Tours')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('registration_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Public' => 'primary',
                        'Internal' => 'success',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
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

                Tables\Filters\Filter::make('upcoming_tours')
                    ->label('Upcoming Tours')
                    ->query(fn ($query) => $query->whereHas('tour', fn ($q) =>
                        $q->whereDate('tour_date', '>=', now()->startOfDay())
                    )),

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
