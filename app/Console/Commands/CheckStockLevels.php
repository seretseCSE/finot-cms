<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\StockAlert;
use Illuminate\Console\Command;

class CheckStockLevels extends Command
{
    protected $signature = 'inventory:check-stock-levels {--threshold=5}';

    protected $description = 'Check inventory stock levels and generate alerts for low stock items';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $lowStockItems = InventoryItem::whereNull('deleted_at')
            ->where('status', 'Active')
            ->get()
            ->filter(fn ($item) => $item->current_stock < $threshold);

        $created = 0;
        foreach ($lowStockItems as $item) {
            $existingAlert = StockAlert::where('item_id', $item->id)
                ->whereIn('status', ['Active', 'Acknowledged'])
                ->first();

            if (! $existingAlert) {
                StockAlert::create([
                    'item_id' => $item->id,
                    'threshold' => $threshold,
                    'current_stock' => $item->current_stock,
                    'status' => 'Active',
                ]);
                $created++;
            } else {
                $existingAlert->update(['current_stock' => $item->current_stock]);
            }
        }

        $this->info("Stock level check complete. Created {$created} new alerts, updated " . ($lowStockItems->count() - $created) . " existing alerts.");

        return self::SUCCESS;
    }
}
