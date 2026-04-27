<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use App\Models\InventoryItem;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;

class InventoryAnalytics extends Page
{
    protected static string $resource = InventoryResource::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return 'Analytics';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

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
        $items = InventoryItem::with(['movements'])
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($item) {
                return [
                    'Item Code' => $item->item_code,
                    'Name' => $item->name,
                    'Category' => $item->category,
                    'Current Stock' => $item->current_stock,
                    'Unit' => $item->unit,
                    'Location' => $item->location ?? 'N/A',
                    'Status' => $item->status,
                    'Purchase Date' => $item->ethiopian_purchase_date,
                    'Purchase Price' => $item->purchase_price,
                    'Supplier' => $item->supplier ?? 'N/A',
                    'Created By' => $item->createdBy?->name ?? 'N/A',
                ];
            });

        $filename = 'inventory-report-' . now()->format('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($items) {
            $file = fopen('php://output', 'w');
            // Headers
            fputcsv($file, array_keys($items->first() ?? []));
            // Data
            foreach ($items as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        response()->stream($callback, 200, $headers)->send();
        exit;
    }

    public function exportToPDF(): void
    {
        $items = InventoryItem::with(['movements', 'createdBy'])
            ->whereNull('deleted_at')
            ->get();

        $totalValue = $items->sum(function ($item) {
            return $item->purchase_price * $item->current_stock;
        });

        $summary = [
            'total_items' => $items->count(),
            'total_value' => $totalValue,
            'active_items' => $items->where('status', 'Active')->count(),
            'low_stock' => $items->filter(fn ($i) => $i->current_stock < 5)->count(),
        ];

        $html = view('exports.inventory-pdf', [
            'items' => $items,
            'summary' => $summary,
            'date' => now()->format('Y-m-d H:i:s'),
        ])->render();

        // Check if DOMPDF is available, otherwise return HTML
        if (class_exists('Barryvdh\DomPDF\Facade\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('a4', 'landscape');
            $pdf->download('inventory-report-' . now()->format('Y-m-d') . '.pdf')->send();
        } else {
            // Fallback: Return printable HTML view
            response()->make($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => 'inline; filename="inventory-report.html"',
            ])->send();
        }
        exit;
    }

    protected function getWidgets(): array
    {
        return [];
    }
}
