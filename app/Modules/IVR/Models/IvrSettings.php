<?php

namespace App\Modules\IVR\Models;

use Illuminate\Database\Eloquent\Model;

class IvrSettings extends Model
{
    protected $fillable = [
        'lock_key',
        'monthly_minutes_quota',
        'price_per_minute_under',
        'price_per_minute_over',
    ];

    protected function casts(): array
    {
        return [
            'monthly_minutes_quota' => 'integer',
            'price_per_minute_under' => 'decimal:4',
            'price_per_minute_over' => 'decimal:4',
        ];
    }

    public static function current(): self
    {
        return self::firstOrCreate(
            ['lock_key' => 'default'],
            [
                'monthly_minutes_quota' => 50000,
                'price_per_minute_under' => '0.3700',
                'price_per_minute_over' => '0.4000',
            ]
        );
    }
}
