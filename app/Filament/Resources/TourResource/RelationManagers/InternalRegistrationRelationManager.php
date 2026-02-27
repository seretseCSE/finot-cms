<?php

namespace App\Filament\Resources\TourResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;

class InternalRegistrationRelationManager extends RelationManager
{
    protected static string $relationship = 'passengers';

    protected static ?string $title = 'Internal Registration';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('phone')
                    ->label('Phone Number')
                    ->required()
                    ->regex('/^\+251[0-9]{9}$/')
                    ->helperText('Format: +251912345678')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($state && strlen($state) >= 13) {
                            try {
                                $response = Http::get(route('tour.lookup-phone', [
                                    'phone' => $state,
                                    'tour_id' => $this->ownerRecord->id,
                                ]));

                                $data = $response->json();

                                if ($data['found']) {
                                    $set('full_name', $data['full_name']);
                                    if ($data['member_id']) {
                                        $set('member_id', $data['member_id']);
                                    }
                                    
                                    // Show success message
                                    $set('lookup_status', $data['message']);
                                    $set('lookup_color', 'green');
                                } else {
                                    $set('lookup_status', $data['message']);
                                    $set('lookup_color', 'orange');
                                }
                            } catch (\Exception $e) {
                                $set('lookup_status', 'Lookup failed');
                                $set('lookup_color', 'red');
                            }
                        }
                    }),

                Forms\Components\Placeholder::make('lookup_status')
                    ->label('Lookup Status')
                    ->content(fn ($get) => $get('lookup_status') ?: 'Enter phone number to lookup')
                    ->extraAttributes(fn ($get) => [
                        'class' => match($get('lookup_color')) {
                            'green' => 'text-green-600',
                            'orange' => 'text-orange-600', 
                            'red' => 'text-red-600',
                            default => 'text-gray-600',
                        }
                    ]),

                Forms\Components\Hidden::make('member_id'),

                Forms\Components\TextInput::make('full_name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('passenger_count')
                    ->label('Number of Passengers')
                    ->required()
                    ->integer()
                    ->default(1),

                Forms\Components\Hidden::make('registration_type')
                    ->default('Internal'),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'Pending' => 'Pending',
                        'Confirmed' => 'Confirmed',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->default('Pending')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('registration_type', 'Internal'))
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
            ])
            ->headerActions([
                Actions\Action::make('add_passenger')
                    ->label('Add Passenger')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('Add Internal Passenger')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Generate passenger code
                        $lastPassenger = \App\Models\TourPassenger::orderBy('id', 'desc')->first();
                        $lastCode = $lastPassenger ? intval(substr($lastPassenger->passenger_code, 3)) : 0;
                        $data['passenger_code'] = 'TP-' . str_pad($lastCode + 1, 6, '0', STR_PAD_LEFT);
                        
                        // Set registration date
                        $data['registration_date'] = now()->toDateString();
                        
                        // Set registered by
                        $data['registered_by'] = auth()->user()->id();
                        
                        return $data;
                    })
                    ->before(function (array $data) {
                        // Check if phone already exists for this tour
                        $exists = \App\Models\TourPassenger::where('tour_id', $this->ownerRecord->id)
                            ->where('phone', $data['phone'])
                            ->exists();
                        
                        if ($exists) {
                            throw new \Exception('This phone number is already registered for this tour');
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'Pending')
                    ->action(function ($record) {
                        $record->confirm();
                    }),

                Actions\Action::make('cancel_registration')
                    ->label('Cancel Registration')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => in_array($record->status, ['Pending', 'Confirmed']))
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->cancel($data['cancellation_reason']);
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('confirm_selected')
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

                    Tables\Actions\BulkAction::make('cancel_selected')
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

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Actions\Action::make('add_passenger')
                    ->label('Add Passenger')
                    ->icon('heroicon-o-user-plus'),
            ])
            ->emptyStateHeading('No internal passengers registered')
            ->emptyStateDescription('Add internal passengers to this tour to get started.')
            ->emptyStateIcon('heroicon-o-user-plus');
    }
}

