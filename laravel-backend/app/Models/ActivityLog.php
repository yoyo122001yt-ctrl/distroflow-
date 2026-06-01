<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    protected $fillable = [
        'user_id', 'action', 'resource_type', 'resource_id',
        'description', 'details', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['details' => 'json'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByResource($query, $type, $id)
    {
        return $query->where('resource_type', $type)->where('resource_id', $id);
    }
}
