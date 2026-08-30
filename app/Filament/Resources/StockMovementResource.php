<?php

namespace App\Filament\Resources;


use App\Filament\Support\HidesFromNavigation;
use App\Filament\Resources\StockMovementResource\Pages;
use Filament\Schemas\Schema;
use App\Models\InventoryMovement;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends BaseResource
{
    use HidesFromNavigation;

    protected static ?string $model = InventoryMovement::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $breadcrumb = 'Stock Movements';

    protected static ?string $label = 'Stock Movement';

    protected static ?string $pluralLabel = 'Stock Movements';

    public static function getNavigationIcon(): string | null
    {
        return 'heroicon-o-arrows-right-left';
    }

    public static function getNavigationLabel(): string
    {
        return 'Stock Movements';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventory Management';
    }


    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Movement Details')
                    ->schema([
                        Forms\Components\Hidden::make('original_quantity')
                            ->default(fn ($record) => $record?->quantity ?? 0),
                        Forms\Components\Hidden::make('original_item_id')
                            ->default(fn ($record) => $record?->item_id ?? null),
                        Forms\Components\Hidden::make('original_movement_type')
                            ->default(fn ($record) => $record?->movement_type ?? null),
                        Forms\Components\Hidden::make('original_sub_type')
                            ->default(fn ($record) => $record?->sub_type ?? null),

                        Forms\Components\Select::make('item_id')
                            ->label('Inventory Item')
                            ->relationship('item', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('movement_type')
                            ->label('Movement Type')
                            ->options([
                                'Stock In' => 'Stock In',
                                'Stock Out' => 'Stock Out',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('sub_type', null);
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
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item Name')
                    ->searchable()
                    ->wrap()
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.category')
                    ->label('Category')
                    ->badge(),

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

                Tables\Filters\Filter::make('movement_date')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until')
                            ->afterOrEqual('from'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('movement_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('movement_date', '<=', $data['until']));
                    }),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
            'view' => Pages\ViewStockMovement::route('/{record}'),
            'edit' => Pages\EditStockMovement::route('/{record}/edit'),
        ];
    }
}
