<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table = 'notification_logs';

    protected $fillable = [
        'store_id', 'delivery_id', 'type', 'event', 'recipient_phone',
        'message', 'status', 'retry_count', 'sent_at', 'error_message', 'provider_response',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'retry_count' => 'integer',
            'provider_response' => 'json',
        ];
    }

    public function store()
    {
        return $this->belongsTo(RetailStore::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }
}
