<?php

namespace App\Filament\Pages;

use App\Filament\Support\HidesFromNavigation;
use App\Filament\Support\NavHubRegistry;
use App\Livewire\EmbeddedResourceTable;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class ResourceTabHub extends Page
{
    use HidesFromNavigation;

    protected static ?string $slug = 'hubs';

    protected static ?string $title = 'Hub';

    protected string $view = 'filament.pages.resource-tab-hub';

    #[Url]
    public string $hub = '';

    #[Url]
    public ?string $tab = null;

    public static function canAccess(): bool
    {
        $hub = request()->query('hub');

        if (is_string($hub) && $hub !== '') {
            return NavHubRegistry::accessibleTabsForHub($hub) !== [];
        }

        return NavHubRegistry::userCanAccessAnyHub();
    }

    public function mount(): void
    {
        if ($this->hub === '' || NavHubRegistry::hub($this->hub) === null) {
            $first = NavHubRegistry::firstAccessibleHubKey();
            abort_unless(is_string($first), 403);
            $this->hub = $first;
        }

        $tabs = $this->tabs();
        abort_unless($tabs !== [], 403);

        $keys = array_column($tabs, 'key');
        if (! in_array($this->tab, $keys, true)) {
            $this->tab = $keys[0];
        }
    }

    public function selectTab(string $tab): void
    {
        $keys = array_column($this->tabs(), 'key');

        if (in_array($tab, $keys, true)) {
            $this->tab = $tab;
        }
    }

    public function getTitle(): string
    {
        return NavHubRegistry::hub($this->hub)['label'] ?? 'Hub';
    }

    public function getPageClasses(): array
    {
        return ['fi-resource-tab-hub'];
    }

    /**
     * @return list<array{key: string, label: string, target: class-string, type: string}>
     */
    public function tabs(): array
    {
        return NavHubRegistry::accessibleTabsForHub($this->hub);
    }

    /**
     * @return array{key: string, label: string, target: class-string, type: string}|null
     */
    public function currentTab(): ?array
    {
        foreach ($this->tabs() as $tab) {
            if ($tab['key'] === $this->tab) {
                return $tab;
            }
        }

        return $this->tabs()[0] ?? null;
    }

    public function currentTabIsResource(): bool
    {
        return ($this->currentTab()['type'] ?? null) === 'resource';
    }

    /**
     * @return class-string|null
     */
    public function currentTarget(): ?string
    {
        return $this->currentTab()['target'] ?? null;
    }

    public function embeddedTableClass(): string
    {
        return EmbeddedResourceTable::class;
    }
}
