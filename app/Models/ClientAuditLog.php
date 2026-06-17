<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAuditLog extends Model
{
    protected $fillable = [
        'action',
        'client_id',
        'target_client_id',
        'reason',
        'performed_by',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }
}
