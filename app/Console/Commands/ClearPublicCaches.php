<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearPublicCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-public {--force : Force clear without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear public-facing caches (tours, songs, etc.) without affecting system caches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('Clear all public-facing caches? This will refresh tour/song listings.')) {
                $this->info('Public cache clearing cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->info('Clearing public-facing caches...');

        // Clear cache version keys
        $publicCacheKeys = [
            'cache.version.tours',
            'cache.version.songs',
            'cache.version.song_categories',
        ];

        foreach ($publicCacheKeys as $key) {
            Cache::forget($key);
            $this->info("✓ Cleared cache key: {$key}");
        }

        // Clear tour caches
        for ($i = 1; $i <= 20; $i++) {
            Cache::forget("tours.index:v{$i}");
        }
        $this->info('✓ Cleared tour listing caches');

        // Clear song caches
        $songCachePatterns = ['songs.index:v*', 'songs.categories:v*'];
        foreach ($songCachePatterns as $pattern) {
            for ($i = 1; $i <= 20; $i++) {
                Cache::forget(str_replace('*', $i, $pattern));
            }
        }
        $this->info('✓ Cleared song caches');

        // Increment versions to force refresh
        foreach ($publicCacheKeys as $key) {
            Cache::increment($key);
            $this->info("✓ Incremented version: {$key}");
        }

        $this->newLine();
        $this->info('🎉 Public caches cleared successfully!');
        $this->info('Tour and song listings will refresh on next page load.');

        return Command::SUCCESS;
    }
}
