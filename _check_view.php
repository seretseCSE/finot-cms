<?php
require 'vendor/autoload.php';

// Check base Dashboard view
$ref = new ReflectionClass('Filament\Pages\Dashboard');
echo "Base Dashboard extension: " . $ref->getParentClass()->getName() . PHP_EOL;

// Check parent Page class for view
$pageRef = new ReflectionClass('Filament\Pages\Page');
echo "Page has 'view' property: " . ($pageRef->hasProperty('view') ? 'yes' : 'no') . PHP_EOL;
echo "Page has 'getView' method: " . ($pageRef->hasMethod('getView') ? 'yes' : 'no') . PHP_EOL;

// Check our Dashboard
$appRef = new ReflectionClass('App\Filament\Pages\Dashboard');
echo "App Dashboard has 'view' property: " . ($appRef->hasProperty('view') ? 'yes' : 'no') . PHP_EOL;
echo "App Dashboard has 'getView' method: " . ($appRef->hasMethod('getView') ? 'yes' : 'no') . PHP_EOL;

// Check if filament::pages.dashboard view exists
try {
    $view = view('filament::pages.dashboard');
    echo "filament::pages.dashboard view: EXISTS" . PHP_EOL;
} catch (\Exception $e) {
    echo "filament::pages.dashboard view: NOT FOUND - " . $e->getMessage() . PHP_EOL;
}

// Check what view the app dashboard uses
$dashboard = $appRef->newInstanceWithoutConstructor();
if ($appRef->hasMethod('getView')) {
    echo "getView(): " . $dashboard->getView() . PHP_EOL;
}

echo PHP_EOL . "--- Page getView source ---" . PHP_EOL;
$getViewMethod = $pageRef->getMethod('getView');
$file = $getViewMethod->getFileName();
$lines = file($file);
for ($i = $getViewMethod->getStartLine() - 1; $i < $getViewMethod->getEndLine(); $i++) {
    echo ($i + 1) . ": " . $lines[$i];
}
