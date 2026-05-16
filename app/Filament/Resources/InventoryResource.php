<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use Filament\Schemas\Schema;
use App\Filament\Resources\InventoryResource\RelationManagers;
use App\Models\InventoryItem;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InventoryResource extends BaseResource
{
    protected static ?string $model = InventoryItem::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-archive-box';
    }

    public static function getNavigationLabel(): string
    {
        return 'Inventory Items';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventory';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }


    public static function canDelete($record): bool
    {
        return Auth::user()?->can('inventory_items.delete') && $record->canBeDeleted();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                \Filament\Schemas\Components\Section::make('Item Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Item Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options([
                                'Electronics' => 'Electronics',
                                'Furniture' => 'Furniture',
                                'Books' => 'Books',
                                'Supplies' => 'Supplies',
                                'Equipment' => 'Equipment',
                                'Other' => 'Other',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Initial Quantity')
                            ->required()
                            ->numeric()
                            ->default(0),

                        Forms\Components\Select::make('unit')
                            ->label('Unit')
                            ->options([
                                'pieces' => 'Pieces',
                                'boxes' => 'Boxes',
                                'sets' => 'Sets',
                                'kg' => 'Kilograms',
                                'liters' => 'Liters',
                                'Other' => 'Other',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('location')
                            ->label('Location')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->columns(2),

                \Filament\Schemas\Components\Section::make('Purchase Information')
                    ->schema([
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Purchase Date')
                            ->native(false)
                            ->maxDate(now()),

                        Forms\Components\TextInput::make('purchase_price')
                            ->label('Purchase Price')
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('supplier')
                            ->label('Supplier')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                \Filament\Schemas\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Placeholder::make('status_display')
                            ->label('Current Status')
                            ->content(fn ($record) => $record ? $record->status : 'Active')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item_code')
                    ->label('Item Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => match($record->category) {
                        'Electronics' => 'blue',
                        'Furniture' => 'brown',
                        'Books' => 'green',
                        'Supplies' => 'yellow',
                        'Equipment' => 'purple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->getStateUsing(fn ($record) => $record->current_stock)
                    ->alignCenter()
                    ->color(fn ($record) => $record->current_stock < 5 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit')
                    ->sortable(),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('ethiopian_purchase_date')
                    ->label('Purchase Date')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        'Electronics' => 'Electronics',
                        'Furniture' => 'Furniture',
                        'Books' => 'Books',
                        'Supplies' => 'Supplies',
                        'Equipment' => 'Equipment',
                        'Other' => 'Other',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Active',
                        'Damaged' => 'Damaged',
                        'Lost' => 'Lost',
                        'Disposed' => 'Disposed',
                    ]),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(fn ($query) => $query->whereRaw('(quantity + (SELECT COALESCE(SUM(CASE WHEN movement_type = \'Stock In\' THEN quantity ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN movement_type = \'Stock Out\' THEN quantity ELSE 0 END), 0)) FROM inventory_movements WHERE item_id = inventory_items.id) < 5')),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn ($record) => static::canEdit($record)),

                Actions\DeleteAction::make()
                    ->visible(fn ($record) => static::canDelete($record)),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('mark_damaged')
                        ->label('Mark Damaged')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('warning')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->status === 'Active') {
                                    $record->markAsDamaged('Bulk action');
                                }
                            }
                        }),

                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No inventory items found')
            ->emptyStateDescription('Add your first inventory item to get started.')
            ->emptyStateIcon('heroicon-o-archive-box');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventory::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
}
