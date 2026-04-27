<?php

namespace App\Filament\Resources\TourResource\RelationManagers;

use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PassengersRelationManager extends RelationManager
{
    protected static string $relationship = 'passengers';

    protected static ?string $title = 'Registrations';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\Select::make('member_id')
                    ->label('Select Member')
                    ->relationship('member', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable(['first_name', 'father_name', 'grandfather_name', 'phone'])
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $member = \App\Models\Member::find($state);
                            if ($member) {
                                $set('full_name', $member->full_name);
                                $set('phone', $member->phone ? preg_replace('/^' . preg_quote(config('finot.phone_prefix', '+251'), '/') . '/', '', $member->phone) : '');
                            }
                        }
                    })
                    ->helperText('Select an existing member to auto-fill details')
                    ->nullable(),

                Forms\Components\TextInput::make('phone')
                    ->label('Phone Number')
                    ->prefix(config('finot.phone_prefix', '+251'))
                    ->regex('/^[0-9]{9}$/')
                    ->maxLength(9)
                    ->placeholder('912345678')
                    ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state && strlen($state) === 9) {
                            $fullPhone = config('finot.phone_prefix', '+251').$state;
                            $member = \App\Models\Member::where('phone', $fullPhone)->first();
                            if ($member) {
                                $set('member_id', $member->id);
                                $set('full_name', $member->full_name);
                            } else {
                                $previous = \App\Models\TourPassenger::where('phone', $fullPhone)
                                    ->orderBy('created_at', 'desc')
                                    ->first();
                                if ($previous) {
                                    $set('full_name', $previous->full_name);
                                }
                            }
                        }
                    })
                    ->required(),

                Forms\Components\TextInput::make('full_name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('passenger_count')
                    ->label('Number of Passengers')
                    ->required()
                    ->integer()
                    ->default(1)
                    ->minValue(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                Tables\Columns\TextColumn::make('passenger_code')
                    ->label('Passenger Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('passenger_count')
                    ->label('Passengers')
                    ->sortable(),

                Tables\Columns\TextColumn::make('registration_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($record) => $record->registration_type_color),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('ethiopian_registration_date')
                    ->label('Registration Date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Linked Member')
                    ->formatStateUsing(fn ($record) => $record->member ? $record->member->full_name : '-')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Pending' => 'Pending',
                        'Confirmed' => 'Confirmed',
                        'Cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('registration_type')
                    ->label('Registration Type')
                    ->options([
                        'Internal' => 'Internal',
                        'Public' => 'Public',
                    ]),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Add Passenger')
                    ->modalHeading('Add Passenger')
                    ->mutateFormDataUsing(function (array $data): array {
                        $phonePrefix = config('finot.phone_prefix', '+251');

                        // Prepend phone prefix to phone
                        if (! empty($data['phone'])) {
                            $data['phone'] = $phonePrefix.preg_replace('/^' . preg_quote($phonePrefix, '/') . '/', '', $data['phone']);
                        }

                        // Generate passenger code
                        $tourPrefix = config('finot.tour_passenger_code_prefix', 'TP-');
                        $lastPassenger = \App\Models\TourPassenger::orderBy('id', 'desc')->first();
                        $lastCode = $lastPassenger ? intval(substr($lastPassenger->passenger_code, strlen($tourPrefix))) : 0;
                        $data['passenger_code'] = $tourPrefix.str_pad($lastCode + 1, 6, '0', STR_PAD_LEFT);

                        // Set registration date
                        $data['registration_date'] = now()->toDateString();

                        // Set registered by
                        $data['registered_by'] = Auth::id();

                        // Default to Internal and Confirmed for admin registrations
                        $data['registration_type'] = 'Internal';
                        $data['status'] = 'Confirmed';

                        return $data;
                    })
                    ->before(function (array $data, Actions\CreateAction $action) {
                        $record = $action->getRecord();
                        $phone = $data['phone'] ?? null;

                        // Only check duplicates if phone is provided and this is a new record
                        if ($phone && ! $record) {
                            $exists = \App\Models\TourPassenger::where('tour_id', $this->ownerRecord->id)
                                ->where('phone', $phone)
                                ->exists();

                            if ($exists) {
                                $action->halt();

                                \Filament\Notifications\Notification::make()
                                    ->title('Duplicate Phone Number')
                                    ->body('This phone number is already registered for this tour.')
                                    ->danger()
                                    ->send();
                            }
                        }
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $phonePrefix = config('finot.phone_prefix', '+251');

                        // Prepend phone prefix if not already present
                        if (! empty($data['phone']) && ! str_starts_with($data['phone'], $phonePrefix)) {
                            $data['phone'] = $phonePrefix.$data['phone'];
                        }

                        return $data;
                    })
                    ->before(function (array $data, Actions\EditAction $action) {
                        $record = $action->getRecord();
                        $phone = $data['phone'] ?? null;

                        // On edit, exclude the current record from duplicate check
                        if ($phone && $record) {
                            $exists = \App\Models\TourPassenger::where('tour_id', $this->ownerRecord->id)
                                ->where('phone', $phone)
                                ->where('id', '!=', $record->id)
                                ->exists();

                            if ($exists) {
                                $action->halt();

                                \Filament\Notifications\Notification::make()
                                    ->title('Duplicate Phone Number')
                                    ->body('This phone number is already registered for this tour.')
                                    ->danger()
                                    ->send();
                            }
                        }
                    }),

                Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => $record && $record->status === 'Pending')
                    ->action(function ($record) {
                        $record->confirm();
                    }),

                Actions\Action::make('cancel_registration')
                    ->label('Cancel Registration')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record && in_array($record->status, ['Pending', 'Confirmed']))
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->cancel($data['cancellation_reason']);
                    }),

                Actions\DeleteAction::make()
                    ->visible(fn ($record): bool => $record !== null),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('confirm_selected')
                        ->label('Confirm Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->status === 'Pending') {
                                    $record->confirm();
                                }
                            }
                        }),

                    Actions\BulkAction::make('cancel_selected')
                        ->label('Cancel Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Forms\Components\Textarea::make('cancellation_reason')
                                ->label('Cancellation Reason')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                if (in_array($record->status, ['Pending', 'Confirmed'])) {
                                    $record->cancel($data['cancellation_reason']);
                                }
                            }
                        }),

                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->label('Add Passenger'),
            ])
            ->emptyStateHeading('No passengers registered')
            ->emptyStateDescription('Add passengers to this tour to get started.')
            ->emptyStateIcon('heroicon-o-users');
    }

    protected function getTableSummary(): array
    {
        $records = $this->getRecords();

        $pendingCount = $records->where('status', 'Pending')->count();
        $confirmedCount = $records->where('status', 'Confirmed')->count();
        $cancelledCount = $records->where('status', 'Cancelled')->count();
        $totalPassengers = $records->where('status', 'Confirmed')->sum('passenger_count');

        return [
            Tables\Columns\TextColumn::make('summary')
                ->label('Summary')
                ->formatStateUsing(function () use ($pendingCount, $confirmedCount, $cancelledCount, $totalPassengers) {
                    return "Pending: {$pendingCount} | Confirmed: {$confirmedCount} | Cancelled: {$cancelledCount} | Total Passengers: {$totalPassengers}";
                }),
        ];
    }
}
