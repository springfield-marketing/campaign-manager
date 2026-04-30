<?php

namespace App\Support\Modules;

use Illuminate\Support\Collection;

class ModuleRegistry
{
    /**
     * @param  array<int, array<string, mixed>>  $modules
     */
    public function __construct(
        private readonly array $modules,
    ) {
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Support\Modules\ModuleDefinition>
     */
    public function all(): Collection
    {
        return collect($this->modules)
            ->filter(fn (array $module): bool => (bool) ($module['enabled'] ?? false))
            ->map(fn (array $module): ModuleDefinition => new ModuleDefinition(
                key: (string) $module['key'],
                name: (string) $module['name'],
                description: (string) $module['description'],
                route: (string) $module['route'],
                enabled: (bool) $module['enabled'],
            ))
            ->values();
    }
}
