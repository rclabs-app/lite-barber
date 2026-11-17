<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToTenant;

class Professional extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'specialties' => 'array',
        'is_active' => 'boolean'
    ];
}
