<?php

namespace App\Models;

use App\Enums\InteractionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInteraction extends Model
{
    public const UPDATED_AT = null; // immutable — no updated_at column

    protected $fillable = ['client_id', 'type', 'source', 'description'];

    protected function casts(): array
    {
        return ['type' => InteractionType::class];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
