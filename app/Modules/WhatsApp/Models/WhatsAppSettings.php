<?php

namespace App\Modules\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSettings extends Model
{
    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'lock_key',
        'hard_fail_threshold',
        'bulk_dead_threshold',
        'no_engagement_threshold',
        'cooldown_no_engagement_days',
        'min_days_between_sends',
        'cooldown_quality_hold_days',
        'cooldown_experiment_days',
        'cooldown_regional_days',
        'reanalysis_status',
        'reanalysis_started_at',
        'reanalysis_completed_at',
        'last_run_duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'hard_fail_threshold'         => 'integer',
            'bulk_dead_threshold'          => 'integer',
            'no_engagement_threshold'      => 'integer',
            'cooldown_no_engagement_days'  => 'integer',
            'min_days_between_sends'       => 'integer',
            'cooldown_quality_hold_days'   => 'integer',
            'cooldown_experiment_days'     => 'integer',
            'cooldown_regional_days'       => 'integer',
            'reanalysis_started_at'        => 'datetime',
            'reanalysis_completed_at'      => 'datetime',
        ];
    }

    public static function current(): self
    {
        return self::firstOrCreate(
            ['lock_key' => 'default'],
            [
                'hard_fail_threshold'        => 3,
                'bulk_dead_threshold'         => 10,
                'no_engagement_threshold'     => 10,
                'cooldown_no_engagement_days' => 30,
                'min_days_between_sends'      => 0,
                'cooldown_quality_hold_days'  => 3,
                'cooldown_experiment_days'    => 7,
                'cooldown_regional_days'      => 30,
            ]
        );
    }
}
