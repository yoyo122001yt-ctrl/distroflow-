<?php

namespace App\Models;

class Driver extends User
{
    protected $table = 'users';

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }
}
