<?php

namespace App\Filament\Pages;

use App\Models\InventoryItem;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Auth;

class InventorySearch extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.inventory-search';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-magnifying-glass';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventory Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationLabel(): string
    {
        return 'Inventory Search';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public ?array $filters = [];

    public bool $hasSearched = false;

    public array $results = [];

    public function mount(): void
    {
        $this->form->fill([
            'query' => '',
            'category' => 'all',
            'location' => '',
            'status' => 'all',
            'stock_min' => null,
            'stock_max' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                TextInput::make('query')
                    ->label('Search')
                    ->placeholder('Item name, code, or supplier...')
                    ->columnSpan(2),

                Select::make('category')
                    ->label('Category')
                    ->options([
                        'all' => 'All Categories',
                        'Electronics' => 'Electronics',
                        'Furniture' => 'Furniture',
                        'Books' => 'Books',
                        'Supplies' => 'Supplies',
                        'Equipment' => 'Equipment',
                        'Other' => 'Other',
                    ])
                    ->default('all'),

                TextInput::make('location')
                    ->label('Location')
                    ->placeholder('Storage location...'),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'all' => 'All Statuses',
                        'Active' => 'Active',
                        'Damaged' => 'Damaged',
                        'Lost' => 'Lost',
                        'Disposed' => 'Disposed',
                    ])
                    ->default('all'),

                TextInput::make('stock_min')
                    ->label('Min Stock')
                    ->numeric()
                    ->step(0.01),

                TextInput::make('stock_max')
                    ->label('Max Stock')
                    ->numeric()
                    ->step(0.01),
            ])
            ->columns(4);
    }

    public function searchInventory(): void
    {
        $this->filters = $this->form->getState();
        $this->hasSearched = true;
        $this->results = [];

        $items = InventoryItem::query()
            ->with(['createdBy', 'movements'])
            ->when(filled($this->filters['query'] ?? null), function ($q) {
                $search = trim((string) $this->filters['query']);
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%");
                });
            })
            ->when(($this->filters['category'] ?? 'all') !== 'all', function ($q) {
                $q->where('category', $this->filters['category']);
            })
            ->when(filled($this->filters['location'] ?? null), function ($q) {
                $location = trim((string) $this->filters['location']);
                $q->where('location', 'like', "%{$location}%");
            })
            ->when(($this->filters['status'] ?? 'all') !== 'all', function ($q) {
                $q->where('status', $this->filters['status']);
            })
            ->orderBy('name')
            ->limit(100)
            ->get();

        $this->results = $items
            ->filter(function (InventoryItem $item) {
                $stock = $item->current_stock;
                $min = $this->filters['stock_min'] ?? null;
                $max = $this->filters['stock_max'] ?? null;

                if (filled($min) && $stock < (float) $min) {
                    return false;
                }

                if (filled($max) && $stock > (float) $max) {
                    return false;
                }

                return true;
            })
            ->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->name,
                'category' => $item->category,
                'current_stock' => $item->current_stock,
                'unit' => $item->unit,
                'location' => $item->location,
                'status' => $item->status,
                'status_color' => $item->status_color,
                'supplier' => $item->supplier,
                'purchase_price' => $item->purchase_price,
                'ethiopian_purchase_date' => $item->ethiopian_purchase_date,
            ])
            ->values()
            ->toArray();
    }

    public function getTotalResultsCount(): int
    {
        return count($this->results);
    }
}
