<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use App\Models\InventoryItem;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InventoryAnalytics extends Page
{
    protected static string $resource = InventoryResource::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-chart-bar'; }

    public static function getNavigationLabel(): string { return 'Analytics'; }

    public static function getNavigationSort(): ?int { return 2; }

    public function getTitle(): string
    {
        return 'Inventory Analytics';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->action('exportToExcel')
                ->color('success'),

            Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action('exportToPDF')
                ->color('primary'),
        ];
    }

    public function exportToExcel(): void
    {
        // Implementation for Excel export
        $items = InventoryItem::with(['movements'])
            ->get()
            ->map(function ($item) {
                return [
                    'Item Code' => $item->item_code,
                    'Name' => $item->name,
                    'Category' => $item->category,
                    'Current Stock' => $item->current_stock,
                    'Unit' => $item->unit,
                    'Location' => $item->location,
                    'Status' => $item->status,
                    'Purchase Date' => $item->ethiopian_purchase_date,
                    'Purchase Price' => $item->purchase_price,
                    'Supplier' => $item->supplier,
                ];
            });

        // Generate and download Excel file
        // This would use Laravel Excel package in real implementation
    }

    public function exportToPDF(): void
    {
        // Implementation for PDF export
        // This would generate PDF with charts and data
    }

    protected function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\TotalItemsByCategoryWidget::class,
            \App\Filament\Widgets\TotalInventoryValueWidget::class,
            \App\Filament\Widgets\MostUsedItemsWidget::class,
            \App\Filament\Widgets\LowStockItemsWidget::class,
            \App\Filament\Widgets\RecentMovementsWidget::class,
        ];
    }
}

