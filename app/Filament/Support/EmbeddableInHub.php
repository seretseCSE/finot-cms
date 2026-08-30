<?php

namespace App\Filament\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

trait EmbeddableInHub
{
    public bool $embeddedInHub = false;

    public function render(): View
    {
        if ($this->embeddedInHub) {
            return view($this->getView(), $this->getViewData());
        }

        return parent::render();
    }

    public function getHeading(): string | Htmlable | null
    {
        if ($this->embeddedInHub) {
            return '';
        }

        return parent::getHeading();
    }

    public function getBreadcrumbs(): array
    {
        if ($this->embeddedInHub) {
            return [];
        }

        return parent::getBreadcrumbs();
    }

    public function getPageClasses(): array
    {
        $classes = parent::getPageClasses();

        if ($this->embeddedInHub) {
            $classes[] = 'fi-embedded-hub-page';
        }

        return $classes;
    }
}
