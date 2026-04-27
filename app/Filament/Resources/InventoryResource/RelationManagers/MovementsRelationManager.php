<?php

namespace App\Filament\Resources\InventoryResource\RelationManagers;

use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Inventory Movements';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\Hidden::make('original_quantity')
                    ->default(fn ($record) => $record?->quantity ?? 0),
                Forms\Components\Hidden::make('original_movement_type')
                    ->default(fn ($record) => $record?->movement_type ?? null),
                Forms\Components\Hidden::make('original_sub_type')
                    ->default(fn ($record) => $record?->sub_type ?? null),

                Forms\Components\Select::make('movement_type')
                    ->label('Movement Type')
                    ->options([
                        'Stock In' => 'Stock In',
                        'Stock Out' => 'Stock Out',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state === 'Stock In') {
                            $set('sub_type', null);
                        } else {
                            $set('sub_type', null);
                        }
                    }),

                Forms\Components\Select::make('sub_type')
                    ->label('Sub Type')
                    ->options(fn (callable $get) => match ($get('movement_type')) {
                        'Stock In' => [
                            'Purchase' => 'Purchase',
                            'Donation' => 'Donation',
                            'Return' => 'Return',
                        ],
                        'Stock Out' => [
                            'Usage' => 'Usage',
                            'Distribution' => 'Distribution',
                            'Loan' => 'Loan',
                            'Loss' => 'Loss',
                        ],
                        default => [],
                    })
                    ->required(),

                Forms\Components\TextInput::make('quantity')
                    ->label('Quantity')
                    ->required()
                    ->numeric()
                    ->gt(0),

                Forms\Components\DatePicker::make('movement_date')
                    ->label('Movement Date')
                    ->required()
                    ->default(now())
                    ->native(false)
                    ->maxDate(now()),

                Forms\Components\TextInput::make('recipient_source')
                    ->label('Recipient/Source')
                    ->maxLength(255),

                Forms\Components\TextInput::make('reference_number')
                    ->label('Reference Number')
                    ->maxLength(100),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('movement_type')
            ->columns([
                Tables\Columns\TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($record) => $record->movement_type === 'Stock In' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('sub_type')
                    ->label('Sub Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->alignCenter()
                    ->color(fn ($record) => $record->movement_type === 'Stock In' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('ethiopian_movement_date')
                    ->label('Date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('recipient_source')
                    ->label('Recipient/Source')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('movement_type')
                    ->label('Movement Type')
                    ->options([
                        'Stock In' => 'Stock In',
                        'Stock Out' => 'Stock Out',
                    ]),

                Tables\Filters\SelectFilter::make('sub_type')
                    ->label('Sub Type')
                    ->options([
                        'Purchase' => 'Purchase',
                        'Donation' => 'Donation',
                        'Return' => 'Return',
                        'Usage' => 'Usage',
                        'Distribution' => 'Distribution',
                        'Loan' => 'Loan',
                        'Loss' => 'Loss',
                    ]),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data, $livewire): array {
                        $data['recorded_by'] = Auth::id();

                        $item = $livewire->getOwnerRecord();
                        if ($item) {
                            $itemId = $item->id;

                            if (($data['movement_type'] ?? null) === 'Stock Out') {
                                if ($data['quantity'] > $item->current_stock) {
                                    throw \Illuminate\Validation\ValidationException::withMessages([
                                        'data.quantity' => "The quantity exceeds the available stock of {$item->current_stock}.",
                                    ]);
                                }
                            }

                            if (($data['movement_type'] ?? null) === 'Stock In' && ($data['sub_type'] ?? null) === 'Return') {
                                $totalOut = (float) \App\Models\InventoryMovement::where('item_id', $itemId)->where('movement_type', 'Stock Out')->sum('quantity');
                                $totalReturns = (float) \App\Models\InventoryMovement::where('item_id', $itemId)->where('movement_type', 'Stock In')->where('sub_type', 'Return')->sum('quantity');
                                $allowed = $totalOut - $totalReturns;

                                if ($data['quantity'] > $allowed) {
                                    throw \Illuminate\Validation\ValidationException::withMessages([
                                        'data.quantity' => "The return quantity cannot exceed the total stock out amount of {$allowed}.",
                                    ]);
                                }
                            }
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, $record, $livewire): array {
                        $item = $livewire->getOwnerRecord();
                        if ($item) {
                            $itemId = $item->id;

                            if (($data['movement_type'] ?? null) === 'Stock Out') {
                                $available = $item->current_stock;

                                if ($record->movement_type === 'Stock Out') {
                                    $available += (float) $record->quantity;
                                }

                                if ($data['quantity'] > $available) {
                                    throw \Illuminate\Validation\ValidationException::withMessages([
                                        'data.quantity' => "The quantity exceeds the available stock of {$available}.",
                                    ]);
                                }
                            }

                            if (($data['movement_type'] ?? null) === 'Stock In' && ($data['sub_type'] ?? null) === 'Return') {
                                $totalOut = (float) \App\Models\InventoryMovement::where('item_id', $itemId)->where('movement_type', 'Stock Out')->sum('quantity');
                                $totalReturns = (float) \App\Models\InventoryMovement::where('item_id', $itemId)->where('movement_type', 'Stock In')->where('sub_type', 'Return')->sum('quantity');

                                $allowed = $totalOut - $totalReturns;
                                if ($record->movement_type === 'Stock In' && $record->sub_type === 'Return') {
                                    $allowed += (float) $record->quantity;
                                }

                                if ($data['quantity'] > $allowed) {
                                    throw \Illuminate\Validation\ValidationException::withMessages([
                                        'data.quantity' => "The return quantity cannot exceed the total stock out amount of {$allowed}.",
                                    ]);
                                }
                            }
                        }

                        return $data;
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
}
