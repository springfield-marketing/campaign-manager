<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'country',
        'nationality',
        'community',
        'resident',
        'city',
        'gender',
        'interest',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(ClientPhoneNumber::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ClientSource::class);
    }
}
