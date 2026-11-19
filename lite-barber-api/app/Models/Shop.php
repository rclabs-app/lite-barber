<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'working_days' => 'array',
        'unavailable_dates' => 'array',
        'is_active' => 'boolean',
    ];

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function professionals()
    {
        return $this->hasMany(Professional::class);
    }

    public function blockedSlots()
    {
        return $this->hasMany(BlockedSlot::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
