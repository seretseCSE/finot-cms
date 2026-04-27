<?php
$path = __DIR__ . '/tests/Feature/AuthorizationCompleteTest.php';
$content = file_get_contents($path);

$content = preg_replace_callback(
    "/Role::create\(\['name' => '([^']+)', 'guard_name' => 'web'\]\)/",
    function ($matches) {
        $name = $matches[1];
        $label = ucfirst(str_replace('_', ' ', $name));
        return "Role::create(['name' => '{$name}', 'label' => '{$label}', 'guard_name' => 'web'])";
    },
    $content
);

file_put_contents($path, $content);
echo "Fixed Role::create calls in AuthorizationCompleteTest.php\n";
