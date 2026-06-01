<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $table = 'driver_profiles';
    protected $fillable = [
        'user_id', 'license_number', 'license_expiry', 'date_of_birth',
        'emergency_contact', 'emergency_phone', 'status',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
            'date_of_birth' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}
