<?php

// Bootstrap Laravel
require __DIR__ . '/artisan';

use Illuminate\Support\Facades\Storage;

echo "=== Upload Permissions Check ===\n";

// Check temporary directory
$tmpDir = sys_get_temp_dir();
echo "Temp directory: {$tmpDir}\n";

// Check if temp directory is writable
if (is_writable($tmpDir)) {
    echo "Temp directory: WRITABLE ✓\n";
} else {
    echo "Temp directory: NOT WRITABLE ✗\n";
}

// Check Laravel storage directories
$storageDirs = [
    'storage/app/public/media/photos',
    'storage/app/public/media/videos',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views'
];

foreach ($storageDirs as $dir) {
    if (is_dir($dir)) {
        echo "{$dir}: EXISTS ✓\n";
        echo "  Writable: " . (is_writable($dir) ? 'YES ✓' : 'NO ✗') . "\n";
        echo "  Permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "\n";
    } else {
        echo "{$dir}: MISSING ✗\n";
    }
}

// Check livewire temp directory specifically
$livewireTmp = 'livewire-tmp';
if (is_dir($livewireTmp)) {
    echo "\nLivewire temp directory: EXISTS ✓\n";
    echo "  Writable: " . (is_writable($livewireTmp) ? 'YES ✓' : 'NO ✗') . "\n";
    echo "  Contents:\n";
    $files = scandir($livewireTmp);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filepath = $livewireTmp . '/' . $file;
            echo "    {$file} - " . (file_exists($filepath) ? 'EXISTS' : 'MISSING') . "\n";
            if (file_exists($filepath)) {
                echo "      Size: " . filesize($filepath) . " bytes\n";
                echo "      Writable: " . (is_writable($filepath) ? 'YES' : 'NO') . "\n";
            }
        }
    }
} else {
    echo "\nLivewire temp directory: MISSING ✗\n";
}

// Check disk space
$freeSpace = disk_free_space('/');
echo "\nDisk space: " . round($freeSpace / 1024 / 1024 / 1024) . " GB available\n";

echo "\n=== Check Complete ===\n";
