<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Filament\Resources\InventoryResource\RelationManagers;
use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InventoryResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-archive-box'; }

    public static function getNavigationLabel(): string { return 'Inventory Items'; }

    public static function getNavigationGroup(): ?string { return 'Inventory'; }

    public static function getNavigationSort(): ?int { return 1; }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin']) && $record->canBeDeleted();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Item Information')
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

                Forms\Components\Section::make('Purchase Information')
                    ->schema([
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Purchase Date')
                            ->native(false),

                        Forms\Components\TextInput::make('purchase_price')
                            ->label('Purchase Price')
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('supplier')
                            ->label('Supplier')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
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
                    ->sortable(),

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
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => static::canEdit($record)),
                
                Tables\Actions\Action::make('record_movement')
                    ->label('Record Movement')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->form([
                        Forms\Components\Radio::make('movement_type')
                            ->label('Movement Type')
                            ->options([
                                'Stock In' => 'Stock In',
                                'Stock Out' => 'Stock Out',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === 'Stock In') {
                                    $set('sub_type_options', ['Purchase', 'Donation', 'Return']);
                                } else {
                                    $set('sub_type_options', ['Usage', 'Distribution', 'Loan', 'Loss']);
                                }
                            }),

                        Forms\Components\Select::make('sub_type')
                            ->label('Sub-type')
                            ->options(fn (callable $get) => $get('sub_type_options') ?? [])
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
                            ->native(false),

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
                    ->action(function ($record, array $data) {
                        $currentStock = $record->current_stock;
                        
                        if ($data['movement_type'] === 'Stock Out' && $data['quantity'] > $currentStock) {
                            if (Auth::user()?->hasRole(['admin', 'superadmin'])) {
                                // Admin override
                                $data['override_justification'] = 'Admin override - insufficient stock';
                            } else {
                                throw new \Exception("Insufficient stock. Available: {$currentStock}, Requested: {$data['quantity']}");
                            }
                        }

                        $record->movements()->create([
                            'movement_type' => $data['movement_type'],
                            'sub_type' => $data['sub_type'],
                            'quantity' => $data['quantity'],
                            'movement_date' => $data['movement_date'],
                            'recipient_source' => $data['recipient_source'],
                            'reference_number' => $data['reference_number'],
                            'notes' => $data['notes'],
                            'override_justification' => $data['override_justification'] ?? null,
                            'recorded_by' => Auth::id(),
                        ]);
                    }),

                Tables\Actions\Action::make('mark_damaged')
                    ->label('Mark Damaged')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'Active')
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Damage Notes')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->markAsDamaged($data['notes']);
                    }),

                Tables\Actions\Action::make('mark_lost')
                    ->label('Mark Lost')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'Active')
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Loss Notes')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->markAsLost($data['notes']);
                    }),

                Tables\Actions\Action::make('mark_disposed')
                    ->label('Mark Disposed')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->visible(fn ($record) => in_array($record->status, ['Active', 'Damaged', 'Lost']))
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Disposal Notes')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->markAsDisposed($data['notes']);
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_damaged')
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

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
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
            'index' => Pages\ListInventory::class,
            'create' => Pages\CreateInventory::class,
            'edit' => Pages\EditInventory::class,
            'analytics' => Pages\InventoryAnalytics::class,
        ];
    }
}

