<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    protected $fillable = [
        'name', 'title', 'bio',
        'avatar_url', 'specializations', 'years_experience',
    ];
    protected $casts = [
    'specializations' => 'array',
];
}