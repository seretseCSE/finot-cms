<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearAllCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-all {--force : Force clear all caches without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all application caches including public view caches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will clear all caches including public data. Are you sure?')) {
                $this->info('Cache clearing cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->info('Clearing all caches...');

        // Clear Laravel application cache
        $this->call('cache:clear');
        $this->info('✓ Application cache cleared');

        // Clear configuration cache
        $this->call('config:clear');
        $this->info('✓ Configuration cache cleared');

        // Clear route cache
        $this->call('route:clear');
        $this->info('✓ Route cache cleared');

        // Clear view cache
        $this->call('view:clear');
        $this->info('✓ View cache cleared');

        // Clear public-specific caches
        $this->clearPublicCaches();

        // Increment cache versions to bust any versioned caches
        $this->incrementCacheVersions();

        $this->newLine();
        $this->info('🎉 All caches cleared successfully!');

        return Command::SUCCESS;
    }

    /**
     * Clear public-facing caches that might affect tour/song listings
     */
    private function clearPublicCaches(): void
    {
        $publicCacheKeys = [
            'cache.version.tours',
            'cache.version.songs',
            'cache.version.song_categories',
        ];

        foreach ($publicCacheKeys as $key) {
            Cache::forget($key);
            $this->info("✓ Cleared cache key: {$key}");
        }

        // Clear common tour cache patterns
        $this->clearTourCaches();

        // Clear song-related caches
        $this->clearSongCaches();
    }

    /**
     * Clear tour-specific caches
     */
    private function clearTourCaches(): void
    {
        // Clear common tour cache keys with different versions
        for ($i = 1; $i <= 20; $i++) {
            Cache::forget("tours.index:v{$i}");
        }

        $this->info('✓ Cleared tour listing caches');
    }

    /**
     * Clear song-specific caches
     */
    private function clearSongCaches(): void
    {
        // Clear common song cache patterns
        $songCachePatterns = [
            'songs.index:v*',
            'songs.categories:v*',
        ];

        foreach ($songCachePatterns as $pattern) {
            // Clear versions 1-20 for each pattern
            for ($i = 1; $i <= 20; $i++) {
                Cache::forget(str_replace('*', $i, $pattern));
            }
        }

        $this->info('✓ Cleared song caches');
    }

    /**
     * Increment cache versions to force refresh
     */
    private function incrementCacheVersions(): void
    {
        $versionKeys = [
            'cache.version.tours',
            'cache.version.songs',
            'cache.version.song_categories',
        ];

        foreach ($versionKeys as $key) {
            Cache::increment($key);
            $this->info("✓ Incremented version: {$key}");
        }
    }
}
