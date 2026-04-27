<?php
$testDir = __DIR__ . '/tests';
$existingPerms = array_flip(array_map('trim', file(__DIR__ . '/list_perms.txt')));

$missing = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testDir));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());
    // Match strings inside hasPermissionTo('...') or givePermissionTo('...')
    preg_match_all("/(?:hasPermissionTo|givePermissionTo)\(['\"]([^'\"]+)['\"]\)/", $content, $matches);
    foreach ($matches[1] as $perm) {
        if (!isset($existingPerms[$perm]) && !str_contains($perm, '*')) {
            $missing[$perm] = true;
        }
    }
}

echo "Missing permissions referenced in tests:\n";
foreach (array_keys($missing) as $perm) {
    echo "  - {$perm}\n";
}
