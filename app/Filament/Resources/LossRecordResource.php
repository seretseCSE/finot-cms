<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LossRecordResource\Pages;
use Filament\Schemas\Schema;
use App\Models\LossRecord;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class LossRecordResource extends Resource
{
    protected static ?string $model = LossRecord::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $breadcrumb = 'Loss/Damage Records';

    protected static ?string $label = 'Loss Record';

    protected static ?string $pluralLabel = 'Loss/Damage Records';

    public static function getNavigationIcon(): string | null
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function getNavigationLabel(): string
    {
        return 'Loss/Damage Records';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventory';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Loss/Damage Details')
                    ->schema([
                        Forms\Components\Select::make('item_id')
                            ->label('Inventory Item')
                            ->relationship('item', 'name', fn ($query) => $query->where('status', 'Active'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('loss_type')
                            ->label('Type')
                            ->options([
                                'Lost' => 'Lost',
                                'Damaged' => 'Damaged',
                                'Disposed' => 'Disposed',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity Lost/Damaged')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Enter the number of units lost or damaged'),

                        Forms\Components\DatePicker::make('loss_date')
                            ->label('Date of Loss/Damage')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->maxDate(now()),

                        Forms\Components\TextInput::make('reference_number')
                            ->label('Reference Number')
                            ->maxLength(100)
                            ->placeholder('e.g., Incident Report #123'),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason/Explanation')
                            ->rows(2)
                            ->placeholder('Explain how or why the item was lost or damaged'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Additional Notes')
                            ->rows(2),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item.item_code')
                    ->label('Item Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item Name')
                    ->searchable()
                    ->wrap()
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.category')
                    ->label('Category')
                    ->badge(),

                Tables\Columns\TextColumn::make('loss_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($record) => $record->loss_type_color),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->alignCenter()
                    ->color('danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('item.unit')
                    ->label('Unit'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('loss_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

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
                Tables\Filters\SelectFilter::make('loss_type')
                    ->label('Type')
                    ->options([
                        'Lost' => 'Lost',
                        'Damaged' => 'Damaged',
                        'Disposed' => 'Disposed',
                    ]),

                Tables\Filters\SelectFilter::make('item.category')
                    ->label('Category')
                    ->options([
                        'Electronics' => 'Electronics',
                        'Furniture' => 'Furniture',
                        'Books' => 'Books',
                        'Supplies' => 'Supplies',
                        'Equipment' => 'Equipment',
                        'Other' => 'Other',
                    ]),

                Tables\Filters\Filter::make('loss_date')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until')
                            ->afterOrEqual('from'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('loss_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('loss_date', '<=', $data['until']));
                    }),
            ])
            ->actions([
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLossRecords::route('/'),
            'create' => Pages\CreateLossRecord::route('/create'),
            'view' => Pages\ViewLossRecord::route('/{record}'),
        ];
    }
}
