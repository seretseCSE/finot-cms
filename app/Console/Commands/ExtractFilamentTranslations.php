<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class ExtractFilamentTranslations extends Command
{
    protected $signature = 'filament:extract-translations
                            {--resource= : Specific resource class name to process}
                            {--dry-run : Preview changes without writing files}';

    protected $description = 'Extract hardcoded ->label() strings from Filament resources into lang files.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $targetResource = $this->option('resource');

        $resourcePath = app_path('Filament/Resources');
        if (! is_dir($resourcePath)) {
            $this->error('Filament Resources directory not found.');

            return self::FAILURE;
        }

        $finder = new Finder();
        $finder->files()->in($resourcePath)->name('*Resource.php');

        if ($targetResource) {
            $finder->name($targetResource . '.php');
        }

        $enTranslations = [];
        $amTranslations = [];

        foreach ($finder as $file) {
            $this->info('Processing: ' . $file->getRelativePathname());

            $content = $file->getContents();
            $className = $this->getClassNameFromFile($file->getRealPath());
            $resourceKey = $this->getResourceTranslationKey($className);

            $originalContent = $content;

            // Extract ->label('...') patterns
            $labels = $this->extractLabels($content);

            foreach ($labels as $label) {
                $translationKey = $this->generateTranslationKey($label);
                $fullKey = "resources.{$resourceKey}.{$translationKey}";

                // Store English translation
                $this->setNestedValue($enTranslations, $fullKey, $label);

                // Store Amharic placeholder
                $this->setNestedValue($amTranslations, $fullKey, $label);

                // Replace in file
                $content = $this->replaceLabelInContent($content, $label, $fullKey);
            }

            if (! $dryRun && $content !== $originalContent) {
                file_put_contents($file->getRealPath(), $content);
                $this->info('  Updated ' . count($labels) . ' labels.');
            } elseif ($dryRun) {
                $this->comment('  Would update ' . count($labels) . ' labels (dry-run).');
            } else {
                $this->comment('  No labels found to extract.');
            }
        }

        // Write translation files
        if (! empty($enTranslations)) {
            $this->writeTranslationFile('en', $enTranslations, $dryRun);
            $this->writeTranslationFile('am', $amTranslations, $dryRun);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function extractLabels(string $content): array
    {
        $labels = [];

        // Match ->label('Some String')
        // Be careful not to match already-translated labels
        if (preg_match_all("/->label\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*\)/", $content, $matches)) {
            foreach ($matches[1] as $label) {
                $label = stripslashes($label);
                // Skip if it looks like a translation key or variable
                if (str_starts_with($label, 'resources.') || str_starts_with($label, '__(')) {
                    continue;
                }
                // Skip very short strings that are likely keys
                if (strlen($label) <= 2) {
                    continue;
                }
                $labels[] = $label;
            }
        }

        // Match ->label("Some String")
        if (preg_match_all('/->label\(\s*"((?:[^"\\\\]|\\\\.)*)"\s*\)/', $content, $matches)) {
            foreach ($matches[1] as $label) {
                $label = stripslashes($label);
                if (str_starts_with($label, 'resources.') || str_starts_with($label, '__(')) {
                    continue;
                }
                if (strlen($label) <= 2) {
                    continue;
                }
                $labels[] = $label;
            }
        }

        return array_unique($labels);
    }

    private function replaceLabelInContent(string $content, string $label, string $translationKey): string
    {
        $escaped = preg_quote($label, '/');

        // Replace single-quoted labels
        $content = preg_replace(
            "/->label\(\s*'{$escaped}'\s*\)/",
            "->label(__('{$translationKey}'))",
            $content
        );

        // Replace double-quoted labels
        $content = preg_replace(
            '/->label\(\s*"' . $escaped . '"\s*\)/',
            "->label(__('{$translationKey}'))",
            $content
        );

        return $content;
    }

    private function generateTranslationKey(string $label): string
    {
        $key = Str::slug($label, '_');
        $key = preg_replace('/[^a-z0-9_]/', '', $key);

        return $key ?: 'label_' . substr(md5($label), 0, 8);
    }

    private function getClassNameFromFile(string $path): string
    {
        $contents = file_get_contents($path);
        $namespace = '';
        $class = '';

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        return $namespace . '\\' . $class;
    }

    private function getResourceTranslationKey(string $className): string
    {
        // e.g., App\Filament\Resources\MemberResource -> member
        $base = class_basename($className);
        $base = str_replace('Resource', '', $base);

        return Str::snake($base);
    }

    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (! isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    private function writeTranslationFile(string $locale, array $translations, bool $dryRun): void
    {
        $path = resource_path("lang/{$locale}/resources.php");

        // Merge with existing translations if file exists
        $existing = [];
        if (file_exists($path)) {
            $existing = require $path;
        }

        $merged = array_merge_recursive($existing, $translations);

        $export = $this->arrayExport($merged);
        $content = "<?php\n\nreturn {$export};\n";

        if ($dryRun) {
            $this->comment("Would write to: {$path}");
        } else {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, $content);
            $this->info("Written: {$path}");
        }
    }

    private function arrayExport(array $array, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $innerSpaces = str_repeat('    ', $indent + 1);

        $parts = [];

        foreach ($array as $key => $value) {
            $keyStr = is_int($key) ? $key : "'" . addcslashes($key, "'\\") . "'";

            if (is_array($value)) {
                $parts[] = "{$innerSpaces}{$keyStr} => " . $this->arrayExport($value, $indent + 1) . ",";
            } else {
                $valStr = var_export($value, true);
                $parts[] = "{$innerSpaces}{$keyStr} => {$valStr},";
            }
        }

        return "[\n" . implode("\n", $parts) . "\n{$spaces}]";
    }
}
