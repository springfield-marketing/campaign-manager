<?php

namespace App\Modules\IVR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IvrScript extends Model
{
    protected $fillable = [
        'name',
        'audio_file_path',
        'audio_original_name',
        'audio_script',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(IvrCampaign::class, 'ivr_script_id');
    }
}
