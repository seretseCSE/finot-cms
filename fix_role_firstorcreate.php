<?php
$files = [
    __DIR__ . '/tests/TestCase.php',
    __DIR__ . '/tests/Unit/ComprehensivePermissionTest.php',
    __DIR__ . '/tests/Unit/RolePermissionTest.php',
];

foreach ($files as $path) {
    $content = file_get_contents($path);
    $original = $content;
    
    // Match Role::firstOrCreate(['name' => ..., 'guard_name' => 'web']) without label
    $content = preg_replace_callback(
        "/Role::firstOrCreate\(\s*\['name' => '([^']+)', 'guard_name' => 'web'\]\s*\)/",
        function ($matches) {
            $name = $matches[1];
            $label = ucwords(str_replace('_', ' ', $name));
            return "Role::firstOrCreate(['name' => '{$name}', 'guard_name' => 'web'], ['label' => '{$label}'])";
        },
        $content
    );
    
    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Updated: " . str_replace(__DIR__ . '/', '', $path) . "\n";
    } else {
        echo "No changes: " . str_replace(__DIR__ . '/', '', $path) . "\n";
    }
}

echo "Done.\n";
