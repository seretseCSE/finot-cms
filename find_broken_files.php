<?php
$dir = 'app/Filament/Resources';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$filesToFix = [];
foreach ($rii as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());
    $hasUsage = strpos($content, 'Actions\CreateAction::') !== false || strpos($content, 'Actions\CreateAction ') !== false;
    $hasNamespaceImport = strpos($content, 'use Filament\Actions;') !== false;
    if ($hasUsage && !$hasNamespaceImport) {
        $filesToFix[] = $file->getPathname();
    }
}
echo implode(PHP_EOL, $filesToFix) . PHP_EOL;
