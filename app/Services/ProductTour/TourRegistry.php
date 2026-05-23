<?php

namespace App\Services\ProductTour;

use Illuminate\Support\Facades\Route;

class TourRegistry
{
    protected array $registered = [];

    protected array $pageTours = [];

    protected array $roleTours = [];

    public function __construct()
    {
        $this->loadFromConfig();
    }

    public function register(string $key, array $definition): self
    {
        $this->registered[$key] = $definition;

        foreach ($definition['roles'] ?? [] as $role) {
            $this->roleTours[$role][$key] = $definition;
        }

        foreach ($definition['pages'] ?? [] as $page) {
            $this->pageTours[$page][$key] = $definition;
        }

        return $this;
    }

    public function get(string $key): ?array
    {
        return $this->registered[$key] ?? null;
    }

    public function all(): array
    {
        return $this->registered;
    }

    public function forRole(string $role): array
    {
        return $this->roleTours[$role] ?? [];
    }

    public function forPage(string $page): array
    {
        return $this->pageTours[$page] ?? [];
    }

    public function forRoleAndPage(string $role, string $page): array
    {
        $roleTours = $this->forRole($role);
        $pageTours = $this->forPage($page);

        return array_intersect_key($roleTours, $pageTours);
    }

    public function currentRouteTour(): ?array
    {
        $route = Route::currentRouteName();
        $page = request()->segment(2) ?? 'dashboard';

        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $role = $user->roles->first()?->name;
        if (!$role) {
            return null;
        }

        $tours = $this->forRoleAndPage($role, $page);

        if (empty($tours)) {
            $tours = $this->forRole($role);
        }

        if (empty($tours)) {
            return null;
        }

        return $tours;
    }

    protected function loadFromConfig(): void
    {
        $tours = config('product-tour.tours', []);

        foreach ($tours as $key => $definition) {
            $this->register($key, $definition);
        }
    }
}
