<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Professional extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'specialties' => 'array',
        'is_active' => 'boolean'
    ];
}
