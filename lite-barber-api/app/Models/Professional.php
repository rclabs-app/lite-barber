<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Professional extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'specialties' => 'array',
        'is_active' => 'boolean',
    ];

    public function blockedSlots()
    {
        return $this->hasMany(BlockedSlot::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
