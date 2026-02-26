<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'whatsapp_connection_id',
        'lead_id',
        'remote_jid',
        'message_id',
        'from_me',
        'body',
        'message_timestamp',
    ];

    protected function casts(): array
    {
        return [
            'from_me' => 'boolean',
            'message_timestamp' => 'integer',
        ];
    }

    public function whatsappConnection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
