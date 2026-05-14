<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = ['name', 'iso_code'];

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
