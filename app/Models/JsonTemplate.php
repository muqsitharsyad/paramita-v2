<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JsonTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'description',
        'template_data',
        'version',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
