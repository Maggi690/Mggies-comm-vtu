<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKeyLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['meta' => 'array','created_at' => 'datetime','updated_at' => 'datetime'];
}
