<?php

/**
 * Filament 5 Migration Fix Script
 * Run from your project root: php fix_filament5.php
 */

// Collect ALL php files under app/Filament recursively
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('app/Filament', RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}

$totalFixed = 0;

foreach ($files as $file) {
    if (!file_exists($file)) continue;

    $content = file_get_contents($file);
    $original = $content;

    $isRelationManager = str_contains($file, 'RelationManager');

    // 1. navigationIcon property -> method
    $content = preg_replace(
        "/    protected static \?string \\\$navigationIcon = '([^']+)';\n/",
        "    public static function getNavigationIcon(): ?string { return '\$1'; }\n",
        $content
    );
    // 2. navigationGroup property -> method
    $content = preg_replace(
        "/    protected static \?string \\\$navigationGroup = '([^']+)';\n/",
        "    public static function getNavigationGroup(): ?string { return '\$1'; }\n",
        $content
    );
    // 3. navigationLabel property -> method
    $content = preg_replace(
        "/    protected static \?string \\\$navigationLabel = '([^']+)';\n/",
        "    public static function getNavigationLabel(): string { return '\$1'; }\n",
        $content
    );
    // 4. modelLabel property -> method
    $content = preg_replace(
        "/    protected static \?string \\\$modelLabel = '([^']+)';\n/",
        "    public static function getModelLabel(): string { return '\$1'; }\n",
        $content
    );
    // 5. pluralModelLabel property -> method
    $content = preg_replace(
        "/    protected static \?string \\\$pluralModelLabel = '([^']+)';\n/",
        "    public static function getPluralModelLabel(): string { return '\$1'; }\n",
        $content
    );
    // 6. navigationSort property -> method
    $content = preg_replace(
        "/    protected static \?int \\\$navigationSort = (\d+);\n/",
        "    public static function getNavigationSort(): ?int { return \$1; }\n",
        $content
    );

    // 7. Fix broken BackedEnum return type
    $content = str_replace(
        'public static function getNavigationIcon(): string|\BackedEnum|null {',
        'public static function getNavigationIcon(): ?string {',
        $content
    );
    $content = str_replace(
        'public static function getNavigationIcon(): BackedEnum|string|null {',
        'public static function getNavigationIcon(): ?string {',
        $content
    );

    // 8. BaseResource: protected -> public navigation methods
    $content = str_replace('protected static function getNavigationLabel()', 'public static function getNavigationLabel()', $content);
    $content = str_replace('protected static function getNavigationIcon()', 'public static function getNavigationIcon()', $content);
    $content = str_replace('protected static function getNavigationGroup()', 'public static function getNavigationGroup()', $content);

    // 9. BaseResource: getEloquentQuery return type (idempotent)
    $content = preg_replace(
        '/public static function getEloquentQuery\(\)(?!:)/',
        'public static function getEloquentQuery(): \\Illuminate\\Database\\Eloquent\\Builder',
        $content
    );

    // 10. Form API: imports
    $content = str_replace('use Filament\Forms\Form;', 'use Filament\Schemas\Schema;', $content);
    $content = str_replace('use Filament\Infolists\Infolist;', '', $content);

    // 11. Form API: method signatures
    $content = str_replace('public static function form(Form $form): Form', 'public static function form(Schema $schema): Schema', $content);
    $content = str_replace('public function form(Form $form): Form', 'public function form(Schema $schema): Schema', $content);
    $content = str_replace('public static function infolist(Infolist $infolist): Infolist', 'public static function infolist(Schema $schema): Schema', $content);

    // 12. Form API: method bodies
    $content = str_replace('return $form' . "\n", 'return $schema' . "\n", $content);
    $content = str_replace('return $infolist' . "\n", 'return $schema' . "\n", $content);
    $content = str_replace('return $form->schema([', 'return $schema->components([', $content);
    $content = str_replace('return $infolist->schema([', 'return $schema->components([', $content);
    $content = str_replace('$form->schema([', '$schema->components([', $content);
    $content = str_replace('$infolist->schema([', '$schema->components([', $content);
    $content = str_replace('return $schema' . "\n" . '            ->schema([', 'return $schema' . "\n" . '            ->components([', $content);
    $content = str_replace('return $schema' . "\n" . '        ->schema([', 'return $schema' . "\n" . '        ->components([', $content);
    $content = str_replace('return $schema->schema([', 'return $schema->components([', $content);

    // 13. static $view -> non-static
    $content = str_replace('protected static string $view', 'protected string $view', $content);

    // 14. RelationManager: static can* -> instance methods
    if ($isRelationManager) {
        foreach (['canCreate','canEdit','canDelete','canView','canViewAny','canDeleteAny',
                  'canForceDelete','canForceDeleteAny','canRestore','canRestoreAny',
                  'canDetach','canDetachAny','canAssociate','canDissociate','canDissociateAny'] as $method) {
            $content = str_replace("public static function {$method}(): bool", "public function {$method}(): bool", $content);
        }
    }

    // 15. Remove duplicate Schema imports
    $lines = explode("\n", $content);
    $seenSchema = false;
    $newLines = [];
    foreach ($lines as $line) {
        if (trim($line) === 'use Filament\Schemas\Schema;') {
            if (!$seenSchema) { $newLines[] = $line; $seenSchema = true; }
        } else {
            $newLines[] = $line;
        }
    }
    $content = implode("\n", $newLines);


    // 16. Row-level table actions moved from Tables\Actions to \Filament\Actions in F5
    //     Use leading \ so PHP doesn't resolve relative to current namespace
    foreach (['EditAction','DeleteAction','ViewAction','RestoreAction','ForceDeleteAction','ReplicateAction'] as $action) {
        // Fix not-yet-replaced
        $content = str_replace(
            "Tables\\Actions\\{$action}",
            "\\Filament\\Actions\\{$action}",
            $content
        );
        // Fix previously replaced without leading backslash
        $content = str_replace(
            "Filament\\Actions\\{$action}",
            "\\Filament\\Actions\\{$action}",
            $content
        );
        // De-duplicate if double-backslashed
        $content = str_replace(
            "\\\\Filament\\Actions\\{$action}",
            "\\Filament\\Actions\\{$action}",
            $content
        );
    }

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Fixed: $file\n";
        $totalFixed++;
    }
}

echo "\nDone. Fixed $totalFixed files.\n";
echo "Now run: php artisan optimize:clear\n";