<?php
// Fix Filament Section imports from Forms to Schemas
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/app/Filament', RecursiveDirectoryIterator::SKIP_DOTS)
);

$old = 'Filament\\Forms\\Components\\Section';
$new = 'Filament\\Schemas\\Components\\Section';

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    if (strpos($content, $old) === false) continue;
    $updated = str_replace($old, $new, $content);
    file_put_contents($path, $updated);
    echo "Updated: " . str_replace(__DIR__ . '/', '', $path) . "\n";
}

echo "Done.\n";
