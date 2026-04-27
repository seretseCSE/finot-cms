<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use Filament\Schemas\Schema;
use App\Models\InventoryItem;
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

class InventoryReport extends Page implements Tables\Contracts\HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = InventoryResource::class;

    protected ?string $heading = 'Inventory Reports';

    public static function getNavigationIcon(): string | null
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationLabel(): string
    {
        return 'Reports';
    }

    public static function getNavigationSort(): int
    {
        return 3;
    }

    protected string $view = 'filament.resources.inventory.pages.report';

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
            'category' => null,
            'status' => null,
        ]);
    }

    public function form(Schema $schema): Schemas\Form
    {
        return $schema->components([
                Section::make('Report Filters')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Forms\Components\DatePicker::make('date_from')
                                    ->label('Date From')
                                    ->native(false),

                                Forms\Components\DatePicker::make('date_to')
                                    ->label('Date To')
                                    ->native(false),

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
                                    ->placeholder('All Categories'),

                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Damaged' => 'Damaged',
                                        'Lost' => 'Lost',
                                        'Disposed' => 'Disposed',
                                    ])
                                    ->placeholder('All Statuses'),
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
                Tables\Columns\TextColumn::make('item_code')
                    ->label('Item Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->wrap(),

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
                    ->label('Unit'),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('Purchase Price')
                    ->money('ETB'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
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
        $category = $filters['category'] ?? null;
        $status = $filters['status'] ?? null;

        $query = InventoryItem::query()->whereNull('deleted_at');

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($status) {
            $query->where('status', $status);
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
        $items = $this->getFilteredQuery()->get();

        $filename = 'inventory-report-' . now()->format('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($items) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Item Code', 'Name', 'Category', 'Stock', 'Unit', 'Location', 'Status', 'Purchase Price', 'Created Date']);

            foreach ($items as $item) {
                fputcsv($file, [
                    $item->item_code,
                    $item->name,
                    $item->category,
                    $item->current_stock,
                    $item->unit,
                    $item->location ?? 'N/A',
                    $item->status,
                    $item->purchase_price,
                    $item->created_at->format('Y-m-d'),
                ]);
            }
            fclose($file);
        };

        response()->stream($callback, 200, $headers)->send();
        exit;
    }
}
