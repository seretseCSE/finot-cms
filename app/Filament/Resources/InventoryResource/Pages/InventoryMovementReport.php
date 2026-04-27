<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use Filament\Schemas\Schema;
use App\Models\InventoryMovement;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InventoryMovementReport extends Page implements Tables\Contracts\HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = InventoryResource::class;

    protected ?string $heading = 'Inventory Movement Reports';

    public static function getNavigationIcon(): string | null
    {
        return 'heroicon-o-arrows-right-left';
    }

    public static function getNavigationLabel(): string
    {
        return 'Movement Reports';
    }

    public static function getNavigationSort(): int
    {
        return 4;
    }

    protected string $view = 'filament.resources.inventory.pages.movement-report';

    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public function mount(): void
    {
        $this->form->fill([
            'date_from' => now()->startOfMonth()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
            'movement_type' => null,
            'item_id' => null,
        ]);
    }

    public function form(Schema $schema): Schemas\Form
    {
        return $schema->components([
                Section::make('Movement Filters')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Forms\Components\DatePicker::make('date_from')
                                    ->label('Date From')
                                    ->native(false),

                                Forms\Components\DatePicker::make('date_to')
                                    ->label('Date To')
                                    ->native(false),

                                Forms\Components\Select::make('movement_type')
                                    ->label('Movement Type')
                                    ->options([
                                        'Stock In' => 'Stock In',
                                        'Stock Out' => 'Stock Out',
                                    ])
                                    ->placeholder('All Types'),

                                Forms\Components\Select::make('item_id')
                                    ->label('Item')
                                    ->relationship('item', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('All Items'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getFilteredQuery())
            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item Name')
                    ->searchable()
                    ->wrap(),

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
                    ->label('Recorded')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getFilteredQuery()
    {
        $filters = $this->form->getState();
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $movementType = $filters['movement_type'] ?? null;
        $itemId = $filters['item_id'] ?? null;

        $query = InventoryMovement::query()->with(['item', 'recordedBy']);

        if ($dateFrom) {
            $query->whereDate('movement_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('movement_date', '<=', $dateTo);
        }

        if ($movementType) {
            $query->where('movement_type', $movementType);
        }

        if ($itemId) {
            $query->where('item_id', $itemId);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn () => $this->exportToCsv()),
        ];
    }

    public function exportToCsv(): void
    {
        $movements = $this->getFilteredQuery()->get();

        $filename = 'inventory-movements-' . now()->format('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($movements) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Item Name', 'Category', 'Type', 'Sub Type', 'Quantity', 'Date', 'Recipient/Source', 'Reference', 'Recorded By']);

            foreach ($movements as $movement) {
                fputcsv($file, [
                    $movement->item?->name ?? 'N/A',
                    $movement->item?->category ?? 'N/A',
                    $movement->movement_type,
                    $movement->sub_type,
                    $movement->quantity,
                    $movement->ethiopian_movement_date,
                    $movement->recipient_source ?? 'N/A',
                    $movement->reference_number ?? 'N/A',
                    $movement->recordedBy?->name ?? 'N/A',
                ]);
            }
            fclose($file);
        };

        response()->stream($callback, 200, $headers)->send();
        exit;
    }
}
