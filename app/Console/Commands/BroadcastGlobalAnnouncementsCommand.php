<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BroadcastGlobalAnnouncementsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'announcements:broadcast-global {--force : Force rebroadcast all active global announcements}';

    /**
     * The console command description.
     */
    protected $description = 'Broadcast active global announcements to target users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting global announcement broadcast...');

        $query = Announcement::where('is_global', true)
            ->where('status', 'Active')
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });

        if (!$this->option('force')) {
            // Only get announcements that haven't been broadcast recently
            $query->where(function ($query) {
                $query->whereNull('broadcast_at')
                    ->orWhere('broadcast_at', '<', now()->subHours(24));
            });
        }

        $announcements = $query->get();

        if ($announcements->isEmpty()) {
            $this->info('No global announcements to broadcast.');
            return Command::SUCCESS;
        }

        $this->info("Found {$announcements->count()} global announcement(s) to broadcast.");

        foreach ($announcements as $announcement) {
            $this->line("Broadcasting: {$announcement->title}");
            
            try {
                $announcement->broadcast();
                
                // Update broadcast timestamp
                $announcement->update(['broadcast_at' => now()]);
                
                $this->info("✅ Successfully broadcasted: {$announcement->title}");
                
            } catch (\Exception $e) {
                $this->error("❌ Failed to broadcast '{$announcement->title}': {$e->getMessage()}");
                
                Log::error('Global announcement broadcast failed', [
                    'announcement_id' => $announcement->id,
                    'title' => $announcement->title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Global announcement broadcast completed.');
        return Command::SUCCESS;
    }
}
