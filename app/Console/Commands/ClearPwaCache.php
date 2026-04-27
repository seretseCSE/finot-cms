<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearPwaCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pwa:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear PWA service worker cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing PWA cache...');

        // Delete service worker file
        $swPath = public_path('service-worker.js');
        if (File::exists($swPath)) {
            File::delete($swPath);
            $this->info('Deleted service worker file');
        }

        // Delete build info file
        $buildInfoPath = public_path('build-info.json');
        if (File::exists($buildInfoPath)) {
            File::delete($buildInfoPath);
            $this->info('Deleted build info file');
        }

        // Clear browser cache hint
        $this->info('PWA cache cleared successfully!');
        $this->info('Note: Users may need to refresh their browsers to see changes.');

        return 0;
    }
}
