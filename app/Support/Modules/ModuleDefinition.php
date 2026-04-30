<?php

namespace App\Support\Modules;

class ModuleDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $description,
        public readonly string $route,
        public readonly bool $enabled,
    ) {
    }
}
