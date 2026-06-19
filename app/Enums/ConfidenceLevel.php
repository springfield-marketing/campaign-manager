<?php

namespace App\Enums;

enum ConfidenceLevel: string
{
    case High   = 'high';
    case Medium = 'medium';
    case Low    = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High   => 'High — verified',
            self::Medium => 'Medium — probable',
            self::Low    => 'Low — uncertain',
        };
    }
}
